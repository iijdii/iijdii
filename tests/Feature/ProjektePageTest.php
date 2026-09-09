<?php

namespace Tests\Feature;

use App\Models\Angebot;
use App\Models\Artikel;
use App\Models\Lagerbewegung;
use App\Models\Projekt;
use App\Models\Reservierung;
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
        foreach (['uebersicht', 'kunde', 'material', 'fotos', 'dokumente', 'zahlungen', 'aktivitaet'] as $tab) {
            $this->actingAs($this->benutzer)
                ->get('/projekte/PRJ-2026-011?tab='.$tab)
                ->assertOk();
        }
    }

    public function test_uebersicht_rendert_bemasste_zeichnungen_und_produktpass(): void
    {
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=uebersicht')
            ->assertOk()
            ->assertSee('Montageübersicht')
            ->assertSee('Detailschnitt A–A')
            ->assertSee('B = 8630')
            ->assertSee('roofLightbox')
            ->assertDontSee('Zeichnungen folgen');

        // Bemaßte Zeichnungsdaten (früher Technik-Tab) liegen jetzt auf der Übersicht.
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=uebersicht')
            ->assertSee('12 Felder à 714')
            ->assertSee('Gefälle 8° ≈ 141 mm/m → Rinne')
            ->assertSee('PRJ-2026-011 · DEMO Demo'); // Titelblock der Zeichnung
    }

    public function test_konfigurator_shows_prototype_positions_and_kalkulation(): void
    {
        // Vorbestell-Liste aus der Konfiguration → Tab «Material + Bestellungen».
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=material')
            ->assertOk()
            ->assertSee('Überdachung Trapez 8630×3500 mm')
            ->assertSee('Pfosten 110×110 · Weiß · RAL 9016')
            ->assertSee('Keil Links · Glas (Klar)');
        // Kalkulation lebt auf der Übersicht.
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011')
            ->assertSee('654 mm')            // Wandblende bei W=8630 (714−60)
            ->assertSee('Dach-Kalkulation');
    }

    public function test_berechnen_previews_without_persisting(): void
    {
        $antwort = $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-011/konfiguration', [
            'aktion' => 'berechnen', 'width' => 6000, 'depth' => 3500,
        ]);

        $antwort->assertRedirect(route('projekte.show', 'PRJ-2026-011'));

        // Vorschau sichtbar, Persistenz unverändert.
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=material')
            ->assertSee('6000×3500');
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011')
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

    public function test_reservierungen_anlegen_und_aufheben(): void
    {
        // PRJ-2026-033 hat keine Seed-Reservierungen → Materialliste leer
        $artikel = Artikel::query()->where('art_nr', 'GU-2')->firstOrFail();
        $verfuegbarVorher = $artikel->verfuegbar();

        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-033/reservierungen', [
            'artikel_id' => $artikel->id, 'menge' => 2,
        ])->assertRedirect(route('projekte.show', ['PRJ-2026-033', 'tab' => 'material']))
            ->assertSessionHas('toast', 'Material reserviert');

        $this->assertSame($verfuegbarVorher - 2, $artikel->fresh()->verfuegbar());
        $bewegung = Lagerbewegung::query()
            ->where('typ', 'Reservierung')->where('referenz', 'PRJ-2026-033')->firstOrFail();
        $this->assertSame(2, (int) $bewegung->menge);

        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-033?tab=material')
            ->assertSee($artikel->name)
            ->assertSee('Material reservieren');

        // Doppelte Reservierung inkrementiert dieselbe Zeile (unique projekt+artikel)
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-033/reservierungen', [
            'artikel_id' => $artikel->id, 'menge' => 1,
        ]);
        $reservierung = Reservierung::query()
            ->where('artikel_id', $artikel->id)
            ->whereRelation('projekt', 'nr', 'PRJ-2026-033')->firstOrFail();
        $this->assertSame(3.0, (float) $reservierung->menge);

        // Überreservierung → Guard-Toast
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-033/reservierungen', [
            'artikel_id' => $artikel->id, 'menge' => 99999,
        ])->assertSessionHas('toast', 'Nur '.$artikel->fresh()->verfuegbar().' verfügbar');

        // Aufheben: Gegenbewegung + Zeile weg
        $this->actingAs($this->benutzer)
            ->post('/projekte/PRJ-2026-033/reservierungen/'.$reservierung->id.'/loeschen')
            ->assertSessionHas('toast', 'Reservierung aufgehoben');
        $this->assertSame($verfuegbarVorher, $artikel->fresh()->verfuegbar());
        $this->assertTrue(Lagerbewegung::query()
            ->where('referenz', 'PRJ-2026-033 aufgehoben')->where('menge', -3)->exists());

        // Fremde Reservierung → 404
        $fremd = Reservierung::query()->whereRelation('projekt', 'nr', 'PRJ-2026-011')->firstOrFail();
        $this->actingAs($this->benutzer)
            ->post('/projekte/PRJ-2026-033/reservierungen/'.$fremd->id.'/loeschen')
            ->assertNotFound();
    }

    public function test_stammdaten_und_status_setzen(): void
    {
        // Termine + Projektleitung setzen → Kalender zeigt das Projekt
        $projektleitung = User::query()->where('email', 'projekt@lea.test')->firstOrFail();
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-035/stammdaten', [
            'termin_von' => '2026-08-10', 'termin_bis' => '2026-08-11',
            'projektleiter_id' => $projektleitung->id,
        ])->assertRedirect(route('projekte.show', 'PRJ-2026-035'))
            ->assertSessionHas('toast', 'Projektdaten gespeichert');

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-035')->firstOrFail();
        $this->assertSame('2026-08-10', $projekt->termin_von->toDateString());
        $this->assertSame($projektleitung->id, $projekt->projektleiter_id);
        $this->assertTrue($projekt->aktivitaeten()->where('titel', 'like', 'Montage-Termin%')->exists());

        $this->actingAs($this->benutzer)->get('/kalender?woche=2026-W33')
            ->assertSee('PRJ-2026-035');

        // Ende vor Beginn → Validierungsfehler
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-035/stammdaten', [
            'termin_von' => '2026-08-10', 'termin_bis' => '2026-08-01',
        ])->assertSessionHasErrors('termin_bis');

        // Statuswechsel: in_montage wird erreichbar, Stufe 5, Aktivität
        $this->actingAs($this->benutzer)->post('/projekte/PRJ-2026-035/status', ['status' => 'in_montage'])
            ->assertSessionHas('toast', 'Status: In Montage');
        $this->assertSame('in_montage', $projekt->fresh()->status->value);
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-035')
            ->assertSee('Status setzen:')
            ->assertSee('In Montage');
    }

    public function test_standardkonfigurations_hinweis(): void
    {
        // PRJ-2026-038 hat keine gespeicherte Konfiguration → Hinweis-Badge
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-038')
            ->assertSee('Standardkonfiguration — noch nicht erfasst');
        // PRJ-2026-011 hat eine → kein Hinweis
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011')
            ->assertDontSee('Standardkonfiguration — noch nicht erfasst');
    }
}
