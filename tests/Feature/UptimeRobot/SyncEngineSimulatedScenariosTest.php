<?php

namespace Tests\Feature\UptimeRobot;

use App\Enums\CheckStatus;
use App\Enums\SyncResult;
use App\Models\Agency;
use App\Models\Check;
use App\Models\Incident;
use App\Models\Site;
use App\Services\Monitoring\UptimeStats;
use App\Services\UptimeRobot\SyncEngine;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Interface\InterfaceTestCase;

/**
 * SCÉNARIOS SIMULÉS — aucune requête réelle.
 * Les réponses imitent les schémas de la spécification officielle UptimeRobot v3
 * (MonitorPaginationDto, MonitorDto, MonitorResponseTimeStatsDto, ApiErrorResponseDto).
 * Les tests réels sont faits avec php artisan uptimerobot:sync contre l'API (voir docs/uptimerobot.md).
 */
class SyncEngineSimulatedScenariosTest extends InterfaceTestCase
{
    private const API = 'https://api.uptimerobot.com/v3';

    private array $queue = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['uptime.uptimerobot.api_key' => 'cle-factice-de-test-0000', 'uptime.uptimerobot.response_times_per_run' => 0]);
        $this->travelTo(now()->startOfMinute()->setTime(10, 0));
        Http::preventStrayRequests();
        Http::fake([self::API.'/monitors*' => function (Request $request) {
            // Seule la liste GET /monitors passe par la file ; les autres routes sont simulées par chaque test.
            if (! preg_match('#/v3/monitors(\?|$)#', $request->url())) {
                return null;
            }

            return array_shift($this->queue) ?? Http::response(['nextLink' => null, 'data' => []]);
        }]);
    }

    /** File de réponses GET /monitors, une par passage. */
    private function respond(mixed ...$responses): void
    {
        $this->queue = $responses;
    }

    private function list(string $status, array $extra = []): PromiseInterface
    {
        return Http::response(['nextLink' => null, 'data' => [$extra + [
            'id' => 500, 'friendlyName' => 'Boutique', 'url' => 'https://boutique.exemple.ci', 'type' => 'HTTP',
            'status' => $status, 'interval' => 300, 'currentStateDuration' => 60,
        ]]]);
    }

    private function site(?Agency $agency = null): Site
    {
        $site = $this->siteFor($agency ?? Agency::factory()->create(), [
            'url' => 'https://boutique.exemple.ci', 'monitoring_provider' => 'uptime_robot', 'external_monitor_id' => '500',
        ]);
        $site->forceFill(['status' => 'up', 'sync_status' => 'synced'])->saveQuietly();

        return $site->fresh();
    }

    /** Passage forcé puis +5 min. */
    private function pass(): array
    {
        $report = app(SyncEngine::class)->run(force: true);
        $this->travel(5)->minutes();

        return $report;
    }

    public function test_simule_incident_ouvert_apres_deux_echecs_confirmes_puis_resolu_avec_duree(): void
    {
        $site = $this->site();
        $this->respond(
            $this->list('DOWN', ['lastIncident' => ['id' => '1', 'cause' => 503, 'reason' => 'Service Unavailable', 'startedAt' => now()->toIso8601String(), 'duration' => 0]]),
            $this->list('DOWN', ['lastIncident' => ['id' => '1', 'cause' => 503, 'reason' => 'Service Unavailable']]),
            $this->list('DOWN', ['lastIncident' => ['id' => '1', 'cause' => 503, 'reason' => 'Service Unavailable']]),
            $this->list('UP'),
        );
        $firstFailure = now()->copy();

        $this->pass();
        $this->assertSame(0, Incident::withoutGlobalScopes()->count(), '1 échec : pas d\'incident');
        $this->assertSame('degraded', $site->fresh()->status->value);
        $this->assertSame(1, $site->fresh()->consecutive_failures);

        $report = $this->pass();
        $this->assertSame(1, $report['incidents_opened']);
        $incident = Incident::withoutGlobalScopes()->sole();
        $this->assertTrue($incident->started_at->equalTo($firstFailure), 'début = première vérification en échec');
        $this->assertSame(2, $incident->confirmed_failures);
        $this->assertSame(503, $incident->last_http_status);
        $this->assertSame('down', $site->fresh()->status->value);

        $this->pass();
        $this->assertSame(1, Incident::withoutGlobalScopes()->count(), 'jamais deux incidents pour la même panne');
        $this->assertSame(3, $incident->fresh()->confirmed_failures);

        $resolvedAt = now()->copy();
        $report = $this->pass();
        $incident->refresh();
        $this->assertSame(1, $report['incidents_resolved']);
        $this->assertTrue($incident->resolved_at->equalTo($resolvedAt));
        $this->assertSame(15 * 60, $incident->duration_seconds);
        $this->assertSame('resolved', $incident->status->value);
        $this->assertSame(0, $site->fresh()->consecutive_failures);
        $this->assertSame(4, Check::withoutGlobalScopes()->where('site_id', $site->id)->count(), 'historique conservé');
        $this->assertSame(503, Check::withoutGlobalScopes()->where('is_up', false)->first()->http_status);
    }

    public function test_simule_api_injoignable_ne_devient_jamais_une_panne(): void
    {
        $site = $this->site();
        $this->respond(
            Http::response(['code' => '503', 'message' => 'Unavailable'], 503),
            Http::response(['code' => '503', 'message' => 'Unavailable'], 503),
            Http::response(['code' => '503', 'message' => 'Unavailable'], 503),
        );
        config(['uptime.uptimerobot.max_attempts' => 1]);

        $report = $this->pass();
        $this->pass();

        $this->assertSame('failed', $report['status']);
        $this->assertSame(0, Incident::withoutGlobalScopes()->count());
        $this->assertSame('up', $site->fresh()->status->value, 'statut du site inchangé');
        $this->assertSame(0, $site->fresh()->consecutive_failures);
        $checks = Check::withoutGlobalScopes()->get();
        $this->assertCount(2, $checks);
        $this->assertTrue($checks->every(fn (Check $c) => $c->is_up === null && $c->status === CheckStatus::Unknown && $c->sync_result === SyncResult::ApiError));
        $this->assertNull(app(UptimeStats::class)->summary(now()->subDay(), $site->id)['uptime'], 'les inconnus ne comptent pas dans la disponibilité');
    }

    public function test_simule_statut_inconnu_ne_casse_ni_ne_complete_la_serie(): void
    {
        $site = $this->site();
        $this->respond($this->list('DOWN'), $this->list('STARTED'), $this->list('STATUT_NON_DOCUMENTE'), $this->list('PAUSED'), $this->list('DOWN'));

        $this->pass();
        $this->pass();
        $this->pass();
        $this->pass();
        $this->assertSame(1, $site->fresh()->consecutive_failures);
        $this->assertSame(0, Incident::withoutGlobalScopes()->count());

        $this->pass();
        $this->assertSame(1, Incident::withoutGlobalScopes()->count());
        $this->assertSame(
            ['down', 'unknown', 'unknown', 'paused', 'down'],
            Check::withoutGlobalScopes()->orderBy('checked_at')->get()->map(fn (Check $c) => $c->status->value)->all(),
        );
    }

    public function test_simule_idempotence_meme_minute(): void
    {
        $site = $this->site();
        $this->respond($this->list('DOWN'), $this->list('DOWN'));

        app(SyncEngine::class)->run(force: true);
        $second = app(SyncEngine::class)->run(force: true);

        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, Check::withoutGlobalScopes()->count());
        $this->assertSame(1, $site->fresh()->consecutive_failures, 'un passage rejoué ne compte pas deux fois');
    }

    public function test_simule_execution_simultanee_bloquee_et_frequence_respectee(): void
    {
        $this->site();
        $lock = Cache::lock(SyncEngine::LOCK_KEY, 60);
        $lock->get();

        $this->assertSame('already_running', app(SyncEngine::class)->run(force: true)['reason']);
        Http::assertNothingSent();
        $lock->release();

        config(['uptime.uptimerobot.sync_every_minutes' => 5]);
        $this->assertSame('ok', app(SyncEngine::class)->run()['status']);
        $this->travel(2)->minutes();
        $this->assertSame('not_due', app(SyncEngine::class)->run()['reason']);
        $this->travel(3)->minutes();
        $this->assertSame('ok', app(SyncEngine::class)->run()['status']);
        Http::assertSentCount(2);
    }

    public function test_simule_temps_de_reponse_dans_la_limite_de_requetes(): void
    {
        config(['uptime.uptimerobot.response_times_per_run' => 3]);
        $site = $this->site();
        $list = fn (string $remaining) => Http::response(['nextLink' => null, 'data' => [['id' => 500, 'url' => 'https://boutique.exemple.ci', 'status' => 'UP']]], 200, ['X-RateLimit-Remaining' => $remaining]);
        // 1er passage : 9 requêtes restantes ; 2e passage : 2 (= réserve).
        $this->respond($list('9'), $list('2'));
        Http::fake([
            self::API.'/monitors/500/stats/response-time*' => Http::response([
                'summary' => ['min' => 120, 'max' => 480, 'avg' => 240], 'data_points' => 2, 'from' => '', 'to' => '',
                'time_series' => [['timestamp' => '2026-10-31T09:50:00Z', 'value' => 300], ['timestamp' => '2026-10-31T10:00:00Z', 'value' => 212.6]],
            ]),
        ]);

        app(SyncEngine::class)->run(force: true);
        $this->assertSame(213, Check::withoutGlobalScopes()->sole()->response_time_ms);
        $this->assertSame(213, $site->fresh()->last_response_time_ms);

        // Plus que 2 requêtes disponibles (réserve) : aucun appel de temps de réponse.
        $this->travel(5)->minutes();
        app(SyncEngine::class)->run(force: true);

        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), '/stats/response-time')));
    }

    public function test_simule_boutons_manuels_limites_a_l_agence(): void
    {
        $user = $this->userFor();
        $mine = $this->site($user->agency);
        $other = $this->siteFor(Agency::factory()->create(), ['url' => 'https://autre.exemple.ci', 'monitoring_provider' => 'uptime_robot', 'external_monitor_id' => '501']);
        $this->respond($this->list('UP'));

        $this->actingAs($user)->post(route('sites.sync-all'))->assertSessionHas('status');
        $this->assertSame(1, Check::withoutGlobalScopes()->where('site_id', $mine->id)->count());
        $this->assertSame(0, Check::withoutGlobalScopes()->where('site_id', $other->id)->count());

        $this->travel(1)->minutes();
        Http::fake([self::API.'/monitors/500' => Http::response(['id' => 500, 'url' => 'https://boutique.exemple.ci', 'status' => 'UP'])]);
        $this->actingAs($user)->post(route('sites.sync', $mine))->assertSessionHas('status');
        $this->assertSame(2, Check::withoutGlobalScopes()->where('site_id', $mine->id)->count());
    }

    public function test_simule_declencheur_externe_protege_par_jeton(): void
    {
        $this->site();
        $this->postJson(route('sync.trigger'))->assertNotFound();

        $token = str_repeat('a', 40);
        config(['uptime.uptimerobot.trigger_token' => $token]);
        $this->postJson(route('sync.trigger'), [], ['Authorization' => 'Bearer mauvais'])->assertUnauthorized();
        Http::assertNothingSent();

        $this->respond($this->list('UP'));
        $this->postJson(route('sync.trigger'), [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson(['status' => 'ok', 'up' => 1]);
        $this->postJson(route('sync.trigger'), [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJson(['status' => 'skipped', 'reason' => 'not_due']);
    }
}
