<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialKatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    public function test_kpis_and_card_view_render(): void
    {
        $this->actingAs($this->benutzer)->get('/material-katalog')
            ->assertOk()
            ->assertSee('Artikel im Katalog')
            ->assertSee('7 Kategorien')
            ->assertSee('Sunshine · Solarlux · Würth')
            ->assertSee('Ø EK-Preis')
            ->assertSee('Bauteil-Zeichnungen hinterlegt')
            ->assertSee('Profile &amp; Bauteile', false)
            ->assertSee('verfügbar');
    }

    public function test_search_matches_name(): void
    {
        $this->actingAs($this->benutzer)->get('/material-katalog?q=rinne')
            ->assertOk()
            ->assertSee('Gigarinne (Regenrinne)')
            ->assertDontSee('PF-110110');
    }

    public function test_search_matches_category_label_with_umlaut(): void
    {
        // 'zubehör' trifft über das Kategorie-Label „Zubehör & Schiebe" —
        // Groß-/Kleinschreibung mit Umlaut (mb_strtolower).
        $this->actingAs($this->benutzer)->get('/material-katalog?q=ZUBEHÖR')
            ->assertOk()
            ->assertSee('SCH-EL');
    }

    public function test_chips_count_after_search(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/material-katalog?q=vsg');

        // Nur die 3 Glasartikel treffen → Chip „Alle" zeigt 3.
        $response->assertOk()->assertSee('Alle<span class="ct">3</span>', false);
    }

    public function test_empty_state(): void
    {
        $this->actingAs($this->benutzer)->get('/material-katalog?q=zzzz')
            ->assertOk()
            ->assertSee('Keine Treffer')
            ->assertSee('Suchbegriff anpassen oder Kategorie zurücksetzen');
    }

    public function test_list_view_shows_stock_badge(): void
    {
        $this->actingAs($this->benutzer)->get('/material-katalog?ansicht=liste')
            ->assertOk()
            ->assertSee('EK-Preis')
            ->assertSee('knapp');
    }

    public function test_cards_open_the_shared_article_modal(): void
    {
        $this->actingAs($this->benutzer)->get('/material-katalog')
            ->assertOk()
            ->assertSee('data-modal-url', false)
            ->assertSee('/lager/artikel/', false);
    }
    public function test_artikel_anlegen_und_bearbeiten(): void
    {
        $lieferant = \App\Models\Lieferant::query()->where('name', 'Würth')->firstOrFail();

        $this->actingAs($this->benutzer)->post('/material-katalog', [
            'art_nr' => 'TEST-01', 'name' => 'Testartikel Schraube M8',
            'kategorie' => 'verbind', 'einheit' => 'Stück',
            'min_bestand' => 5, 'ek_preis' => 1.25, 'lieferant_id' => $lieferant->id,
            'bestand' => 40,
        ])->assertSessionHas('toast', 'Artikel TEST-01 angelegt');

        $artikel = \App\Models\Artikel::query()->where('art_nr', 'TEST-01')->firstOrFail();
        $this->assertSame(40, $artikel->bestand);

        // Doppelte Art.-Nr. wird abgelehnt
        $this->actingAs($this->benutzer)->post('/material-katalog', [
            'art_nr' => 'TEST-01', 'name' => 'Dublette', 'kategorie' => 'verbind',
            'einheit' => 'Stück', 'min_bestand' => 0, 'ek_preis' => 0, 'lieferant_id' => $lieferant->id,
        ])->assertSessionHasErrors('art_nr');

        // Update: ek_preis ändert sich, mitgesendeter bestand wird ignoriert
        $this->actingAs($this->benutzer)->put('/material-katalog/'.$artikel->id, [
            'art_nr' => 'TEST-01', 'name' => $artikel->name, 'kategorie' => 'verbind',
            'einheit' => 'Stück', 'min_bestand' => 5, 'ek_preis' => 1.5,
            'lieferant_id' => $lieferant->id, 'bestand' => 999,
        ])->assertSessionHas('toast', 'Artikel aktualisiert');

        $artikel->refresh();
        $this->assertSame('1.50', $artikel->ek_preis);
        $this->assertSame(40, $artikel->bestand); // unangetastet
    }

    public function test_aliase_verwalten_und_aufloesung(): void
    {
        $artikel = \App\Models\Artikel::query()->where('art_nr', 'PF-110110')->firstOrFail();

        $this->actingAs($this->benutzer)
            ->post('/material-katalog/'.$artikel->id.'/aliase', ['alias' => 'Pfosten  110×110 SUPER'])
            ->assertSessionHas('toast', 'Alias gespeichert');

        // Auflösung über Normalisierung (Groß/klein, Mehrfach-Leerzeichen, × → x)
        $this->assertTrue(\App\Models\Artikel::findeNachName('pfosten 110x110 super')?->is($artikel));

        // Duplikat (normalisiert) wird abgewiesen
        $this->actingAs($this->benutzer)
            ->post('/material-katalog/'.$artikel->id.'/aliase', ['alias' => 'PFOSTEN 110x110 super'])
            ->assertSessionHas('toast', 'Alias existiert bereits');

        $alias = $artikel->aliase()->where('alias', 'Pfosten  110×110 SUPER')->firstOrFail();
        $this->actingAs($this->benutzer)
            ->post('/material-katalog/'.$artikel->id.'/aliase/'.$alias->id.'/loeschen')
            ->assertSessionHas('toast', 'Alias entfernt');
        $this->assertNull(\App\Models\Artikel::findeNachName('pfosten 110x110 super'));

        // Fremder Artikel → 404
        $anderer = \App\Models\Artikel::query()->where('art_nr', '!=', 'PF-110110')->firstOrFail();
        $fremd = $anderer->aliase()->create(['alias' => 'Fremdalias XYZ']);
        $this->actingAs($this->benutzer)
            ->post('/material-katalog/'.$artikel->id.'/aliase/'.$fremd->id.'/loeschen')
            ->assertNotFound();
    }
}
