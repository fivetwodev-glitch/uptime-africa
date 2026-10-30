<?php

namespace Tests\Feature;

use App\Exceptions\TenantViolationException;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agencyA;

    private Agency $agencyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agencyA = Agency::factory()->create();
        $this->agencyB = Agency::factory()->create();

        Client::factory()->count(2)->create(['agency_id' => $this->agencyA->id]);
        Client::factory()->count(3)->create(['agency_id' => $this->agencyB->id]);
    }

    public function test_an_agency_only_sees_its_own_clients(): void
    {
        app(TenantContext::class)->set($this->agencyA->id);

        $this->assertSame(2, Client::count());
        $this->assertTrue(Client::all()->every(fn ($c) => $c->agency_id === $this->agencyA->id));
    }

    public function test_another_agency_record_is_not_found(): void
    {
        $foreign = Client::where('agency_id', $this->agencyB->id)->first();

        app(TenantContext::class)->set($this->agencyA->id);

        $this->assertNull(Client::find($foreign->id));
    }

    public function test_agency_id_is_filled_automatically_on_creation(): void
    {
        app(TenantContext::class)->set($this->agencyA->id);

        $client = Client::create(['name' => 'Nouveau client']);

        $this->assertSame($this->agencyA->id, $client->agency_id);
    }

    public function test_writing_into_another_agency_is_refused(): void
    {
        app(TenantContext::class)->set($this->agencyA->id);

        $this->expectException(TenantViolationException::class);

        $client = new Client(['name' => 'Intrus']);
        $client->agency_id = $this->agencyB->id;
        $client->save();
    }

    public function test_sites_are_isolated_too(): void
    {
        Site::factory()->create(['client_id' => Client::where('agency_id', $this->agencyB->id)->first()->id]);

        app(TenantContext::class)->set($this->agencyA->id);

        $this->assertSame(0, Site::count());
    }

    public function test_without_tenant_context_everything_is_visible(): void
    {
        // Cas du planificateur et de l'administrateur général.
        $this->assertSame(5, Client::count());
    }

    public function test_logged_in_member_is_scoped_by_middleware(): void
    {
        Route::middleware('web')->get('/_test/clients', fn () => ['count' => Client::count()]);

        $user = User::factory()->create(['agency_id' => $this->agencyA->id]);

        $this->actingAs($user)->getJson('/_test/clients')->assertJson(['count' => 2]);
    }

    public function test_super_admin_is_not_scoped(): void
    {
        Route::middleware('web')->get('/_test/clients', fn () => ['count' => Client::count()]);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->getJson('/_test/clients')->assertJson(['count' => 5]);
    }

    public function test_user_of_inactive_agency_is_refused(): void
    {
        Route::middleware('web')->get('/_test/clients', fn () => ['count' => Client::count()]);

        $user = User::factory()->create(['agency_id' => Agency::factory()->inactive()->create()->id]);

        $this->actingAs($user)->getJson('/_test/clients')->assertForbidden();
    }

    public function test_role_and_agency_cannot_be_mass_assigned(): void
    {
        // Mode strict : toute tentative d'assignation de rôle ou d'agence lève une exception.
        $this->expectException(MassAssignmentException::class);

        new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'role' => 'super_admin', 'agency_id' => $this->agencyB->id]);
    }
}
