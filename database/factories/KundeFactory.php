<?php

namespace Database\Factories;

use App\Models\Kunde;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kunde>
 */
class KundeFactory extends Factory
{
    public function definition(): array
    {
        $vorname = fake()->firstName();
        $nachname = fake()->lastName();

        return [
            'kunden_nr' => 'K-'.fake()->unique()->numberBetween(1000, 9999),
            'typ' => 'privat',
            'anzeigename' => $vorname.' '.$nachname,
            'vorname' => $vorname,
            'nachname' => $nachname,
            'email' => fake()->safeEmail(),
            'telefon' => fake()->phoneNumber(),
            'strasse' => fake()->streetName(),
            'hausnummer' => (string) fake()->buildingNumber(),
            'plz' => fake()->postcode(),
            'stadt' => fake()->city(),
            'status' => 'Aktiv',
        ];
    }

    public function gewerbe(): static
    {
        return $this->state(function () {
            $firma = fake()->company();

            return [
                'typ' => 'gewerbe',
                'anzeigename' => $firma,
                'firma' => $firma,
                'ansprechpartner' => fake()->name(),
            ];
        });
    }
}
