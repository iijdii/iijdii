<?php

namespace Tests\Unit;

use App\Support\KonfiguratorRechner;
use PHPUnit\Framework\TestCase;

class KonfiguratorRechnerTest extends TestCase
{
    public function test_reference_numbers_follow_the_glass_rules(): void
    {
        // Demo-Projekt W=8630, D=3500. Glasregeln des Betreibers:
        // max. 750 mm Glasbreite, Breite = Achsmaß − 22, Tiefe = T − 60.
        $e = KonfiguratorRechner::berechne([]);

        $this->assertSame(4, $e['rec']);       // ceil(8630/4000)+1
        $this->assertSame(4, $e['pn']);
        $this->assertSame(12, $e['fields']);   // ceil(8630/772) — Minimum unter dem Limit
        $this->assertSame(13, $e['rafters']);  // fields+1
        $this->assertSame(719, $e['spar']);    // round(8630/12)
        $this->assertSame(697, $e['glasB']);   // spar−22, ≤ 750
        $this->assertSame(3440, $e['glasT']);  // 3500−60
        $this->assertSame('697 × 3.440 mm', $e['glasText']);
        $this->assertFalse($e['glasZuBreit']);
        $this->assertSame('659 mm', $e['blendeText']); // spar−60, de-DE
        $this->assertSame(12, $e['ledTot']);
        $this->assertFalse($e['warnung']);
    }

    public function test_manual_field_count_overrides_and_warns_beyond_the_limit(): void
    {
        // Manuell weniger Felder: Rechner folgt der Vorgabe …
        $e = KonfiguratorRechner::berechne(['fieldN' => 8]);
        $this->assertSame(8, $e['fields']);
        $this->assertSame(9, $e['rafters']);
        $this->assertSame(1079, $e['spar']);   // round(8630/8)
        $this->assertSame(1057, $e['glasB']);
        $this->assertTrue($e['glasZuBreit']);  // … warnt aber über 750 mm.
        $this->assertSame(12, $e['autoFields']);

        // Leer/0 → wieder automatisch.
        $this->assertSame(12, KonfiguratorRechner::berechne(['fieldN' => ''])['fields']);
        $this->assertSame(12, KonfiguratorRechner::berechne(['fieldN' => 0])['fields']);
    }

    public function test_post_override_and_led_six(): void
    {
        $e = KonfiguratorRechner::berechne(['postN' => 6, 'led' => ['total' => 6]]);

        $this->assertSame(6, $e['pn']);
        $this->assertSame(4, $e['rec']);
        $this->assertSame(6, $e['ledTot']);

        // Alles außer exakt 6 wird 12 (Prototyp-Regel).
        $this->assertSame(12, KonfiguratorRechner::berechne(['led' => ['total' => 8]])['ledTot']);
    }

    public function test_depth_warning(): void
    {
        $this->assertTrue(KonfiguratorRechner::berechne(['depth' => 4001])['warnung']);
        $this->assertFalse(KonfiguratorRechner::berechne(['depth' => 4000])['warnung']);
    }

    public function test_position_list_matches_prototype_strings(): void
    {
        $e = KonfiguratorRechner::berechne([
            'shape' => 'trapez',
            'extras' => ['Keile', 'Festelemente', 'Schiebe-Elemente', 'Markisen', 'Sonnensegel'],
        ]);

        $namen = array_column($e['positionen'], 'name');
        $mengen = array_column($e['positionen'], 'menge');

        $this->assertSame([
            'Überdachung Trapez 8630×3500 mm',
            'Pfosten 110×110 · Weiß · RAL 9016',
            'Dachsparren 80×60 mm',
            'Dachfeld VSG-Glas 8 mm',
            'Keil Links · Glas (Klar)',
            'Festfeld 1000×2000 · VSG-Glas',
            'Schiebeanlage 4000×2200 · 3 Elem.',
            'Varisol T200 B5000 · Ausf. 3000',
            'Sonnensegel 4×4 m · Sandbeige',
        ], $namen);

        $this->assertSame([1, 4, 13, 12, 1, 1, 1, 2, 1], $mengen);
        $this->assertSame(range(1, 9), array_column($e['positionen'], 'pos'));
    }

    public function test_extras_replace_instead_of_merge(): void
    {
        $e = KonfiguratorRechner::berechne(['extras' => []]);

        $this->assertCount(4, $e['positionen']); // nur Basispositionen
    }

    public function test_zero_width_degrades_gracefully(): void
    {
        $e = KonfiguratorRechner::berechne(['width' => 0, 'extras' => []]);

        $this->assertSame(0, $e['rec']);
        $this->assertSame(0, $e['rafters']);
        $this->assertSame(0, $e['fields']);
        $this->assertSame('–', $e['sparText']);
    }
}
