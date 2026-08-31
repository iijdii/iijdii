<?php

namespace Tests\Unit;

use App\Support\AufmassRechner;
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
