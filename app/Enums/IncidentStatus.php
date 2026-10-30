<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'En cours',
            self::Acknowledged => 'Pris en charge',
            self::Resolved => 'Résolu',
        };
    }
}
