<?php

namespace Tests\Feature\Interface;

use App\Enums\RoleName;
use App\Models\Incident;

class PermissionsTest extends InterfaceTestCase
{
    public function test_a_viewer_can_read_but_not_change_anything(): void
    {
        $viewer = $this->userFor(role: RoleName::Viewer);
        $site = $this->siteFor($viewer->agency);
        $incident = Incident::factory()->create(['site_id' => $site->id]);
        $this->actingAs($viewer);

        $this->get(route('sites.index'))->assertOk()->assertDontSee('Ajouter un site');
        $this->get(route('sites.show', $site))->assertOk()->assertDontSee('Supprimer');
        $this->get(route('incidents.show', $incident))->assertOk()->assertDontSee('Prendre en charge');

        $this->get(route('sites.create'))->assertForbidden();
        $this->post(route('sites.store'), [])->assertForbidden();
        $this->get(route('sites.edit', $site))->assertForbidden();
        $this->delete(route('sites.destroy', $site))->assertForbidden();
        $this->patch(route('sites.toggle', $site))->assertForbidden();
        $this->post(route('clients.store'), ['name' => 'X'])->assertForbidden();
        $this->post(route('incidents.acknowledge', $incident))->assertForbidden();
        $this->put(route('settings.agency'), ['name' => 'X', 'timezone' => 'Africa/Abidjan'])->assertForbidden();

        $this->get(route('notifications.index'))->assertForbidden();
        $this->get(route('subscription.show'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee(route('notifications.index'))->assertDontSee(route('subscription.show'));

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
        $this->assertSame('open', $incident->fresh()->status->value);
    }

    public function test_a_member_manages_sites_but_not_alert_channels(): void
    {
        $member = $this->userFor(role: RoleName::Member);
        $site = $this->siteFor($member->agency);
        $this->actingAs($member);

        $this->get(route('sites.create'))->assertOk();
        $this->patch(route('sites.toggle', $site))->assertRedirect();
        $this->get(route('notifications.index'))->assertOk()->assertDontSee('Ajouter un destinataire');
        $this->post(route('notifications.channels.store'), ['type' => 'email', 'destination' => 'a@b.ci'])->assertForbidden();
    }
}
