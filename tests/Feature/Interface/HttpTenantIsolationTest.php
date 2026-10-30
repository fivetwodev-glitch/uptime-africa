<?php

namespace Tests\Feature\Interface;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Site;

/**
 * Vérifie au niveau HTTP qu'une agence ne peut ni voir ni modifier
 * les données d'une autre agence (liaison de route résolue après le contexte).
 */
class HttpTenantIsolationTest extends InterfaceTestCase
{
    public function test_foreign_records_return_404_on_every_route(): void
    {
        $user = $this->userFor();
        $other = Agency::factory()->create();
        $site = $this->siteFor($other);
        $incident = Incident::factory()->create(['site_id' => $site->id]);
        $channel = $this->channelFor($other);
        $this->actingAs($user);

        $this->get(route('sites.show', $site))->assertNotFound();
        $this->get(route('sites.edit', $site))->assertNotFound();
        $this->put(route('sites.update', $site), ['name' => 'Piraté'])->assertNotFound();
        $this->patch(route('sites.toggle', $site))->assertNotFound();
        $this->delete(route('sites.destroy', $site))->assertNotFound();

        $this->get(route('clients.show', $site->client_id))->assertNotFound();
        $this->get(route('clients.edit', $site->client_id))->assertNotFound();
        $this->delete(route('clients.destroy', $site->client_id))->assertNotFound();

        $this->get(route('incidents.show', $incident))->assertNotFound();
        $this->post(route('incidents.acknowledge', $incident))->assertNotFound();

        $this->patch(route('notifications.channels.toggle', $channel))->assertNotFound();
        $this->put(route('notifications.channels.preferences', $channel), ['delay_minutes' => 0])->assertNotFound();
        $this->delete(route('notifications.channels.destroy', $channel))->assertNotFound();

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => $site->name]);
        $this->assertDatabaseHas('clients', ['id' => $site->client_id]);
        $this->assertDatabaseHas('notification_channels', ['id' => $channel->id, 'is_active' => true]);
    }

    public function test_lists_only_show_own_records(): void
    {
        $user = $this->userFor();
        $this->siteFor($user->agency, ['name' => 'Mon site']);
        $this->siteFor(Agency::factory()->create(), ['name' => 'Site étranger']);

        $this->actingAs($user)->get(route('sites.index'))->assertSee('Mon site')->assertDontSee('Site étranger');
        $this->actingAs($user)->get(route('incidents.index'))->assertDontSee('Site étranger');
    }

    public function test_a_site_cannot_be_attached_to_a_foreign_client(): void
    {
        $user = $this->userFor();
        $foreignClient = Client::factory()->create(['agency_id' => Agency::factory()->create()->id]);

        $this->actingAs($user)->post(route('sites.store'), [
            'name' => 'Test', 'url' => 'https://exemple.ci', 'client_id' => $foreignClient->id,
            'interval_minutes' => 5, 'timeout_seconds' => 10, 'monitoring_provider' => 'internal',
        ])->assertSessionHasErrors('client_id');

        $this->assertSame(0, Site::withoutGlobalScopes()->where('client_id', $foreignClient->id)->count());
    }
}
