<?php

namespace Tests\Unit;

use App\Enums\ProjektProdukt;
use PHPUnit\Framework\TestCase;

class ProjektProduktTest extends TestCase
{
    public function test_zehn_produkte_in_drei_gruppen(): void
    {
        $this->assertCount(10, ProjektProdukt::cases());

        $gruppen = ProjektProdukt::nachGruppen();
        $this->assertSame(['Überdachungen', 'Extras', 'Sonnenschutz'], array_keys($gruppen));
        $this->assertCount(4, $gruppen['Überdachungen']);
        $this->assertCount(4, $gruppen['Extras']);
        $this->assertCount(2, $gruppen['Sonnenschutz']);
    }

    public function test_gruppen_phase_und_labels(): void
    {
        $this->assertTrue(ProjektProdukt::Carport->istDach());
        $this->assertSame(1, ProjektProdukt::Kube->phase());
        $this->assertSame('extra', ProjektProdukt::Gelaender->gruppe());
        $this->assertSame(2, ProjektProdukt::Gelaender->phase());
        $this->assertSame('sonnenschutz', ProjektProdukt::Markise->gruppe());
        $this->assertSame(2, ProjektProdukt::Sonnensegel->phase());
        $this->assertSame('Wand / Festelement', ProjektProdukt::Wand->label());
        $this->assertSame('Carport', ProjektProdukt::Carport->konfiguratorProdukt());
        $this->assertSame('Überdachung', ProjektProdukt::Wand->konfiguratorProdukt());
    }
}
