<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paramètres clé/valeur : globaux (agency_id NULL) ou propres à une agence.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // VARCHAR (et non CHAR) : requis par MariaDB pour la colonne générée ci-dessous.
            $table->string('agency_id', 26)->nullable();
            $table->string('key', 100);
            $table->json('value')->nullable();
            // 1 pour un paramètre global, sinon NULL.
            $table->unsignedTinyInteger('global_marker')->nullable()
                ->storedAs('case when agency_id is null then 1 else null end');
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agencies')->cascadeOnDelete();
            // Une clé unique par agence, et une seule valeur globale par clé.
            $table->unique(['agency_id', 'key']);
            $table->unique(['global_marker', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
