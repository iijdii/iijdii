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
}
