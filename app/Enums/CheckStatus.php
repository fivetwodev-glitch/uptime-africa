<?php

namespace App\Enums;

/** Statut détaillé d'une vérification. */
enum CheckStatus: string
{
    case Up = 'up';
    case Down = 'down';
    case Degraded = 'degraded';
    case Paused = 'paused';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Up => 'En ligne',
            self::Down => 'Hors ligne',
            self::Degraded => 'Panne non confirmée',
            self::Paused => 'En pause',
            self::Unknown => 'Inconnu',
        };
    }

    /** true = succès, false = échec confirmé, null = ni l'un ni l'autre (jamais compté comme panne). */
    public function isUp(): ?bool
    {
        return match ($this) {
            self::Up => true,
            self::Down => false,
            default => null,
        };
    }
}
