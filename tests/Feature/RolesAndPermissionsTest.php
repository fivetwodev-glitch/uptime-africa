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

    public function test_owner_can_manage_everything_in_the_agency_but_not_the_platform(): void
    {
        $owner = User::factory()->withRole(RoleName::Owner)->create();

        $this->assertTrue($owner->hasPermission('subscriptions.manage'));
        $this->assertTrue($owner->hasPermission('sites.manage'));
        $this->assertFalse($owner->hasPermission('admin.access'));
    }

    public function test_agency_admin_cannot_manage_subscription(): void
    {
        $admin = User::factory()->withRole(RoleName::Admin)->create();

        $this->assertTrue($admin->hasPermission('users.manage'));
        $this->assertFalse($admin->hasPermission('subscriptions.manage'));
    }

    public function test_viewer_is_read_only(): void
    {
        $viewer = User::factory()->withRole(RoleName::Viewer)->create();

        $this->assertTrue($viewer->hasPermission('sites.view'));
        $this->assertFalse($viewer->hasPermission('sites.manage'));
        $this->assertFalse($viewer->hasPermission('clients.manage'));
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

        User::factory()->withRole(RoleName::Member)->create(['agency_id' => null]);
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        User::factory()->withRole(RoleName::Member)->create();

        $this->expectException(QueryException::class);

        Role::findBySlug(RoleName::Member)->delete();
    }
}
