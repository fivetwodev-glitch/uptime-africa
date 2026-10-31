<?php

namespace App\Services\Billing;

use App\Enums\PlanFeature;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Limites de l'offre en cours d'une agence. Sans abonnement en cours (essai,
 * actif ou paiement en retard), aucune création n'est permise ; la surveillance
 * des sites existants continue.
 */
class PlanLimits
{
    public const RESOURCES = ['sites' => 'site(s)', 'clients' => 'client(s)', 'users' => 'utilisateur(s)'];

    /** @var array<string, Plan|null> */
    private array $plans = [];

    public function planFor(Agency|string $agency): ?Plan
    {
        $id = $agency instanceof Agency ? $agency->id : $agency;

        return $this->plans[$id] ??= Subscription::withoutGlobalScopes()
            ->where('agency_id', $id)->whereNotNull('current_marker')->with('plan')->first()?->plan;
    }

    public function forget(Agency|string $agency): void
    {
        unset($this->plans[$agency instanceof Agency ? $agency->id : $agency]);
    }

    /** null = illimité. */
    public function max(?Plan $plan, string $resource): ?int
    {
        return match ($resource) {
            'sites' => $plan->max_sites,
            'clients' => $plan->max_clients,
            'users' => $plan->max_users,
        };
    }

    public function usage(Agency|string $agency): array
    {
        $id = $agency instanceof Agency ? $agency->id : $agency;

        return [
            'sites' => Site::withoutGlobalScopes()->where('agency_id', $id)->count(),
            'clients' => Client::withoutGlobalScopes()->where('agency_id', $id)->count(),
            'users' => User::where('agency_id', $id)->count(),
        ];
    }

    /** Refuse l'ajout d'un élément au-delà de la limite (message affiché sous le champ $field). */
    public function ensureCanAdd(Agency|string $agency, string $resource, string $field): void
    {
        $plan = $this->planFor($agency);

        if ($plan === null) {
            throw ValidationException::withMessages([$field => 'Aucun abonnement en cours : choisissez une formule dans la page Abonnement.']);
        }

        $max = $this->max($plan, $resource);
        if ($max !== null && $this->usage($agency)[$resource] >= $max) {
            throw ValidationException::withMessages([$field => "Votre formule {$plan->name} est limitée à {$max} ".self::RESOURCES[$resource].'. Passez à une formule supérieure pour en ajouter.']);
        }
    }

    /** Raisons pour lesquelles l'agence ne peut pas passer à $plan (utilisation actuelle trop élevée). */
    public function blockersFor(Agency|string $agency, Plan $plan): array
    {
        $blockers = [];

        foreach ($this->usage($agency) as $resource => $used) {
            $max = $this->max($plan, $resource);
            if ($max !== null && $used > $max) {
                $blockers[] = "{$used} ".self::RESOURCES[$resource]." pour {$max} autorisé(s)";
            }
        }

        return $blockers;
    }

    public function has(Agency|string $agency, PlanFeature $feature): bool
    {
        return (bool) $this->planFor($agency)?->hasFeature($feature);
    }

    public function allowsWhatsApp(Agency|string $agency): bool
    {
        return (bool) $this->planFor($agency)?->includes_whatsapp;
    }

    public function minInterval(Agency|string $agency): ?int
    {
        return $this->planFor($agency)?->min_interval_minutes;
    }

    /** Nombre de jours d'historique consultables ; null = pas d'offre (historique de 7 jours). */
    public function retentionDays(Agency|string $agency): int
    {
        return $this->planFor($agency)?->retention_days ?? 7;
    }
}
