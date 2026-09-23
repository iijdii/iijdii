<?php

namespace Tests\Unit;

use App\Support\Preisliste;
use PHPUnit\Framework\TestCase;

class PreislisteTest extends TestCase
{
    public function test_exakte_rasterwerte_treffen_die_tabellenzelle(): void
    {
        // Eckwerte beider Matrizen (Quelle: Preisseite lea-ueberdachung.de)
        $this->assertSame(3840, Preisliste::dachPreis('Polycarbonat', 2000, 2000));
        $this->assertSame(8400, Preisliste::dachPreis('Polycarbonat', 10000, 6000));
        $this->assertSame(4670, Preisliste::dachPreis('Glas', 2000, 2000));
        $this->assertSame(10310, Preisliste::dachPreis('Glas', 10000, 6000));

        // Mittlere Zellen
        $this->assertSame(5520, Preisliste::dachPreis('Polycarbonat', 6000, 3000));
        $this->assertSame(7070, Preisliste::dachPreis('Glas', 6000, 3000));
    }

    public function test_zwischenmasse_werden_aufgerundet(): void
    {
        // 6.300 × 3.100 mm → Raster 7.000 × 3.500 mm
        $this->assertSame(6360, Preisliste::dachPreis('Polycarbonat', 6300, 3100));
        $this->assertSame(7790, Preisliste::dachPreis('Glas', 6300, 3100));

        // Untergrenze: kleiner als das Raster → kleinste Zelle
        $this->assertSame(4670, Preisliste::dachPreis('Glas', 1500, 1800));
    }

    public function test_ausserhalb_der_preisliste_gibt_es_keinen_preis(): void
    {
        $this->assertNull(Preisliste::dachPreis('Glas', 10500, 3000));
        $this->assertNull(Preisliste::dachPreis('Glas', 6000, 6500));
        $this->assertNull(Preisliste::dachPreis('Glas', 0, 3000));
    }

    public function test_legacy_deckungen_landen_in_der_richtigen_matrix(): void
    {
        $this->assertSame(5520, Preisliste::dachPreis('Polycarbonat opal', 6000, 3000));
        $this->assertSame(7070, Preisliste::dachPreis('VSG-Glas', 6000, 3000));
    }

    public function test_aus_konfiguration_nutzt_pcfg_defaults(): void
    {
        // Nur Maße gesetzt → Deckung aus den Defaults (VSG-Glas)
        $this->assertSame(7070, Preisliste::ausKonfiguration(['width' => 6000, 'depth' => 3000]));
        $this->assertNull(Preisliste::ausKonfiguration(['width' => 12000, 'depth' => 3000]));
    }
}
