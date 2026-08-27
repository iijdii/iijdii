<?php

namespace Database\Factories;

use App\Models\Lieferant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lieferant>
 */
class LieferantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'ansprechpartner' => fake()->name(),
            'email' => fake()->companyEmail(),
            'telefon' => fake()->phoneNumber(),
            'stadt' => fake()->city(),
        ];
    }
}
