<?php

namespace App\Enums;

/** Qui effectue les contrôles : notre moteur ou un service externe synchronisé. */
enum MonitoringProvider: string
{
    case Internal = 'internal';
    case UptimeRobot = 'uptime_robot';
    case BetterStack = 'better_stack';
    case Other = 'other';

    /**
     * Fournisseurs proposés à la création d'un site : seulement ceux dont l'intégration
     * fonctionne réellement. Le moteur interne, Better Stack et « Autre » ne contrôlent
     * encore rien : les proposer laisserait croire qu'un site est surveillé.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::UptimeRobot];
    }

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
