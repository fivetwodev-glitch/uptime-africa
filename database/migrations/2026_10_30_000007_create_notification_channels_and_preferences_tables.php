<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Destinataires des alertes (adresse email ou numéro WhatsApp +225…).
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label', 100)->nullable();
            $table->string('destination');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id', 'agency_id']);
            $table->unique(['agency_id', 'type', 'destination']);
        });

        // Préférences d'alerte : pour un canal, quels sites et quels événements.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->ulid('agency_id');
            $table->unsignedBigInteger('notification_channel_id');
            // NULL = tous les sites de l'agence. VARCHAR (et non CHAR) : requis par MariaDB
            // pour la colonne générée ci-dessous ; compatible avec la clé étrangère.
            $table->string('site_id', 26)->nullable();
            $table->boolean('notify_on_down')->default(true);
            $table->boolean('notify_on_recovery')->default(true);
            // Délai avant alerte (minutes) pour ignorer les micro-coupures.
            $table->unsignedSmallInteger('delay_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            // 1 pour la préférence « tous les sites », sinon NULL.
            $table->unsignedTinyInteger('all_sites_marker')->nullable()
                ->storedAs('case when site_id is null then 1 else null end');
            $table->timestamps();

            $table->foreign(['notification_channel_id', 'agency_id'], 'fk_notif_prefs_channel_same_agency')->references(['id', 'agency_id'])->on('notification_channels')->cascadeOnDelete();
            $table->foreign(['site_id', 'agency_id'], 'fk_notif_prefs_site_same_agency')->references(['id', 'agency_id'])->on('sites')->cascadeOnDelete();
            // Une préférence par canal et par site, et une seule préférence « tous les sites » par canal.
            $table->unique(['notification_channel_id', 'site_id'], 'notification_preferences_channel_site_unique');
            $table->unique(['notification_channel_id', 'all_sites_marker'], 'notification_preferences_channel_all_unique');
            $table->index('agency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_channels');
    }
};
