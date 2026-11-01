<?php

namespace Tests\Feature\Interface;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\Site;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class ManagementActionsTest extends InterfaceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // UptimeRobot simulé : aucun moniteur existant, création acceptée.
        config(['uptime.uptimerobot.api_key' => 'cle-factice-de-test-0000']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.uptimerobot.com/v3/monitors?*' => Http::response(['nextLink' => null, 'data' => []]),
            'https://api.uptimerobot.com/v3/monitors' => Http::response(['id' => 901, 'friendlyName' => 'Boutique', 'url' => 'https://boutique.exemple.ci', 'type' => 'HTTP', 'status' => 'STARTED', 'interval' => 300], 201),
            'https://api.uptimerobot.com/v3/monitors/*' => Http::response(['id' => 901, 'url' => 'https://boutique.exemple.ci', 'status' => 'STARTED', 'interval' => 300]),
        ]);
    }

    private function validSite(Client $client, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Boutique', 'url' => 'https://boutique.exemple.ci', 'client_id' => $client->id,
            'interval_minutes' => 5, 'timeout_seconds' => 10, 'monitoring_provider' => 'uptime_robot',
            'expected_status_codes' => '200, 301',
        ];
    }

    public function test_client_and_site_full_lifecycle(): void
    {
        $user = $this->userFor();
        $this->actingAs($user);

        $this->post(route('clients.store'), ['name' => 'Pharmacie du Port', 'email' => 'contact@pharma.ci'])->assertRedirect();
        $client = Client::withoutGlobalScopes()->where('name', 'Pharmacie du Port')->firstOrFail();
        $this->assertSame($user->agency_id, $client->agency_id);

        $this->post(route('sites.store'), $this->validSite($client))->assertRedirect();
        $site = Site::withoutGlobalScopes()->where('url', 'https://boutique.exemple.ci')->firstOrFail();
        $this->assertSame([200, 301], $site->expected_status_codes);
        $this->assertSame('unknown', $site->status->value);

        $this->put(route('sites.update', $site), $this->validSite($client, ['name' => 'Boutique en ligne']))->assertRedirect(route('sites.show', $site));
        $this->assertSame('Boutique en ligne', $site->fresh()->name);

        $this->patch(route('sites.toggle', $site));
        $this->assertSame('paused', $site->fresh()->monitoring_status->value);
        $this->patch(route('sites.toggle', $site));
        $this->assertSame('active', $site->fresh()->monitoring_status->value);

        $this->delete(route('sites.destroy', $site))->assertRedirect(route('sites.index'));
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);

        $this->put(route('clients.update', $client), ['name' => 'Pharmacie du Port SA'])->assertRedirect();
        $this->delete(route('clients.destroy', $client))->assertRedirect(route('clients.index'));
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);

        $this->assertTrue(AuditLog::where('action', 'site.created')->where('agency_id', $user->agency_id)->exists());
    }

    public function test_site_validation_rejects_unsafe_or_duplicate_urls(): void
    {
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);
        $this->actingAs($user);

        foreach (['http://127.0.0.1', 'http://localhost', 'https://192.168.1.10/admin', 'ftp://exemple.ci', 'https://intranet.local', 'pas-une-url'] as $url) {
            $this->post(route('sites.store'), $this->validSite($client, ['url' => $url]))->assertSessionHasErrors('url');
        }

        $this->post(route('sites.store'), $this->validSite($client))->assertSessionHasNoErrors();
        $this->post(route('sites.store'), $this->validSite($client))->assertSessionHasErrors('url');

        // Fournisseurs sans intégration opérationnelle : refusés (le site ne serait jamais contrôlé).
        foreach (['internal', 'better_stack', 'other'] as $provider) {
            $this->post(route('sites.store'), $this->validSite($client, ['url' => 'https://autre.ci', 'monitoring_provider' => $provider]))
                ->assertSessionHasErrors('monitoring_provider');
        }
        $this->post(route('sites.store'), $this->validSite($client, ['url' => 'https://autre.ci', 'interval_minutes' => 3]))
            ->assertSessionHasErrors('interval_minutes');
    }

    public function test_site_search_and_filters(): void
    {
        $user = $this->userFor();
        $this->siteFor($user->agency, ['name' => 'Alpha', 'status' => 'up']);
        $this->siteFor($user->agency, ['name' => 'Beta', 'status' => 'down']);
        $this->actingAs($user);

        $this->get(route('sites.index', ['q' => 'alp']))->assertSee('Alpha')->assertDontSee('Beta');
        $this->get(route('sites.index', ['statut' => 'down']))->assertSee('Beta')->assertDontSee('Alpha');
        $this->get(route('sites.index', ['statut' => 'inconnu-invalide']))->assertSessionHasErrors('statut');
    }

    public function test_incident_acknowledgement(): void
    {
        $user = $this->userFor();
        $incident = Incident::factory()->create(['site_id' => $this->siteFor($user->agency)->id]);

        $this->actingAs($user)->post(route('incidents.acknowledge', $incident))->assertRedirect();

        $incident->refresh();
        $this->assertSame('acknowledged', $incident->status->value);
        $this->assertSame($user->id, $incident->acknowledged_by);
        $this->actingAs($user)->get(route('incidents.show', $incident))->assertSee($user->name)->assertDontSee('>Prendre en charge<', false);
    }

    public function test_notification_channels(): void
    {
        $user = $this->userFor();
        $this->actingAs($user);

        $this->post(route('notifications.channels.store'), ['type' => 'whatsapp', 'destination' => '0701020304'])->assertSessionHasErrors('destination');
        $this->post(route('notifications.channels.store'), ['type' => 'email', 'destination' => 'pas-un-email'])->assertSessionHasErrors('destination');
        $this->post(route('notifications.channels.store'), ['type' => 'whatsapp', 'destination' => '+2250701020304'])->assertSessionHasNoErrors();

        $channel = NotificationChannel::withoutGlobalScopes()->where('destination', '+2250701020304')->firstOrFail();
        $this->assertSame(1, $channel->preferences()->withoutGlobalScopes()->whereNull('site_id')->count());

        $this->put(route('notifications.channels.preferences', $channel), ['notify_on_down' => '1', 'notify_on_recovery' => '0', 'delay_minutes' => 5])->assertSessionHasNoErrors();
        $pref = $channel->preferences()->withoutGlobalScopes()->whereNull('site_id')->first();
        $this->assertTrue($pref->notify_on_down);
        $this->assertFalse($pref->notify_on_recovery);
        $this->assertSame(5, $pref->delay_minutes);

        $this->patch(route('notifications.channels.toggle', $channel));
        $this->assertFalse($channel->fresh()->is_active);

        $this->delete(route('notifications.channels.destroy', $channel));
        $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
    }

    public function test_settings_forms(): void
    {
        $user = $this->userFor();
        $this->actingAs($user);

        $this->put(route('settings.agency'), ['name' => 'Agence Renommée', 'timezone' => 'Africa/Lagos'])->assertSessionHasNoErrors();
        $this->assertSame('Africa/Lagos', $user->agency->fresh()->timezone);

        $this->put(route('settings.profile'), ['name' => 'Nouveau Nom', 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame('Nouveau Nom', $user->fresh()->name);

        $this->put(route('settings.password'), ['current_password' => 'faux', 'password' => 'Nouveau-Pass-2026', 'password_confirmation' => 'Nouveau-Pass-2026'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->put(route('settings.password'), ['current_password' => 'password', 'password' => 'Nouveau-Pass-2026', 'password_confirmation' => 'Nouveau-Pass-2026'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Nouveau-Pass-2026', $user->fresh()->password));
    }
}
