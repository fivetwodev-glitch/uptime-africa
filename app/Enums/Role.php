<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Administrateur général',
            self::Owner => 'Propriétaire de l\'agence',
            self::Member => 'Membre',
        };
    }
}
