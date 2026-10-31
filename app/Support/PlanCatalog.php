<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Insère les offres de config/plans.php si elles n'existent pas encore.
 * Une offre existante n'est jamais modifiée : l'administration fait foi.
 */
class PlanCatalog
{
    public static function sync(): void
    {
        $now = now();

        foreach (require config_path('plans.php') as $code => $plan) {
            if (DB::table('plans')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('plans')->insert([
                'code' => $code,
                'name' => $plan['name'],
                'audience' => $plan['audience'],
                'tagline' => $plan['tagline'],
                'monthly_price_xof' => 0,
                'yearly_price_xof' => null,
                'price_validated' => false,
                'max_sites' => $plan['max_sites'],
                'max_clients' => $plan['max_clients'],
                'max_users' => $plan['max_users'],
                'min_interval_minutes' => $plan['min_interval_minutes'],
                'retention_days' => $plan['retention_days'],
                'includes_whatsapp' => $plan['includes_whatsapp'],
                'whatsapp_monthly_quota' => $plan['whatsapp_monthly_quota'],
                'features' => json_encode($plan['features']),
                'is_public' => true,
                'is_active' => true,
                'sort_order' => $plan['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
