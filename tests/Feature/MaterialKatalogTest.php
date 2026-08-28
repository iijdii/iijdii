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
}
