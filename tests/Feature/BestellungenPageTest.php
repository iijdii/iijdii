<?php

namespace Tests\Feature;

use App\Enums\BestellungStatus;
use App\Models\Bestellung;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BestellungenPageTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'projekt@lea.test')->firstOrFail();
    }

    public function test_list_shows_chips_cards_and_table_view(): void
    {
        $this->actingAs($this->benutzer)->get('/bestellungen')
            ->assertOk()
            ->assertSee('BST-2026-112')
            ->assertSee('Solarlux')
            ->assertSee('Positionen');

        $this->actingAs($this->benutzer)->get('/bestellungen?status=geliefert')
            ->assertOk()
            ->assertSee('BST-2026-112')
            ->assertDontSee('BST-2026-111');

        $this->actingAs($this->benutzer)->get('/bestellungen?ansicht=tabelle')
            ->assertOk()
            ->assertSee('Liefertermin');
    }

    public function test_delivered_order_detail_shows_banner_stepper_and_glass(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-112');

        $response->assertOk()
            ->assertSee('Geliefert &amp; eingelagert', false)
            ->assertSee('LS-88214')
            ->assertSee('polygon class="glp"', false)
            ->assertSee('× 8')
            ->assertSee('aus Projekt')
            // Stepper zeigt alle 7 Status
            ->assertSee('Entwurf')
            ->assertSee('Montiert')
            ->assertSee('Storniert');
    }

    public function test_mixed_order_detail_shows_trapez_schiebe_and_lager_column(): void
    {
        $response = $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-110');

        $response->assertOk()
            ->assertSee('rect class="rohm"', false)   // Rohmaß-Rahmen (Trapez)
            ->assertSee('LAUFSCHIENE')
            ->assertSee('Schiebe-Elemente')
            ->assertSee('Offen')
            ->assertSee('Notiz');
    }

    public function test_status_change_via_stepper_flashes_toast(): void
    {
        $response = $this->actingAs($this->benutzer)
            ->post('/bestellungen/BST-2026-111/status', ['status' => 'bereit']);

        $response->assertRedirect(route('bestellungen.show', 'BST-2026-111'))
            ->assertSessionHas('toast', 'Status: Bereit');

        $this->assertSame(
            BestellungStatus::Bereit,
            Bestellung::query()->where('nr', 'BST-2026-111')->firstOrFail()->status,
        );
    }

    public function test_setting_geliefert_books_the_order_automatically(): void
    {
        $response = $this->actingAs($this->benutzer)
            ->post('/bestellungen/BST-2026-109/status', ['status' => 'geliefert']);

        $bestellung = Bestellung::query()->where('nr', 'BST-2026-109')->firstOrFail();

        $response->assertRedirect(route('bestellungen.show', 'BST-2026-109'));
        $this->assertStringContainsString('Wareneingang BST-2026-109 gebucht', session('toast'));
        $this->assertSame(1, $bestellung->wareneingaenge()->count());
        $this->assertTrue($bestellung->positionen->every->eingelagert);
    }

    public function test_re_setting_geliefert_does_not_double_book(): void
    {
        $this->actingAs($this->benutzer)->post('/bestellungen/BST-2026-109/status', ['status' => 'geliefert']);
        $this->actingAs($this->benutzer)->post('/bestellungen/BST-2026-109/status', ['status' => 'bestellt']);
        $this->actingAs($this->benutzer)->post('/bestellungen/BST-2026-109/status', ['status' => 'geliefert']);

        $this->assertSame(
            1,
            Bestellung::query()->where('nr', 'BST-2026-109')->firstOrFail()->wareneingaenge()->count(),
        );
        $this->assertSame('Status: Geliefert', session('toast'));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->actingAs($this->benutzer)
            ->post('/bestellungen/BST-2026-111/status', ['status' => 'quatsch'])
            ->assertSessionHasErrors('status');
    }
}
