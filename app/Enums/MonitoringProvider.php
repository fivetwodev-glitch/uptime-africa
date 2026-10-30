<?php

namespace App\Enums;

/** Qui effectue les contrôles : notre moteur ou un service externe synchronisé. */
enum MonitoringProvider: string
{
    case Internal = 'internal';
    case UptimeRobot = 'uptime_robot';
    case BetterStack = 'better_stack';
    case Other = 'other';

    public function isExternal(): bool
    {
        return $this !== self::Internal;
    }

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Moteur Uptime Africa',
            self::UptimeRobot => 'UptimeRobot',
            self::BetterStack => 'Better Stack',
            self::Other => 'Autre fournisseur',
        };
    }
}
