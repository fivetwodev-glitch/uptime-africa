<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifications issues d'un fournisseur externe :
 * - is_up devient nullable : un statut inconnu (API muette, moniteur en pause) n'est ni un succès ni une panne ;
 * - status : statut détaillé (up, down, degraded, paused, unknown) ;
 * - sync_result : résultat de la synchronisation (ok, api_error, monitor_missing) ;
 * - unicité (site, fournisseur, minute) : rejouer une synchronisation ne duplique rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->boolean('is_up')->nullable()->change();
            $table->string('status', 20)->nullable()->after('is_up');
            $table->string('sync_result', 20)->nullable()->after('provider');
            $table->unique(['site_id', 'provider', 'checked_at'], 'checks_site_provider_time_unique');
        });
    }

    public function down(): void
    {
        Schema::table('checks', function (Blueprint $table) {
            $table->dropUnique('checks_site_provider_time_unique');
            $table->dropColumn(['status', 'sync_result']);
        });
    }
};
