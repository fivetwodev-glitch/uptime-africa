<?php

namespace Tests\Unit;

use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_monitoring_defaults_respect_hostinger_limits(): void
    {
        // Un cycle doit finir avant le cron suivant (60 s).
        $this->assertLessThan(60, config('uptime.monitoring.cycle_budget_seconds'));
        $this->assertGreaterThanOrEqual(1, config('uptime.monitoring.min_interval_minutes'));
        $this->assertGreaterThanOrEqual(2, config('uptime.monitoring.failures_before_incident'));
    }

    public function test_application_is_configured_for_french_and_xof(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('XOF', config('uptime.currency'));
    }
}
