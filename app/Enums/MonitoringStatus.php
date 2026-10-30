<?php

namespace App\Enums;

enum MonitoringStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Surveillance active',
            self::Paused => 'En pause',
            self::Disabled => 'Désactivée',
        };
    }
}
