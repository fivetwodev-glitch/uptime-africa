<?php

namespace Tests\Feature\UptimeRobot;

use App\Enums\RoleName;
use App\Enums\SyncStatus;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Site;
use App\Models\User;
use App\Services\UptimeRobot\UptimeRobotClient;
use App\Services\UptimeRobot\UptimeRobotException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Interface\InterfaceTestCase;

/**
 * Les réponses simulées reprennent strictement les schémas de la spécification officielle
 * (https://cdn.uptimerobot.com/api/openapi.yaml) : UserDto, MonitorDto,
 * MonitorPaginationDto {nextLink, data}, ApiErrorResponseDto {code, message}.
 * Ce ne sont pas des tests réels : voir les commandes uptimerobot:test et uptimerobot:test-create.
 */
class UptimeRobotIntegrationTest extends InterfaceTestCase
{
    private const FAKE_KEY = 'cle-factice-de-test-0000';

    private const API = 'https://api.uptimerobot.com/v3';

    protected function setUp(): void
    {
        parent::setUp();
        config(['uptime.uptimerobot.api_key' => self::FAKE_KEY]);
        Http::preventStrayRequests();
    }

    private function monitor(int $id, string $url, string $status = 'UP', array $extra = []): array
    {
        return $extra + [
            'id' => $id, 'friendlyName' => 'Moniteur '.$id, 'url' => $url, 'type' => 'HTTP',
            'status' => $status, 'interval' => 300, 'timeout' => 10, 'currentStateDuration' => 60,
            'lastIncidentId' => null, 'lastIncident' => null,
        ];
    }

    private function page(array $monitors, ?string $next = null): array
    {
        return ['nextLink' => $next, 'data' => $monitors];
    }

    private function sitePayload(Client $client, array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Boutique', 'url' => 'https://boutique.exemple.ci', 'client_id' => $client->id,
            'interval_minutes' => 5, 'timeout_seconds' => 10, 'monitoring_provider' => 'uptime_robot',
            'expected_status_codes' => '',
        ];
    }

    private function linkedSite(Agency $agency, int $monitorId, string $url, bool $managed = true, string $status = 'unknown'): Site
    {
        $site = $this->siteFor($agency, ['url' => $url, 'monitoring_provider' => 'uptime_robot', 'external_monitor_id' => (string) $monitorId]);
        $site->forceFill(['external_managed' => $managed, 'status' => $status, 'sync_status' => 'synced'])->saveQuietly();

        return $site->fresh();
    }

    public function test_missing_key_is_reported_clearly_and_no_request_is_sent(): void
    {
        config(['uptime.uptimerobot.api_key' => null]);
        Http::fake();

        $this->artisan('uptimerobot:test')->expectsOutputToContain('ZITE_UPTIMEROBOT_API_KEY')->assertFailed();

        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);
        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client))->assertSessionHas('error');

        $site = Site::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(SyncStatus::Failed, $site->sync_status);
        $this->assertNull($site->external_monitor_id);
        $this->actingAs($user)->get(route('settings.index'))->assertSee('Intégration UptimeRobot non configurée.');
        Http::assertNothingSent();
    }

    public function test_connection_test_uses_bearer_token_and_reads_user_me(): void
    {
        Http::fake([self::API.'/user/me' => Http::response([
            'email' => 'ops@uptime.africa', 'fullName' => 'Ops', 'monitorsCount' => 3, 'monitorLimit' => 50,
            'activeSubscription' => ['plan' => 'FREE', 'monitorLimit' => 50, 'status' => 'ACTIVE'],
        ], 200, ['X-RateLimit-Remaining' => '9'])]);

        $this->artisan('uptimerobot:test')->expectsOutputToContain('réussie')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/user/me' && $r->hasHeader('Authorization', 'Bearer '.self::FAKE_KEY));
    }

    public function test_site_creation_creates_monitor_and_stores_its_id_only_on_success(): void
    {
        Http::fake([
            self::API.'/monitors?*' => Http::response($this->page([])),
            self::API.'/monitors' => Http::response($this->monitor(801, 'https://boutique.exemple.ci', 'STARTED'), 201),
        ]);
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);

        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client))
            ->assertSessionHas('status', fn ($m) => str_contains($m, '801'));

        $site = Site::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('801', $site->external_monitor_id);
        $this->assertTrue($site->external_managed);
        $this->assertSame(SyncStatus::Synced, $site->sync_status);
        $this->assertNotNull($site->last_synced_at);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::API.'/monitors'
            && $r['type'] === 'HTTP' && $r['url'] === 'https://boutique.exemple.ci'
            && $r['interval'] === 300 && $r['timeout'] === 10 && str_contains($r['friendlyName'], 'UA-'.$site->id));
    }

    public function test_failed_creation_keeps_the_site_unsynced_and_is_not_retried(): void
    {
        Http::fake([
            self::API.'/monitors?*' => Http::response($this->page([])),
            self::API.'/monitors' => Http::response(['code' => '000-001', 'message' => ['interval must not be less than 300']], 400),
        ]);
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);

        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'interval must not be less than 300') && str_contains($m, '000-001'));

        $site = Site::withoutGlobalScopes()->firstOrFail();
        $this->assertNull($site->external_monitor_id);
        $this->assertSame(SyncStatus::Failed, $site->sync_status);
        Http::assertSentCount(2);
    }

    public function test_server_error_on_creation_is_not_retried_to_avoid_duplicates(): void
    {
        Http::fake([
            self::API.'/monitors?*' => Http::response($this->page([])),
            self::API.'/monitors' => Http::response(['code' => '500', 'message' => 'Internal'], 500),
        ]);
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);

        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client))->assertSessionHas('error');

        Http::assertSentCount(2);
    }

    public function test_existing_monitor_with_same_url_is_reused_instead_of_duplicated(): void
    {
        Http::fake([self::API.'/monitors?*' => Http::response($this->page([$this->monitor(55, 'https://BOUTIQUE.exemple.ci/')]))]);
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);

        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client))->assertSessionHas('status');

        $site = Site::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('55', $site->external_monitor_id);
        $this->assertFalse($site->external_managed);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_a_monitor_linked_to_another_agency_cannot_be_taken(): void
    {
        $this->linkedSite(Agency::factory()->create(), 77, 'https://boutique.exemple.ci');
        Http::fake([
            self::API.'/monitors/77' => Http::response($this->monitor(77, 'https://boutique.exemple.ci')),
            self::API.'/monitors?*' => Http::response($this->page([$this->monitor(77, 'https://boutique.exemple.ci')])),
        ]);
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);

        // Par identifiant : refusé à la validation (déjà rattaché).
        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client, ['external_monitor_id' => '77']))
            ->assertSessionHasErrors('external_monitor_id');
        // Sans identifiant : le moniteur trouvé par URL appartient à un autre site.
        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'déjà surveillée'));

        $mine = Site::withoutGlobalScopes()->where('agency_id', $user->agency_id)->firstOrFail();
        $this->assertNull($mine->external_monitor_id);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_linking_by_id_requires_the_monitor_to_watch_the_same_url(): void
    {
        Http::fake([self::API.'/monitors/90' => Http::response($this->monitor(90, 'https://autre-site.ci'))]);
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);

        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client, ['external_monitor_id' => '90']))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'ne surveille pas'));
        $this->assertNull(Site::withoutGlobalScopes()->firstOrFail()->external_monitor_id);
    }

    public function test_sync_follows_pagination_and_maps_statuses(): void
    {
        config(['uptime.uptimerobot.response_times_per_run' => 0]);
        $agency = Agency::factory()->create();
        $a = $this->linkedSite($agency, 1, 'https://a.exemple.ci', status: 'up');
        $b = $this->linkedSite($agency, 2, 'https://b.exemple.ci');
        $gone = $this->linkedSite($agency, 3, 'https://c.exemple.ci');

        Http::fakeSequence(self::API.'/monitors*')
            ->push($this->page([$this->monitor(1, 'https://a.exemple.ci', 'DOWN')], self::API.'/monitors?cursor=1&limit=200'))
            ->push($this->page([$this->monitor(2, 'https://b.exemple.ci', 'LOOKS_DOWN')]));

        $this->artisan('uptimerobot:sync --force')->expectsOutputToContain('3 site(s)')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/monitors?cursor=1&limit=200');
        // Un seul échec : panne non confirmée, pas d'incident.
        $this->assertSame('degraded', $a->fresh()->status->value);
        $this->assertSame('degraded', $b->fresh()->status->value);
        $this->assertSame(SyncStatus::Failed, $gone->fresh()->sync_status);
        $this->assertSame(0, Incident::withoutGlobalScopes()->count());
    }

    public function test_foreign_pagination_host_is_refused(): void
    {
        $this->linkedSite(Agency::factory()->create(), 1, 'https://a.exemple.ci');
        Http::fake([self::API.'/monitors*' => Http::response($this->page([], 'https://evil.example/v3/monitors?cursor=2'))]);

        $this->artisan('uptimerobot:sync --force')->expectsOutputToContain('pagination')->assertFailed();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example'));
    }

    public function test_rate_limit_and_server_errors_are_retried_but_client_errors_are_not(): void
    {
        Http::fakeSequence(self::API.'/user/me')
            ->push(['code' => '429', 'message' => 'Too Many Requests'], 429, ['Retry-After' => '2', 'X-RateLimit-Remaining' => '0'])
            ->push(['code' => '503', 'message' => 'Unavailable'], 503)
            ->push(['email' => 'ops@uptime.africa', 'monitorsCount' => 0, 'monitorLimit' => 50]);

        $client = app(UptimeRobotClient::class);
        $this->assertSame('ops@uptime.africa', $client->me()['email']);
        Http::assertSentCount(3);

        Http::fake([self::API.'/monitors/5' => Http::response(['code' => '401', 'message' => 'Unauthorized'], 401)]);
        try {
            app(UptimeRobotClient::class)->getMonitor(5);
            $this->fail('Exception attendue');
        } catch (UptimeRobotException $e) {
            $this->assertSame(401, $e->httpStatus);
            $this->assertStringContainsString('Clé API UptimeRobot refusée', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_rate_limit_wait_beyond_cap_gives_up_immediately(): void
    {
        Http::fake([self::API.'/user/me' => Http::response(['code' => '429', 'message' => 'Too Many Requests'], 429, ['Retry-After' => '600'])]);

        $this->expectException(UptimeRobotException::class);
        try {
            app(UptimeRobotClient::class)->me();
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_pause_and_delete_are_propagated_only_to_the_linked_monitor(): void
    {
        $user = $this->userFor();
        $managed = $this->linkedSite($user->agency, 11, 'https://geree.exemple.ci', managed: true);
        $foreign = $this->linkedSite($user->agency, 12, 'https://associee.exemple.ci', managed: false);
        Http::fake([
            self::API.'/monitors/11/pause' => Http::response($this->monitor(11, 'https://geree.exemple.ci', 'PAUSED'), 201),
            self::API.'/monitors/11' => Http::response([], 200),
        ]);

        $this->actingAs($user)->patch(route('sites.toggle', $managed))->assertSessionHas('status');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::API.'/monitors/11/pause');

        $this->actingAs($user)->delete(route('sites.destroy', $managed))->assertRedirect(route('sites.index'));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::API.'/monitors/11');

        $this->actingAs($user)->delete(route('sites.destroy', $foreign))->assertRedirect(route('sites.index'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/monitors/12'));
    }

    public function test_remote_delete_failure_keeps_the_site(): void
    {
        $user = $this->userFor();
        $site = $this->linkedSite($user->agency, 13, 'https://geree.exemple.ci');
        Http::fake([self::API.'/monitors/13' => Http::response(['code' => '403', 'message' => 'Forbidden'], 403)]);

        $this->actingAs($user)->delete(route('sites.destroy', $site))->assertSessionHas('error');
        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    public function test_admin_page_is_super_admin_only_and_never_shows_the_key(): void
    {
        $this->actingAs($this->userFor())->get(route('admin.uptimerobot'))->assertForbidden();

        $admin = User::factory()->withRole(RoleName::SuperAdmin)->create(['agency_id' => null]);
        Http::fake([self::API.'/monitors*' => Http::response($this->page([$this->monitor(1, 'https://a.exemple.ci')]))]);

        $this->actingAs($admin)->get(route('admin.uptimerobot', ['lister' => 1]))
            ->assertOk()->assertSee('Clé API configurée')->assertSee('https://a.exemple.ci')->assertDontSee(self::FAKE_KEY);
    }

    public function test_key_never_reaches_logs_database_or_pages(): void
    {
        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event->message.json_encode($event->context);
        });
        Http::fake([
            self::API.'/monitors?*' => Http::response($this->page([])),
            self::API.'/monitors' => Http::response(['code' => '401', 'message' => 'Unauthorized'], 401),
        ]);
        $user = $this->userFor();
        $client = Client::factory()->create(['agency_id' => $user->agency_id]);

        $this->actingAs($user)->post(route('sites.store'), $this->sitePayload($client));
        $site = Site::withoutGlobalScopes()->firstOrFail();
        $pages = $this->actingAs($user)->get(route('sites.show', $site))->getContent()
            .$this->actingAs($user)->get(route('settings.index'))->getContent()
            .$this->actingAs($user)->get(route('sites.edit', $site))->getContent();

        $this->assertNotEmpty($logged);
        $this->assertStringNotContainsString(self::FAKE_KEY, implode("\n", $logged));
        $this->assertStringNotContainsString(self::FAKE_KEY, $pages);
        foreach (['sites', 'audit_logs', 'settings'] as $table) {
            $this->assertStringNotContainsString(self::FAKE_KEY, json_encode(\DB::table($table)->get()));
        }
    }

    public function test_editing_a_managed_site_patches_its_monitor_and_retry_creates_a_missing_one(): void
    {
        $user = $this->userFor();
        $site = $this->linkedSite($user->agency, 21, 'https://geree.exemple.ci');
        Http::fake([
            self::API.'/monitors/21' => Http::response($this->monitor(21, 'https://nouvelle.exemple.ci'), 200),
            self::API.'/monitors?*' => Http::response($this->page([])),
            self::API.'/monitors' => Http::response($this->monitor(22, 'https://interne.exemple.ci', 'STARTED'), 201),
        ]);

        $this->actingAs($user)->put(route('sites.update', $site), $this->sitePayload($site->client, ['url' => 'https://nouvelle.exemple.ci', 'interval_minutes' => 10]))
            ->assertSessionHas('status');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['url'] === 'https://nouvelle.exemple.ci' && $r['interval'] === 600);
        $this->assertSame('21', $site->fresh()->external_monitor_id);

        $other = $this->siteFor($user->agency, ['url' => 'https://interne.exemple.ci', 'monitoring_provider' => 'uptime_robot']);
        $this->actingAs($user)->post(route('sites.sync', $other))->assertSessionHas('status');
        $this->assertSame('22', $other->fresh()->external_monitor_id);
    }
}
