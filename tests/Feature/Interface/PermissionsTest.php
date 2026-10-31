<?php

namespace Tests\Feature\Interface;

use App\Enums\RoleName;
use App\Models\Incident;

/** Les autorisations sont vérifiées côté serveur : chaque requête interdite est refusée, bouton visible ou non. */
class PermissionsTest extends InterfaceTestCase
{
    public function test_a_collaborator_works_on_sites_and_incidents(): void
    {
        $collaborator = $this->userFor(role: RoleName::Collaborator);
        $site = $this->siteFor($collaborator->agency);
        $incident = Incident::factory()->create(['site_id' => $site->id]);
        $this->actingAs($collaborator);

        $this->get(route('sites.create'))->assertOk();
        $this->patch(route('sites.toggle', $site))->assertRedirect();
        $this->post(route('clients.store'), ['name' => 'Client du collaborateur'])->assertRedirect();
        $this->post(route('incidents.acknowledge', $incident))->assertRedirect();
        $this->assertSame('acknowledged', $incident->fresh()->status->value);
        $this->get(route('team.index'))->assertOk()->assertDontSee('Inviter un utilisateur');
    }

    public function test_a_collaborator_cannot_administer_the_agency_even_with_forged_requests(): void
    {
        $collaborator = $this->userFor(role: RoleName::Collaborator);
        $colleague = $this->userFor($collaborator->agency, RoleName::Collaborator);
        $channel = $this->channelFor($collaborator->agency);
        $this->actingAs($collaborator);

        $this->get(route('subscription.show'))->assertForbidden();
        $this->get(route('team.create'))->assertForbidden();
        $this->post(route('team.store'), ['name' => 'Intrus', 'email' => 'intrus@exemple.ci', 'role' => 'admin'])->assertForbidden();
        $this->get(route('team.edit', $colleague))->assertForbidden();
        $this->put(route('team.update', $colleague), ['name' => 'X', 'role' => 'admin'])->assertForbidden();
        $this->put(route('team.update', $collaborator), ['name' => 'Moi', 'role' => 'admin'])->assertForbidden();
        $this->patch(route('team.toggle', $colleague))->assertForbidden();
        $this->delete(route('team.destroy', $colleague))->assertForbidden();
        $this->put(route('settings.agency'), ['name' => 'Piratée', 'timezone' => 'Africa/Abidjan'])->assertForbidden();
        $this->post(route('notifications.channels.store'), ['type' => 'email', 'destination' => 'a@b.ci'])->assertForbidden();
        $this->delete(route('notifications.channels.destroy', $channel))->assertForbidden();
        $this->get(route('admin.index'))->assertForbidden();

        $this->assertTrue($collaborator->fresh()->hasRole(RoleName::Collaborator));
        $this->assertTrue($colleague->fresh()->is_active);
        $this->assertDatabaseMissing('users', ['email' => 'intrus@exemple.ci']);
        $this->get(route('dashboard'))->assertDontSee(route('subscription.show'));
    }
}
