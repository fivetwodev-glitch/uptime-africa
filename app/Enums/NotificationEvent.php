<?php

namespace App\Enums;

enum NotificationEvent: string
{
    case IncidentOpened = 'incident_opened';
    case IncidentResolved = 'incident_resolved';
    case ChannelVerification = 'channel_verification';
    case TestMessage = 'test_message';
    case SubscriptionReminder = 'subscription_reminder';

    public function label(): string
    {
        return match ($this) {
            self::IncidentOpened => 'Incident ouvert',
            self::IncidentResolved => 'Incident résolu',
            self::ChannelVerification => 'Vérification du canal',
            self::TestMessage => 'Message de test',
            self::SubscriptionReminder => 'Rappel d\'abonnement',
        };
    }
}
