<?php

namespace Database\Factories;

use App\Enums\AnfrageStatus;
use App\Models\Anfrage;
use App\Models\Kunde;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Anfrage>
 */
class AnfrageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nummer' => 'ANF-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'kunde_id' => Kunde::factory(),
            'status' => AnfrageStatus::Neu,
            'interessierte_produkte' => ['terrassenueberdachung'],
            'breite_cm' => fake()->numberBetween(300, 800),
            'tiefe_cm' => fake()->numberBetween(250, 500),
            'anfrage_quelle' => fake()->randomElement(['website', 'empfehlung', 'telefon', 'google']),
        ];
    }
}
