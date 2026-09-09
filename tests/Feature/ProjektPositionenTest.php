<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjektPositionenTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    public function test_dachposition_anlegen_synct_konfiguration_und_ist_einzigartig(): void
    {
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/positionen', [
            'position' => ['produkt' => 'carport', 'felder' => [
                'width' => 5400, 'depth' => 5400, 'thickness' => '16 mm', 'shape' => 'rechteck',
            ]],
        ])->assertSessionHas('toast', 'Position hinzugefügt · Carport');

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-038')->firstOrFail();
        $this->assertSame('dach', $projekt->positionen()->first()->gruppe);
        $this->assertSame(1, $projekt->positionen()->first()->phase);
        $this->assertSame('Carport', $projekt->konfiguration['product']); // gespiegelt
        $this->assertSame(5400, $projekt->konfiguration['width']);

        // Zweite Dachposition wird abgewiesen
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/positionen', [
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000]],
        ])->assertSessionHas('toast', 'Nur eine Dachposition pro Projekt — bestehende bearbeiten');
        $this->assertSame(1, $projekt->positionen()->count());
    }

    public function test_element_positionen_crud_und_ownership(): void
    {
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/positionen', [
            'position' => ['produkt' => 'gelaender', 'felder' => [
                'laenge_mm' => 4000, 'hoehe_mm' => 1000, 'material' => 'Edelstahl',
            ]],
        ]);
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-038')->firstOrFail();
        $position = $projekt->positionen()->firstOrFail();
        $this->assertSame('extra', $position->gruppe);
        $this->assertSame(2, $position->phase); // Endmaße-Phase

        $this->actingAs($this->benutzer)->put('/projekte/PRJ-2026-038/positionen/'.$position->id, [
            'position' => ['produkt' => 'gelaender', 'felder' => ['laenge_mm' => 4200]],
        ])->assertSessionHas('toast', 'Position 1 aktualisiert');
        $this->assertSame(4200, $position->fresh()->felder['laenge_mm']);

        // Fremdes Projekt → 404
        $this->actingAs($this->benutzer)
            ->post('/projekte/PRJ-2026-035/positionen/'.$position->id.'/loeschen')
            ->assertNotFound();

        $this->actingAs($this->benutzer)
            ->post('/projekte/PRJ-2026-038/positionen/'.$position->id.'/loeschen')
            ->assertSessionHas('toast', 'Position entfernt');
        $this->assertSame(0, $projekt->positionen()->count());
    }

    public function test_monteur_darf_keine_positionen_schreiben(): void
    {
        $monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();

        $this->actingAs($monteur)->post('/projekte/PRJ-2026-038/positionen', [
            'position' => ['produkt' => 'markise'],
        ])->assertForbidden();
    }

    public function test_konfig_tab_zeigt_editor_und_konfigurator_schreibt_durch_die_dachposition(): void
    {
        // Dachposition + Element anlegen
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/positionen', [
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000, 'depth' => 3000]],
        ]);
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/positionen', [
            'position' => ['produkt' => 'markise', 'felder' => ['breite_mm' => 4500]],
        ]);

        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-038?tab=konfig')
            ->assertOk()
            ->assertSee('Position 1 — Überdachung')
            ->assertSee('Position 2 — Markise')
            ->assertSee('Phase 2 · Endmaße nach Dachmontage')
            ->assertSee('Position hinzufügen');

        // Großer Konfigurator speichert → Dachposition wird mitgeschrieben
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/konfiguration', [
            'aktion' => 'speichern', 'width' => 7200, 'depth' => 3100,
        ]);
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-038')->firstOrFail();
        $dach = $projekt->positionen()->where('gruppe', 'dach')->firstOrFail();
        $this->assertSame(7200, $dach->felder['width']);
        $this->assertSame(7200, $projekt->konfiguration['width']);

        // Element-Update lässt die Dach-Spiegelung intakt
        $markise = $projekt->positionen()->where('produkt', 'markise')->firstOrFail();
        $this->actingAs($this->benutzer)->put('/projekte/PRJ-2026-038/positionen/'.$markise->id, [
            'position' => ['produkt' => 'markise', 'felder' => ['breite_mm' => 5000]],
        ]);
        $this->assertSame(7200, $projekt->fresh()->konfiguration['width']);
    }
}
