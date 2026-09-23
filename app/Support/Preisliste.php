<?php

namespace App\Support;

/**
 * Listenpreise für Überdachungen (Quelle: lea-ueberdachung.de,
 * «Preise für Überdachungen und Carports», Stand 09/2026). Zwei
 * Matrizen — 16 mm Poly-Eindeckung und 8 mm VSG-Eindeckung — über
 * Breite 2,0–10,0 m (Raster 1 m) × Tiefe 2,0–6,0 m (Raster 0,5 m).
 * Zwischenmaße werden auf den nächsten Rasterwert AUFgerundet; alle
 * Preise brutto und zuzüglich Montagekosten. Außerhalb des Rasters
 * (Breite > 10 m, Tiefe > 6 m) gibt es keinen Listenpreis (null).
 */
final class Preisliste
{
    /** @var list<int> Raster der Breiten in mm (Tabellenspalten) */
    public const BREITEN = [2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000];

    /** @var list<int> Raster der Tiefen in mm (Tabellenzeilen) */
    public const TIEFEN = [2000, 2500, 3000, 3500, 4000, 4500, 5000, 5500, 6000];

    /** @var list<list<int>> Preise € je Tiefe (Zeile) × Breite (Spalte) — 16 mm Poly */
    private const POLY = [
        [3840, 4080, 4200, 4320, 4560, 5040, 5400, 5880, 6360],
        [3960, 4200, 4440, 4800, 5160, 5640, 6000, 6480, 6960],
        [4080, 4320, 4560, 5280, 5520, 6000, 6360, 6840, 7320],
        [4440, 4560, 4680, 5400, 5880, 6360, 6720, 7200, 7680],
        [4560, 4800, 4920, 5760, 6120, 6600, 6960, 7440, 7920],
        [4680, 4920, 5040, 5880, 6240, 6720, 7080, 7560, 8040],
        [4800, 5040, 5160, 6000, 6360, 6840, 7200, 7680, 8160],
        [4920, 5160, 5280, 6120, 6480, 6960, 7320, 7800, 8280],
        [5040, 5280, 5400, 6240, 6600, 7080, 7440, 7920, 8400],
    ];

    /** @var list<list<int>> Preise € je Tiefe (Zeile) × Breite (Spalte) — 8 mm VSG */
    private const VSG = [
        [4670, 4910, 5270, 5750, 5990, 6350, 6710, 7190, 7670],
        [4790, 5030, 5510, 6350, 6590, 6830, 7190, 7670, 8150],
        [5030, 5390, 5750, 6710, 7070, 7550, 7910, 8390, 8870],
        [5270, 5510, 5990, 6950, 7310, 7790, 8150, 8630, 9110],
        [5510, 5870, 6230, 7190, 7670, 8030, 8390, 8870, 9350],
        [5750, 6110, 6470, 7430, 7910, 8270, 8630, 9110, 9590],
        [5990, 6350, 6710, 7670, 8150, 8510, 8870, 9350, 9830],
        [6230, 6590, 6950, 7910, 8390, 8750, 9110, 9590, 10070],
        [6470, 6830, 7190, 8150, 8630, 8990, 9350, 9830, 10310],
    ];

    /**
     * Listenpreis in € für ein Dach: Deckung (Polycarbonat* → Poly-Matrix,
     * alles andere → VSG-Matrix, wie KonfiguratorRechner), Maße in mm auf
     * das nächste Raster aufgerundet. null, wenn außerhalb der Preisliste.
     */
    public static function dachPreis(string $covering, int $breite, int $tiefe): ?int
    {
        if ($breite <= 0 || $tiefe <= 0) {
            return null;
        }

        $spalte = self::rasterIndex(self::BREITEN, $breite);
        $zeile = self::rasterIndex(self::TIEFEN, $tiefe);
        if ($spalte === null || $zeile === null) {
            return null;
        }

        $tabelle = str_starts_with($covering, 'Polycarbonat') ? self::POLY : self::VSG;

        return $tabelle[$zeile][$spalte];
    }

    /** Listenpreis direkt aus einer (Projekt-)Konfiguration im pcfg-Format. */
    public static function ausKonfiguration(?array $konfiguration): ?int
    {
        $p = KonfiguratorRechner::merge($konfiguration ?? []);

        return self::dachPreis((string) $p['covering'], (int) $p['width'], (int) $p['depth']);
    }

    /** @param list<int> $raster */
    private static function rasterIndex(array $raster, int $mass): ?int
    {
        foreach ($raster as $index => $wert) {
            if ($mass <= $wert) {
                return $index;
            }
        }

        return null;
    }
}
