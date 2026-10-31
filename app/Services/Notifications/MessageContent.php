<?php

namespace App\Services\Notifications;

use App\Enums\NotificationEvent;
use App\Models\Agency;
use App\Models\Incident;
use App\Models\NotificationLog;
use App\Models\Site;
use App\Support\Format;

/**
 * Contenu d'un message, identique pour l'email et WhatsApp.
 * whatsapp = valeurs des variables {{1}}, {{2}}… du modèle approuvé (voir docs/notifications.md).
 */
final class MessageContent
{
    public function __construct(
        public readonly string $subject,
        public readonly string $headline,
        public readonly string $tone, // danger | success | warning | info
        public readonly array $facts,
        public readonly ?string $intro = null,
        public readonly ?string $actionUrl = null,
        public readonly array $whatsapp = [],
        public readonly array $extra = [],
    ) {}

    public static function for(NotificationLog $log): self
    {
        $incident = $log->incident_id ? Incident::withoutGlobalScopes()->find($log->incident_id) : null;
        $site = $log->site_id ? Site::withoutGlobalScopes()->find($log->site_id) : null;
        $agency = Agency::find($log->agency_id);

        return match ($log->event) {
            NotificationEvent::IncidentOpened => self::down($site, $incident),
            NotificationEvent::IncidentResolved => self::recovery($site, $incident),
            NotificationEvent::IncidentProlonged => self::prolonged($site, $incident),
            NotificationEvent::TestMessage => self::test($agency),
            default => throw new DeliveryException('Événement sans modèle de message : '.$log->event->value),
        };
    }

    private static function down(Site $site, Incident $incident): self
    {
        $reason = $incident->last_error_message ?: 'Le site ne répond pas correctement.';

        return new self(
            subject: "🔴 Panne : {$site->name}",
            headline: "{$site->name} est hors ligne",
            tone: 'danger',
            intro: 'La panne a été confirmée par deux vérifications consécutives.',
            facts: [
                'Site' => $site->name,
                'Adresse' => $site->url,
                'Début' => Format::datetime($incident->started_at),
                'Code HTTP' => $incident->last_http_status ? (string) $incident->last_http_status : '—',
                'Détail' => $reason,
            ],
            actionUrl: route('incidents.show', $incident->id),
            whatsapp: [$site->name, $site->url, Format::datetime($incident->started_at), mb_substr($reason, 0, 200)],
        );
    }

    private static function recovery(Site $site, Incident $incident): self
    {
        $duration = Format::duration($incident->duration_seconds);

        return new self(
            subject: "🟢 Retour en ligne : {$site->name}",
            headline: "{$site->name} est de nouveau en ligne",
            tone: 'success',
            facts: [
                'Site' => $site->name,
                'Adresse' => $site->url,
                'Début de la panne' => Format::datetime($incident->started_at),
                'Retour en ligne' => Format::datetime($incident->resolved_at),
                'Durée' => $duration,
            ],
            actionUrl: route('incidents.show', $incident->id),
            whatsapp: [$site->name, $site->url, $duration],
        );
    }

    private static function prolonged(Site $site, Incident $incident): self
    {
        $duration = Format::duration((int) $incident->started_at->diffInSeconds(now(), true));

        return new self(
            subject: "🟠 Panne prolongée : {$site->name}",
            headline: "{$site->name} est toujours hors ligne",
            tone: 'warning',
            intro: "La panne dure depuis {$duration}.",
            facts: [
                'Site' => $site->name,
                'Adresse' => $site->url,
                'Début' => Format::datetime($incident->started_at),
                'Durée actuelle' => $duration,
                'Détail' => $incident->last_error_message ?: '—',
            ],
            actionUrl: route('incidents.show', $incident->id),
            whatsapp: [$site->name, $duration, $site->url],
        );
    }

    private static function test(?Agency $agency): self
    {
        $name = $agency?->name ?? config('app.name');

        return new self(
            subject: 'Test de notification Uptime Africa',
            headline: 'Votre configuration fonctionne',
            tone: 'info',
            intro: "Ce message de test confirme que ce destinataire recevra les alertes de l'agence {$name}.",
            facts: ['Agence' => $name, 'Envoyé le' => Format::datetime(now())],
            actionUrl: route('notifications.index'),
            whatsapp: [$name],
        );
    }
}
