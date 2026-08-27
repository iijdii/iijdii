<?php

namespace Database\Factories;

use App\Enums\ArtikelKategorie;
use App\Enums\Einheit;
use App\Models\Artikel;
use App\Models\Lieferant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artikel>
 */
class ArtikelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'art_nr' => strtoupper(fake()->unique()->bothify('??-#####')),
            'name' => fake()->words(3, true),
            'kategorie' => fake()->randomElement(ArtikelKategorie::cases()),
            'einheit' => fake()->randomElement(Einheit::cases()),
            'lagerort' => 'H1-A-'.fake()->numberBetween(1, 99),
            'bestand' => fake()->numberBetween(0, 200),
            'min_bestand' => fake()->numberBetween(1, 50),
            'ek_preis' => fake()->randomFloat(2, 1, 400),
            'lieferant_id' => Lieferant::factory(),
        ];
    }
}
