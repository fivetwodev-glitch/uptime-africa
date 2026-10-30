<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\MonitoringProvider;
use App\Enums\MonitoringStatus;
use App\Enums\SiteStatus;
use App\Enums\SyncStatus;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Site;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_site_defaults(): void
    {
        $site = Site::factory()->create()->refresh();

        $this->assertSame(SiteStatus::Unknown, $site->status);
        $this->assertSame(MonitoringStatus::Active, $site->monitoring_status);
        $this->assertSame(MonitoringProvider::Internal, $site->monitoring_provider);
        $this->assertSame(SyncStatus::NotApplicable, $site->sync_status);
        $this->assertSame(0, $site->consecutive_failures);
    }

    public function test_site_agency_is_derived_from_its_client(): void
    {
        $client = Client::factory()->create();

        $site = Site::create(['client_id' => $client->id, 'name' => 'Site', 'url' => 'https://example.com']);

        $this->assertSame($client->agency_id, $site->agency_id);
    }

    public function test_url_hash_ignores_case_of_domain_and_trailing_slash(): void
    {
        $this->assertSame(Site::hashUrl('https://example.com/a'), Site::hashUrl('HTTPS://Example.COM/a/'));
        $this->assertNotSame(Site::hashUrl('https://example.com/a'), Site::hashUrl('https://example.com/b'));
    }

    public function test_external_provider_marks_site_as_pending_sync(): void
    {
        $site = Site::factory()->create(['monitoring_provider' => MonitoringProvider::UptimeRobot, 'external_monitor_id' => '42']);

        $this->assertSame(SyncStatus::Pending, $site->sync_status);
    }

    public function test_resolving_an_incident_sets_status_and_duration(): void
    {
        $incident = Incident::factory()->create(['started_at' => now()->subMinutes(15)]);

        $incident->resolve(now());

        $this->assertSame(IncidentStatus::Resolved, $incident->status);
        $this->assertSame(900, $incident->duration_seconds);
    }

    public function test_incident_agency_is_derived_from_site(): void
    {
        $site = Site::factory()->create();

        $incident = Incident::create(['site_id' => $site->id, 'type' => 'downtime', 'started_at' => now()]);

        $this->assertSame($site->agency_id, $incident->agency_id);
    }

    public function test_tenant_context_hides_other_agency_incidents(): void
    {
        $mine = Incident::factory()->create();
        Incident::factory()->create();

        app(TenantContext::class)->set($mine->agency_id);

        $this->assertSame([$mine->id], Incident::pluck('id')->all());
    }
}
