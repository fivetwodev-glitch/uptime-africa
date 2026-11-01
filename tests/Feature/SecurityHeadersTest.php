<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_php_version_is_not_disclosed_and_storage_is_not_served(): void
    {
        $this->get('/')->assertHeaderMissing('X-Powered-By');
        $this->get('/storage/app/private/.env')->assertNotFound();
        $this->assertFalse(Route::has('storage.local'));
    }

    public function test_hsts_is_not_sent_outside_production(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_a_request_id_is_generated(): void
    {
        $id = $this->get('/')->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f\-]{36}$/', $id);
    }

    public function test_a_valid_incoming_request_id_is_kept(): void
    {
        $this->get('/', ['X-Request-Id' => 'abc12345-trace'])
            ->assertHeader('X-Request-Id', 'abc12345-trace');
    }

    public function test_a_malicious_incoming_request_id_is_replaced(): void
    {
        $id = $this->get('/', ['X-Request-Id' => '<script>alert(1)</script>'])->headers->get('X-Request-Id');

        $this->assertNotSame('<script>alert(1)</script>', $id);
    }
}
