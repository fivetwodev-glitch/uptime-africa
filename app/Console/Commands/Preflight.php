<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Plan;
use App\Models\User;
use App\Services\Notifications\WhatsAppClient;
use App\Services\UptimeRobot\UptimeRobotClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Vérifications avant et après mise en production, à lancer sur le serveur.
 * N'affiche jamais la valeur d'un secret : seulement « défini » ou « absent ».
 * Code retour 1 si au moins une vérification bloquante échoue.
 */
class Preflight extends Command
{
    protected $signature = 'uptime:preflight {--network : teste aussi les connexions sortantes (UptimeRobot, SMTP)}';

    protected $description = 'Contrôle la configuration de production (PHP, .env, base, cron, fournisseurs)';

    private const EXTENSIONS = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'pdo_mysql', 'session', 'tokenizer', 'xml'];

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->section('PHP');
        $this->check(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP '.PHP_VERSION.' (8.3 minimum)');
        foreach (self::EXTENSIONS as $ext) {
            $this->check(extension_loaded($ext), "Extension $ext");
        }
        $this->check((int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 60, 'max_execution_time = '.ini_get('max_execution_time').' s', warning: true);

        $this->section('Application');
        $this->check(app()->isProduction(), 'APP_ENV = production');
        $this->check(! config('app.debug'), 'APP_DEBUG = false');
        $this->check(filled(config('app.key')), 'APP_KEY défini');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL en https ('.config('app.url').')');
        $this->check(is_file(public_path('build/manifest.json')), 'Assets compilés (public/build/manifest.json)');
        foreach ([storage_path(), storage_path('logs'), storage_path('framework/sessions'), base_path('bootstrap/cache')] as $dir) {
            $this->check(is_dir($dir) && is_writable($dir), 'Dossier inscriptible : '.str_replace(base_path().'/', '', $dir));
        }

        $this->section('Base de données');
        try {
            DB::connection()->getPdo();
            $this->check(true, 'Connexion '.config('database.default').' ('.DB::connection()->getDatabaseName().')');
            $pending = $this->pendingMigrations();
            $this->check($pending === 0, $pending === 0 ? 'Migrations à jour' : "$pending migration(s) en attente : php artisan migrate --force");
            $this->check(User::whereHas('role', fn ($q) => $q->where('slug', RoleName::SuperAdmin->value))->where('is_active', true)->exists(), 'Super administrateur actif (php artisan db:seed --force)');
            $unpriced = Plan::where('is_active', true)->where('price_validated', false)->pluck('name')->all();
            $this->check($unpriced === [], $unpriced === [] ? 'Tarifs validés' : 'Tarifs non validés : '.implode(', ', $unpriced).' (souscription payante impossible)', warning: true);
            $floor = (int) config('uptime.monitoring.min_interval_minutes');
            $promised = Plan::where('is_active', true)->where('min_interval_minutes', '<', $floor)->pluck('name')->all();
            $this->check($promised === [], $promised === [] ? "Intervalles des offres compatibles avec le plancher ($floor min)" : 'Offre(s) annonçant moins de '.$floor.' min : '.implode(', ', $promised).' (plancher MONITOR_MIN_INTERVAL_MINUTES ; 1 min exige un plan UptimeRobot payant)', warning: true);
        } catch (Throwable $e) {
            $this->check(false, 'Connexion à la base impossible : '.$e->getMessage());
        }

        $this->section('Sessions, cache, file');
        $this->check(config('session.driver') === 'database', 'SESSION_DRIVER = database');
        $this->check((bool) config('session.secure'), 'SESSION_SECURE_COOKIE = true');
        $this->check((bool) config('session.encrypt'), 'SESSION_ENCRYPT = true', warning: true);
        $this->check(config('queue.default') !== 'sync', 'QUEUE_CONNECTION = database (envois hors requête)');
        $this->check(config('queue.default') === 'sync' || config('uptime.queue.run_from_scheduler'), 'QUEUE_RUN_FROM_SCHEDULER = true (sinon aucune notification ne part)');
        $last = Cache::get(config('uptime.heartbeat.cache_key'));
        $fresh = $last && Carbon::parse($last)->gt(now()->subMinutes(3));
        $this->check($fresh, $last ? 'Dernier passage du cron : '.Carbon::parse($last)->diffForHumans() : 'Cron jamais exécuté (tâche cron Hostinger à créer)', warning: true);
        $this->check(filled(config('uptime.heartbeat.url')), 'HEARTBEAT_URL (sonde externe si le cron s\'arrête)', warning: true);

        $this->section('Email');
        $mailer = config('mail.default');
        $this->check($mailer === 'smtp', "MAIL_MAILER = $mailer");
        $this->check(filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password')), 'SMTP : hôte, identifiant et mot de passe définis');
        $this->check(filled(config('mail.from.address')), 'MAIL_FROM_ADDRESS défini');

        $this->section('UptimeRobot');
        $uptimeRobot = app(UptimeRobotClient::class);
        $this->check($uptimeRobot->isConfigured(), 'ZITE_UPTIMEROBOT_API_KEY défini');
        $token = (string) config('uptime.uptimerobot.trigger_token');
        $this->check($token === '' || strlen($token) >= 32, $token === '' ? 'Déclencheur externe désactivé (cron seul)' : 'UPTIME_SYNC_TRIGGER_TOKEN ≥ 32 caractères');

        $this->section('WhatsApp');
        $whatsapp = app(WhatsAppClient::class);
        if (config('uptime.whatsapp.enabled')) {
            $this->check($whatsapp->isConfigured(), 'Identifiants WhatsApp Cloud API définis');
            $this->check(filled(config('uptime.whatsapp.app_secret')) && filled(config('uptime.whatsapp.webhook_verify_token')), 'Secret d\'app et jeton du webhook définis');
        } else {
            $this->check(false, 'WhatsApp désactivé : les alertes WhatsApp seront tracées « ignorées »', warning: true);
        }

        if ($this->option('network')) {
            $this->section('Connexions sortantes');
            try {
                $account = $uptimeRobot->me();
                $this->check(true, 'API UptimeRobot joignable (compte '.($account['activeSubscription']['plan'] ?? '?').', '.($account['monitorsCount'] ?? '?').'/'.($account['monitorLimit'] ?? '?').' moniteurs)');
            } catch (Throwable $e) {
                $this->check(false, 'API UptimeRobot : '.$e->getMessage());
            }
            $host = (string) config('mail.mailers.smtp.host');
            $port = (int) config('mail.mailers.smtp.port');
            $socket = $host ? @fsockopen((config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl://' : '').$host, $port, $code, $message, 10) : false;
            $this->check($socket !== false, "SMTP $host:$port joignable".($socket === false && $host ? " ($message)" : ''));
            if ($socket) {
                fclose($socket);
            }
            try {
                $this->check(Http::timeout(10)->get('https://api.pwnedpasswords.com/range/00000')->successful(), 'Contrôle des mots de passe compromis joignable', warning: true);
            } catch (Throwable) {
                $this->check(false, 'Contrôle des mots de passe compromis injoignable', warning: true);
            }
        }

        $this->newLine();
        $summary = "{$this->errors} erreur(s) bloquante(s), {$this->warnings} avertissement(s).";
        $this->errors === 0 ? $this->info($summary) : $this->error($summary);

        return $this->errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>$title</>");
    }

    private function check(bool $passed, string $label, bool $warning = false): void
    {
        if ($passed) {
            $this->line("  <fg=green>✓</> $label");

            return;
        }

        $warning ? $this->warnings++ : $this->errors++;
        $this->line($warning ? "  <fg=yellow>!</> $label" : "  <fg=red>✗</> $label");
    }

    private function pendingMigrations(): int
    {
        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')));

        return count(array_diff($files, $ran));
    }
}
