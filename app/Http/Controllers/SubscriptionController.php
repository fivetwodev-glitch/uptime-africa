<?php

namespace App\Http\Controllers;

use App\Enums\BillingPeriod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Billing\Gateways\GatewayRegistry;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Abonnement de l'agence : formule, limites, demandes de changement et de renouvellement, paiements. */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionManager $manager, private readonly PlanLimits $limits) {}

    public function show(Request $request): View
    {
        $this->authorize('subscriptions.view');

        $agency = $request->user()->agency;
        $subscription = $this->manager->current($agency);

        return view('subscription.show', [
            'subscription' => $subscription,
            'usage' => $this->limits->usage($agency),
            'history' => $agency->subscriptions()->with('plan:id,name')->where('status', '!=', SubscriptionStatus::Pending)->latest('id')->limit(10)->get(),
            'plans' => Plan::where('is_public', true)->where('is_active', true)->ordered()->get(),
            'payments' => Payment::with('subscription.plan:id,name')->latest('id')->limit(20)->get(),
            'pending' => Payment::with('subscription.plan:id,name')->where('status', PaymentStatus::Pending)->latest('id')->get(),
            'gateway' => app(GatewayRegistry::class)->default(),
            'supportEmail' => config('uptime.support_email'),
        ]);
    }

    public function requestPlan(Request $request): RedirectResponse
    {
        $this->authorize('subscriptions.manage');
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_public', true)->where('is_active', true)],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
        ], [], ['plan_id' => 'formule', 'billing_period' => 'période']);

        $agency = $request->user()->agency;
        $plan = Plan::findOrFail($data['plan_id']);
        $current = $this->manager->current($agency);
        $period = BillingPeriod::from($data['billing_period']);
        if ($current && $current->plan_id === $plan->id && $current->billing_period === $period && $current->status !== SubscriptionStatus::Trialing) {
            throw ValidationException::withMessages(['plan_id' => 'C\'est déjà votre formule. Utilisez « Renouveler » pour prolonger.']);
        }

        $payment = $this->manager->requestChange($agency, $plan, $period);

        return $this->afterRequest($payment, "Demande enregistrée pour la formule {$plan->name}.");
    }

    public function requestRenewal(Request $request): RedirectResponse
    {
        $this->authorize('subscriptions.manage');
        $current = $this->manager->current($request->user()->agency);
        if (! $current) {
            throw ValidationException::withMessages(['subscription' => 'Aucun abonnement en cours à renouveler.']);
        }

        return $this->afterRequest($this->manager->requestRenewal($current), 'Demande de renouvellement enregistrée.');
    }

    public function cancelRequest(Payment $payment): RedirectResponse
    {
        $this->authorize('subscriptions.manage');
        $this->manager->cancelPayment($payment);

        return back()->with('status', 'Demande annulée.');
    }

    private function afterRequest(Payment $payment, string $message): RedirectResponse
    {
        $gateway = app(GatewayRegistry::class)->get($payment->provider);
        if ($url = $gateway->checkoutUrl($payment)) {
            return redirect()->away($url);
        }

        return back()->with('status', $message.' Le paiement en ligne n\'est pas encore disponible : l\'équipe Uptime Africa vous contacte pour le règlement, puis active la formule.');
    }
}
