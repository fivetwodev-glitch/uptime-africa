<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Exceptions\UptimeException;
use App\Models\Agency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_roles_and_permissions_are_installed_by_migration(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(RoleName::cases(), 'value'),
            Role::pluck('slug')->all(),
        );
        $this->assertSame(count(config('permissions.permissions')), Permission::count());
    }

    public function test_agency_admin_manages_everything_in_the_agency_but_not_the_platform(): void
    {
        $admin = User::factory()->withRole(RoleName::Admin)->create();

        $this->assertTrue($admin->hasPermission('users.manage'));
        $this->assertTrue($admin->hasPermission('subscriptions.manage'));
        $this->assertTrue($admin->hasPermission('sites.manage'));
        $this->assertFalse($admin->hasPermission('admin.access'));
    }

    public function test_collaborator_works_on_sites_but_cannot_administer_the_agency(): void
    {
        $collaborator = User::factory()->withRole(RoleName::Collaborator)->create();

        $this->assertTrue($collaborator->hasPermission('sites.manage'));
        $this->assertTrue($collaborator->hasPermission('incidents.acknowledge'));
        foreach (['users.manage', 'agency.update', 'notifications.manage', 'subscriptions.view', 'settings.manage', 'audit.view', 'admin.access'] as $permission) {
            $this->assertFalse($collaborator->hasPermission($permission), $permission);
        }
    }

    public function test_super_admin_has_every_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (array_keys(config('permissions.permissions')) as $permission) {
            $this->assertTrue($admin->hasPermission($permission), $permission);
        }
    }

    public function test_super_admin_cannot_belong_to_an_agency(): void
    {
        $this->expectException(UptimeException::class);

        User::factory()->superAdmin()->create(['agency_id' => Agency::factory()->create()->id]);
    }

    public function test_agency_user_must_belong_to_an_agency(): void
    {
        $this->expectException(UptimeException::class);

        User::factory()->withRole(RoleName::Collaborator)->create(['agency_id' => null]);
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        User::factory()->withRole(RoleName::Collaborator)->create();

        $this->expectException(QueryException::class);

        Role::findBySlug(RoleName::Collaborator)->delete();
    }
}
