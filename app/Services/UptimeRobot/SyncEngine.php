<?php

namespace App\Services\UptimeRobot;

use App\Enums\CheckStatus;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\MonitoringProvider;
use App\Enums\MonitoringStatus;
use App\Enums\SiteStatus;
use App\Enums\SyncResult;
use App\Enums\SyncStatus;
use App\Models\Check;
use App\Models\Incident;
use App\Models\Site;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Moteur de synchronisation UptimeRobot → vérifications locales → incidents.
 *
 * - Un passage = 1 requête par tranche de 200 moniteurs (+ quelques temps de réponse si la limite le permet).
 * - Idempotent : une vérification par (site, fournisseur, minute) ; un passage rejoué dans la même minute ne compte rien deux fois.
 * - Verrou : deux passages ne tournent jamais en même temps (cron, déclencheur externe, bouton).
 * - Une API muette ou un statut inconnu donne une vérification « inconnue » : jamais une panne.
 * - Incident ouvert après N échecs consécutifs confirmés (DOWN), un seul ouvert par site, résolu au retour UP.
 */
class SyncEngine
{
    public const LOCK_KEY = 'uptimerobot:sync-lock';

    public const LAST_RUN_KEY = 'uptimerobot:last_sync';

    public function __construct(private readonly UptimeRobotClient $client) {}

    /**
     * @param  string|null  $agencyId  limite le passage aux sites d'une agence (bouton manuel d'agence)
     * @param  bool  $force  ignore l'espacement minimal (déclenchement manuel)
     * @return array<string, mixed> rapport du passage
     */
    public function run(?string $agencyId = null, bool $force = false, string $trigger = 'cron'): array
    {
        if (! $this->client->isConfigured()) {
            return $this->report('skipped', $trigger, ['reason' => 'not_configured', 'message' => UptimeRobotException::notConfigured()->getMessage()]);
        }

        if (! $force && ! $this->isDue()) {
            return $this->report('skipped', $trigger, ['reason' => 'not_due']);
        }

        $lock = Cache::lock(self::LOCK_KEY, (int) config('uptime.uptimerobot.lock_seconds', 600));
        if (! $lock->get()) {
            return $this->report('skipped', $trigger, ['reason' => 'already_running', 'message' => 'Une synchronisation est déjà en cours.']);
        }

        try {
            return $this->execute($this->linkedSites($agencyId), $agencyId === null, $trigger);
        } finally {
            $lock->release();
        }
    }

    /** Synchronise un seul site (1 requête GET /monitors/{id}). */
    public function runForSite(Site $site): array
    {
        $lock = Cache::lock(self::LOCK_KEY, (int) config('uptime.uptimerobot.lock_seconds', 600));
        if (! $lock->get()) {
            return $this->report('skipped', 'manual', ['reason' => 'already_running', 'message' => 'Une synchronisation est déjà en cours.']);
        }

        try {
            $checkedAt = now()->startOfMinute();
            try {
                $monitor = $this->client->getMonitor((int) $site->external_monitor_id);
            } catch (UptimeRobotException $e) {
                $result = $e->httpStatus === 404 ? SyncResult::MonitorMissing : SyncResult::ApiError;
                $this->recordUnknown($site, $checkedAt, $result, $e->getMessage());

                return $this->report('failed', 'manual', ['message' => $e->getMessage(), 'sites' => 1, 'unknown' => 1]);
            }

            $outcome = $this->record($site, $monitor, $checkedAt);
            $this->fetchResponseTimes(new Collection([$site]), $checkedAt);

            return $this->report('ok', 'manual', ['sites' => 1, $outcome => 1]);
        } finally {
            $lock->release();
        }
    }

    public function isDue(): bool
    {
        $last = Cache::get(self::LAST_RUN_KEY);
        if (! is_array($last) || empty($last['at']) || ($last['scope'] ?? 'all') !== 'all') {
            return true;
        }

        // Marge de 30 s : un cron à la minute ne saute pas un passage à cause d'une seconde de décalage.
        $every = (int) config('uptime.uptimerobot.sync_every_minutes');

        return Carbon::parse($last['at'])->addMinutes($every)->subSeconds(30)->isPast();
    }

    private function execute(Collection $sites, bool $global, string $trigger): array
    {
        $started = microtime(true);
        $checkedAt = now()->startOfMinute();
        $counts = ['sites' => $sites->count(), 'monitors' => 0, 'up' => 0, 'down' => 0, 'unknown' => 0, 'skipped' => 0, 'incidents_opened' => 0, 'incidents_resolved' => 0];

        try {
            $monitors = [];
            foreach ($this->client->monitors() as $monitor) {
                if (isset($monitor['id'])) {
                    $monitors[(string) $monitor['id']] = $monitor;
                }
            }
            $counts['monitors'] = count($monitors);
        } catch (UptimeRobotException $e) {
            // L'API ne répond pas : aucune conclusion sur les sites, seulement une trace « inconnue ».
            foreach ($sites as $site) {
                $this->recordUnknown($site, $checkedAt, SyncResult::ApiError, $e->getMessage()) ? $counts['unknown']++ : $counts['skipped']++;
            }
            Log::warning('Synchronisation UptimeRobot : API indisponible, aucun site marqué en panne.', ['error' => $e->getMessage(), 'sites' => $sites->count()]);

            return $this->finish('failed', $trigger, $global, $started, $counts + ['message' => $e->getMessage()]);
        }

        foreach ($sites as $site) {
            try {
                $monitor = $monitors[$site->external_monitor_id] ?? null;
                if ($monitor === null) {
                    $this->recordUnknown($site, $checkedAt, SyncResult::MonitorMissing, 'Moniteur introuvable dans le compte UptimeRobot (supprimé ?).') ? $counts['unknown']++ : $counts['skipped']++;

                    continue;
                }

                $outcome = $this->record($site, $monitor, $checkedAt);
                $counts[$outcome]++;
            } catch (Throwable $e) {
                // Un site en erreur n'arrête pas les autres.
                $counts['skipped']++;
                Log::error('Synchronisation UptimeRobot : échec sur un site.', ['site_id' => $site->id, 'error' => $e->getMessage()]);
            }
        }

        $counts['incidents_opened'] = $this->openedThisRun;
        $counts['incidents_resolved'] = $this->resolvedThisRun;
        $this->fetchResponseTimes($sites, $checkedAt);

        return $this->finish('ok', $trigger, $global, $started, $counts);
    }

    private int $openedThisRun = 0;

    private int $resolvedThisRun = 0;

    /**
     * Enregistre la vérification puis applique les règles d'incident.
     *
     * @return 'up'|'down'|'unknown'|'skipped'
     */
    private function record(Site $site, array $monitor, Carbon $checkedAt): string
    {
        $status = $this->mapStatus($monitor['status'] ?? null);
        $httpStatus = $this->httpStatus($monitor, $status);
        $reason = is_array($monitor['lastIncident'] ?? null) && is_string($monitor['lastIncident']['reason'] ?? null)
            ? mb_substr($monitor['lastIncident']['reason'], 0, 500) : null;

        return DB::transaction(function () use ($site, $monitor, $status, $httpStatus, $reason, $checkedAt) {
            $inserted = $this->insertCheck($site, $checkedAt, [
                'is_up' => $status->isUp(),
                'status' => $status->value,
                'http_status' => $httpStatus,
                'error_message' => $status === CheckStatus::Down ? $reason : null,
                'sync_result' => SyncResult::Ok->value,
            ]);

            if (! $inserted) {
                return 'skipped';
            }

            $site = Site::withoutGlobalScopes()->lockForUpdate()->findOrFail($site->id);
            $site->last_checked_at = $checkedAt;
            $site->last_synced_at = now();
            $site->sync_status = SyncStatus::Synced;
            $site->sync_error = $status === CheckStatus::Paused && $site->monitoring_status === MonitoringStatus::Active
                ? 'Le moniteur est en pause chez UptimeRobot alors que la surveillance est active ici.' : null;
            if ($httpStatus !== null) {
                $site->last_http_status = $httpStatus;
            }

            match ($status) {
                CheckStatus::Up => $this->onUp($site, $checkedAt),
                CheckStatus::Down => $this->onDown($site, $monitor, $checkedAt, $httpStatus, $reason),
                // Panne non confirmée, pause, statut inconnu : ni succès ni échec, le compteur ne bouge pas.
                CheckStatus::Degraded => $site->status = SiteStatus::Degraded,
                default => null,
            };

            $site->saveQuietly();

            return match ($status) {
                CheckStatus::Up => 'up',
                CheckStatus::Down => 'down',
                default => 'unknown',
            };
        });
    }

    private function onUp(Site $site, Carbon $checkedAt): void
    {
        $site->status = SiteStatus::Up;
        $site->consecutive_failures = 0;

        $open = Incident::withoutGlobalScopes()->where('site_id', $site->id)->whereNull('resolved_at')->lockForUpdate()->first();
        if ($open !== null) {
            // La durée est calculée par le modèle (resolved_at - started_at).
            $open->resolve($checkedAt->lessThan($open->started_at) ? $open->started_at : $checkedAt);
            $this->resolvedThisRun++;
            DB::afterCommit(fn () => $this->notify(fn (NotificationDispatcher $d) => $d->incidentResolved($open)));
        }
    }

    private function onDown(Site $site, array $monitor, Carbon $checkedAt, ?int $httpStatus, ?string $reason): void
    {
        $site->consecutive_failures = min(65535, $site->consecutive_failures + 1);
        $threshold = (int) config('uptime.uptimerobot.failures_before_incident', 2);
        // Tant que la panne n'est pas confirmée, le site est « dégradé », pas « hors ligne ».
        $site->status = $site->consecutive_failures >= $threshold ? SiteStatus::Down : SiteStatus::Degraded;

        $open = Incident::withoutGlobalScopes()->where('site_id', $site->id)->whereNull('resolved_at')->lockForUpdate()->first();

        if ($open !== null) {
            $open->confirmed_failures = min(65535, $site->consecutive_failures);
            $open->last_http_status = $httpStatus ?? $open->last_http_status;
            $open->last_error_message = $reason ?? $open->last_error_message;
            $open->save();

            return;
        }

        if ($site->consecutive_failures < $threshold) {
            return;
        }

        $incident = new Incident([
            'site_id' => $site->id,
            'type' => $httpStatus !== null ? IncidentType::HttpError : IncidentType::Downtime,
            'status' => IncidentStatus::Open,
            'started_at' => $this->firstFailureAt($site, $checkedAt),
            'confirmed_failures' => $site->consecutive_failures,
            'last_http_status' => $httpStatus,
            'last_error_message' => $reason,
            'description' => 'Panne confirmée par UptimeRobot ('.$site->consecutive_failures.' vérifications consécutives en échec)'.($reason ? ' : '.$reason : '.'),
        ]);
        $incident->agency_id = $site->agency_id;
        $incident->save();
        $this->openedThisRun++;
        DB::afterCommit(fn () => $this->notify(fn (NotificationDispatcher $d) => $d->incidentOpened($incident)));
    }

    /** Une erreur de notification ne doit jamais faire échouer la synchronisation. */
    private function notify(callable $send): void
    {
        try {
            $send(app(NotificationDispatcher::class));
        } catch (Throwable $e) {
            Log::error('Notifications : planification impossible.', ['error' => $e->getMessage()]);
        }
    }

    /** Début de la panne = première vérification en échec depuis le dernier succès. */
    private function firstFailureAt(Site $site, Carbon $fallback): Carbon
    {
        $lastUp = Check::withoutGlobalScopes()->where('site_id', $site->id)->where('is_up', true)->max('checked_at');

        $first = Check::withoutGlobalScopes()->where('site_id', $site->id)->where('is_up', false)
            ->when($lastUp, fn ($q) => $q->where('checked_at', '>', $lastUp))
            ->min('checked_at');

        return $first ? Carbon::parse($first) : $fallback;
    }

    /** Trace « inconnue » : n'ouvre ni ne résout aucun incident, ne touche pas au compteur d'échecs. */
    private function recordUnknown(Site $site, Carbon $checkedAt, SyncResult $result, string $message): bool
    {
        $inserted = $this->insertCheck($site, $checkedAt, [
            'is_up' => null,
            'status' => CheckStatus::Unknown->value,
            'error_message' => mb_substr($message, 0, 500),
            'sync_result' => $result->value,
        ]);

        if ($inserted) {
            Site::withoutGlobalScopes()->whereKey($site->id)->update([
                'sync_status' => SyncStatus::Failed->value,
                'sync_error' => mb_substr($message, 0, 500),
            ]);
        }

        return $inserted;
    }

    private function insertCheck(Site $site, Carbon $checkedAt, array $values): bool
    {
        return Check::withoutGlobalScopes()->insertOrIgnore([$values + [
            'site_id' => $site->id,
            'agency_id' => $site->agency_id,
            'checked_at' => $checkedAt,
            'provider' => MonitoringProvider::UptimeRobot->value,
        ]]) === 1;
    }

    /**
     * Temps de réponse : endpoint par moniteur, donc limité à quelques sites par passage
     * (les plus anciennement mesurés d'abord) et seulement si la limite de requêtes le permet.
     */
    private function fetchResponseTimes(Collection $sites, Carbon $checkedAt): void
    {
        $budget = (int) config('uptime.uptimerobot.response_times_per_run', 3);
        $remaining = $this->client->rateLimitRemaining;
        if ($remaining !== null) {
            $budget = min($budget, $remaining - (int) config('uptime.uptimerobot.keep_requests_in_reserve', 2));
        }
        if ($budget <= 0) {
            return;
        }

        $candidates = $sites->filter(fn (Site $s) => $s->monitoring_status === MonitoringStatus::Active)
            ->sortBy(fn (Site $s) => Cache::get('uptimerobot:rt:'.$s->id, 0))
            ->take($budget);

        foreach ($candidates as $site) {
            Cache::put('uptimerobot:rt:'.$site->id, time(), now()->addDay());
            try {
                $ms = $this->client->latestResponseTime((int) $site->external_monitor_id, $checkedAt->copy()->subMinutes(30)->utc());
            } catch (UptimeRobotException $e) {
                Log::info('Temps de réponse UptimeRobot non récupéré.', ['site_id' => $site->id, 'error' => $e->getMessage()]);
                if ($e->httpStatus === 429) {
                    return;
                }

                continue;
            }

            if ($ms === null) {
                continue;
            }

            Check::withoutGlobalScopes()->where('site_id', $site->id)->where('provider', MonitoringProvider::UptimeRobot->value)
                ->where('checked_at', $checkedAt)->update(['response_time_ms' => $ms]);
            Site::withoutGlobalScopes()->whereKey($site->id)->update(['last_response_time_ms' => $ms]);
        }
    }

    /** Statuts documentés : UP, DOWN, LOOKS_DOWN, PAUSED, STARTED. Tout autre valeur → inconnu. */
    private function mapStatus(mixed $status): CheckStatus
    {
        return match (is_string($status) ? strtoupper($status) : null) {
            'UP' => CheckStatus::Up,
            'DOWN' => CheckStatus::Down,
            'LOOKS_DOWN' => CheckStatus::Degraded,
            'PAUSED' => CheckStatus::Paused,
            default => CheckStatus::Unknown,
        };
    }

    /** lastIncident.cause n'est retenu comme code HTTP que pendant une panne et s'il est dans 100-599. */
    private function httpStatus(array $monitor, CheckStatus $status): ?int
    {
        $cause = $monitor['lastIncident']['cause'] ?? null;

        return $status === CheckStatus::Down && is_numeric($cause) && $cause >= 100 && $cause <= 599 ? (int) $cause : null;
    }

    private function linkedSites(?string $agencyId): Collection
    {
        return Site::withoutGlobalScopes()
            ->where('monitoring_provider', MonitoringProvider::UptimeRobot)
            ->whereNotNull('external_monitor_id')
            ->where('monitoring_status', '!=', MonitoringStatus::Disabled)
            ->when($agencyId, fn ($q) => $q->where('agency_id', $agencyId))
            ->get();
    }

    private function finish(string $status, string $trigger, bool $global, float $started, array $counts): array
    {
        $report = $this->report($status, $trigger, $counts + ['duration_ms' => (int) round((microtime(true) - $started) * 1000)]);
        $report['scope'] = $global ? 'all' : 'agency';

        if ($global) {
            Cache::forever(self::LAST_RUN_KEY, $report);
        }
        Log::info('Synchronisation UptimeRobot terminée.', $report);

        return $report;
    }

    private function report(string $status, string $trigger, array $data = []): array
    {
        return ['status' => $status, 'trigger' => $trigger, 'at' => now()->toIso8601String()] + $data;
    }
}
