<?php

use App\Support\PlanCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('audience', 120)->nullable()->after('name');
            $table->string('tagline', 255)->nullable()->after('audience');
            // Faux tant que le tarif n'a pas été validé : l'offre ne peut pas être payée.
            $table->boolean('price_validated')->default(false)->after('yearly_price_xof');
            $table->unsignedInteger('max_clients')->nullable()->after('max_sites');
            $table->json('features')->nullable()->after('whatsapp_monthly_quota');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Changement de plan / renouvellement : abonnement remplacé.
            $table->foreignId('previous_subscription_id')->nullable()->after('plan_id')->constrained('subscriptions')->nullOnDelete();
            $table->timestamp('renewed_at')->nullable()->after('cancelled_at');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            // activation (nouveau plan) ou renewal (prolongation de l'abonnement en cours).
            $table->string('purpose', 20);
            $table->unsignedInteger('amount_xof');
            $table->string('currency', 3)->default('XOF');
            $table->string('status', 20);
            $table->string('provider', 32);
            $table->string('provider_reference', 100)->nullable();
            $table->string('method', 40)->nullable();
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['agency_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        PlanCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_subscription_id');
            $table->dropColumn('renewed_at');
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['audience', 'tagline', 'price_validated', 'max_clients', 'features', 'sort_order']);
        });
    }
};
