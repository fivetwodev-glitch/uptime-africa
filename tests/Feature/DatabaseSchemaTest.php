<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Enums\MonitoringProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Check;
use App\Models\Client;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Vérifie les contraintes posées EN BASE (clés étrangères, unicités),
 * indépendamment des garde-fous des modèles.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_tables_exist(): void
    {
        foreach (['agencies', 'users', 'roles', 'permissions', 'permission_role', 'sessions',
            'clients', 'sites', 'checks', 'incidents', 'daily_stats', 'plans', 'subscriptions',
            'notification_channels', 'notification_preferences', 'notification_logs',
            'settings', 'audit_logs', 'jobs', 'failed_jobs', 'cache'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table manquante : {$table}");
        }
    }

    public function test_site_fields_requested_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('sites', [
            'name', 'url', 'client_id', 'agency_id', 'status', 'monitoring_status', 'interval_minutes',
            'last_http_status', 'last_response_time_ms', 'last_checked_at', 'consecutive_failures',
            'monitoring_provider', 'external_monitor_id', 'sync_status',
        ]));
    }

    public function test_incident_fields_requested_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('incidents', [
            'site_id', 'started_at', 'resolved_at', 'duration_seconds', 'type', 'description',
            'status', 'confirmed_failures',
        ]));
    }

    public function test_a_site_cannot_point_to_a_client_of_another_agency(): void
    {
        $clientB = Client::factory()->create();
        $agencyA = Agency::factory()->create();

        $this->expectException(QueryException::class);

        // Insertion brute : seule la clé étrangère composite peut l'empêcher.
        DB::table('sites')->insert([
            'id' => (string) str()->ulid(), 'agency_id' => $agencyA->id, 'client_id' => $clientB->id,
            'name' => 'Intrus', 'url' => 'https://example.com', 'url_hash' => Site::hashUrl('https://example.com'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_check_must_belong_to_the_agency_of_its_site(): void
    {
        $site = Site::factory()->create();
        $otherAgency = Agency::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('checks')->insert([
            'agency_id' => $otherAgency->id, 'site_id' => $site->id, 'checked_at' => now(), 'is_up' => true,
        ]);
    }

    public function test_the_same_url_cannot_be_added_twice_in_one_agency(): void
    {
        $site = Site::factory()->create(['url' => 'https://example.com/page']);

        $this->expectException(QueryException::class);

        Site::factory()->create(['client_id' => $site->client_id, 'url' => 'HTTPS://EXAMPLE.COM/page/']);
    }

    public function test_the_same_url_is_allowed_in_two_agencies(): void
    {
        Site::factory()->create(['url' => 'https://example.com']);
        Site::factory()->create(['url' => 'https://example.com']);

        $this->assertSame(2, Site::count());
    }

    public function test_an_external_monitor_id_is_unique_per_provider(): void
    {
        Site::factory()->create(['monitoring_provider' => MonitoringProvider::UptimeRobot, 'external_monitor_id' => '123']);

        $this->expectException(QueryException::class);

        Site::factory()->create(['monitoring_provider' => MonitoringProvider::UptimeRobot, 'external_monitor_id' => '123']);
    }

    public function test_only_one_open_incident_per_site(): void
    {
        $site = Site::factory()->create();
        Incident::factory()->create(['site_id' => $site->id]);

        $this->expectException(QueryException::class);

        Incident::factory()->create(['site_id' => $site->id]);
    }

    public function test_a_new_incident_is_allowed_once_the_previous_is_resolved(): void
    {
        $site = Site::factory()->create();
        Incident::factory()->create(['site_id' => $site->id])->resolve();
        Incident::factory()->create(['site_id' => $site->id, 'type' => IncidentType::Timeout]);

        $this->assertSame(2, $site->incidents()->count());
        $this->assertSame(1, $site->incidents()->open()->count());
    }

    public function test_only_one_current_subscription_per_agency(): void
    {
        $agency = Agency::factory()->withoutSubscription()->create();
        $plan = $this->plan();
        $this->subscription($agency, $plan, SubscriptionStatus::Expired);
        $this->subscription($agency, $plan, SubscriptionStatus::Active);

        $this->expectException(QueryException::class);

        $this->subscription($agency, $plan, SubscriptionStatus::Trialing);
    }

    public function test_a_subscribed_plan_cannot_be_deleted(): void
    {
        $plan = $this->plan();
        $this->subscription(Agency::factory()->withoutSubscription()->create(), $plan, SubscriptionStatus::Active);

        $this->expectException(QueryException::class);

        $plan->delete();
    }

    public function test_global_and_agency_settings_are_unique_per_scope(): void
    {
        $agency = Agency::factory()->create();
        Setting::put('maintenance_message', 'global');
        Setting::put('maintenance_message', 'agence', $agency->id);
        Setting::put('maintenance_message', 'global modifié');

        $this->assertSame('global modifié', Setting::get('maintenance_message'));
        $this->assertSame('agence', Setting::get('maintenance_message', $agency->id));

        $this->expectException(QueryException::class);
        DB::table('settings')->insert(['key' => 'maintenance_message', 'value' => '"doublon"']);
    }

    public function test_a_channel_has_one_preference_for_all_sites(): void
    {
        $agency = Agency::factory()->create();
        $channel = $this->channel($agency);
        $this->preference($channel, null);

        $this->expectException(QueryException::class);

        $this->preference($channel, null);
    }

    public function test_deleting_an_agency_removes_all_its_data(): void
    {
        $site = Site::factory()->create();
        $agency = Agency::find($site->agency_id);
        User::factory()->create(['agency_id' => $agency->id]);
        Incident::factory()->create(['site_id' => $site->id]);
        Check::create(['site_id' => $site->id, 'agency_id' => $agency->id, 'checked_at' => now(), 'is_up' => true]);

        $agency->delete();

        foreach (['users', 'clients', 'sites', 'checks', 'incidents'] as $table) {
            $this->assertSame(0, DB::table($table)->where('agency_id', $agency->id)->count(), $table);
        }
    }

    public function test_notification_history_survives_site_deletion(): void
    {
        $site = Site::factory()->create();
        $incident = Incident::factory()->create(['site_id' => $site->id]);
        $channel = $this->channel(Agency::find($site->agency_id));
        $log = NotificationLog::forceCreate([
            'agency_id' => $site->agency_id, 'site_id' => $site->id, 'incident_id' => $incident->id,
            'notification_channel_id' => $channel->id, 'channel_type' => 'email',
            'recipient' => $channel->destination, 'event' => 'incident_opened',
        ]);

        $site->delete();
        $log->refresh();

        $this->assertNull($log->site_id);
        $this->assertNull($log->incident_id);
        $this->assertSame($channel->destination, $log->recipient);
    }

    public function test_a_notification_is_logged_once_per_incident_channel_and_event(): void
    {
        $incident = Incident::factory()->create();
        $channel = $this->channel(Agency::find($incident->agency_id));
        $row = [
            'agency_id' => $incident->agency_id, 'incident_id' => $incident->id, 'notification_channel_id' => $channel->id,
            'channel_type' => 'email', 'recipient' => $channel->destination, 'event' => 'incident_opened',
        ];
        NotificationLog::forceCreate($row);

        $this->expectException(QueryException::class);

        NotificationLog::forceCreate($row);
    }

    public function test_mysql_check_constraints_reject_inconsistent_rows(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Contraintes CHECK appliquées uniquement sur MySQL/MariaDB.');
        }

        $site = Site::factory()->create();
        $base = ['agency_id' => $site->agency_id, 'site_id' => $site->id, 'type' => 'downtime', 'created_at' => now(), 'updated_at' => now()];

        $rejected = [
            fn () => DB::table('incidents')->insert($base + ['id' => (string) str()->ulid(), 'status' => 'resolved', 'started_at' => now(), 'resolved_at' => now()->subHour()]),
            fn () => DB::table('incidents')->insert($base + ['id' => (string) str()->ulid(), 'status' => 'resolved', 'started_at' => now(), 'resolved_at' => null]),
            fn () => DB::table('sites')->where('id', $site->id)->update(['interval_minutes' => 0]),
        ];

        foreach ($rejected as $i => $attempt) {
            try {
                $attempt();
                $this->fail("La contrainte n°{$i} aurait dû refuser l'écriture.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function plan(): Plan
    {
        return Plan::create([
            'code' => 'p'.str()->random(6), 'name' => 'Offre test', 'monthly_price_xof' => 0,
            'max_sites' => 5, 'max_users' => 2, 'min_interval_minutes' => 5, 'retention_days' => 7,
        ]);
    }

    private function subscription(Agency $agency, Plan $plan, SubscriptionStatus $status): Subscription
    {
        $subscription = new Subscription(['plan_id' => $plan->id, 'status' => $status, 'price_xof' => 0, 'starts_at' => now()]);
        $subscription->agency_id = $agency->id;
        $subscription->save();

        return $subscription;
    }

    private function channel(Agency $agency): NotificationChannel
    {
        $channel = new NotificationChannel(['type' => 'email', 'destination' => str()->random(8).'@example.com']);
        $channel->agency_id = $agency->id;
        $channel->save();

        return $channel;
    }

    private function preference(NotificationChannel $channel, ?string $siteId): NotificationPreference
    {
        $preference = new NotificationPreference(['notification_channel_id' => $channel->id, 'site_id' => $siteId]);
        $preference->agency_id = $channel->agency_id;
        $preference->save();

        return $preference;
    }
}
