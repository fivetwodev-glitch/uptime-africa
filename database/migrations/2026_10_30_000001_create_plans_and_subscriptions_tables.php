<?php

use App\Support\SchemaConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            // Montants en FCFA (XOF), entiers sans décimales.
            $table->unsignedInteger('monthly_price_xof');
            $table->unsignedInteger('yearly_price_xof')->nullable();
            $table->unsignedInteger('max_sites');
            $table->unsignedSmallInteger('max_users');
            $table->unsignedSmallInteger('min_interval_minutes');
            $table->unsignedSmallInteger('retention_days');
            $table->boolean('includes_whatsapp')->default(false);
            $table->unsignedInteger('whatsapp_monthly_quota')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            // Une offre souscrite ne peut pas être supprimée.
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('billing_period', 10)->default('monthly');
            // Prix figé au moment de la souscription.
            $table->unsignedInteger('price_xof');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('payment_provider', 32)->nullable();
            $table->string('payment_reference', 100)->nullable();
            // 1 tant que l'abonnement est en cours, sinon NULL : une seule souscription en cours par agence.
            $table->unsignedTinyInteger('current_marker')->nullable()
                ->storedAs("case when status in ('trialing', 'active', 'past_due') then 1 else null end");
            $table->timestamps();

            $table->unique(['agency_id', 'current_marker'], 'subscriptions_one_current_per_agency');
            $table->unique(['payment_provider', 'payment_reference']);
            $table->index(['status', 'ends_at']);
        });

        SchemaConstraints::check('subscriptions', 'chk_subscriptions_period', 'ends_at is null or ends_at >= starts_at');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
