<?php

namespace Tests\Feature;

use App\Models\Angebot;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AngeboteTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    public function test_list_shows_quotes_with_status_badges(): void
    {
        $this->actingAs($this->benutzer)->get('/angebote')
            ->assertOk()
            ->assertSee('ANG-2026-010')
            ->assertSee('17.671,50 €')
            ->assertSee('Angenommen')
            ->assertSee('Versendet')
            ->assertSee('Abgelehnt');
    }

    public function test_rows_link_to_quote_detail(): void
    {
        $this->actingAs($this->benutzer)->get('/angebote')
            ->assertSee('/angebote/ANG-2026-010', false)
            ->assertSee('/angebote/ANG-2026-071', false)
            ->assertSee('/projekte/PRJ-2026-011', false) // Projekt-Spalte verlinkt
            ->assertDontSee('data-toast="Öffne', false);
    }

    public function test_detail_renders_positions_and_meta(): void
    {
        // ANG-2026-010 hängt am Projekt PRJ-2026-011 → Positionen aus dessen Konfiguration
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010')
            ->assertOk()
            ->assertSee('ANG-2026-010')
            ->assertSee('17.671,50 €')
            ->assertSee('PRJ-2026-011')
            ->assertSee('Überdachung Trapez 8630×3500 mm')
            ->assertSee('Angenommen');

        // Unverknüpft und ohne Konfiguration: Hinweis statt Positionen
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-071')
            ->assertOk()
            ->assertSee('Keine Konfiguration hinterlegt')
            ->assertSee('28.400,00 €');
    }

    public function test_summe_kann_erfasst_werden(): void
    {
        $this->actingAs($this->benutzer)->post('/angebote/ANG-2026-069/summe', ['summe' => 12500])
            ->assertRedirect(route('angebote.show', 'ANG-2026-069'))
            ->assertSessionHas('toast', 'Angebotssumme gespeichert');

        $this->assertSame('12500.00', Angebot::query()->where('nr', 'ANG-2026-069')->value('summe'));
    }

    public function test_status_uebergaenge_mit_guards(): void
    {
        // Ungültiger Sprung: Entwurf → Angenommen
        $this->actingAs($this->benutzer)->post('/angebote/ANG-2026-069/status', ['status' => 'angenommen'])
            ->assertSessionHas('toast', 'Übergang nicht möglich');

        // Versendet, aber ohne Summe nicht annehmbar
        $angebot = Angebot::query()->where('nr', 'ANG-2026-069')->firstOrFail();
        $angebot->update(['summe' => null]);
        $this->actingAs($this->benutzer)->post('/angebote/ANG-2026-069/status', ['status' => 'versendet'])
            ->assertSessionHas('toast', 'Status: Versendet');
        $this->actingAs($this->benutzer)->post('/angebote/ANG-2026-069/status', ['status' => 'angenommen'])
            ->assertSessionHas('toast', 'Bitte zuerst die Angebotssumme erfassen');
        $this->assertSame('versendet', $angebot->fresh()->status->value);

        // Mit Summe: Annehmen klappt
        $this->actingAs($this->benutzer)->post('/angebote/ANG-2026-069/summe', ['summe' => 9990]);
        $this->actingAs($this->benutzer)->post('/angebote/ANG-2026-069/status', ['status' => 'angenommen'])
            ->assertSessionHas('toast', 'Status: Angenommen');
        $this->assertSame('angenommen', $angebot->fresh()->status->value);
    }

    public function test_annahme_loggt_aktivitaet_und_belebt_zahlungsplan(): void
    {
        // Frisches Angebot am Projekt PRJ-2026-038 erzeugen (Konfigurator-Weg)
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-038')->firstOrFail();
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/angebot');
        $angebot = $projekt->fresh()->angebot;

        $this->actingAs($this->benutzer)->post(route('angebote.summe', $angebot), ['summe' => 20000]);
        $this->actingAs($this->benutzer)->post(route('angebote.status', $angebot), ['status' => 'versendet']);
        $this->actingAs($this->benutzer)->post(route('angebote.status', $angebot), ['status' => 'angenommen']);

        $this->assertTrue($projekt->aktivitaeten()->where('titel', 'like', 'Angebot%angenommen')->exists());

        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-038?tab=zahlungen')
            ->assertSee('Bezahlt')
            ->assertSee('6.000,00 €'); // Rate 1 = 30 % von 20.000
    }

    public function test_angebot_zeigt_element_positionen_des_projekts(): void
    {
        // Anfrage mit Dach → Auto-Projekt/-Angebot, dann Markise ergänzen
        $this->actingAs($this->benutzer)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000, 'depth' => 3000]],
        ]);
        $projekt = Projekt::query()->orderByDesc('id')->first();
        $this->actingAs($this->benutzer)->post('/projekte/'.$projekt->nr.'/positionen', [
            'position' => ['produkt' => 'markise', 'felder' => [
                'breite_mm' => 4500, 'ausfall_mm' => 3000, 'felder_n' => 2,
            ]],
        ]);

        $this->actingAs($this->benutzer)->get('/angebote/'.$projekt->angebot->nr)
            ->assertOk()
            ->assertSee('Markise 4500×3000 mm')
            ->assertSee('Dachfeld VSG-Glas 8 mm'); // Dach-Rechenkern weiterhin dabei

        $this->actingAs($this->benutzer)->get('/angebote/'.$projekt->angebot->nr.'/pdf')
            ->assertOk();
    }
}
