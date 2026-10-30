<?php

namespace App\Enums;

enum SyncStatus: string
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Sans objet',
            self::Pending => 'En attente',
            self::Synced => 'Synchronisé',
            self::Failed => 'Échec de synchronisation',
        };
    }
}
