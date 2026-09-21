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

        // sparLen = 3500-110 = 3390; ohne Lampen kein Abstand.
        $this->assertSame('3.390', $z['kpis']['sparLen']);
        $this->assertNull($z['kpis']['abstand']);
        $this->assertSame([], $z['abstaende']);
        $this->assertSame(11, $z['kpis']['frei']);
        $this->assertSame(12, $z['kpis']['total']);
        $this->assertSame([], $z['lampen']);
    }

    public function test_abstand_folgt_der_lampenzahl_je_sparren(): void
    {
        // Regel des Betreibers: n Lampen teilen den Sparren in n+1 Stücke —
        // 1 Lampe → L/2, 2 Lampen → L/3 (sparLen 3390).
        $kalk = KonfiguratorRechner::berechne([]);
        $z = LedPlan::zeichnung($kalk, ['s1.0', 's3.0', 's3.2']);

        $this->assertCount(3, $z['lampen']);
        $this->assertSame(
            [['sparren' => 1, 'anzahl' => 1, 'abstand' => '1.695'],
                ['sparren' => 3, 'anzahl' => 2, 'abstand' => '1.130']],
            $z['abstaende'],
        );
        // Gemischte Anzahl → kein gemeinsamer Abstand, keine linke Maßkette
        // mit Teilungstexten (nur die horizontale Sparren-Kette bleibt).
        $this->assertNull($z['kpis']['abstand']);

        // Einheitlich 1 Lampe je Sparren → gemeinsamer Abstand L/2 samt Kette;
        // der Wert steht außerdem direkt am Sparren auf der Zeichnung.
        $z = LedPlan::zeichnung($kalk, ['s1.0', 's3.1']);
        $this->assertSame('1.695', $z['kpis']['abstand']);
        $this->assertNotEmpty($z['ketten']);
        $this->assertSame('1.695', $z['texte'][0]['t']); // Label am Sparren
        $this->assertTrue($z['texte'][0]['led'] ?? false);
        $this->assertContains('1.695 mm', array_column($z['texte'], 't')); // Maßkette
    }

    public function test_voll_flag_at_total(): void
    {
        $kalk = KonfiguratorRechner::berechne(['led' => ['total' => 6]]);
        $gesetzt = ['s1.0', 's1.1', 's2.0', 's2.1', 's3.0', 's3.1'];

        $this->assertTrue(LedPlan::zeichnung($kalk, $gesetzt)['kpis']['voll']);
    }
}
