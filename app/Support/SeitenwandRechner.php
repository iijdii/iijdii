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
}
