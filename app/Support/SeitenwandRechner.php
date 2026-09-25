<?php

namespace App\Support;

/**
 * Trapez-Zuschnitt einer Seitenwand-Verglasung (v3.2,
 * sidewall_glass_calculation): Panelbreiten mit Fugenabzug (Ränder G/2,
 * Mitte G), Höhen links/rechts je Panel entlang des Gefälles.
 * Grundlage der Endmaße-Nachbestellung (Phase 2).
 */
final class SeitenwandRechner
{
    /**
     * @param  int  $breite  Gesamtbreite der Wand (mm)
     * @param  int  $hLinks  Höhe am linken Rand (mm)
     * @param  int  $hRechts  Höhe am rechten Rand (mm)
     * @param  int  $anzahl  Panelanzahl
     * @param  int  $fuge  Fugenabzug G (mm)
     * @return list<array{nr: int, breite: int, hLinks: int, hRechts: int, form: string}>
     */
    public static function panels(int $breite, int $hLinks, int $hRechts, int $anzahl, int $fuge = 30): array
    {
        $anzahl = max(1, $anzahl);
        $b = (int) round($breite / $anzahl);
        $k = $breite > 0 ? ($hRechts - $hLinks) / $breite : 0;

        $panels = [];
        for ($i = 1; $i <= $anzahl; $i++) {
            $panelBreite = $anzahl === 1
                ? $b - $fuge
                : (($i === 1 || $i === $anzahl) ? $b - intdiv($fuge, 2) : $b - $fuge);
            $hL = (int) round($hLinks + ($i - 1) * $b * $k);
            $hR = (int) round($hLinks + $i * $b * $k);
            $panels[] = [
                'nr' => $i,
                'breite' => max(0, $panelBreite),
                'hLinks' => $hL,
                'hRechts' => $hR,
                'form' => abs($hL - $hR) > 2 ? 'Trapez' : 'Rechteck',
            ];
        }

        return $panels;
    }

    /**
     * Raster-Zuschnitt: Spalten nebeneinander (wie panels()) UND Reihen
     * übereinander. Die Schnittlinien der Reihen laufen waagerecht —
     * untere Reihen sind Rechtecke, die oberste Reihe trägt die Schräge.
     * Reihenhöhe = min(hLinks, hRechts) der Spalte geteilt durch die
     * Reihenzahl; Fugenabzug in der Höhe wie in der Breite (Ränder G/2,
     * innen G). Mit reihen = 1 bleibt das Ergebnis von panels() erhalten
     * (volle Höhen, nur um spalte/reihe ergänzt).
     *
     * @return list<array{nr: int, spalte: int, reihe: int, breite: int, hLinks: int, hRechts: int, form: string}>
     */
    public static function raster(int $breite, int $hLinks, int $hRechts, int $spalten, int $reihen, int $fuge = 30): array
    {
        $reihen = max(1, $reihen);
        $ergebnis = [];
        $nr = 0;

        foreach (self::panels($breite, $hLinks, $hRechts, $spalten, $fuge) as $spalte) {
            if ($reihen === 1) {
                $ergebnis[] = ['nr' => ++$nr, 'spalte' => $spalte['nr'], 'reihe' => 1] + $spalte;

                continue;
            }

            $stufe = intdiv(min($spalte['hLinks'], $spalte['hRechts']), $reihen);
            for ($r = 1; $r <= $reihen; $r++) {
                if ($r < $reihen) {
                    $hl = $hr = $stufe;
                } else {
                    $unten = ($reihen - 1) * $stufe;
                    $hl = $spalte['hLinks'] - $unten;
                    $hr = $spalte['hRechts'] - $unten;
                }
                $abzug = ($r === 1 || $r === $reihen) ? intdiv($fuge, 2) : $fuge;
                $hl = max(0, $hl - $abzug);
                $hr = max(0, $hr - $abzug);
                $ergebnis[] = [
                    'nr' => ++$nr,
                    'spalte' => $spalte['nr'],
                    'reihe' => $r,
                    'breite' => $spalte['breite'],
                    'hLinks' => $hl,
                    'hRechts' => $hr,
                    'form' => abs($hl - $hr) > 2 ? 'Trapez' : 'Rechteck',
                ];
            }
        }

        return $ergebnis;
    }
}
