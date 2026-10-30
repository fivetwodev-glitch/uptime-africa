<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Les URL utilisent le domaine réservé example.com (RFC 2606).
 *
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'agency_id' => fn (array $attributes) => Client::withoutGlobalScopes()->find($attributes['client_id'])->agency_id,
            'name' => 'Site test',
            'url' => 'https://example.com/'.fake()->unique()->slug(2),
            'interval_minutes' => 5,
            'timeout_seconds' => 10,
        ];
    }
}
