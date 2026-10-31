<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * external_managed : le moniteur externe a été créé par Uptime Africa
 * (il est donc supprimé chez le fournisseur quand le site est supprimé).
 * Un moniteur seulement associé n'est jamais supprimé à distance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('external_managed')->default(false)->after('external_monitor_id');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('external_managed');
        });
    }
};
