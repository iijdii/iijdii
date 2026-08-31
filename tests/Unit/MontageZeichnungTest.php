<?php

namespace Tests\Unit;

use App\Support\MontageZeichnung;
use PHPUnit\Framework\TestCase;

class MontageZeichnungTest extends TestCase
{
    public function test_keil_has_wall_hatch_rohmass_and_oblique_label(): void
    {
        $z = MontageZeichnung::extra([
            'shape' => 'keil', 'fields' => [3500, 520, 120, 3523],
            'side' => 'Links', 'qty' => 1, 'unterzug' => '110×110',
        ]);

        $this->assertMatchesRegularExpression('/^-?[\d.]+ -?[\d.]+ [\d.]+ [\d.]+$/', $z['viewBox']);
        $this->assertSame('Seitenansicht · rechtwinkliges Trapez', $z['sub']);
        $this->assertTrue(collect($z['polys'])->contains(fn ($p) => $p['fill'] === 'url(#dwhx)'));
        $texte = collect($z['labels'])->pluck('t');
        $this->assertTrue($texte->contains(fn ($t) => str_starts_with($t, 'Rohmaß ')));
        $this->assertTrue($texte->contains(fn ($t) => str_starts_with($t, 'D  ')));
        $this->assertTrue($texte->contains('WAND'));
    }

    public function test_schiebe_draws_leaves_and_laufschiene_note(): void
    {
        $z = MontageZeichnung::extra([
            'shape' => 'schiebe', 'fields' => [4000, 2200, 1373, 4000], 'dir' => 'center', 'qty' => 3,
        ]);

        $this->assertSame('3 Flügel · Ansicht außen', $z['sub']);
        $this->assertTrue(collect($z['labels'])->pluck('t')->contains(fn ($t) => str_starts_with($t, 'LAUFSCHIENE')));
        // Flügelnummern 1..3 vorhanden
        $this->assertTrue(collect($z['labels'])->pluck('t')->contains('2'));
    }

    public function test_markise_has_kassette_and_konsolen(): void
    {
        $z = MontageZeichnung::extra([
            'shape' => 'markise', 'fields' => [5000, 3000, 2350, 2250],
        ]);

        $this->assertStringContainsString('Konsolen', $z['sub']);
        $this->assertTrue(collect($z['labels'])->pluck('t')->contains('KASSETTE'));
    }

    public function test_segel_has_six_dimension_labels(): void
    {
        $z = MontageZeichnung::extra([
            'shape' => 'segel', 'fields' => [4000, 4000, 4000, 4000, 5657, 5657], 'qty' => 1,
        ]);

        $tags = collect($z['labels'])->pluck('t')->filter(fn ($t) => preg_match('/^[A-F]  /', $t));
        $this->assertCount(6, $tags);
        $this->assertSame('Draufsicht · 4 Befestigungspunkte', $z['sub']);
    }

    public function test_fest_rechteck_has_no_wall_when_nowall(): void
    {
        $z = MontageZeichnung::extra([
            'shape' => 'fest', 'fields' => [1059, 3450, 3450, 3609], 'noWall' => true, 'qty' => 8,
        ]);

        $this->assertSame('Ansicht außen · Rechteck', $z['sub']);
        $this->assertFalse(collect($z['polys'])->contains(fn ($p) => $p['fill'] === 'url(#dwhx)'));
        $this->assertTrue(collect($z['labels'])->pluck('t')->contains('× 8 Stk'));
    }
}
