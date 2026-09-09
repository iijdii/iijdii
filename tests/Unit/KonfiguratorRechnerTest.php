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
        $this->assertSame(714, $e['spar']);    // round((8630−60)/12)
        $this->assertSame(692, $e['glasB']);   // spar−22, ≤ 750
        $this->assertSame(3450, $e['glasT']);  // 3500−50 (KD)
        $this->assertSame('692 × 3.450 mm', $e['glasText']);
        $this->assertFalse($e['glasZuBreit']);
        $this->assertSame('654 mm', $e['blendeText']); // spar−60, de-DE
        $this->assertSame(12, $e['ledTot']);
        $this->assertFalse($e['warnung']);
    }

    public function test_polycarbonat_zielt_auf_die_980er_stegplatte(): void
    {
        // KD Kap. 6: Stegplatte 980 mm → Achsmaß 1002; Länge = T − 50.
        $e = KonfiguratorRechner::berechne([
            'covering' => 'Polycarbonat klar', 'width' => 6060, 'depth' => 3050,
        ]);
        $this->assertSame(6, $e['autoFields']);   // 6 volle 1000er-Felder, kein Rest
        $this->assertSame(1000, $e['spar']);
        $this->assertSame(980, $e['glasB']);      // ungeschnittene Stegplatte
        $this->assertSame(3000, $e['glasT']);
        $this->assertNull($e['polyRestPlatte']);
        $this->assertFalse($e['glasZuBreit']);
        $this->assertSame(980, $e['maxPlatte']);

        // Mit Rest: volle Platten + ein Zuschnitt-Feld (Rest − 20).
        $e = KonfiguratorRechner::berechne([
            'covering' => 'Polycarbonat klar', 'width' => 6560, 'depth' => 3050,
        ]);
        $this->assertSame(7, $e['fields']);       // 6 volle + Restfeld 500
        $this->assertSame(980, $e['glasB']);
        $this->assertSame(480, $e['polyRestPlatte']);
        $this->assertStringContainsString('Restfeld 480 mm', $e['glasText']);

        // Glas bleibt bei der 750er-Grenze.
        $this->assertSame(750, KonfiguratorRechner::berechne(['covering' => 'VSG-Glas'])['maxPlatte']);
    }

    public function test_hoehen_und_neigung_verrechnen_sich_gegenseitig(): void
    {
        // Beide Höhen gesetzt → Winkel aus atan (Default 2990/2500 bei T=3500 ≈ 8°).
        $e = KonfiguratorRechner::berechne([]);
        $this->assertSame(8.0, $e['slopeEff']);
        $this->assertSame(14.1, $e['gefaelleProzent']); // KD: 8° ≈ 14 %

        // Nur Rinnenhöhe → Wandhöhe folgt aus der Neigung (KD-Beispiel S. 10:
        // 2000 + 3 m · 14 % ≈ 2420).
        $e = KonfiguratorRechner::berechne([
            'wallH' => 0, 'gutterH' => 2000, 'slope' => 8, 'depth' => 3000,
        ]);
        $this->assertSame(2422, $e['wallHEff']); // tan(8°) = 14,05 %
        $this->assertSame(2000, $e['gutterHEff']);
    }

    public function test_unterzug_pflicht_und_position(): void
    {
        // Standard-Wandmontage, T=3500: kein Unterzug nötig.
        $this->assertFalse(KonfiguratorRechner::berechne([])['unterzug']['erforderlich']);

        // Freistehend → Pflicht.
        $u = KonfiguratorRechner::berechne(['mounting' => 'freistehend'])['unterzug'];
        $this->assertTrue($u['erforderlich']);
        $this->assertContains('freistehende Konstruktion', $u['gruende']);

        // Pfostenlinie vor der Traufe: Position + Überstand.
        $u = KonfiguratorRechner::berechne(['depth' => 3500, 'terraceDepth' => 3000])['unterzug'];
        $this->assertTrue($u['erforderlich']);
        $this->assertSame(3000, $u['position']);
        $this->assertSame(500, $u['ueberstand']);

        // Tiefe über 4000 → Pflicht.
        $this->assertTrue(KonfiguratorRechner::berechne(['depth' => 4200])['unterzug']['erforderlich']);
    }

    public function test_pfosten_positionen_segmente_und_stoss(): void
    {
        // Demo 8630, pn=4: gleichmäßig verteilt; Rinne > 7000 → 2 Segmente,
        // der Stoß (7000 − 55) hat keinen Pfosten in ±100 → Empfehlung.
        $e = KonfiguratorRechner::berechne([]);
        $this->assertSame([0, 2877, 5753, 8630], $e['postPositionen']);
        $this->assertSame([7000, 1630], $e['profilSegmente']);
        $this->assertSame([6945], $e['stossPfosten']);
        $this->assertFalse($e['spannZuGross']);

        // Randabstände und Mittelpfosten
        $e = KonfiguratorRechner::berechne([
            'width' => 6000, 'postN' => 3,
            'postLeftOffset' => 300, 'postRightOffset' => 200, 'postMiddle' => 2500,
        ]);
        $this->assertSame([300, 2500, 5800], $e['postPositionen']);
        $this->assertSame([6000], $e['profilSegmente']);

        // Manuelle CSV-Positionen gewinnen; Spannweite > 4000 warnt.
        $e = KonfiguratorRechner::berechne(['width' => 9000, 'postManual' => '0, 4500, 9000']);
        $this->assertSame([0, 4500, 9000], $e['postPositionen']);
        $this->assertTrue($e['spannZuGross']);
    }

    public function test_manual_field_count_overrides_and_warns_beyond_the_limit(): void
    {
        // Manuell weniger Felder: Rechner folgt der Vorgabe …
        $e = KonfiguratorRechner::berechne(['fieldN' => 8]);
        $this->assertSame(8, $e['fields']);
        $this->assertSame(9, $e['rafters']);
        $this->assertSame(1071, $e['spar']);   // round((8630−60)/8)
        $this->assertSame(1049, $e['glasB']);
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
