<?php

namespace Database\Factories;

use App\Models\Kunde;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Projekt>
 */
class ProjektFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nr' => 'PRJ-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'titel' => 'Terrassenüberdachung '.fake()->lastName(),
            'kunde_id' => Kunde::factory(),
            'status' => 'in_planung',
            'konfiguration' => [
                'width' => fake()->numberBetween(4000, 8000),
                'depth' => fake()->numberBetween(2500, 4500),
                'covering' => 'VSG-Glas',
            ],
        ];
    }
}
