<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Pending = 'pending';
    case Up = 'up';
    case Down = 'down';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente du premier contrôle',
            self::Up => 'En ligne',
            self::Down => 'Hors ligne',
            self::Paused => 'En pause',
        };
    }
}
