<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\Gateways\GatewayRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie des abonnements. Règles :
 * - une seule souscription en cours par agence (index unique en base) ;
 * - le prix est figé à la souscription ;
 * - une offre dont le tarif n'est pas validé ne peut être que testée (essai) ;
 * - un changement de plan ou un renouvellement n'est effectif qu'au paiement confirmé.
 */
class SubscriptionManager
{
    public function __construct(private readonly PlanLimits $limits, private readonly GatewayRegistry $gateways) {}

    public function current(Agency $agency): ?Subscription
    {
        return Subscription::withoutGlobalScopes()->where('agency_id', $agency->id)->whereNotNull('current_marker')->with('plan')->first();
    }

    public function startTrial(Agency $agency, Plan $plan, int $days): Subscription
    {
        return $this->open($agency, $plan, BillingPeriod::Monthly, SubscriptionStatus::Trialing, price: 0, trialDays: $days);
    }

    /** Création directe par le Super Admin (essai ou abonnement actif sans paiement en ligne). */
    public function create(Agency $agency, Plan $plan, BillingPeriod $period, SubscriptionStatus $status, ?int $trialDays = null): Subscription
    {
        if (! in_array($status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
            throw ValidationException::withMessages(['status' => 'Statut initial invalide.']);
        }
        if ($status === SubscriptionStatus::Active && $plan->priceFor($period) === null) {
            throw ValidationException::withMessages(['plan_id' => "Le tarif {$period->label()} de l'offre {$plan->name} n'est pas validé."]);
        }

        return $this->open($agency, $plan, $period, $status, price: $status === SubscriptionStatus::Active ? $plan->priceFor($period) : 0, trialDays: $trialDays);
    }

    /**
     * Demande de souscription / changement de plan par l'agence : abonnement
     * « en attente de paiement » + paiement en attente. L'abonnement en cours
     * reste actif jusqu'à la confirmation du paiement.
     */
    public function requestChange(Agency $agency, Plan $plan, BillingPeriod $period): Payment
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'Cette offre n\'est plus proposée.']);
        }
        $price = $plan->priceFor($period);
        if ($price === null) {
            throw ValidationException::withMessages(['plan_id' => "Le tarif {$period->label()} de l'offre {$plan->name} n'est pas encore disponible."]);
        }
        if ($blockers = $this->limits->blockersFor($agency, $plan)) {
            throw ValidationException::withMessages(['plan_id' => "L'offre {$plan->name} ne couvre pas votre utilisation actuelle : ".implode(', ', $blockers).'.']);
        }

        return DB::transaction(function () use ($agency, $plan, $period, $price) {
            $this->cancelPendingRequests($agency);

            $subscription = $this->newSubscription($agency, $plan, $period, SubscriptionStatus::Pending, $price);
            $subscription->previous_subscription_id = $this->current($agency)?->id;
            $subscription->save();

            $payment = $this->newPayment($subscription, PaymentPurpose::Activation, $price);
            AuditLog::record('subscription.requested', $subscription, ['plan' => $plan->code, 'period' => $period->value, 'amount_xof' => $price, 'payment_id' => $payment->id]);

            return $payment;
        });
    }

    /** Demande de renouvellement de l'abonnement payant en cours, au prix figé. */
    public function requestRenewal(Subscription $subscription): Payment
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true) || $subscription->price_xof <= 0) {
            throw ValidationException::withMessages(['subscription' => 'Seul un abonnement payant en cours peut être renouvelé. Choisissez une formule.']);
        }

        return DB::transaction(function () use ($subscription) {
            $existing = $subscription->payments()->withoutGlobalScopes()
                ->where('purpose', PaymentPurpose::Renewal)->where('status', PaymentStatus::Pending)->first();
            if ($existing) {
                return $existing;
            }

            $payment = $this->newPayment($subscription, PaymentPurpose::Renewal, $subscription->price_xof);
            AuditLog::record('subscription.renewal_requested', $subscription, ['amount_xof' => $payment->amount_xof, 'payment_id' => $payment->id]);

            return $payment;
        });
    }

    /**
     * Paiement confirmé (par l'équipe ou, plus tard, par un prestataire vérifié).
     * Idempotent : un second appel sur un paiement déjà payé ne fait rien.
     */
    public function markPaid(Payment $payment, ?string $reference = null, ?string $method = null, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($payment, $reference, $method, $notes) {
            $payment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }
            if ($payment->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => "Ce paiement est « {$payment->status->label()} » : il ne peut plus être confirmé."]);
            }

            $subscription = Subscription::withoutGlobalScopes()->with('plan')->lockForUpdate()->findOrFail($payment->subscription_id);
            $now = now();

            if ($payment->purpose === PaymentPurpose::Activation) {
                if ($subscription->status !== SubscriptionStatus::Pending) {
                    throw ValidationException::withMessages(['payment' => 'La demande liée à ce paiement n\'est plus en attente.']);
                }
                $this->closeCurrent($subscription->agency_id, SubscriptionStatus::Cancelled);
                $starts = $now;
                $subscription->forceFill([
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => $starts,
                    'ends_at' => $this->addPeriod($starts, $subscription->billing_period),
                ])->save();
            } else {
                $starts = $subscription->ends_at && $subscription->ends_at->isFuture() ? $subscription->ends_at->copy() : $now;
                $subscription->forceFill([
                    'status' => SubscriptionStatus::Active,
                    'ends_at' => $this->addPeriod($starts, $subscription->billing_period),
                    'renewed_at' => $now,
                ])->save();
            }

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => $now,
                'provider_reference' => $reference ?: $payment->provider_reference,
                'method' => $method ?: $payment->method,
                'notes' => $notes ?: $payment->notes,
                'period_starts_at' => $starts,
                'period_ends_at' => $subscription->ends_at,
                'recorded_by' => auth()->id(),
            ])->save();

            $this->limits->forget($subscription->agency_id);
            AuditLog::record('payment.confirmed', $payment, [
                'subscription_id' => $subscription->id, 'purpose' => $payment->purpose->value, 'amount_xof' => $payment->amount_xof,
                'reference' => $payment->provider_reference, 'ends_at' => $subscription->ends_at?->toIso8601String(),
            ]);

            return $payment;
        });
    }

    public function cancelPayment(Payment $payment, PaymentStatus $status = PaymentStatus::Cancelled): void
    {
        DB::transaction(function () use ($payment, $status) {
            $payment = Payment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Pending) {
                return;
            }
            $payment->forceFill(['status' => $status])->save();

            $subscription = Subscription::withoutGlobalScopes()->find($payment->subscription_id);
            if ($payment->purpose === PaymentPurpose::Activation && $subscription?->status === SubscriptionStatus::Pending) {
                $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'ends_at' => now()])->save();
            }
            AuditLog::record('payment.'.$status->value, $payment, ['subscription_id' => $payment->subscription_id]);
        });
    }

    /** Changement de plan immédiat par le Super Admin, sans paiement ; l'échéance est conservée. */
    public function changePlanNow(Subscription $current, Plan $plan): Subscription
    {
        if (! $current->status->isCurrent()) {
            throw ValidationException::withMessages(['plan_id' => 'Cet abonnement n\'est plus en cours.']);
        }

        return DB::transaction(function () use ($current, $plan) {
            $agency = Agency::findOrFail($current->agency_id);
            $endsAt = $current->ends_at;
            $trialEndsAt = $current->trial_ends_at;
            $status = $current->status;
            $this->closeCurrent($agency->id, SubscriptionStatus::Cancelled);

            $price = $status === SubscriptionStatus::Trialing ? 0 : ($plan->priceFor($current->billing_period) ?? $current->price_xof);
            $subscription = $this->newSubscription($agency, $plan, $current->billing_period, $status, $price);
            $subscription->forceFill(['ends_at' => $endsAt, 'trial_ends_at' => $trialEndsAt, 'previous_subscription_id' => $current->id])->save();

            $this->limits->forget($agency);
            AuditLog::record('subscription.plan_changed', $subscription, ['from' => $current->plan?->code, 'to' => $plan->code, 'by' => 'super_admin']);

            return $subscription;
        });
    }

    public function cancel(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'ends_at' => now()->max($subscription->starts_at)])->save();
            Payment::withoutGlobalScopes()->where('subscription_id', $subscription->id)->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Cancelled->value, 'updated_at' => now()]);
            $this->limits->forget($subscription->agency_id);
            AuditLog::record('subscription.cancelled', $subscription);
        });
    }

    /** Fins d'essai et échéances : planifié chaque heure (subscriptions:check). */
    public function sweep(): array
    {
        $now = now();
        $grace = max(0, (int) config('billing.grace_days'));
        $counts = ['trials_expired' => 0, 'past_due' => 0, 'expired' => 0];

        Subscription::withoutGlobalScopes()->where('status', SubscriptionStatus::Trialing)->where('trial_ends_at', '<=', $now)
            ->each(function (Subscription $s) use (&$counts) {
                $s->forceFill(['status' => SubscriptionStatus::Expired, 'ends_at' => $s->trial_ends_at->max($s->starts_at)])->save();
                AuditLog::record('subscription.trial_expired', $s);
                $counts['trials_expired']++;
            });

        Subscription::withoutGlobalScopes()->where('status', SubscriptionStatus::Active)->whereNotNull('ends_at')->where('ends_at', '<=', $now)
            ->each(function (Subscription $s) use (&$counts) {
                $s->forceFill(['status' => SubscriptionStatus::PastDue])->save();
                AuditLog::record('subscription.past_due', $s);
                $counts['past_due']++;
            });

        Subscription::withoutGlobalScopes()->where('status', SubscriptionStatus::PastDue)->where('ends_at', '<=', $now->copy()->subDays($grace))
            ->each(function (Subscription $s) use (&$counts) {
                $s->forceFill(['status' => SubscriptionStatus::Expired])->save();
                AuditLog::record('subscription.expired', $s);
                $counts['expired']++;
            });

        return $counts;
    }

    public function addPeriod(CarbonInterface $from, BillingPeriod $period): CarbonInterface
    {
        return $period === BillingPeriod::Yearly ? $from->copy()->addYearNoOverflow() : $from->copy()->addMonthNoOverflow();
    }

    private function open(Agency $agency, Plan $plan, BillingPeriod $period, SubscriptionStatus $status, int $price, ?int $trialDays): Subscription
    {
        return DB::transaction(function () use ($agency, $plan, $period, $status, $price, $trialDays) {
            if ($this->current($agency)) {
                throw ValidationException::withMessages(['agency_id' => "L'agence {$agency->name} a déjà un abonnement en cours."]);
            }

            $subscription = $this->newSubscription($agency, $plan, $period, $status, $price);
            if ($status === SubscriptionStatus::Trialing) {
                $subscription->trial_ends_at = now()->addDays(max(1, (int) $trialDays));
            } else {
                $subscription->ends_at = $this->addPeriod($subscription->starts_at, $period);
            }
            $subscription->save();

            $this->limits->forget($agency);
            AuditLog::record('subscription.created', $subscription, ['plan' => $plan->code, 'status' => $status->value, 'period' => $period->value, 'price_xof' => $price]);

            return $subscription;
        });
    }

    private function newSubscription(Agency $agency, Plan $plan, BillingPeriod $period, SubscriptionStatus $status, int $price): Subscription
    {
        $subscription = new Subscription(['plan_id' => $plan->id, 'status' => $status, 'billing_period' => $period, 'price_xof' => $price, 'starts_at' => now()]);
        $subscription->agency_id = $agency->id;

        return $subscription;
    }

    private function newPayment(Subscription $subscription, PaymentPurpose $purpose, int $amount): Payment
    {
        $payment = new Payment([
            'subscription_id' => $subscription->id, 'purpose' => $purpose, 'amount_xof' => $amount, 'currency' => 'XOF',
            'status' => PaymentStatus::Pending, 'provider' => $this->gateways->default()->code(),
        ]);
        $payment->agency_id = $subscription->agency_id;
        $payment->save();

        return $payment;
    }

    private function closeCurrent(string $agencyId, SubscriptionStatus $status): void
    {
        $current = Subscription::withoutGlobalScopes()->where('agency_id', $agencyId)->whereNotNull('current_marker')->lockForUpdate()->first();
        if ($current) {
            $current->forceFill(['status' => $status, 'cancelled_at' => now(), 'ends_at' => now()->max($current->starts_at)])->save();
        }
    }

    private function cancelPendingRequests(Agency $agency): void
    {
        Subscription::withoutGlobalScopes()->where('agency_id', $agency->id)->where('status', SubscriptionStatus::Pending)
            ->each(function (Subscription $pending) {
                Payment::withoutGlobalScopes()->where('subscription_id', $pending->id)->where('status', PaymentStatus::Pending)
                    ->update(['status' => PaymentStatus::Cancelled->value, 'updated_at' => now()]);
                $pending->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'ends_at' => now()])->save();
            });
    }
}
