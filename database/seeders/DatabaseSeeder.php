<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Données de RÉFÉRENCE uniquement (aucune donnée métier fictive) :
 * - rôles et permissions système ;
 * - administrateur général si SUPER_ADMIN_EMAIL est défini.
 * Données de démonstration : php artisan db:seed --class=TestDataSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PermissionRegistry::sync();

        $email = config('uptime.super_admin.email');

        if (! $email) {
            $this->command?->warn('SUPER_ADMIN_EMAIL non défini : aucun administrateur créé.');

            return;
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $password = Str::password(16);
            $user->name = config('uptime.super_admin.name');
            $user->password = $password;
            $user->email_verified_at = now();
            $this->command?->info("Administrateur créé : {$email} / mot de passe provisoire : {$password}");
        }

        $user->agency_id = null;
        $user->assignRole(RoleName::SuperAdmin)->save();
    }
}
