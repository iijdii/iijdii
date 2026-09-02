<?php

namespace Tests\Feature;

use App\Models\Angebot;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjektePageTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    public function test_index_shows_cards_and_status_chips(): void
    {
        $this->actingAs($this->benutzer)->get('/projekte')
            ->assertOk()
            ->assertSee('PRJ-2026-011')
            ->assertSee('DEMO Demo')
            ->assertSee('17.671,50 €');
    }

    public function test_all_tabs_render(): void
    {
        foreach (['uebersicht', 'konfig', 'technik', 'material', 'dokumente', 'zahlungen', 'aktivitaet'] as $tab) {
            $this->actingAs($this->benutzer)
                ->get('/projekte/PRJ-2026-011?tab='.$tab)
                ->assertOk();
        }
    }

    public function test_uebersicht_und_technik_render_the_five_roof_drawings(): void
    {
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=uebersicht')
            ->assertOk()
            ->assertSee('Montageübersicht')
            ->assertSee('Detailschnitt A–A')
            ->assertSee('B = 8630')
            ->assertSee('roofLightbox')
            ->assertDontSee('Zeichnungen folgen');

        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=technik')
            ->assertOk()
            ->assertSee('8 Felder à 1079')
            ->assertSee('Gefälle 8° ≈ 141 mm/m → Rinne')
            ->assertSee('PRJ-2026-011 · DEMO Demo') // Titelblock der Zeichnung
            ->assertDontSee('Zeichnungen folgen');
    }

    public function test_konfigurator_shows_prototype_positions_and_kalkulation(): void
    {
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=konfig')
            ->assertOk()
            ->assertSee('Überdachung Trapez 8630×3500 mm')
            ->assertSee('Pfosten 110×110 · Weiß · RAL 9016')
            ->assertSee('Keil Links · Glas (Klar)')
            ->assertSee('1.019 mm')          // Wandblende bei W=8630
            ->assertSee('Dach-Kalkulation');
    }

    public function test_berechnen_previews_without_persisting(): void
    {
        $antwort = $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-011/konfiguration', [
            'aktion' => 'berechnen', 'width' => 6000, 'depth' => 3500,
        ]);

        $antwort->assertRedirect(route('projekte.show', ['PRJ-2026-011', 'tab' => 'konfig']));

        // Vorschau sichtbar, Persistenz unverändert.
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=konfig')
            ->assertSee('6000×3500')
            ->assertSee('Vorschau — noch nicht gespeichert');
        $this->assertSame(8630, Projekt::query()->where('nr', 'PRJ-2026-011')->value('konfiguration')['width'] ?? json_decode(Projekt::query()->where('nr', 'PRJ-2026-011')->value('konfiguration'), true)['width']);
    }

    public function test_speichern_persists_and_logs_activity(): void
    {
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-011/konfiguration', [
            'aktion' => 'speichern', 'width' => 7000, 'depth' => 3000, 'extras' => ['Keile'],
        ])->assertSessionHas('toast', 'Projekt-Konfiguration gespeichert');

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $this->assertSame(7000, $projekt->konfiguration['width']);
        $this->assertTrue($projekt->aktivitaeten()->where('titel', 'Konfiguration gespeichert')->exists());
    }

    public function test_material_tab_shows_live_warehouse_state(): void
    {
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=material')
            ->assertOk()
            ->assertSee('Geliefert')     // VSG aus BST-2026-112 (LS-88214)
            ->assertSee('Bestellt')      // offene BST-2026-111
            ->assertSee('von 10 Positionen geliefert und eingelagert');
    }

    public function test_zahlungen_computes_30_40_30_from_the_quote(): void
    {
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=zahlungen')
            ->assertOk()
            ->assertSee('14.850,00 €')   // Netto
            ->assertSee('2.821,50 €')    // MwSt
            ->assertSee('5.301,45 €')    // Rate 1/3
            ->assertSee('7.068,60 €')    // Rate 2
            ->assertSee('Bezahlt');
    }

    public function test_als_angebot_uebergeben_creates_and_links_a_quote(): void
    {
        // PRJ-2026-038 hat noch kein Angebot.
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/angebot')
            ->assertSessionHas('toast', 'Angebot aus Konfiguration erstellt');

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-038')->firstOrFail();
        $this->assertNotNull($projekt->angebot_id);
        $this->assertSame('ANG-2026-072', $projekt->angebot->nr); // Seed-Maximum 071
        $this->assertSame('entwurf', $projekt->angebot->status->value);

        // Zweiter Klick legt kein Duplikat an.
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-038/angebot');
        $this->assertSame(1, Angebot::query()->where('nr', 'like', 'ANG-2026-072')->count());
    }
}
