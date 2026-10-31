<?php

namespace App\Services\Notifications;

use App\Enums\ChannelType;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Enums\PlanFeature;
use App\Jobs\SendNotification;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Collection;

/**
 * Décide qui reçoit quoi, écrit le journal puis place l'envoi en file.
 * Anti-doublon : une ligne par (incident, destinataire, événement) — contrainte unique en base,
 * plus une clé dedup_key pour les résumés et les tests.
 */
class NotificationDispatcher
{
    public function __construct(private readonly WhatsAppClient $whatsapp, private readonly PlanLimits $limits) {}

    public function incidentOpened(Incident $incident): int
    {
        return $this->forIncident($incident, NotificationEvent::IncidentOpened, function (NotificationPreference $pref) {
            return $pref->notify_on_down ? max(0, (int) $pref->delay_minutes) : null;
        });
    }

    /** Retour en ligne : seulement aux destinataires qui ont effectivement reçu l'alerte de panne. */
    public function incidentResolved(Incident $incident): int
    {
        $alerted = NotificationLog::withoutGlobalScopes()
            ->where('incident_id', $incident->id)
            ->where('event', NotificationEvent::IncidentOpened)
            ->whereIn('status', [NotificationStatus::Sent, NotificationStatus::Delivered, NotificationStatus::Read])
            ->pluck('notification_channel_id')->all();

        // L'alerte de panne encore en attente (délai) devient sans objet.
        NotificationLog::withoutGlobalScopes()
            ->where('incident_id', $incident->id)->where('event', NotificationEvent::IncidentOpened)
            ->where('status', NotificationStatus::Queued)
            ->update(['status' => NotificationStatus::Skipped, 'error_message' => 'Incident résolu avant l\'envoi.']);

        return $this->forIncident($incident, NotificationEvent::IncidentResolved, function (NotificationPreference $pref, NotificationChannel $channel) use ($alerted) {
            return $pref->notify_on_recovery && in_array($channel->id, $alerted, true) ? 0 : null;
        });
    }

    /** Incident encore ouvert après le seuil de la préférence : une seule alerte par incident et destinataire. */
    public function incidentProlonged(Incident $incident): int
    {
        if (! $this->limits->has($incident->agency_id, PlanFeature::AdvancedNotifications)) {
            return 0; // alerte de panne prolongée : notifications avancées (Pro, Business)
        }
        $minutes = (int) $incident->started_at->diffInMinutes(now(), true);

        return $this->forIncident($incident, NotificationEvent::IncidentProlonged, function (NotificationPreference $pref) use ($minutes) {
            return $pref->notify_on_prolonged && $minutes >= (int) $pref->prolonged_after_minutes ? 0 : null;
        });
    }

    /** Message de test immédiat (hors file) : le résultat réel est renvoyé à l'interface. */
    public function test(NotificationChannel $channel, ChannelSender $sender): NotificationLog
    {
        $log = new NotificationLog([
            'notification_channel_id' => $channel->id,
            'channel_type' => $channel->type,
            'recipient' => $channel->destination,
            'event' => NotificationEvent::TestMessage,
        ]);
        $log->agency_id = $channel->agency_id;
        $log->save();

        $sender->send($log);

        return $log->refresh();
    }

    /**
     * @param  callable(NotificationPreference, NotificationChannel): ?int  $delayFor  délai en minutes, ou null = ne pas envoyer
     */
    private function forIncident(Incident $incident, NotificationEvent $event, callable $delayFor): int
    {
        $queued = 0;

        foreach ($this->recipients($incident) as [$channel, $pref]) {
            $delay = $delayFor($pref, $channel);
            if ($delay === null) {
                continue;
            }

            $values = [
                'agency_id' => $incident->agency_id,
                'site_id' => $incident->site_id,
                'incident_id' => $incident->id,
                'notification_channel_id' => $channel->id,
                'channel_type' => $channel->type->value,
                'recipient' => $channel->destination,
                'event' => $event->value,
                'status' => NotificationStatus::Queued->value,
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! NotificationSettings::allows($incident->agency_id, $channel->type)) {
                continue;
            }
            if ($channel->type === ChannelType::WhatsApp && ! $this->limits->allowsWhatsApp($incident->agency_id)) {
                $values['status'] = NotificationStatus::Skipped->value;
                $values['error_message'] = 'La formule de l\'agence n\'inclut pas WhatsApp.';
            } elseif ($channel->type === ChannelType::WhatsApp && ! $this->whatsapp->isConfigured()) {
                // Tracé dans le journal plutôt qu'ignoré en silence ; jamais présenté comme envoyé.
                $values['status'] = NotificationStatus::Skipped->value;
                $values['error_message'] = 'WhatsApp n\'est pas configuré sur le serveur.';
            }

            if (NotificationLog::withoutGlobalScopes()->insertOrIgnore([$values]) !== 1) {
                continue; // déjà journalisé pour cet incident et ce destinataire
            }
            if ($values['status'] !== NotificationStatus::Queued->value) {
                continue;
            }

            $logId = NotificationLog::withoutGlobalScopes()
                ->where('incident_id', $incident->id)->where('notification_channel_id', $channel->id)->where('event', $event->value)
                ->value('id');

            SendNotification::dispatch($logId)->delay(now()->addMinutes($delay))->afterCommit();
            $queued++;
        }

        return $queued;
    }

    /**
     * Destinataires actifs de l'agence avec la préférence applicable
     * (préférence du site si elle existe, sinon « tous les sites »).
     *
     * @return Collection<int, array{0: NotificationChannel, 1: NotificationPreference}>
     */
    private function recipients(Incident $incident): Collection
    {
        $channels = NotificationChannel::withoutGlobalScopes()
            ->where('agency_id', $incident->agency_id)->where('is_active', true)
            ->with(['preferences' => fn ($q) => $q->withoutGlobalScopes()->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $incident->site_id))])
            ->get();

        return $channels->map(function (NotificationChannel $channel) {
            $pref = $channel->preferences->firstWhere('site_id', '!=', null) ?? $channel->preferences->firstWhere('site_id', null);

            return $pref ? [$channel, $pref] : null;
        })->filter()->values();
    }
}
