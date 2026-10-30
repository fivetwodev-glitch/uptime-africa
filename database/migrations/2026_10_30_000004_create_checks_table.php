<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table la plus volumineuse : colonnes compactes, index limités au strict nécessaire.
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->ulid('agency_id');
            $table->ulid('site_id');
            $table->timestamp('checked_at');
            $table->boolean('is_up');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('error_type', 32)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('provider', 32)->default('internal');

            $table->foreign(['site_id', 'agency_id'], 'fk_checks_site_same_agency')->references(['id', 'agency_id'])->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'checked_at']);
            // Purge des contrôles anciens.
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
