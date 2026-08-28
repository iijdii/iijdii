<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LagerPageTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'lager@lea.test')->firstOrFail();
    }

    public function test_bestand_tab_shows_kpis_low_banner_and_badges(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/lager');

        $response->assertOk()
            ->assertSee('Artikel im Lager')
            ->assertSee('Lagerwert (EK)')
            ->assertSee('Artikel unter Mindestbestand')
            ->assertSee('Bestellvorschlag')
            ->assertSee('Auf Lager')
            ->assertSee('Nachbestellen')
            // PF-110110: bestand 4, min 12 → niedrig/leer-Zone, im Low-Banner
            ->assertSee('Alu-Pfosten 110×110');
    }

    public function test_category_filter_narrows_the_table(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/lager?tab=bestand&kategorie=glas');

        // Der Low-Banner nennt Artikel unabhängig vom Filter — deshalb
        // prüfen wir auf die Art-Nr., die nur in Tabellenzeilen steht.
        $response->assertOk()
            ->assertSee('GL-VSG8-K')
            ->assertDontSee('PF-110110');
    }

    public function test_wareneingang_tab_shows_rule_card_and_booking_states(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/lager?tab=wareneingang');

        $response->assertOk()
            ->assertSee('Automatische Zubuchung')
            ->assertSee('Wareneingang buchen')
            ->assertSee('LS-88214')
            ->assertSee('Eingebucht am')
            ->assertSee('Entwurf — beim Lieferanten noch nicht bestellt');
    }

    public function test_reservierungen_tab_groups_by_project(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/lager?tab=reservierungen');

        $response->assertOk()
            ->assertSee('PRJ-2026-011')
            ->assertSee('Stück reserviert')
            ->assertSee('Im Lager')
            ->assertSee('Kommissionierung öffnen');
    }

    public function test_bewegungen_tab_lists_the_journal(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/lager?tab=bewegungen');

        $response->assertOk()
            ->assertSee('Lagerbewegungen')
            ->assertSee('Inventur KW 28')
            ->assertSee('Korrektur');
    }

    public function test_artikel_modal_fragment_renders_stock_cells(): void
    {
        $artikel = \App\Models\Artikel::query()->where('art_nr', 'PF-110110')->firstOrFail();

        $response = $this->actingAs($this->benutzer)->get('/lager/artikel/'.$artikel->id);

        $response->assertOk()
            ->assertSee('Bestand')
            ->assertSee('Reserviert')
            ->assertSee('Verfügbar')
            ->assertSee('Mindestbestand')
            ->assertSee('Korrektur buchen')
            ->assertSee('Nachbestellen');
    }

    public function test_booking_endpoint_books_and_redirects_with_toast(): void
    {
        $response = $this->actingAs($this->benutzer)->post('/lager/wareneingang/BST-2026-109');

        $response->assertRedirect(route('lager', ['tab' => 'wareneingang']))
            ->assertSessionHas('toast');

        $this->assertStringContainsString(
            'Wareneingang BST-2026-109 gebucht',
            session('toast'),
        );
    }

    public function test_draft_order_cannot_be_booked_via_endpoint(): void
    {
        $response = $this->actingAs($this->benutzer)->post('/lager/wareneingang/BST-2026-107');

        $response->assertRedirect(route('lager', ['tab' => 'wareneingang']));
        $this->assertSame(0, \App\Models\Bestellung::query()->where('nr', 'BST-2026-107')->firstOrFail()->wareneingaenge()->count());
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/lager')->assertRedirect(route('login'));
    }
}
