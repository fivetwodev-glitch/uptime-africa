<?php

namespace Tests\Feature;

use App\Exceptions\UptimeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/metier', fn () => throw new UptimeException('Quota de sites atteint.', 422, 'quota exceeded', ['agency' => 'x']));
        Route::get('/_test/crash', fn () => throw new RuntimeException('détail interne secret'));
    }

    public function test_missing_page_shows_french_404(): void
    {
        $this->get('/page-inexistante')
            ->assertNotFound()
            ->assertSee('Page introuvable.');
    }

    public function test_missing_page_returns_json_with_request_id(): void
    {
        $this->getJson('/page-inexistante')
            ->assertNotFound()
            ->assertJsonStructure(['message', 'request_id']);
    }

    public function test_business_exception_shows_safe_message(): void
    {
        $this->get('/_test/metier')
            ->assertStatus(422)
            ->assertSee('Quota de sites atteint.')
            ->assertDontSee('quota exceeded');
    }

    public function test_business_exception_is_logged_as_warning(): void
    {
        Log::spy();

        $this->getJson('/_test/metier')->assertStatus(422)->assertJson(['message' => 'Quota de sites atteint.']);

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'quota exceeded');
    }

    public function test_internal_error_hides_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/_test/crash')
            ->assertStatus(500)
            ->assertJson(['message' => 'Une erreur interne est survenue.'])
            ->assertDontSee('détail interne secret');
    }
}
