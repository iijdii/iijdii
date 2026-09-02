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
            // Ab 500, damit keine Kollision mit den geseedeten BST-2026-107…112
            'nr' => 'BST-2026-'.fake()->unique()->numberBetween(500, 999),
            'lieferant_id' => Lieferant::factory(),
            'titel' => fake()->words(3, true),
            'status' => BestellungStatus::Entwurf,
        ];
    }
}
