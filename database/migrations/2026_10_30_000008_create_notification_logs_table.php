<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal des notifications : historique conservé même si le site,
        // l'incident ou le canal sont supprimés (références mises à NULL,
        // destinataire et type copiés dans la ligne).
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('notification_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_type', 20);
            $table->string('recipient');
            $table->string('event', 40);
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider_message_id', 191)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Anti-doublon : un seul envoi par incident, canal et événement.
            $table->unique(['incident_id', 'notification_channel_id', 'event'], 'notification_logs_dedup');
            $table->index(['agency_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
