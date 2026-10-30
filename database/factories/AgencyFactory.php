<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Données de TEST uniquement (jamais exécutées en production).
 *
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Agence test '.Str::upper(Str::random(6));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'country_code' => 'CI',
            'timezone' => 'Africa/Abidjan',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
