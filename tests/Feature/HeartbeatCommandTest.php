<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HeartbeatCommandTest extends TestCase
{
    public function test_heartbeat_records_last_run(): void
    {
        Http::fake();

        $this->artisan('uptime:heartbeat')->assertSuccessful();

        $this->assertNotNull(Cache::get(config('uptime.heartbeat.cache_key')));
        Http::assertNothingSent();
    }

    public function test_heartbeat_pings_external_url_when_configured(): void
    {
        config(['uptime.heartbeat.url' => 'https://hc.example.com/ping/test']);
        Http::fake(['hc.example.com/*' => Http::response('OK')]);

        $this->artisan('uptime:heartbeat')->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://hc.example.com/ping/test');
    }

    public function test_heartbeat_fails_gracefully_when_ping_fails(): void
    {
        config(['uptime.heartbeat.url' => 'https://hc.example.com/ping/test']);
        Http::fake(['hc.example.com/*' => Http::response('KO', 500)]);

        $this->artisan('uptime:heartbeat')->assertFailed();
    }
}
