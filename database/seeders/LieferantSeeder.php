<?php

namespace Database\Seeders;

use App\Models\Lieferant;
use App\Models\User;
use Illuminate\Database\Seeder;

class LieferantSeeder extends Seeder
{
    /**
     * Drei Demo-Lieferanten aus dem Prototyp (Kontaktdaten plausibel,
     * .example-Adressen) plus die realen Lieferanten des Betriebs —
     * Rhein-Main Überdachungen (liefert Profile, Glas und
     * Schiebe-Elemente) und KD Überdachung (Hauptsitz & Produktion).
     * Quellen: rheinmain-ueberdachungen.de bzw. kd-ueberdachung.de.
     */
    public function run(): void
    {
        $lieferanten = [
            'Sunshine' => ['Fr. Behrens', 'bestellung@sunshine-alu.example', '+49 30 4410 220', 'Industriestraße 44', '12459', 'Berlin', 'Aluminium-Systeme'],
            'Solarlux' => ['Hr. Thewes', 'order@solarlux.example', '+49 5422 9271 0', 'Industriepark 1', '49324', 'Melle', 'Glas & Schiebe-Systeme'],
            'Würth' => ['Service-Center', 'service@wuerth.example', '+49 7940 15 0', 'Reinhold-Würth-Straße 12', '74653', 'Künzelsau', 'Befestigung & Kleinteile'],
            'Rhein-Main Überdachungen' => ['Ausstellung Maintal', 'anfrage@rheinmain-ueberdachungen.de', '+49 6181 3009600', 'Lise-Meitner-Str. 21', '63477', 'Maintal', 'Profile · Glas · Schiebe-Elemente'],
            'KD Überdachung GmbH' => ['Hauptsitz & Produktion', 'info@kd-ueberdachung.de', '+49 6142 33060-0', 'Karl-Landsteiner-Ring 1', '65428', 'Rüsselsheim am Main', 'Terrassenüberdachung · Carport · Wintergarten · Sonnenschutz'],
            // Markisen-Lieferant laut Bestellblättern des Betreibers.
            'Rödelbronn GmbH (Varisol)' => ['Bestellannahme', 'bestellung@varisol.de', '+49 2166 96498-0', 'Hanns-Martin-Schleyer-Str. 8', '41199', 'Mönchengladbach', 'Markisen Varisol (T200, F513)', 'D.60986'],
        ];

        foreach ($lieferanten as $name => $daten) {
            [$ansprechpartner, $email, $telefon, $strasse, $plz, $stadt, $sortiment] = $daten;
            Lieferant::query()->updateOrCreate(['name' => $name], [
                'ansprechpartner' => $ansprechpartner,
                'email' => $email,
                'telefon' => $telefon,
                'strasse' => $strasse,
                'plz' => $plz,
                'stadt' => $stadt,
                'sortiment' => $sortiment,
            ] + (isset($daten[7]) ? ['kundennummer' => $daten[7]] : []));
        }

        // Portal-Benutzer (UserSeeder) mit seinem Lieferanten verknüpfen.
        User::query()->where('email', 'lieferant@lea.test')->update([
            'lieferant_id' => Lieferant::query()->where('name', 'Sunshine')->value('id'),
        ]);
    }
}
