<?php

namespace App\Console\Commands\Notifications;

use App\Enums\ChannelType;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Enums\PlanFeature;
use App\Mail\DigestMail;
use App\Models\Agency;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\Site;
use App\Services\Billing\PlanLimits;
use App\Services\Monitoring\UptimeStats;
use App\Services\Notifications\NotificationSettings;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Résumé périodique par email (quotidien ou hebdomadaire), à l'heure locale de l'agence.
 * Lancé chaque heure par le planificateur ; dedup_key = agence + destinataire + période : jamais deux fois.
 */
class SendDigests extends Command
{
    protected $signature = 'notifications:digest {--force : envoyer maintenant, quelle que soit l\'heure}';

    protected $description = 'Envoie les résumés périodiques activés';

    public function handle(UptimeStats $stats, TenantContext $tenant): int
    {
        $sent = 0;

        foreach (Agency::where('is_active', true)->get() as $agency) {
            $settings = NotificationSettings::for($agency->id);
            if (! $settings['enabled'] || ! $settings['channels']['email'] || $settings['digest'] === 'off'
                || ! app(PlanLimits::class)->has($agency, PlanFeature::AdvancedNotifications)) {
                continue;
            }

            $local = now()->timezone($agency->timezone ?: 'Africa/Abidjan');
            $due = $local->hour === (int) config('uptime.notifications.digest_hour')
                && ($settings['digest'] === 'daily' || $local->isMonday());
            if (! $due && ! $this->option('force')) {
                continue;
            }

            $tenant->set($agency->id);
            try {
                $sent += $this->sendFor($agency, $settings['digest'], $local, $stats);
            } finally {
                $tenant->clear();
            }
        }

        $this->line("{$sent} résumé(s) envoyé(s).");

        return self::SUCCESS;
    }

    private function sendFor(Agency $agency, string $frequency, Carbon $local, UptimeStats $stats): int
    {
        $to = $local->copy()->startOfDay()->utc();
        $from = $frequency === 'weekly' ? $to->copy()->subWeek() : $to->copy()->subDay();
        $period = $frequency === 'weekly' ? 'hebdomadaire' : 'quotidien';

        $sites = Site::orderBy('name')->get(['id', 'name']);
        $incidentCounts = Incident::whereBetween('started_at', [$from, $to])->selectRaw('site_id, count(*) as total')->groupBy('site_id')->pluck('total', 'site_id');
        $digest = [
            'agency' => $agency->name,
            'period_label' => $period,
            'from' => $from,
            'to' => $to,
            'sites_count' => $sites->count(),
            'incidents_count' => (int) $incidentCounts->sum(),
            'open_count' => Incident::open()->count(),
            'sites' => $sites->map(fn (Site $site) => [
                'name' => $site->name,
                'uptime' => $stats->summary($from, $site->id, $to)['uptime'],
                'incidents' => (int) ($incidentCounts[$site->id] ?? 0),
            ])->all(),
        ];

        $count = 0;
        $channels = NotificationChannel::where('type', ChannelType::Email)->where('is_active', true)->get();
        foreach ($channels as $channel) {
            $key = "digest:{$agency->id}:{$channel->id}:{$frequency}:".$from->toDateString();
            $inserted = NotificationLog::withoutGlobalScopes()->insertOrIgnore([[
                'agency_id' => $agency->id, 'notification_channel_id' => $channel->id, 'channel_type' => ChannelType::Email->value,
                'recipient' => $channel->destination, 'event' => NotificationEvent::PeriodicDigest->value, 'dedup_key' => $key,
                'status' => NotificationStatus::Sending->value, 'attempts' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]]);
            if ($inserted !== 1) {
                continue;
            }

            $log = NotificationLog::withoutGlobalScopes()->where('dedup_key', $key)->first();
            try {
                $message = Mail::to($channel->destination)->send(new DigestMail($digest));
                $log->forceFill(['status' => NotificationStatus::Sent, 'sent_at' => now(), 'provider' => 'smtp', 'provider_message_id' => $message?->getMessageId()])->saveQuietly();
                $count++;
            } catch (Throwable $e) {
                $log->forceFill(['status' => NotificationStatus::Failed, 'error_message' => mb_substr($e->getMessage(), 0, 500)])->saveQuietly();
            }
        }

        return $count;
    }
}
