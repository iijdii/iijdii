<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Support\KonfigurationSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KonfigurationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dachposition_wird_nach_konfiguration_gespiegelt(): void
    {
        $projekt = Projekt::factory()->create(['konfiguration' => null]);
        $projekt->positionen()->create([
            'pos' => 1, 'gruppe' => 'dach', 'produkt' => 'carport', 'phase' => 1,
            'felder' => ['width' => 5400, 'depth' => 5400, 'shape' => 'rechteck', 'thickness' => '16 mm'],
        ]);

        KonfigurationSync::spiegleDach($projekt);

        $k = $projekt->fresh()->konfiguration;
        $this->assertSame('Carport', $k['product']);
        $this->assertSame(5400, $k['width']);
        $this->assertSame('16 mm', $k['thickness']);
        $this->assertSame(2500, $k['gutterH']); // Rechner-Default ergänzt
    }

    public function test_ohne_dachposition_bleibt_konfiguration_unberuehrt(): void
    {
        $projekt = Projekt::factory()->create(['konfiguration' => ['width' => 8630]]);
        $projekt->positionen()->create([
            'pos' => 1, 'gruppe' => 'sonnenschutz', 'produkt' => 'markise', 'phase' => 2,
            'felder' => ['breite_mm' => 5000],
        ]);

        KonfigurationSync::spiegleDach($projekt);

        $this->assertSame(8630, $projekt->fresh()->konfiguration['width']);
    }
}
