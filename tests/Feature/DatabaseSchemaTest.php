<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_business_tables_exist(): void
    {
        foreach (['agencies', 'users', 'clients', 'sites', 'checks', 'incidents', 'daily_stats',
            'notification_channels', 'alerts', 'plans', 'subscriptions', 'audit_logs', 'jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table manquante : {$table}");
        }
    }

    public function test_deleting_an_agency_removes_its_data(): void
    {
        $site = Site::factory()->create();
        $agency = Agency::find($site->agency_id);

        $agency->delete();

        $this->assertSame(0, Client::where('agency_id', $agency->id)->count());
        $this->assertNull(Site::find($site->id));
    }
}
