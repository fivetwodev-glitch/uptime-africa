<?php

namespace Tests\Feature\Security;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\Feature\Interface\InterfaceTestCase;

class RoleEscalationTest extends InterfaceTestCase
{
    public function test_profile_update_ignores_role_agency_and_status_fields(): void
    {
        $collaborator = $this->userFor(role: RoleName::Collaborator);
        $otherAgency = $this->userFor()->agency;

        $this->actingAs($collaborator)->put(route('settings.profile'), [
            'name' => 'Nouveau nom', 'email' => $collaborator->email,
            'role_id' => Role::findBySlug(RoleName::SuperAdmin)->id, 'role' => 'admin',
            'agency_id' => $otherAgency->id, 'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $fresh = $collaborator->fresh();
        $this->assertSame('Nouveau nom', $fresh->name);
        $this->assertTrue($fresh->hasRole(RoleName::Collaborator));
        $this->assertSame($collaborator->agency_id, $fresh->agency_id);
        $this->assertTrue($fresh->is_active);
    }

    public function test_role_and_agency_are_never_mass_assignable(): void
    {
        // En production, les champs non autorisés sont ignorés (hors production, une exception les signale).
        Model::preventSilentlyDiscardingAttributes(false);
        $user = new User(['name' => 'X', 'email' => 'x@exemple.ci', 'password' => 'secret-123', 'role_id' => 1, 'agency_id' => 'abc', 'is_active' => false]);
        Model::preventSilentlyDiscardingAttributes();

        $this->assertNull($user->role_id);
        $this->assertNull($user->agency_id);
        $this->assertNull($user->getAttributes()['is_active'] ?? null);
    }

    public function test_registration_always_creates_an_agency_admin(): void
    {
        $this->post(route('register'), [
            'agency_name' => 'Agence Test', 'name' => 'Koffi', 'email' => 'koffi@exemple.ci',
            'password' => 'MotDePasse-2026', 'password_confirmation' => 'MotDePasse-2026',
            'role' => 'super_admin', 'role_id' => Role::findBySlug(RoleName::SuperAdmin)->id,
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'koffi@exemple.ci')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleName::Admin));
        $this->assertNotNull($user->agency_id);
    }

    public function test_an_admin_cannot_grant_super_admin(): void
    {
        $admin = $this->userFor();
        $member = $this->userFor($admin->agency, RoleName::Collaborator);
        $this->actingAs($admin);

        $this->put(route('team.update', $member), ['name' => 'X', 'role' => 'super_admin'])->assertSessionHasErrors('role');
        $this->post(route('team.store'), ['name' => 'Y', 'email' => 'y@exemple.ci', 'role' => 'super_admin'])->assertSessionHasErrors('role');

        $this->assertTrue($member->fresh()->hasRole(RoleName::Collaborator));
        $this->assertDatabaseMissing('users', ['email' => 'y@exemple.ci']);
    }

    public function test_an_admin_cannot_change_their_own_role_or_status(): void
    {
        $admin = $this->userFor();
        $this->userFor($admin->agency); // deuxième administrateur : la règle « dernier admin » n'intervient pas
        $this->actingAs($admin);

        $this->put(route('team.update', $admin), ['name' => 'Moi', 'role' => 'collaborator'])->assertSessionHasErrors('role');
        $this->patch(route('team.toggle', $admin))->assertSessionHasErrors('member');
        $this->delete(route('team.destroy', $admin))->assertSessionHasErrors('member');

        $fresh = $admin->fresh();
        $this->assertTrue($fresh->hasRole(RoleName::Admin));
        $this->assertTrue($fresh->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.change_refused', 'user_id' => $admin->id]);
    }

    public function test_an_agency_always_keeps_an_active_admin(): void
    {
        $admin = $this->userFor();
        $other = $this->userFor($admin->agency);
        $this->actingAs($admin);

        // Deux administrateurs : l'un peut rétrograder ou désactiver l'autre…
        $this->put(route('team.update', $other), ['name' => $other->name, 'role' => 'collaborator'])->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->hasRole(RoleName::Collaborator));

        // …mais personne ne peut se retirer lui-même : l'agence garde toujours l'administrateur qui agit.
        $this->put(route('team.update', $admin), ['name' => $admin->name, 'role' => 'collaborator'])->assertSessionHasErrors('role');
        $this->assertSame(1, User::where('agency_id', $admin->agency_id)->where('is_active', true)
            ->where('role_id', Role::findBySlug(RoleName::Admin)->id)->count());
    }

    public function test_an_admin_promotes_and_demotes_collaborators(): void
    {
        $admin = $this->userFor();
        $member = $this->userFor($admin->agency, RoleName::Collaborator);

        $this->actingAs($admin)->put(route('team.update', $member), ['name' => $member->name, 'role' => 'admin'])->assertRedirect(route('team.index'));

        $this->assertTrue($member->fresh()->hasRole(RoleName::Admin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'subject_id' => (string) $member->id]);
    }
}
