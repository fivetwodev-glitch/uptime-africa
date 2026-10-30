<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => 'Client test '.fake()->unique()->numberBetween(1, 99999),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
