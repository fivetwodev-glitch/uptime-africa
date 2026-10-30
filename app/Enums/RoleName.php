<?php

namespace App\Enums;

/** Identifiants des rôles système (table roles, colonne slug). */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';
}
