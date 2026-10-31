<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingPeriod;
use App\Enums\MonitoringStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Espace super administrateur : statistiques, agences, suspension / réactivation.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin.access');

        return view('admin.index', [
            'agencies' => Agency::withCount(['users', 'clients', 'sites'])->with('currentSubscription.plan:id,name')->orderBy('name')->paginate(30),
            'lastHeartbeat' => Cache::get(config('uptime.heartbeat.cache_key')),
        ]);
    }

    /** Statistiques globales de la plateforme (aucune donnée inventée : uniquement des comptages en base). */
    public function stats(): View
    {
        $this->authorize('admin.access');

        $subscriptions = Subscription::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $active = Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])->get(['billing_period', 'price_xof']);

        return view('admin.stats', [
            'agencies' => ['total' => Agency::count(), 'active' => Agency::where('is_active', true)->count()],
            'users' => ['total' => User::count(), 'active' => User::where('is_active', true)->count()],
            'sites' => ['total' => Site::count(), 'monitored' => Site::where('monitoring_status', MonitoringStatus::Active)->count()],
            'incidents' => ['open' => Incident::whereNull('resolved_at')->count(), 'last30' => Incident::where('started_at', '>=', now()->subDays(30))->count()],
            'subscriptions' => $subscriptions,
            'byPlan' => Plan::ordered()->withCount(['subscriptions as current_count' => fn ($q) => $q->whereNotNull('current_marker')])->get(),
            // Revenu mensuel récurrent : abonnements payants en cours, prix annuels ramenés au mois.
            'mrr' => (int) round($active->sum(fn ($s) => $s->billing_period === BillingPeriod::Yearly ? $s->price_xof / 12 : $s->price_xof)),
            'paidThisMonth' => (int) Payment::where('status', PaymentStatus::Paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount_xof'),
            'pendingPayments' => Payment::where('status', PaymentStatus::Pending)->count(),
            'lastHeartbeat' => Cache::get(config('uptime.heartbeat.cache_key')),
        ]);
    }

    /** Suspendre une agence bloque immédiatement l'accès de tous ses utilisateurs (SetTenantContext). */
    public function toggleAgency(Agency $agency): RedirectResponse
    {
        $this->authorize('admin.access');

        $agency->is_active = ! $agency->is_active;
        $agency->save();

        if (! $agency->is_active) {
            $agency->users()->get()->each(fn ($user) => Sessions::revokeAll($user));
        }
        AuditLog::record($agency->is_active ? 'agency.reactivated' : 'agency.suspended', $agency);

        return back()->with('status', $agency->is_active ? "Agence « {$agency->name} » réactivée." : "Agence « {$agency->name} » suspendue.");
    }
}
