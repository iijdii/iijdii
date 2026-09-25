<?php

namespace Tests\Unit;

use App\Support\SeitenwandRechner;
use PHPUnit\Framework\TestCase;

class SeitenwandRechnerTest extends TestCase
{
    public function test_trapez_zuschnitt_mit_fugenabzug(): void
    {
        // Wand 3000 mm, Höhen 2000 → 2420 (Gefälle), 3 Panels, Fuge 30:
        // B = 1000; Ränder −15, Mitte −30; Höhen entlang der Steigung.
        $panels = SeitenwandRechner::panels(3000, 2000, 2420, 3);

        $this->assertCount(3, $panels);
        $this->assertSame([985, 970, 985], array_column($panels, 'breite'));
        $this->assertSame(2000, $panels[0]['hLinks']);
        $this->assertSame(2140, $panels[0]['hRechts']);
        $this->assertSame(2280, $panels[1]['hRechts']);
        $this->assertSame(2420, $panels[2]['hRechts']);
        $this->assertSame(['Trapez', 'Trapez', 'Trapez'], array_column($panels, 'form'));
    }

    public function test_rechteck_bei_gleichen_hoehen_und_einzelpanel(): void
    {
        $panels = SeitenwandRechner::panels(2000, 2200, 2200, 1);
        $this->assertCount(1, $panels);
        $this->assertSame(1970, $panels[0]['breite']); // B − G
        $this->assertSame('Rechteck', $panels[0]['form']);
    }

    public function test_raster_mit_einer_reihe_entspricht_panels(): void
    {
        $raster = SeitenwandRechner::raster(3000, 2000, 2420, 3, 1);
        $this->assertSame([985, 970, 985], array_column($raster, 'breite'));
        $this->assertSame([1, 1, 1], array_column($raster, 'reihe'));
        $this->assertSame([1, 2, 3], array_column($raster, 'spalte'));
        $this->assertSame(2000, $raster[0]['hLinks']); // volle Höhen wie panels()
    }

    public function test_raster_teilt_vertikal_untere_reihen_rechteckig_obere_mit_schraege(): void
    {
        // 1 Spalte × 2 Reihen, Rechteckwand: Stufe 1100, Ränder −15
        $raster = SeitenwandRechner::raster(2000, 2200, 2200, 1, 2);
        $this->assertCount(2, $raster);
        $this->assertSame([1085, 1085], array_column($raster, 'hLinks'));
        $this->assertSame(['Rechteck', 'Rechteck'], array_column($raster, 'form'));

        // Trapezwand 3 Spalten × 2 Reihen: 6 Felder, unten Rechtecke,
        // oben trägt die Reihe die Schräge der jeweiligen Spalte.
        $raster = SeitenwandRechner::raster(3000, 2000, 2420, 3, 2);
        $this->assertCount(6, $raster);
        $spalte1 = array_values(array_filter($raster, fn ($p) => $p['spalte'] === 1));
        $this->assertSame([985, 985], [$spalte1[0]['hLinks'], $spalte1[0]['hRechts']]); // 1000 − 15
        $this->assertSame('Rechteck', $spalte1[0]['form']);
        $this->assertSame([985, 1125], [$spalte1[1]['hLinks'], $spalte1[1]['hRechts']]); // Rest mit Schräge
        $this->assertSame('Trapez', $spalte1[1]['form']);
    }
}
