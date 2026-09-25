<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Bestellformular der Varisol-Markisen (Lieferant Rödelbronn GmbH) nach
 * den Original-Bestellblättern des Betreibers: T200 Terrassendachmarkise
 * und F513 Kubische Senkrechtmarkise mit Zip. Eine Definition für
 * Konfigurator, Montage-Modus (Endmaße), Validierung und Bestell-PDF.
 */
final class MarkisenFormular
{
    public const MODELLE = [
        'T200' => 'Varisol T200 – Terrassendachmarkise',
        'F513' => 'Varisol F513 – Kubische Senkrechtmarkise mit Zip',
    ];

    public const GESTELLFARBEN = [
        'RAL 9016', 'RAL 9001', 'RAL 9007', 'RAL 7016', 'RAL 8022',
        'TG 29/90147', 'TG 29/60740', 'TG 29/80077', 'TG 29/71289', 'TG 29/80303', 'TG 29/90146',
        'Sonderfarbe',
    ];

    /** Hinweise unter den TG-Farbtönen, wie im Bestellblatt. */
    public const FARB_HINWEISE = [
        'TG 29/90147' => 'ca. RAL 9007 FS', 'TG 29/60740' => '= Sepia Brown', 'TG 29/80077' => 'ca. DB 703 FS',
        'TG 29/71289' => '= RAL 7016 FS', 'TG 29/80303' => '= RAL 9005 FS', 'TG 29/90146' => '= RAL 9006 FS',
    ];

    public const KABELABGAENGE = [
        '1' => 'Blende nach unten',
        '2' => 'Wandmontage nach hinten',
        '3' => 'Wandmontage nach oben',
        '4' => 'Deckenmontage nach hinten',
        '5' => 'Deckenmontage nach oben',
        '6' => 'durch Kastenseitenkappe',
        '7' => 'vorne durch Blende',
        '8' => 'durch die Schiene',
    ];

    /**
     * Felder des Bestellblatts: key => [Label, Typ, Optionen, Modelle].
     * Typ: wahl (eine Option), zahl (mm), text, ja (Häkchen).
     * Optionen je Modell unterschiedlich → ['T200' => [...], 'F513' => [...]].
     *
     * @return array<string, array{0: string, 1: string, 2: array, 3: list<string>}>
     */
    public static function felder(): array
    {
        $beide = ['T200', 'F513'];

        return [
            'typ' => ['Ausführung', 'wahl', ['Typ A', 'Typ B (gekoppelt)'], ['T200']],
            'b1_mm' => ['Aufteilung B1 (Typ B)', 'zahl', [], ['T200']],
            'b2_mm' => ['Aufteilung B2 (Typ B)', 'zahl', [], ['T200']],
            'antriebsseite' => ['Antriebsseite (von außen gesehen)', 'wahl', [
                'T200' => ['Links', 'Rechts', 'Links + Links', 'Rechts + Rechts', 'Rechts + Links mittig', 'Links + Rechts außen'],
                'F513' => ['Links', 'Rechts'],
            ], $beide],
            'antrieb' => ['Antrieb', 'wahl', [
                'T200' => ['Kurbel', 'Motor', 'io Funkmotor', 'Funkmotor RTS'],
                'F513' => ['Motor', 'io Funkmotor', 'Funkmotor RTS'],
            ], $beide],
            'kurbellaenge_mm' => ['Kurbellänge (Standard 1.400 mm)', 'zahl', [], ['T200']],
            'handsender' => ['Handsender', 'wahl', ['1-Kanal', '5-Kanal', 'für Soliris Sensor', 'ohne Handsender'], $beide],
            'kabellaenge' => ['Kabellänge', 'wahl', [
                'T200' => ['Standard (1 m)', '3 Meter', '5 Meter', '10 Meter'],
                'F513' => ['Standard (1 m)', '3 Meter', '5 Meter', '10 Meter', 'Hirschmannkupplung (0,5 m)'],
            ], $beide],
            'kabelabgang' => ['Kabelabgang Nr.', 'wahl', array_map('strval', array_keys(self::KABELABGAENGE)), ['F513']],
            'gestellfarbe' => ['Gestellfarbe', 'wahl', self::GESTELLFARBEN, $beide],
            'sonderfarbe' => ['Sonderfarbe', 'text', [], $beide],
            'dessin' => ['Dessin (Tuch)', 'text', [], $beide],
            'vario_volant' => ['Mit Vario-Volant', 'ja', [], ['T200']],
            'vv_ausfall_mm' => ['VV-Ausfall', 'zahl', [], ['T200']],
            'vv_dessin' => ['Vario-Volant Dessin', 'text', [], ['T200']],
            'fuehrungsschienenhalter' => ['Verlängerte Führungsschienenhalter', 'wahl', ['80 mm Gesamthöhe', '130 mm Gesamthöhe'], ['T200']],
            'winkel_wand' => ['Winkel für seitliche Wandbefestigung', 'wahl', ['Motor-/Lagerseite 50×50 mm', 'Getriebeseite 80×60 mm'], ['T200']],
            'befestigung_kasten' => ['Befestigung Kasten', 'wahl', [
                'T200' => ['Wand', 'Decke', 'Ohne'],
                'F513' => ['Wand', 'Decke'],
            ], $beide],
            'deckenkonsolen' => ['Verlängerte Deckenkonsolen', 'wahl', ['+ 50 mm', '+ 100 mm'], ['T200']],
            'montageplatte' => ['Montageplatte (inkl. Führungsschienenhalter)', 'wahl', ['eingerückte Montage 60 mm', 'eingerückte Montage 100 mm'], ['T200']],
            'schalter' => ['Schalter', 'wahl', ['AP 80.30.010', 'UP 80.30.100', 'Ohne'], $beide],
            'windwaechter' => ['Sonne-Windwächter', 'wahl', ['Smoove Uno 80.65.220', 'Soliris Sensor RTS LED 80.65.040', 'Soliris Sensor IO LED 80.65.041'], $beide],
            'sonstiges' => ['Sonstiges', 'text', [], $beide],
        ];
    }

    /** Modell-Schlüssel aus einem gespeicherten Wert (auch Freitext «Varisol T200»). */
    public static function modell(array $werte): string
    {
        return str_contains(strtoupper((string) ($werte['modell'] ?? '')), 'F513') ? 'F513' : 'T200';
    }

    /** Optionen eines Wahl-Feldes für ein Modell. */
    public static function optionen(string $feld, string $modell): array
    {
        $optionen = self::felder()[$feld][2] ?? [];

        return array_is_list($optionen) ? $optionen : ($optionen[$modell] ?? []);
    }

    /**
     * Aktueller Stand eines Formulars: Konfigurator-Felder, darüber die
     * Endmaße (Breite/Ausfall vom Monteur) und das im Montage-Modus
     * gepflegte Formular.
     */
    public static function werte(array $felder, array $endmasse = []): array
    {
        $werte = array_merge($felder, $endmasse['formular'] ?? []);
        foreach (['breite_mm', 'ausfall_mm'] as $mass) {
            if (isset($endmasse[$mass])) {
                $werte[$mass] = $endmasse[$mass];
            }
        }
        $werte['modell'] = self::modell($werte);

        return $werte;
    }

    /** Validierungsregeln für die Konfigurator-Felder (Markise). */
    public static function regeln(): array
    {
        $regeln = [
            'modell' => ['nullable', 'string', 'max:64'],
            'breite_mm' => ['nullable', 'integer', 'min:0'],
            'ausfall_mm' => ['nullable', 'integer', 'min:0'],
            'felder_n' => ['nullable', 'integer', 'min:0'],
            'anzahl' => ['nullable', 'integer', 'min:0'],
        ];
        foreach (self::felder() as $feld => [, $typ, $optionen]) {
            $regeln[$feld] = match ($typ) {
                'wahl' => ['nullable', Rule::in(array_merge(...array_values(array_is_list($optionen) ? [$optionen] : $optionen)))],
                'zahl' => ['nullable', 'integer', 'min:0'],
                'ja' => ['nullable', Rule::in(['0', '1', 0, 1])],
                default => ['nullable', 'string', 'max:160'],
            };
        }

        return $regeln;
    }

    /**
     * Eingabe aus dem Montage-Modus bereinigen: nur bekannte Felder des
     * Modells, Wahlfelder nur mit gültiger Option, Zahlen als int.
     */
    public static function bereinige(array $eingabe, string $modell): array
    {
        $sauber = ['modell' => $modell];
        foreach (self::felder() as $feld => [, $typ, , $modelle]) {
            if (! in_array($modell, $modelle, true) || ! array_key_exists($feld, $eingabe)) {
                continue;
            }
            $wert = is_string($eingabe[$feld]) ? trim($eingabe[$feld]) : $eingabe[$feld];
            if ($wert === '' || $wert === null) {
                continue;
            }
            $sauber[$feld] = match ($typ) {
                'wahl' => in_array((string) $wert, self::optionen($feld, $modell), true) ? (string) $wert : null,
                'zahl' => is_numeric($wert) ? max(0, (int) $wert) : null,
                'ja' => (string) $wert === '1' ? '1' : null,
                default => mb_substr((string) $wert, 0, 160),
            };
        }

        return array_filter($sauber, fn ($w) => $w !== null);
    }
}
