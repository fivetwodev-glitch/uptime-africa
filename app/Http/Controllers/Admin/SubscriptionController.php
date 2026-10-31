<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionManager;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Super Admin : abonnements et paiements de toutes les agences. */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionManager $manager) {}

    public function index(Request $request): View
    {
        $this->authorize('admin.access');
        $filters = $request->validate([
            'statut' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'offre' => ['nullable', 'integer'],
        ]);

        return view('admin.subscriptions.index', [
            'subscriptions' => Subscription::with(['agency:id,name', 'plan:id,name'])
                ->when($filters['statut'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['offre'] ?? null, fn ($q, $p) => $q->where('plan_id', $p))
                ->latest('id')->paginate(30)->withQueryString(),
            'pendingPayments' => Payment::with(['agency:id,name', 'subscription.plan:id,name'])->where('status', PaymentStatus::Pending)->latest('id')->get(),
            'filters' => $filters,
            'plans' => Plan::ordered()->get(),
            'agenciesWithout' => Agency::whereDoesntHave('subscriptions', fn ($q) => $q->whereNotNull('current_marker'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('admin.access');
        $data = $request->validate([
            'agency_id' => ['required', 'string', Rule::exists('agencies', 'id')],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'status' => ['required', Rule::in([SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ], [], ['agency_id' => 'agence', 'plan_id' => 'offre']);

        $subscription = $this->manager->create(
            Agency::findOrFail($data['agency_id']), Plan::findOrFail($data['plan_id']),
            BillingPeriod::from($data['billing_period']), SubscriptionStatus::from($data['status']), $data['trial_days'] ?? 14,
        );

        return redirect()->route('admin.subscriptions.show', $subscription)->with('status', 'Abonnement créé.');
    }

    public function show(Subscription $subscription): View
    {
        $this->authorize('admin.access');

        return view('admin.subscriptions.show', [
            'subscription' => $subscription->load(['agency', 'plan', 'previous.plan:id,name']),
            'payments' => Payment::with('recorder:id,email')->where('agency_id', $subscription->agency_id)->latest('id')->get(),
            'history' => Subscription::with('plan:id,name')->where('agency_id', $subscription->agency_id)->latest('id')->get(),
            'plans' => Plan::ordered()->get(),
        ]);
    }

    public function changePlan(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorize('admin.access');
        $data = $request->validate(['plan_id' => ['required', 'integer', Rule::exists('plans', 'id'), Rule::notIn([$subscription->plan_id])]], [], ['plan_id' => 'offre']);

        $new = $this->manager->changePlanNow($subscription, Plan::findOrFail($data['plan_id']));

        return redirect()->route('admin.subscriptions.show', $new)->with('status', "Offre changée : {$new->plan->name}.");
    }

    /** Renouvellement enregistré par l'équipe (paiement hors ligne reçu). */
    public function renew(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorize('admin.access');
        $data = $this->paymentFields($request);

        $payment = $this->manager->requestRenewal($subscription);
        $this->manager->markPaid($payment, $data['reference'], $data['method'], $data['notes'] ?? null);

        return back()->with('status', 'Renouvellement enregistré. Nouvelle échéance : '.Format::datetime($subscription->fresh()->ends_at).'.');
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        $this->authorize('admin.access');
        $this->manager->cancel($subscription);

        return back()->with('status', 'Abonnement annulé.');
    }

    public function confirmPayment(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('admin.access');
        $data = $this->paymentFields($request);

        $this->manager->markPaid($payment, $data['reference'], $data['method'], $data['notes'] ?? null);
        $message = $payment->purpose === PaymentPurpose::Activation ? 'Paiement confirmé : la nouvelle formule est active.' : 'Paiement confirmé : abonnement prolongé.';

        return back()->with('status', $message);
    }

    public function cancelPayment(Payment $payment): RedirectResponse
    {
        $this->authorize('admin.access');
        $this->manager->cancelPayment($payment);

        return back()->with('status', 'Paiement annulé.');
    }

    private function paymentFields(Request $request): array
    {
        return $request->validate([
            // Référence de la transaction (virement, Mobile Money) : unique, sert d'anti-doublon.
            'reference' => ['required', 'string', 'max:100', Rule::unique('payments', 'provider_reference')->where('provider', 'manual')],
            'method' => ['required', Rule::in(array_keys(self::METHODS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['reference.unique' => 'Cette référence de paiement est déjà enregistrée.'], ['reference' => 'référence', 'method' => 'moyen de paiement']);
    }

    public const METHODS = [
        'mobile_money' => 'Mobile Money',
        'bank_transfer' => 'Virement bancaire',
        'cash' => 'Espèces',
        'card' => 'Carte bancaire',
        'other' => 'Autre',
    ];
}
