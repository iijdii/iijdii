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
        $zeile = function (string $key, string $titel, array $details, int $menge, int|float|null $listenpreis = null) use ($preise, $rabatte): array {
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
            $p = $kalk['pcfg'];
            // Das Dach trägt nur seine eigenen Bauteile (Pfosten, Sparren,
            // Dachfelder) als Beschreibung; Extras aus Alt-Konfigurationen
            // (Keile, Festelemente, Schiebe, Markisen, Segel) werden je als
            // eigene Position geführt — wie die Element-Positionen unten.
            $dachDetails = array_map(
                fn (array $z) => $z['name'].((int) $z['menge'] > 1 ? ' — '.$z['menge'].' Stück' : ''),
                array_slice($kalk['positionen'], 1, 3),
            );
            $positionen[] = $zeile(
                'dach',
                $kalk['positionen'][0]['name'] ?? 'Überdachung',
                $dachDetails,
                1,
                Preisliste::ausKonfiguration($konfiguration),
            );

            // Zuschläge zum Standard der Preisliste — jeweils eigene Position:
            // freistehend → Unterzug + zusätzliche Pfosten (hintere Reihe),
            // Pfostenhalter (Konsole) → je Stück, Milchglas → Aufpreis
            // (Polycarbonat ausgenommen).
            $freistehend = ($p['mounting'] ?? '') === 'freistehend';
            if ($freistehend) {
                $positionen[] = $zeile('zpfosten', 'Zusätzliche Pfosten (freistehend)', [], (int) $kalk['pn']);
            }
            if ($freistehend || ($kalk['unterzug']['gewaehlt'] ?? false)) {
                $positionen[] = $zeile(
                    'unterzug',
                    'Unterzug '.($kalk['unterzug']['groesse'] ?? '110×190').' mm',
                    [],
                    max(1, (int) ($kalk['unterzug']['anzahl'] ?? 1)),
                );
            }
            $istKonsole = fn ($montage) => str_starts_with((string) $montage, 'Pfostenhalter');
            $proPfosten = ($p['postMontageJe'] ?? '') == 1
                ? array_filter((array) ($p['postMontageListe'] ?? []), $istKonsole)
                : null;
            $konsolen = $proPfosten !== null
                ? count($proPfosten)
                : ($istKonsole($p['postMontage'] ?? '') ? (int) $kalk['pn'] * ($freistehend ? 2 : 1) : 0);
            if ($konsolen > 0) {
                $positionen[] = $zeile('konsolen', 'Montage auf Pfostenhalter (Konsole)', [], $konsolen);
            }
            $milchglas = ! str_starts_with((string) $p['covering'], 'Polycarbonat')
                && (($p['glasTrans'] ?? '') === 'Milch' || str_contains((string) $p['covering'], 'mattiert'));
            if ($milchglas) {
                $positionen[] = $zeile('milchglas', 'Aufpreis Milchglas (VSG matt)', [], (int) $kalk['fields']);
            }

            foreach (array_slice($kalk['positionen'], 4) as $index => $extra) {
                $positionen[] = $zeile('k'.$index, $extra['name'], [], (int) $extra['menge']);
            }
        }

        foreach ($angebot->projekt?->positionen ?? [] as $position) {
            if ($position->produkt->istDach()) {
                continue;
            }
            $f = $position->felder ?? [];
            // Keil führt zwei Höhen (hinten/vorn) — beide in den Titel.
            $hoehe = $f['hoehe_mm'] ?? $f['ausfall_mm'] ?? $f['h_links_mm'] ?? null;
            if ($hoehe === null && (isset($f['h_hinten_mm']) || isset($f['h_vorn_mm']))) {
                $hoehe = implode('/', array_filter([$f['h_hinten_mm'] ?? null, $f['h_vorn_mm'] ?? null]));
            }
            $masse = array_filter([
                $f['breite_mm'] ?? $f['laenge_mm'] ?? null,
                $hoehe,
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

        // Freie Positionen des Verkäufers: Preis/Rabatt liegen in der
        // Position selbst, ein Override in preise/rabatte gewinnt.
        foreach ($angebot->freie_positionen ?? [] as $frei) {
            $key = 'f'.($frei['id'] ?? '');
            $positionen[] = $zeile(
                $key,
                (string) ($frei['titel'] ?? 'Position'),
                [],
                (int) ($frei['menge'] ?? 1),
                isset($frei['preis']) && $frei['preis'] !== '' && ! isset($preise[$key])
                    ? round((float) $frei['preis'], 2)
                    : null,
            );
            if (! isset($rabatte[$key]) && (float) ($frei['rabatt'] ?? 0) > 0) {
                $letzte = array_key_last($positionen);
                $rabatt = min(100.0, max(0.0, (float) $frei['rabatt']));
                $positionen[$letzte]['rabatt'] = $rabatt;
                if ($positionen[$letzte]['einzelpreis'] !== null) {
                    $positionen[$letzte]['gesamt'] = round(
                        $positionen[$letzte]['einzelpreis'] * $positionen[$letzte]['menge'] * (1 - $rabatt / 100), 2,
                    );
                }
            }
        }

        // Vom Verkäufer entfernte (ausgeblendete) generierte Positionen
        $ausgeblendet = $angebot->ausgeblendet ?? [];
        $positionen = array_values(array_filter(
            $positionen,
            fn (array $zeileDaten) => ! in_array($zeileDaten['key'], $ausgeblendet, true),
        ));

        foreach ($positionen as $i => $zeileDaten) {
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
