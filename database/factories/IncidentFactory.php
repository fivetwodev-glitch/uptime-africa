<?php

namespace Database\Factories;

use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Données de TEST uniquement.
 *
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'type' => IncidentType::Downtime,
            'started_at' => now()->subMinutes(10),
            'confirmed_failures' => 2,
        ];
    }
}
