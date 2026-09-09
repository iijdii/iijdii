<?php

namespace Tests\Feature;

use App\Models\Lieferant;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjektAufmassTest extends TestCase
{
    use RefreshDatabase;

    private User $verkauf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    /** Frisches Projekt ohne Bestellungen über den Anfrage-Autostart (M9). */
    private function frischesProjekt(): Projekt
    {
        $this->actingAs($this->verkauf)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000, 'depth' => 3000]],
        ]);

        return Projekt::query()->orderByDesc('id')->firstOrFail();
    }

    public function test_bestaetigen_speichert_wer_wann_und_vor_ort(): void
    {
        $projekt = $this->frischesProjekt();
        $this->assertFalse($projekt->aufmassBestaetigt());

        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen', 'vor_ort_gewesen' => 1])
            ->assertRedirect(route('projekte.show', [$projekt, 'tab' => 'material']))
            ->assertSessionHas('toast', 'Aufmaß bestätigt — Bestellungen sind jetzt möglich');

        $projekt->refresh();
        $this->assertTrue($projekt->aufmassBestaetigt());
        $this->assertSame($this->verkauf->id, $projekt->aufmass_von);
        $this->assertTrue($projekt->vor_ort_gewesen);
        $this->assertTrue($projekt->aktivitaeten()->where('titel', 'Aufmaß bestätigt (vor Ort)')->exists());

        // Tab «Material + Bestellungen» zeigt die Bestätigung
        $this->actingAs($this->verkauf)->get('/projekte/'.$projekt->nr.'?tab=material')
            ->assertSee('Aufmaß bestätigt')
            ->assertSee($this->verkauf->name);
    }

    public function test_zuruecksetzen_sperrt_wieder(): void
    {
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $this->assertTrue($projekt->aufmassBestaetigt()); // Seed: Projekt mit Bestellungen

        $this->actingAs($this->verkauf)
            ->post('/projekte/PRJ-2026-011/aufmass-bestaetigung', ['aktion' => 'zuruecksetzen'])
            ->assertSessionHas('toast', 'Aufmaß-Bestätigung zurückgesetzt');

        $projekt->refresh();
        $this->assertFalse($projekt->aufmassBestaetigt());
        $this->assertNull($projekt->aufmass_von);
        $this->assertFalse($projekt->vor_ort_gewesen);

        $this->actingAs($this->verkauf)->get('/projekte/PRJ-2026-011?tab=material')
            ->assertSee('Aufmaß offen');
    }

    public function test_monteur_darf_nicht_bestaetigen(): void
    {
        $monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();

        $this->actingAs($monteur)
            ->post('/projekte/PRJ-2026-011/aufmass-bestaetigung', ['aktion' => 'bestaetigen'])
            ->assertForbidden();
    }

    public function test_bestellung_mit_projektbezug_blockiert_ohne_bestaetigung(): void
    {
        $projekt = $this->frischesProjekt();
        $lieferant = Lieferant::query()->firstOrFail();
        $projektleiter = User::query()->where('email', 'projekt@lea.test')->firstOrFail();

        // Harte Sperre: ohne Bestätigung keine Bestellung am Projekt.
        $this->actingAs($projektleiter)->post('/bestellungen', [
            'titel' => 'Glas Phase 1', 'lieferant_id' => $lieferant->id, 'projekt_id' => $projekt->id,
        ])->assertSessionHasErrors(['projekt_id']);
        $this->assertSame(0, $projekt->bestellungen()->count());

        // Ohne Projektbezug bleibt das Anlegen frei.
        $this->actingAs($projektleiter)->post('/bestellungen', [
            'titel' => 'Lagerauffüllung', 'lieferant_id' => $lieferant->id,
        ])->assertSessionHasNoErrors();

        // Nach Bestätigung geht der Projektbezug durch.
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);
        $this->actingAs($projektleiter)->post('/bestellungen', [
            'titel' => 'Glas Phase 1', 'lieferant_id' => $lieferant->id, 'projekt_id' => $projekt->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $projekt->bestellungen()->count());
    }
}
