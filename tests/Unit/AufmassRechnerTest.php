<?php

namespace Tests\Unit;

use App\Models\ProjektPosition;
use App\Support\AufmassRechner;
use App\Support\KonfiguratorRechner;
use PHPUnit\Framework\TestCase;

class AufmassRechnerTest extends TestCase
{
    /** pcfg des Demo-Projekts PRJ-2026-011 (Auszug). */
    private function pcfg(array $extra = []): array
    {
        return array_merge([
            'width' => 8630, 'depth' => 3500, 'wallH' => 2900, 'gutterH' => 2500,
            'slope' => 8,
            'extras' => ['Keile', 'Festelemente', 'Schiebe-Elemente', 'Markisen', 'Sonnensegel'],
        ], $extra);
    }

    public function test_keil_soll_values_match_the_prototype_formulas(): void
    {
        $keil = collect(AufmassRechner::gruppen($this->pcfg(), [], 5, 15))->firstWhere('ek', 'keil');
        $felder = collect($keil['fields'])->keyBy('tag');

        // hK = 2900-2500 = 400; hFront 120 → B 520, D = √(3500² + 400²)
        $this->assertSame(3500, $felder['A']['soll']);
        $this->assertSame(520, $felder['B']['soll']);
        $this->assertSame(120, $felder['C']['soll']);
        $this->assertSame((int) round(sqrt(3500 ** 2 + 400 ** 2)), $felder['D']['soll']);
    }

    public function test_keil_position_nutzt_eingegebene_breite_und_beide_hoehen(): void
    {
        // Betreiber-Vorgabe: Keil trägt drei eigene Maße — Breite,
        // Höhe hinten, Höhe vorn. Die Sollwerte folgen der Eingabe,
        // nicht mehr der Ableitung aus Dachtiefe/Gefälle.
        $position = new ProjektPosition([
            'pos' => 1, 'gruppe' => 'extra', 'produkt' => 'keil', 'phase' => 2,
            'felder' => ['anzahl' => 2, 'breite_mm' => 3200, 'h_hinten_mm' => 450, 'h_vorn_mm' => 150],
        ]);
        $kalk = KonfiguratorRechner::berechne($this->pcfg());

        $keil = collect(AufmassRechner::gruppenAusPositionen([$position], $kalk, 5, 15))->first();
        $felder = collect($keil['fields'])->keyBy('tag');

        $this->assertSame(3200, $felder['A']['soll']);
        $this->assertSame(450, $felder['B']['soll']);
        $this->assertSame(150, $felder['C']['soll']);
        $this->assertSame((int) round(sqrt(3200 ** 2 + 300 ** 2)), $felder['D']['soll']);
    }

    public function test_sonnensegel_position_je_dachfeld_mit_blendenbreite_und_dachtiefe(): void
    {
        // Betreiber-Standard: Stückzahl = Dachfelder, Breite wie die
        // Wandblende (Achsmaß − 60 mm), Länge = Dachtiefe. pcfg-Referenz:
        // 12 Felder, Achsmaß 714 → Blende 654, Tiefe 3500.
        $kalk = KonfiguratorRechner::berechne($this->pcfg());
        $position = new ProjektPosition([
            'pos' => 1, 'gruppe' => 'sonnenschutz', 'produkt' => 'sonnensegel', 'phase' => 2,
            'felder' => [],
        ]);

        $segel = collect(AufmassRechner::gruppenAusPositionen([$position], $kalk, 5, 15))->first();
        $felder = collect($segel['fields'])->keyBy('tag');

        $this->assertCount(13, $segel['fields']); // 12 Segel + Länge
        $this->assertSame(654, $felder['1']['soll']);
        $this->assertSame(654, $felder['12']['soll']);
        $this->assertSame(3500, $felder['L']['soll']);

        // Eingaben in der Position übersteuern alle Segel auf einmal.
        $position->felder = ['anzahl' => 3, 'breite_mm' => 700, 'laenge_mm' => 3000];
        $segel = collect(AufmassRechner::gruppenAusPositionen([$position], $kalk, 5, 15))->first();
        $felder = collect($segel['fields'])->keyBy('tag');
        $this->assertCount(4, $segel['fields']);
        $this->assertSame(700, $felder['3']['soll']);
        $this->assertSame(3000, $felder['L']['soll']);
    }

    public function test_schiebe_fluegelbreite_and_segel_diagonals(): void
    {
        $gruppen = collect(AufmassRechner::gruppen($this->pcfg(), [], 5, 15));

        $schiebe = collect($gruppen->firstWhere('ek', 'schiebe')['fields'])->keyBy('tag');
        // Defaults: width 4000, count 3 → C = round((4000+2·60)/3) = 1373
        $this->assertSame(1373, $schiebe['C']['soll']);
        $this->assertSame(4000, $schiebe['D']['soll']);

        $segel = collect($gruppen->firstWhere('ek', 'segel')['fields'])->keyBy('tag');
        // '4×4 m' → 4000/4000, Diagonale √2·4000 ≈ 5657
        $this->assertSame(4000, $segel['A']['soll']);
        $this->assertSame(5657, $segel['E']['soll']);
        $this->assertSame($segel['E']['soll'], $segel['F']['soll']);
    }

    public function test_markise_konsolenachsabstand(): void
    {
        $markise = collect(AufmassRechner::gruppen($this->pcfg(), [], 5, 15))->firstWhere('ek', 'markise');
        $felder = collect($markise['fields'])->keyBy('tag');

        // Defaults: width 5000, felder 2 → kn 3 → C = round((5000-300)/2) = 2350; D = 2500-250
        $this->assertSame(2350, $felder['C']['soll']);
        $this->assertSame(2250, $felder['D']['soll']);
        $this->assertStringContainsString('3 Konsolen', $markise['note']);
    }

    public function test_ampel_thresholds_are_inclusive(): void
    {
        $this->assertSame('ok', AufmassRechner::ampel(5, 5, 15));
        $this->assertSame('warn', AufmassRechner::ampel(-6, 5, 15));
        $this->assertSame('warn', AufmassRechner::ampel(15, 5, 15));
        $this->assertSame('bad', AufmassRechner::ampel(16, 5, 15));
    }

    public function test_ist_accepts_comma_decimals_and_marks_delta(): void
    {
        $gruppen = AufmassRechner::gruppen($this->pcfg(), ['keil.A' => '3.502'], 5, 15);
        // "3.502" (Punkt) wird als 3.502 mm-Dezimal gelesen? Nein — als 3.502 float.
        // Realistischer Fall: Komma-Dezimale.
        $gruppen = AufmassRechner::gruppen($this->pcfg(), ['keil.A' => '3502,4'], 5, 15);
        $feldA = collect(collect($gruppen)->firstWhere('ek', 'keil')['fields'])->firstWhere('tag', 'A');

        $this->assertSame(3502.4, $feldA['ist']);
        $this->assertSame('+2 mm', $feldA['deltaText']);
        $this->assertSame('ok', $feldA['ampel']);
    }

    public function test_only_configured_extras_produce_groups(): void
    {
        $gruppen = AufmassRechner::gruppen($this->pcfg(['extras' => ['Keile']]), [], 5, 15);

        $this->assertCount(1, $gruppen);
        $this->assertSame('keil', $gruppen[0]['ek']);
    }
}
