<?php

namespace Tests\Unit;

use App\Support\KonfiguratorRechner;
use App\Support\LedPlan;
use PHPUnit\Framework\TestCase;

class LedPlanTest extends TestCase
{
    public function test_candidates_are_three_per_inner_rafter(): void
    {
        // W=8630 → 13 Sparren → 11 innere → 33 Kandidaten.
        $kalk = KonfiguratorRechner::berechne([]);
        $kandidaten = LedPlan::kandidaten($kalk);

        $this->assertCount(33, $kandidaten);
        $this->assertSame('s1.0', $kandidaten[0]['key']);
        $this->assertSame('s11.2', end($kandidaten)['key']);
    }

    public function test_geometry_kpis(): void
    {
        $kalk = KonfiguratorRechner::berechne([]);
        $z = LedPlan::zeichnung($kalk, []);

        // sparLen = 3500-110 = 3390; pitch 1130; edge 565.
        $this->assertSame('3.390', $z['kpis']['sparLen']);
        $this->assertSame('1.130', $z['kpis']['pitch']);
        $this->assertSame('565', $z['kpis']['edge']);
        $this->assertSame(11, $z['kpis']['frei']);
        $this->assertSame(12, $z['kpis']['total']);
        $this->assertSame([], $z['lampen']);
    }

    public function test_drawing_contains_only_set_lamps_and_their_chains(): void
    {
        $kalk = KonfiguratorRechner::berechne([]);
        $z = LedPlan::zeichnung($kalk, ['s1.0', 's3.1']);

        $this->assertCount(2, $z['lampen']);
        $this->assertSame(2, $z['kpis']['gesetzt']);
        $this->assertFalse($z['kpis']['voll']);
        $this->assertNotEmpty($z['ketten']);
        // Kettentexte enthalten mm-Segmente
        $this->assertStringContainsString('mm', $z['texte'][0]['t']);
    }

    public function test_voll_flag_at_total(): void
    {
        $kalk = KonfiguratorRechner::berechne(['led' => ['total' => 6]]);
        $gesetzt = ['s1.0', 's1.1', 's2.0', 's2.1', 's3.0', 's3.1'];

        $this->assertTrue(LedPlan::zeichnung($kalk, $gesetzt)['kpis']['voll']);
    }
}
