<?php

namespace App\Enums;

/** Identifiants des rôles système (table roles, colonne slug). */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Collaborator = 'collaborator';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Admin => 'Administrateur d\'agence',
            self::Collaborator => 'Collaborateur',
        };
    }

    /** Rôles qu'un administrateur d'agence peut attribuer. Jamais le super administrateur. */
    public static function assignableInAgency(): array
    {
        return [self::Admin, self::Collaborator];
    }
}
