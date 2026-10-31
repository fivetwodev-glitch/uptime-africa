<?php

namespace App\Enums;

enum NotificationEvent: string
{
    case IncidentOpened = 'incident_opened';
    case IncidentResolved = 'incident_resolved';
    case IncidentProlonged = 'incident_prolonged';
    case PeriodicDigest = 'periodic_digest';
    case ChannelVerification = 'channel_verification';
    case TestMessage = 'test_message';
    case SubscriptionReminder = 'subscription_reminder';

    public function label(): string
    {
        return match ($this) {
            self::IncidentOpened => 'Panne',
            self::IncidentResolved => 'Retour en ligne',
            self::IncidentProlonged => 'Incident prolongé',
            self::PeriodicDigest => 'Résumé périodique',
            self::ChannelVerification => 'Vérification du canal',
            self::TestMessage => 'Message de test',
            self::SubscriptionReminder => 'Rappel d\'abonnement',
        };
    }
}
