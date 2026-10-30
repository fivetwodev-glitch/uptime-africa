<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_is_displayed_in_french(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('Tous les sites de vos clients surveillés');
    }

    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
