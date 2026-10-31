<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use App\Services\Billing\SubscriptionManager;
use Tests\Feature\Interface\InterfaceTestCase;

class BillingTest extends InterfaceTestCase
{
    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function plan(string $code): Plan
    {
        return Plan::where('code', $code)->firstOrFail();
    }

    private function validatePrices(string $code, int $monthly, ?int $yearly = null): Plan
    {
        $plan = $this->plan($code);
        $plan->forceFill(['monthly_price_xof' => $monthly, 'yearly_price_xof' => $yearly, 'price_validated' => true])->save();

        return $plan;
    }

    /** Agence dont l'abonnement en cours porte sur l'offre $code. */
    private function agencyOn(string $code, SubscriptionStatus $status = SubscriptionStatus::Active, int $price = 0): Agency
    {
        $agency = Agency::factory()->create();
        Subscription::withoutGlobalScopes()->where('agency_id', $agency->id)
            ->update(['plan_id' => $this->plan($code)->id, 'status' => $status->value, 'price_xof' => $price]);
        app(PlanLimits::class)->forget($agency);

        return $agency;
    }

    private function current(Agency $agency): ?Subscription
    {
        return Subscription::withoutGlobalScopes()->where('agency_id', $agency->id)->whereNotNull('current_marker')->with('plan')->first();
    }

    public function test_three_offers_exist_with_prices_to_validate(): void
    {
        Agency::factory()->create(); // insère le catalogue

        $plans = Plan::ordered()->get();
        $this->assertSame(['starter', 'pro', 'business'], $plans->pluck('code')->all());
        $this->assertTrue($plans->every(fn (Plan $p) => ! $p->price_validated));
        $this->assertSame('Tarif à valider', $plans->first()->formattedMonthlyPrice());
    }

    public function test_registration_starts_a_trial(): void
    {
        Agency::factory()->create();
        $this->post(route('register'), [
            'agency_name' => 'Agence Plateau', 'name' => 'Koffi', 'email' => 'koffi@plateau.ci',
            'password' => 'MotDePasse-2026', 'password_confirmation' => 'MotDePasse-2026',
        ])->assertRedirect(route('dashboard'));

        $subscription = $this->current(User::where('email', 'koffi@plateau.ci')->sole()->agency);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame('pro', $subscription->plan->code);
        $this->assertSame(0, $subscription->price_xof);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $subscription->trial_ends_at->timestamp, 5);
    }

    public function test_starter_limits_are_enforced(): void
    {
        $agency = $this->agencyOn('starter');
        $user = $this->userFor($agency);
        Client::factory()->count(3)->create(['agency_id' => $agency->id]);

        $this->actingAs($user)->post(route('clients.store'), ['name' => 'Quatrième client'])
            ->assertSessionHasErrors(['name' => 'Votre formule Starter est limitée à 3 client(s). Passez à une formule supérieure pour en ajouter.']);
        $this->actingAs($user)->post(route('team.store'), ['name' => 'Second', 'email' => 'second@agence.ci', 'role' => 'collaborator'])
            ->assertSessionHasErrors('email');
        $this->actingAs($user)->post(route('notifications.channels.store'), ['type' => 'whatsapp', 'destination' => '+2250701020304'])
            ->assertSessionHasErrors(['destination' => 'Votre formule n\'inclut pas les alertes WhatsApp (formules Pro et Business).']);
        $this->assertSame(3, Client::withoutGlobalScopes()->where('agency_id', $agency->id)->count());
    }

    public function test_without_current_subscription_nothing_can_be_added(): void
    {
        $user = $this->userFor(Agency::factory()->withoutSubscription()->create());

        $this->actingAs($user)->post(route('clients.store'), ['name' => 'Client'])
            ->assertSessionHasErrors(['name' => 'Aucun abonnement en cours : choisissez une formule dans la page Abonnement.']);
        $this->actingAs($user)->get(route('subscription.show'))->assertOk()->assertSee('Aucun abonnement actif');
    }

    public function test_history_is_limited_by_the_plan(): void
    {
        $agency = $this->agencyOn('starter'); // 30 jours
        $site = $this->siteFor($agency, ['name' => 'Vitrine']);
        Incident::factory()->create(['site_id' => $site->id, 'started_at' => now()->subDays(45), 'resolved_at' => now()->subDays(45)->addHour(), 'last_error_message' => 'Ancien incident']);
        Incident::factory()->create(['site_id' => $site->id, 'started_at' => now()->subDays(2), 'resolved_at' => now()->subDays(2)->addHour(), 'last_error_message' => 'Incident récent']);

        $this->actingAs($this->userFor($agency))->get(route('incidents.index'))
            ->assertOk()->assertViewHas('incidents', fn ($page) => $page->total() === 1 && $page->first()->last_error_message === 'Incident récent');
    }

    public function test_plan_change_is_effective_only_once_paid(): void
    {
        $agency = $this->agencyOn('starter', SubscriptionStatus::Trialing);
        $admin = $this->userFor($agency);

        // Tarif non validé : aucune souscription payante possible.
        $this->actingAs($admin)->post(route('subscription.request'), ['plan_id' => $this->plan('pro')->id, 'billing_period' => 'monthly'])
            ->assertSessionHasErrors('plan_id');

        $pro = $this->validatePrices('pro', 15000, 150000);
        $this->actingAs($admin)->post(route('subscription.request'), ['plan_id' => $pro->id, 'billing_period' => 'yearly'])
            ->assertSessionHas('status');

        $payment = Payment::withoutGlobalScopes()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(150000, $payment->amount_xof);
        $this->assertSame('manual', $payment->provider);
        $this->assertSame('starter', $this->current($agency)->plan->code, 'la formule actuelle reste active');

        $super = $this->superAdmin();
        $this->actingAs($super)->post(route('admin.payments.confirm', $payment), ['reference' => 'OM-2026-0001', 'method' => 'mobile_money'])
            ->assertSessionHas('status');

        $current = $this->current($agency);
        $this->assertSame('pro', $current->plan->code);
        $this->assertSame(SubscriptionStatus::Active, $current->status);
        $this->assertSame(150000, $current->price_xof);
        $this->assertEqualsWithDelta(now()->addYear()->timestamp, $current->ends_at->timestamp, 5);
        $this->assertSame(SubscriptionStatus::Cancelled, $current->previous->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame($super->id, $payment->fresh()->recorded_by);
        $this->assertTrue(AuditLog::where('action', 'subscription.requested')->exists());
        $this->assertTrue(AuditLog::where('action', 'payment.confirmed')->where('user_id', $super->id)->exists());

        // Rejeu : idempotent ; même référence refusée.
        app(SubscriptionManager::class)->markPaid($payment->fresh());
        $this->assertSame(1, Subscription::withoutGlobalScopes()->where('agency_id', $agency->id)->where('status', 'active')->count());

        $this->actingAs($admin)->get(route('subscription.show'))->assertOk()->assertSee('OM-2026-0001')->assertSee('Pro');
    }

    public function test_renewal_extends_from_the_current_expiry(): void
    {
        $agency = $this->agencyOn('pro', SubscriptionStatus::Active, 15000);
        $subscription = $this->current($agency);
        $subscription->forceFill(['ends_at' => now()->addDays(10)])->save();
        $admin = $this->userFor($agency);

        $this->actingAs($admin)->post(route('subscription.renew'))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('subscription.renew')); // pas de doublon
        $payment = Payment::withoutGlobalScopes()->sole();
        $this->assertSame(PaymentPurpose::Renewal, $payment->purpose);

        $this->actingAs($this->superAdmin())->post(route('admin.payments.confirm', $payment), ['reference' => 'VIR-77', 'method' => 'bank_transfer']);

        $this->assertEqualsWithDelta(now()->addDays(10)->addMonthNoOverflow()->timestamp, $subscription->fresh()->ends_at->timestamp, 5);
        $this->assertNotNull($subscription->fresh()->renewed_at);

        // Référence déjà utilisée : refusée.
        $this->actingAs($this->superAdmin())->post(route('admin.subscriptions.renew', $subscription), ['reference' => 'VIR-77', 'method' => 'bank_transfer'])
            ->assertSessionHasErrors('reference');
    }

    public function test_downgrade_is_refused_when_usage_exceeds_the_new_plan(): void
    {
        $agency = $this->agencyOn('pro');
        Client::factory()->count(4)->create(['agency_id' => $agency->id]);
        $starter = $this->validatePrices('starter', 5000);

        $this->actingAs($this->userFor($agency))->post(route('subscription.request'), ['plan_id' => $starter->id, 'billing_period' => 'monthly'])
            ->assertSessionHasErrors(['plan_id' => 'L\'offre Starter ne couvre pas votre utilisation actuelle : 4 client(s) pour 3 autorisé(s).']);
        $this->assertSame(0, Payment::withoutGlobalScopes()->count());
    }

    public function test_trials_and_expiries_are_swept(): void
    {
        config(['billing.grace_days' => 7]);
        $trial = $this->agencyOn('pro', SubscriptionStatus::Trialing);
        $this->current($trial)->forceFill(['starts_at' => now()->subDays(14), 'trial_ends_at' => now()->subMinute()])->save();
        $late = $this->agencyOn('pro', SubscriptionStatus::Active, 15000);
        $this->current($late)->forceFill(['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()])->save();
        $gone = $this->agencyOn('pro', SubscriptionStatus::PastDue, 15000);
        $this->current($gone)->forceFill(['starts_at' => now()->subMonths(2), 'ends_at' => now()->subDays(8)])->save();

        $this->artisan('subscriptions:check')->expectsOutputToContain('Essais terminés : 1 · en retard : 1 · expirés : 1')->assertSuccessful();

        $this->assertNull($this->current($trial));
        $this->assertSame(SubscriptionStatus::PastDue, $this->current($late)->status);
        $this->assertNull($this->current($gone));
        $this->assertSame(SubscriptionStatus::Expired, Subscription::withoutGlobalScopes()->where('agency_id', $gone->id)->sole()->status);
    }

    public function test_agency_admins_never_reach_the_super_admin_space(): void
    {
        $agency = Agency::factory()->create();
        $subscription = $this->current($agency);
        $plan = $this->plan('pro');

        foreach ([$this->userFor($agency), $this->userFor($agency, RoleName::Collaborator)] as $user) {
            foreach (['admin.index', 'admin.stats', 'admin.users', 'admin.sites', 'admin.incidents', 'admin.plans', 'admin.subscriptions', 'admin.audit'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
            $this->actingAs($user)->get(route('admin.plans.edit', $plan))->assertForbidden();
            $this->actingAs($user)->put(route('admin.plans.update', $plan), ['name' => 'Piraté'])->assertForbidden();
            $this->actingAs($user)->get(route('admin.subscriptions.show', $subscription))->assertForbidden();
            $this->actingAs($user)->post(route('admin.subscriptions.change', $subscription), ['plan_id' => $plan->id])->assertForbidden();
            $this->actingAs($user)->post(route('admin.subscriptions.store'), [])->assertForbidden();
            $this->actingAs($user)->patch(route('admin.users.toggle', $user))->assertForbidden();
        }
        $this->assertSame('Pro', $plan->fresh()->name);
    }

    public function test_super_admin_sees_every_page(): void
    {
        $agency = Agency::factory()->create(['name' => 'Agence Cocody']);
        $site = $this->siteFor($agency, ['name' => 'Site Cocody']);
        Incident::factory()->create(['site_id' => $site->id, 'started_at' => now()]);
        $this->userFor($agency)->forceFill(['email' => 'chef@cocody.ci'])->save();
        $super = $this->superAdmin();

        $this->actingAs($super)->get(route('admin.stats'))->assertOk()->assertSee('Revenu mensuel récurrent');
        $this->actingAs($super)->get(route('admin.index'))->assertOk()->assertSee('Agence Cocody')->assertSee('Business');
        $this->actingAs($super)->get(route('admin.users'))->assertOk()->assertSee('chef@cocody.ci');
        $this->actingAs($super)->get(route('admin.sites'))->assertOk()->assertSee('Site Cocody');
        $this->actingAs($super)->get(route('admin.incidents'))->assertOk()->assertSee('Site Cocody');
        $this->actingAs($super)->get(route('admin.plans'))->assertOk()->assertSee('Tarif à valider');
        $this->actingAs($super)->get(route('admin.plans.edit', $this->plan('pro')))->assertOk();
        $this->actingAs($super)->get(route('admin.subscriptions'))->assertOk()->assertSee('Agence Cocody');
        $this->actingAs($super)->get(route('admin.subscriptions.show', $this->current($agency)))->assertOk();
        $this->actingAs($super)->get(route('admin.audit'))->assertOk();
    }

    public function test_plan_update_is_journaled_with_the_changes(): void
    {
        $plan = $this->plan('starter');
        $super = $this->superAdmin();

        $this->actingAs($super)->put(route('admin.plans.update', $plan), [
            'name' => 'Starter', 'audience' => $plan->audience, 'tagline' => $plan->tagline,
            'monthly_price_xof' => 5000, 'yearly_price_xof' => 50000, 'price_validated' => '1',
            'max_sites' => 5, 'max_clients' => 3, 'max_users' => 1, 'min_interval_minutes' => 5, 'retention_days' => 30,
            'includes_whatsapp' => '0', 'whatsapp_monthly_quota' => 0, 'features' => [], 'is_public' => '1', 'is_active' => '1', 'sort_order' => 1,
        ])->assertRedirect(route('admin.plans'));

        $this->assertTrue($plan->fresh()->price_validated);
        $log = AuditLog::where('action', 'plan.price_validated')->sole();
        $this->assertSame($super->id, $log->user_id);
        $this->assertSame(['avant' => 0, 'apres' => 5000], $log->metadata['changes']['monthly_price_xof']);
    }

    public function test_super_admin_can_deactivate_an_account(): void
    {
        $member = $this->userFor();
        $super = $this->superAdmin();

        $this->actingAs($super)->patch(route('admin.users.toggle', $super))->assertSessionHasErrors('user');
        $this->actingAs($super)->patch(route('admin.users.toggle', $member))->assertSessionHas('status');

        $this->assertFalse($member->fresh()->is_active);
        $this->assertTrue(AuditLog::where('action', 'user.deactivated_by_admin')->where('subject_id', (string) $member->id)->exists());
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $member->email, 'password' => 'password'])->assertSessionHasErrors();
    }

    public function test_an_agency_cannot_touch_another_agency_payment(): void
    {
        $pro = $this->validatePrices('pro', 15000);
        $victim = $this->agencyOn('starter', SubscriptionStatus::Trialing);
        $payment = app(SubscriptionManager::class)->requestChange($victim, $pro, BillingPeriod::Monthly);

        $this->actingAs($this->userFor())->delete(route('subscription.request.cancel', $payment))->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }
}
