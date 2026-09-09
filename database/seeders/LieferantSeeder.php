<?php

namespace Database\Seeders;

use App\Models\Lieferant;
use App\Models\User;
use Illuminate\Database\Seeder;

class LieferantSeeder extends Seeder
{
    /**
     * Der Prototyp nennt nur die drei Namen; die Kontaktdaten sind
     * plausible Demo-Stammdaten für die Lieferanten-Übersicht.
     */
    public function run(): void
    {
        $lieferanten = [
            'Sunshine' => ['Fr. Behrens', 'bestellung@sunshine-alu.example', '+49 30 4410 220', 'Industriestraße 44', '12459', 'Berlin'],
            'Solarlux' => ['Hr. Thewes', 'order@solarlux.example', '+49 5422 9271 0', 'Industriepark 1', '49324', 'Melle'],
            'Würth' => ['Service-Center', 'service@wuerth.example', '+49 7940 15 0', 'Reinhold-Würth-Straße 12', '74653', 'Künzelsau'],
        ];

        foreach ($lieferanten as $name => [$ansprechpartner, $email, $telefon, $strasse, $plz, $stadt]) {
            Lieferant::query()->updateOrCreate(['name' => $name], [
                'ansprechpartner' => $ansprechpartner,
                'email' => $email,
                'telefon' => $telefon,
                'strasse' => $strasse,
                'plz' => $plz,
                'stadt' => $stadt,
            ]);
        }

        // Portal-Benutzer (UserSeeder) mit seinem Lieferanten verknüpfen.
        User::query()->where('email', 'lieferant@lea.test')->update([
            'lieferant_id' => Lieferant::query()->where('name', 'Sunshine')->value('id'),
        ]);
    }
}
