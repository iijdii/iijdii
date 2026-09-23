<?php

namespace App\Support;

use App\Models\Angebot;

/**
 * Angebotskalkulation (Spez. v3.2 «Angebot build logic»): die Positionen
 * sind die Quelle der Wahrheit, Summen werden daraus bei jedem Rendern
 * neu berechnet. Positionen: das Dach als eine Vertragsposition
 * (Einzelpreis = Preisliste, vom Verkäufer überschreibbar; die
 * Rechenkern-Zeilen erscheinen als Beschreibung), je Element-Position des
 * Projekts eine Zeile mit manuellem Preis sowie eine optionale
 * Montage-Zeile. Alle Preise brutto (Endkundenpreise der Website);
 * Rabatt wirkt global, die enthaltene MwSt. wird ausgewiesen.
 */
final class AngebotsRechnung
{
    public const MWST = 19.0;

    /**
     * @return array{
     *     positionen: list<array{key: string, pos: int, titel: string, details: list<string>,
     *         menge: int, einheit: string, listenpreis: int|null, einzelpreis: float|null, gesamt: float|null}>,
     *     zwischensumme: float, rabattProzent: float, rabattBetrag: float,
     *     gesamt: float, mwst: float, netto: float
     * }
     */
    public static function fuer(Angebot $angebot): array
    {
        $preise = $angebot->preise ?? [];
        $rabatte = $angebot->rabatte ?? [];
        // Das Projekt ist die Quelle (Einheitssystem); die Kopie auf dem
        // Angebot trägt nur projektlose Alt-Angebote.
        $konfiguration = $angebot->projekt?->konfiguration ?? $angebot->konfiguration;

        $positionen = [];
        $zeile = function (string $key, string $titel, array $details, int $menge, ?int $listenpreis = null) use ($preise, $rabatte): array {
            $einzelpreis = isset($preise[$key]) && $preise[$key] !== ''
                ? round((float) $preise[$key], 2)
                : ($listenpreis !== null ? (float) $listenpreis : null);
            $rabatt = isset($rabatte[$key]) ? min(100.0, max(0.0, (float) $rabatte[$key])) : 0.0;

            return [
                'key' => $key,
                'pos' => 0,
                'titel' => $titel,
                'details' => $details,
                'menge' => max(1, $menge),
                'einheit' => 'Stk.',
                'listenpreis' => $listenpreis,
                'einzelpreis' => $einzelpreis,
                'rabatt' => $rabatt,
                'gesamt' => $einzelpreis !== null
                    ? round($einzelpreis * max(1, $menge) * (1 - $rabatt / 100), 2)
                    : null,
            ];
        };

        if ($konfiguration !== null) {
            $kalk = KonfiguratorRechner::berechne($konfiguration);
            // Das Dach trägt nur seine eigenen Bauteile (Pfosten, Sparren,
            // Dachfelder) als Beschreibung; Extras aus Alt-Konfigurationen
            // (Keile, Festelemente, Schiebe, Markisen, Segel) werden je als
            // eigene Position geführt — wie die Element-Positionen unten.
            $dachDetails = array_map(
                fn (array $p) => $p['name'].((int) $p['menge'] > 1 ? ' — '.$p['menge'].' Stück' : ''),
                array_slice($kalk['positionen'], 1, 3),
            );
            $positionen[] = $zeile(
                'dach',
                $kalk['positionen'][0]['name'] ?? 'Überdachung',
                $dachDetails,
                1,
                Preisliste::ausKonfiguration($konfiguration),
            );
            foreach (array_slice($kalk['positionen'], 4) as $index => $extra) {
                $positionen[] = $zeile('k'.$index, $extra['name'], [], (int) $extra['menge']);
            }
        }

        foreach ($angebot->projekt?->positionen ?? [] as $position) {
            if ($position->produkt->istDach()) {
                continue;
            }
            $f = $position->felder ?? [];
            $masse = array_filter([
                $f['breite_mm'] ?? $f['laenge_mm'] ?? null,
                $f['hoehe_mm'] ?? $f['ausfall_mm'] ?? $f['h_links_mm'] ?? null,
            ]);
            $positionen[] = $zeile(
                'p'.$position->id,
                $position->produkt->label()
                    .($masse !== [] ? ' '.implode('×', $masse).' mm' : '')
                    .(isset($f['glas']) ? ' · '.$f['glas'] : '')
                    .(isset($f['groesse']) ? ' '.$f['groesse'] : ''),
                [],
                (int) ($f['anzahl'] ?? 1),
            );
        }

        $positionen[] = $zeile('montage', 'Montage & Lieferung', [], 1);

        foreach ($positionen as $i => $p) {
            $positionen[$i]['pos'] = $i + 1;
        }

        $zwischensumme = round(array_sum(array_map(fn (array $p) => $p['gesamt'] ?? 0.0, $positionen)), 2);
        $rabattProzent = (float) ($angebot->rabatt_prozent ?? 0);
        $rabattBetrag = round($zwischensumme * $rabattProzent / 100, 2);
        $gesamt = round($zwischensumme - $rabattBetrag, 2);
        $mwst = round($gesamt * self::MWST / (100 + self::MWST), 2);

        return [
            'positionen' => $positionen,
            'zwischensumme' => $zwischensumme,
            'rabattProzent' => $rabattProzent,
            'rabattBetrag' => $rabattBetrag,
            'gesamt' => $gesamt,
            'mwst' => $mwst,
            'netto' => round($gesamt - $mwst, 2),
        ];
    }

    /**
     * Schreibt die aus den Positionen berechnete Bruttosumme in summe —
     * nur wenn tatsächlich etwas bepreist ist, damit ein Dach außerhalb
     * der Preisliste eine manuell erfasste Summe nicht überschreibt.
     */
    public static function aktualisiereSumme(Angebot $angebot): void
    {
        $gesamt = self::fuer($angebot)['gesamt'];
        if ($gesamt > 0) {
            $angebot->forceFill(['summe' => $gesamt])->saveQuietly();
        }
    }
}
