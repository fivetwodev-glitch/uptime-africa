<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En file',
            self::Sending => 'Envoi en cours',
            self::Sent => 'Envoyée',
            self::Delivered => 'Remise',
            self::Read => 'Lue',
            self::Failed => 'Échec',
            self::Skipped => 'Ignorée',
        };
    }

    /** Rang de progression : un webhook ne fait jamais reculer un statut. */
    public function rank(): int
    {
        return match ($this) {
            self::Queued, self::Skipped => 0,
            self::Sending => 1,
            self::Sent => 2,
            self::Delivered => 3,
            self::Read => 4,
            self::Failed => 5,
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Sent, self::Delivered, self::Read], true);
    }
}
