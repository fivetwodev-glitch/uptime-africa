<?php

use App\Support\SchemaConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->ulid('client_id');
            $table->string('name');
            $table->string('url', 2048);
            // SHA-256 de l'URL normalisée : permet l'unicité sans indexer 2048 caractères.
            $table->char('url_hash', 64);

            // État du site constaté par le dernier contrôle.
            $table->string('status', 20)->default('unknown');
            // Surveillance active, en pause ou désactivée.
            $table->string('monitoring_status', 20)->default('active');
            $table->unsignedSmallInteger('interval_minutes')->default(5);
            $table->unsignedTinyInteger('timeout_seconds')->default(10);
            $table->json('expected_status_codes')->nullable();

            // Dernier résultat (dénormalisé pour le tableau de bord).
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);

            // Surveillance interne ou déléguée à un service externe.
            $table->string('monitoring_provider', 32)->default('internal');
            $table->string('external_monitor_id', 100)->nullable();
            $table->string('sync_status', 20)->default('not_applicable');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_error', 500)->nullable();

            $table->timestamps();

            // Le client doit appartenir à la MÊME agence que le site.
            $table->foreign(['client_id', 'agency_id'], 'fk_sites_client_same_agency')->references(['id', 'agency_id'])->on('clients')->cascadeOnDelete();
            $table->unique(['id', 'agency_id']);
            $table->unique(['agency_id', 'url_hash']);
            $table->unique(['monitoring_provider', 'external_monitor_id']);
            $table->index(['monitoring_status', 'next_check_at']);
            $table->index(['agency_id', 'status']);
            $table->index(['sync_status']);
        });

        SchemaConstraints::check('sites', 'chk_sites_interval', 'interval_minutes between 1 and 1440');
        SchemaConstraints::check('sites', 'chk_sites_timeout', 'timeout_seconds between 1 and 60');
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
