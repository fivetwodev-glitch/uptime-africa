<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ne crée AUCUNE donnée métier fictive.
 * Seul l'administrateur général est créé si SUPER_ADMIN_EMAIL est défini.
 * Les offres d'abonnement seront saisies par l'administrateur (phase 7).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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

        $user->role = Role::SuperAdmin;
        $user->agency_id = null;
        $user->save();
    }
}
