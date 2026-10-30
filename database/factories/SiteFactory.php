<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Données de TEST uniquement. Les URL utilisent example.com (domaine réservé, RFC 2606).
 *
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => 'Site test',
            'url' => 'https://example.com/'.fake()->unique()->slug(2),
            'interval_minutes' => 5,
            'timeout_seconds' => 10,
        ];
    }
}
