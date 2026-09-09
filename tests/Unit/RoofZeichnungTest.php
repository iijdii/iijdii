<?php

namespace Tests\Unit;

use App\Support\KonfiguratorRechner;
use App\Support\RoofZeichnung;
use PHPUnit\Framework\TestCase;

class RoofZeichnungTest extends TestCase
{
    private const META = ['firma' => 'LEA Überdachungen GmbH', 'projekt' => 'PRJ-2026-011 · DEMO Demo', 'datum' => '02.09.2026'];

    /** Kalk der Demo-Konfiguration: pn=4, rafters=13, fields=12, spar=714. */
    private function kalk(): array
    {
        return KonfiguratorRechner::berechne([
            'width' => 8630, 'depth' => 3500, 'wallH' => 2900,
            'gutterH' => 2500, 'slope' => 8, 'postN' => 4,
        ]);
    }

    private function texte(array $z): array
    {
        return array_values(array_map(
            fn ($n) => $n['t'],
            array_filter($z['nodes'], fn ($n) => $n['tag'] === 'text'),
        ));
    }

    public function test_iso_projiziert_eckpunkt_und_bemasst_mit_konfigwerten(): void
    {
        $z = RoofZeichnung::ansicht('iso', $this->kalk(), self::META);

        $this->assertSame('0 0 470 340', $z['viewBox']);
        $texte = $this->texte($z);
        $this->assertContains('MONTAGEÜBERSICHT', $texte);
        $this->assertContains('B = 8630', $texte);
        $this->assertContains('D = 3500', $texte);
        $this->assertContains('H1 = 2900', $texte); // wallH, nicht Protos eingefrorene 3050
        $this->assertContains('H2 = 2500', $texte);

        // Vorderer linker Bodenpunkt = rekonstruierter Ursprung (54.0, 196.4)
        $pfosten = array_values(array_filter($z['nodes'], fn ($n) => str_contains($n['extra'] ?? '', 'stroke-width="4.5"')));
        $this->assertCount(4, $pfosten); // pn = 4, nicht Protos 2 Eckpfosten
        $this->assertEqualsWithDelta(54.0, (float) $pfosten[0]['x1'], 0.1);
        $this->assertEqualsWithDelta(196.4, (float) $pfosten[0]['y1'], 0.1);

        // 11 Innensparren (rafters−2)
        $this->assertCount(11, array_filter($z['nodes'], fn ($n) => $n['cls'] === 'raf'));
    }

    public function test_draufsicht_sparren_pfostenkette_und_fussnote(): void
    {
        $z = RoofZeichnung::ansicht('top', $this->kalk(), self::META);

        $this->assertCount(11, array_filter($z['nodes'], fn ($n) => $n['cls'] === 'raf'));

        $texte = $this->texte($z);
        $this->assertContains('DRAUFSICHT', $texte);
        $this->assertContains('Maße in mm · 12 Felder à 714 · Sparren 80×60', $texte);
        $this->assertContains('Rinne (Traufe)', $texte);
        $this->assertContains('Wandanschlussprofil', $texte);
        $this->assertSame(3, count(array_keys($texte, '2877', true))); // Kette W/(pn−1) × 3
        $this->assertContains('8630', $texte);
        $this->assertContains('3500', $texte);

        // 4 Pfostenquadrate 7×7 auf der Rinnenachse
        $quadrate = array_filter($z['nodes'], fn ($n) => $n['tag'] === 'rect' && $n['cls'] === 'mem' && $n['w'] === '7');
        $this->assertCount(4, $quadrate);

        // Titelblock
        $this->assertContains('LEA Überdachungen GmbH', $texte);
        $this->assertContains('PRJ-2026-011 · DEMO Demo', $texte);
        $this->assertContains('Draufsicht', $texte);
        $this->assertContains('1:50', $texte);
        $this->assertContains('1 / 4', $texte);
    }

    public function test_vorderansicht_pfosten_fallrohr_und_farb_label(): void
    {
        $z = RoofZeichnung::ansicht('front', $this->kalk(), self::META);

        $texte = $this->texte($z);
        $this->assertContains('VORDERANSICHT', $texte);
        $this->assertContains('Fallrohr Ø60', $texte);
        $this->assertContains('Regenrinne integriert · Weiß · RAL 9016', $texte);
        $this->assertContains('Maße in mm · Durchgangshöhe an Traufe', $texte);
        $this->assertSame(3, count(array_keys($texte, '2877', true)));
        $this->assertContains('2500', $texte);

        // 4 Pfosten (6 px breit) + 4 Fußplatten
        $pfosten = array_filter($z['nodes'], fn ($n) => $n['tag'] === 'rect' && $n['cls'] === 'mem' && $n['w'] === '6');
        $this->assertCount(4, $pfosten);
        $this->assertContains('2 / 4', $texte);
    }

    public function test_seitenansicht_gefaelle_aus_slope(): void
    {
        $z = RoofZeichnung::ansicht('side', $this->kalk(), self::META);

        $texte = $this->texte($z);
        $this->assertContains('SEITENANSICHT', $texte);
        // Formel identisch zur Kernmaße-Zeile im Montage-Modus (141, nicht Protos 140)
        $this->assertContains('Gefälle 8° ≈ 141 mm/m → Rinne', $texte);
        $this->assertContains('Maße in mm · Pultdach an Hauswand, Neigung 8°', $texte);
        $this->assertContains('Hauswand', $texte);
        $this->assertContains('2900', $texte);
        $this->assertContains('3500', $texte);
        $this->assertContains('3 / 4', $texte);
    }

    public function test_detail_glaslabel_und_feldbreite_aus_pcfg(): void
    {
        $z = RoofZeichnung::ansicht('detail', $this->kalk(), self::META);

        $texte = $this->texte($z);
        $this->assertContains('DETAIL A–A', $texte);
        $this->assertContains('Glasfalz · Rundleiste', $texte);
        $this->assertContains('VSG 8 mm klar', $texte);
        $this->assertContains('Sparren 80 × 60 mm', $texte);
        $this->assertContains('Abdeckprofil (Rundleiste)', $texte);
        $this->assertContains('714', $texte); // spar statt Protos veralteter 1050
        $this->assertContains('Maßstab', $texte);
        $this->assertContains('1:5', $texte);
        $this->assertContains('4 / 4', $texte);
    }

    public function test_alle_liefert_fuenf_ansichten_in_reihenfolge(): void
    {
        $alle = RoofZeichnung::alle($this->kalk(), self::META);

        $this->assertSame(RoofZeichnung::ANSICHTEN, array_keys($alle));
        $this->assertSame('Montageübersicht', RoofZeichnung::TITEL['iso']);
        $this->assertSame('Detailschnitt A–A', RoofZeichnung::TITEL['detail']);
    }

    public function test_nullbreite_rendert_ohne_division_durch_null(): void
    {
        $kalk = KonfiguratorRechner::berechne(['width' => 0, 'depth' => 0]);

        foreach (RoofZeichnung::ANSICHTEN as $ansicht) {
            $z = RoofZeichnung::ansicht($ansicht, $kalk, self::META);
            $this->assertNotEmpty($z['nodes']);
        }
    }
}
