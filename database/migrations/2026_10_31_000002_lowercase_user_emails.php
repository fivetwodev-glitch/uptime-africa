<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Les emails sont désormais stockés en minuscules (connexion et unicité insensibles à la casse). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update(['email' => DB::raw('lower(email)')]);
    }

    public function down(): void
    {
        // Rien à restaurer : la casse d'origine n'a pas de valeur fonctionnelle.
    }
};
