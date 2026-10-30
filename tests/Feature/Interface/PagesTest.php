<?php

namespace Tests\Feature\Interface;

use App\Models\Agency;
use App\Models\Check;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Site;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\TestDataSeeder;

class PagesTest extends InterfaceTestCase
{
    public function test_every_page_loads_for_an_empty_agency(): void
    {
        $user = $this->userFor();

        foreach (['dashboard', 'sites.index', 'clients.index', 'incidents.index', 'checks.index',
            'notifications.index', 'subscription.show', 'settings.index', 'clients.create'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
        $this->actingAs($user)->get(route('notifications.index', ['onglet' => 'journal']))->assertOk();
    }

    public function test_empty_agency_sees_explicit_empty_states_and_no_invented_figures(): void
    {
        $user = $this->userFor();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aucun site surveillé pour le moment')
            ->assertSee('Ajouter un client')
            ->assertDontSee('Disponibilité moyenne')
            ->assertDontSee('%');

        $this->actingAs($user)->get(route('incidents.index'))->assertSee('Aucun incident enregistré');
        $this->actingAs($user)->get(route('checks.index'))->assertSee('Aucun contrôle enregistré');
        $this->actingAs($user)->get(route('subscription.show'))->assertSee('Aucun abonnement actif');
    }

    public function test_dashboard_counts_come_from_the_database(): void
    {
        $user = $this->userFor();
        $this->siteFor($user->agency, ['status' => 'up']);
        $this->siteFor($user->agency, ['status' => 'down']);
        $this->siteFor($user->agency, ['status' => 'down']);
        $this->siteFor(Agency::factory()->create(), ['status' => 'down']); // autre agence : ignoré

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame(3, $response->viewData('totalSites'));
        $this->assertSame(1, $response->viewData('upSites'));
        $this->assertSame(2, $response->viewData('downSites'));
        $this->assertNull($response->viewData('last24h')['uptime']);
        $response->assertSee('Aucun contrôle sur les dernières 24 h');
    }

    public function test_site_detail_shows_real_measurements(): void
    {
        $user = $this->userFor();
        $site = $this->siteFor($user->agency, ['name' => 'Boutique Plateau']);
        foreach ([[true, 180], [true, 220], [false, null]] as $i => [$up, $ms]) {
            Check::forceCreate([
                'agency_id' => $user->agency_id, 'site_id' => $site->id, 'checked_at' => now()->subMinutes(30 - $i * 10),
                'is_up' => $up, 'http_status' => $up ? 200 : 503, 'response_time_ms' => $ms, 'provider' => 'internal',
            ]);
        }
        $incident = Incident::factory()->create(['site_id' => $site->id]);

        $response = $this->actingAs($user)->get(route('sites.show', $site))->assertOk()->assertSee('Boutique Plateau');

        $this->assertSame(3, $response->viewData('uptime24h')['total']);
        $this->assertEqualsWithDelta(66.67, $response->viewData('uptime24h')['uptime'], 0.01);
        $response->assertSee('<polyline', false);

        $this->actingAs($user)->get(route('incidents.show', $incident))->assertOk()->assertSee('Prendre en charge');
        $this->actingAs($user)->get(route('checks.index'))->assertOk()->assertSee('Boutique Plateau');
    }

    public function test_seeded_test_data_renders_on_every_page(): void
    {
        $this->seed(TestDataSeeder::class);
        $user = User::whereNotNull('agency_id')->whereHas('agency', fn ($q) => $q->where('is_test_data', true))->firstOrFail();

        foreach (['dashboard', 'sites.index', 'clients.index', 'incidents.index', 'checks.index',
            'notifications.index', 'subscription.show', 'settings.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }

        app(TenantContext::class)->set($user->agency_id);
        $site = Site::firstOrFail();
        $client = Client::firstOrFail();
        app(TenantContext::class)->clear();

        $this->actingAs($user)->get(route('sites.show', $site))->assertOk();
        $this->actingAs($user)->get(route('sites.edit', $site))->assertOk();
        $this->actingAs($user)->get(route('clients.show', $client))->assertOk();
    }
}
