<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            // Email ou numéro WhatsApp au format international (+225…).
            $table->string('destination');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['agency_id', 'type', 'destination']);
        });

        // Envois d'alertes liés aux incidents (nommé « alerts » pour ne pas
        // entrer en conflit avec la table « notifications » de Laravel).
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['incident_id', 'notification_channel_id', 'event']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('notification_channels');
    }
};
