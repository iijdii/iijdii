<?php

namespace Database\Factories;

use App\Enums\BestellungStatus;
use App\Models\Bestellung;
use App\Models\Lieferant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bestellung>
 */
class BestellungFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nr' => 'BST-2026-'.fake()->unique()->numberBetween(100, 999),
            'lieferant_id' => Lieferant::factory(),
            'titel' => fake()->words(3, true),
            'status' => BestellungStatus::Entwurf,
        ];
    }
}
