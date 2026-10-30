<?php

namespace App\Enums;

/** État constaté par le dernier contrôle. */
enum SiteStatus: string
{
    case Unknown = 'unknown';
    case Up = 'up';
    case Down = 'down';
    case Degraded = 'degraded';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Inconnu',
            self::Up => 'En ligne',
            self::Down => 'Hors ligne',
            self::Degraded => 'Dégradé',
        };
    }
}
