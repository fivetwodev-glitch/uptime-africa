<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\PlanCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Données de TEST uniquement (jamais exécutées en production).
 *
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Agence test '.Str::upper(Str::random(6));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'country_code' => 'CI',
            'timezone' => 'Africa/Abidjan',
            'is_active' => true,
        ];
    }

    /** Par défaut, l'agence de test a un abonnement Business actif (limites hautes). */
    public function configure(): static
    {
        return $this->afterCreating(function (Agency $agency) {
            PlanCatalog::sync();
            $subscription = new Subscription([
                'plan_id' => Plan::where('code', 'business')->value('id'), 'status' => SubscriptionStatus::Active,
                'billing_period' => BillingPeriod::Monthly, 'price_xof' => 0, 'starts_at' => now(), 'ends_at' => now()->addYear(),
            ]);
            $subscription->agency_id = $agency->id;
            $subscription->save();
        });
    }

    public function withoutSubscription(): static
    {
        return $this->afterCreating(fn (Agency $agency) => Subscription::withoutGlobalScopes()->where('agency_id', $agency->id)->delete());
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
