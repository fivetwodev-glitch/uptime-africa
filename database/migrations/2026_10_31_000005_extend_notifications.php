<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications email + WhatsApp :
 * - préférences : alerte d'incident prolongé (seuil en minutes) ;
 * - journal : clé anti-doublon générique (résumés, tests), modèle utilisé, fournisseur,
 *   horodatages de remise et de lecture (webhooks WhatsApp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('notify_on_prolonged')->default(true)->after('notify_on_recovery');
            $table->unsignedSmallInteger('prolonged_after_minutes')->default(30)->after('notify_on_prolonged');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('dedup_key', 191)->nullable()->after('event');
            $table->string('template', 100)->nullable()->after('dedup_key');
            $table->string('provider', 32)->nullable()->after('channel_type');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');

            $table->unique('dedup_key', 'notification_logs_dedup_key');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropUnique('notification_logs_dedup_key');
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn(['dedup_key', 'template', 'provider', 'delivered_at', 'read_at']);
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['notify_on_prolonged', 'prolonged_after_minutes']);
        });
    }
};
