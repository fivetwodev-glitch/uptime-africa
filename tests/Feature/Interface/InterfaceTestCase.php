<?php

namespace Tests\Feature\Interface;

use App\Enums\RoleName;
use App\Models\Agency;
use App\Models\Client;
use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class InterfaceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function userFor(?Agency $agency = null, RoleName $role = RoleName::Admin): User
    {
        return User::factory()->withRole($role)->create(['agency_id' => ($agency ?? Agency::factory()->create())->id]);
    }

    protected function siteFor(Agency $agency, array $attributes = []): Site
    {
        $client = Client::factory()->create(['agency_id' => $agency->id]);

        return Site::factory()->create(['client_id' => $client->id] + $attributes);
    }

    protected function channelFor(Agency $agency, string $destination = 'alertes@example.com'): NotificationChannel
    {
        return NotificationChannel::forceCreate([
            'agency_id' => $agency->id, 'type' => 'email', 'destination' => $destination,
        ]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }
}
