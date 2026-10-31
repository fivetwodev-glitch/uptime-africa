<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteRequest;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Super Admin : gestion des offres (limites, fonctionnalités, tarifs et leur validation). */
class PlanController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin.access');

        return view('admin.plans.index', [
            'plans' => Plan::ordered()->withCount(['subscriptions as current_count' => fn ($q) => $q->whereNotNull('current_marker')])->get(),
        ]);
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('admin.access');

        return view('admin.plans.edit', ['plan' => $plan, 'features' => PlanFeature::cases(), 'intervals' => SiteRequest::INTERVALS]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('admin.access');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'audience' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'monthly_price_xof' => ['required', 'integer', 'min:0', 'max:100000000'],
            'yearly_price_xof' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'price_validated' => ['boolean'],
            'max_sites' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_clients' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_users' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_interval_minutes' => ['required', 'integer', Rule::in(SiteRequest::INTERVALS)],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'includes_whatsapp' => ['boolean'],
            'whatsapp_monthly_quota' => ['required', 'integer', 'min:0', 'max:1000000'],
            'features' => ['array'],
            'features.*' => [Rule::enum(PlanFeature::class)],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [], ['monthly_price_xof' => 'prix mensuel', 'yearly_price_xof' => 'prix annuel', 'max_sites' => 'nombre de sites']);

        foreach (['price_validated', 'includes_whatsapp', 'is_public', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['features'] = array_values(array_unique($data['features'] ?? []));

        $plan->fill($data);
        $changes = collect($plan->getDirty())->except('updated_at')
            ->map(fn ($new, $field) => ['avant' => $plan->getOriginal($field), 'apres' => $plan->getAttribute($field)])->all();
        $plan->save();

        if ($changes) {
            // Les abonnements déjà souscrits gardent leur prix figé ; les limites s'appliquent immédiatement.
            AuditLog::record(isset($changes['price_validated']) && $plan->price_validated ? 'plan.price_validated' : 'plan.updated', $plan, ['code' => $plan->code, 'changes' => $changes]);
        }

        return redirect()->route('admin.plans')->with('status', "Offre {$plan->name} enregistrée.");
    }
}
