<?php

namespace Tests\Feature;

use App\Enums\AnfrageStatus;
use App\Models\Anfrage;
use App\Models\Kunde;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnfragenFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    public function test_list_shows_chips_cards_and_table(): void
    {
        $this->actingAs($this->benutzer)->get('/anfragen')
            ->assertOk()
            ->assertSee('ANF-2026-012')
            ->assertSee('In Bearbeitung')
            ->assertSee('Neu anlegen');

        $this->actingAs($this->benutzer)->get('/anfragen?stufe=1')
            ->assertOk()
            ->assertSee('ANF-2026-010')
            ->assertDontSee('ANF-2026-012');
    }

    public function test_detail_renders_construction_cards_and_keil_sketch(): void
    {
        $this->actingAs($this->benutzer)->get('/anfragen/ANF-2026-012')
            ->assertOk()
            ->assertSee('Position 1 — Überdachung')
            ->assertSee('Keil(e)')
            ->assertSee('polygon class="glp"', false)   // kSk-Skizze
            ->assertSee('Länge Wandprofil')             // Trapez-Felder
            ->assertSee('Schiebesystem(e)')
            ->assertSee('Projekt erstellen');
    }

    public function test_stepper_changes_status_and_logs_activity(): void
    {
        $this->actingAs($this->benutzer)
            ->post('/anfragen/ANF-2026-010/status', ['stufe' => 3])
            ->assertSessionHas('toast', 'Status: Aufmaß geplant');

        $anfrage = Anfrage::query()->where('nummer', 'ANF-2026-010')->firstOrFail();
        $this->assertSame(AnfrageStatus::TerminVereinbart, $anfrage->status);

        $log = $anfrage->aktivitaeten()->where('typ', 'status_geaendert')->latest('id')->first();
        $this->assertSame(['von' => 'neu', 'nach' => 'termin_vereinbart'], $log->details);
    }

    public function test_create_form_stores_a_new_anfrage_with_sequence_number(): void
    {
        $kunde = Kunde::query()->where('kunden_nr', 'K-1042')->firstOrFail();

        $this->actingAs($this->benutzer)->post('/anfragen', [
            'kunde_id' => $kunde->id,
            'status' => 'neu',
            'produkt_notiz' => 'Terrassenüberdachung',
            'breite_cm' => 500,
            'tiefe_cm' => 300,
        ])->assertSessionHas('toast', 'Anfrage gespeichert · ANF-2026-015');

        $anfrage = Anfrage::query()->where('nummer', 'ANF-2026-015')->firstOrFail();
        $this->assertSame($kunde->id, $anfrage->kunde_id);
        $this->assertSame($kunde->telefon, $anfrage->kunden_telefon); // denormalisiert
        $this->assertTrue($anfrage->aktivitaeten()->where('typ', 'angelegt')->exists());
    }

    public function test_update_edits_an_existing_anfrage(): void
    {
        $anfrage = Anfrage::query()->where('nummer', 'ANF-2026-010')->firstOrFail();

        $this->actingAs($this->benutzer)->put('/anfragen/ANF-2026-010', [
            'kunde_id' => $anfrage->kunde_id,
            'status' => 'in_bearbeitung',
            'breite_cm' => 480,
        ])->assertSessionHas('toast', 'Anfrage ANF-2026-010 aktualisiert');

        $this->assertSame(480, $anfrage->fresh()->breite_cm);
    }

    public function test_projekt_erstellen_maps_the_configuration(): void
    {
        $antwort = $this->actingAs($this->benutzer)->post('/anfragen/ANF-2026-012/projekt');

        $projekt = Projekt::query()->orderByDesc('id')->first();

        $antwort->assertRedirect(route('projekte.show', [$projekt, 'tab' => 'konfig']))
            ->assertSessionHas('toast', 'Projekt '.$projekt->nr.' aus ANF-2026-012 erstellt');

        $this->assertSame('PRJ-2026-039', $projekt->nr); // Seed-Maximum 038
        $this->assertSame('Terrassenüberdachung DEMO Demo', $projekt->titel);

        $k = $projekt->konfiguration;
        $this->assertSame('trapez', $k['shape']);
        $this->assertSame(6000, $k['width']);
        $this->assertContains('Keile', $k['extras']);
        $this->assertContains('Schiebe-Elemente', $k['extras']);
        $this->assertSame('center', $k['schiebe']['dir']); // 'Mittig' normalisiert
        $this->assertSame('Rechts', $k['keil']['side']);

        $this->assertTrue($projekt->aktivitaeten()->where('titel', 'like', 'Projekt aus ANF-2026-012%')->exists());
        $this->assertTrue(
            Anfrage::query()->where('nummer', 'ANF-2026-012')->firstOrFail()
                ->aktivitaeten()->where('typ', 'projekt_erstellt')->exists()
        );
    }
}
