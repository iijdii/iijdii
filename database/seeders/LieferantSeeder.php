<?php

namespace Database\Seeders;

use App\Models\Lieferant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LieferantSeeder extends Seeder
{
    /**
     * Drei Demo-Lieferanten aus dem Prototyp (Kontaktdaten plausibel,
     * .example-Adressen) plus die realen Lieferanten des Betriebs —
     * Rhein-Main Überdachungen (liefert Profile, Glas und
     * Schiebe-Elemente) und KD Überdachung (Hauptsitz & Produktion).
     * Quellen: rheinmain-ueberdachungen.de bzw. kd-ueberdachung.de.
     *
     * Läuft bei jedem /einrichtung erneut: legt nur fehlende Lieferanten
     * an, überschreibt keine in der CRM gepflegten Daten und holt keine
     * dort gelöschten zurück (Setting «lieferanten_entfernt»).
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

        $entfernt = Schema::hasTable('settings') ? (array) Setting::wert('lieferanten_entfernt', []) : [];

        foreach ($lieferanten as $name => $daten) {
            if (in_array($name, $entfernt, true)) {
                continue;
            }
            [$ansprechpartner, $email, $telefon, $strasse, $plz, $stadt, $sortiment] = $daten;
            $lieferant = Lieferant::query()->firstOrCreate(['name' => $name], [
                'ansprechpartner' => $ansprechpartner,
                'email' => $email,
                'telefon' => $telefon,
                'strasse' => $strasse,
                'plz' => $plz,
                'stadt' => $stadt,
                'sortiment' => $sortiment,
            ]);
            if (isset($daten[7]) && blank($lieferant->kundennummer)) {
                $lieferant->update(['kundennummer' => $daten[7]]);
            }
        }

        // Portal-Benutzer (UserSeeder) mit seinem Lieferanten verknüpfen,
        // sofern in der Benutzerverwaltung noch keiner zugeordnet ist.
        User::query()->where('email', 'lieferant@lea.test')->whereNull('lieferant_id')->update([
            'lieferant_id' => Lieferant::query()->where('name', 'Sunshine')->value('id'),
        ]);
    }
}
