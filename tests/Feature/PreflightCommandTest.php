<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreflightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_outside_production_and_never_prints_secrets(): void
    {
        config([
            'uptime.uptimerobot.api_key' => 'u0000000-cle-secrete-a-ne-pas-afficher',
            'mail.mailers.smtp.password' => 'mot-de-passe-smtp-secret',
        ]);

        $this->artisan('uptime:preflight')
            ->expectsOutputToContain('APP_ENV = production')
            ->expectsOutputToContain('ZITE_UPTIMEROBOT_API_KEY défini')
            ->doesntExpectOutputToContain('cle-secrete')
            ->doesntExpectOutputToContain('mot-de-passe-smtp-secret')
            ->assertFailed();
    }
}
