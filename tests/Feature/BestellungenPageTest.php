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
    public function test_bestellung_anlegen_und_kopf_guard(): void
    {
        $lieferant = \App\Models\Lieferant::query()->where('name', 'Sunshine')->firstOrFail();

        $this->actingAs($this->benutzer)->post('/bestellungen', [
            'titel' => 'Ersatzteile Carport', 'lieferant_id' => $lieferant->id,
            'kategorie' => 'gemischt', 'liefertermin' => '2026-08-01',
        ])->assertRedirect(route('bestellungen.show', 'BST-2026-113'))
            ->assertSessionHas('toast', 'Bestellung BST-2026-113 angelegt (Entwurf)');

        $bestellung = \App\Models\Bestellung::query()->where('nr', 'BST-2026-113')->firstOrFail();
        $this->assertSame('entwurf', $bestellung->status->value);

        // Kopf editierbar im Entwurf
        $this->actingAs($this->benutzer)->put('/bestellungen/BST-2026-113', [
            'titel' => 'Ersatzteile Carport II', 'lieferant_id' => $lieferant->id,
        ])->assertSessionHas('toast', 'Bestellung aktualisiert');

        // Bestellte Bestellung nicht mehr editierbar
        $this->actingAs($this->benutzer)->put('/bestellungen/BST-2026-111', [
            'titel' => 'Hack', 'lieferant_id' => $lieferant->id,
        ])->assertSessionHas('toast', 'Nur im Entwurf/Geprüft bearbeitbar');
        $this->assertNotSame('Hack', \App\Models\Bestellung::query()->where('nr', 'BST-2026-111')->value('titel'));
    }
    public function test_positions_editor_mit_alias_aufloesung_und_einlagerung(): void
    {
        $lieferant = \App\Models\Lieferant::query()->where('name', 'Solarlux')->firstOrFail();
        $this->actingAs($this->benutzer)->post('/bestellungen', [
            'titel' => 'Nachbestellung Glas', 'lieferant_id' => $lieferant->id,
        ]);
        $bestellung = \App\Models\Bestellung::query()->where('nr', 'BST-2026-113')->firstOrFail();

        // Material mit Alias-Auflösung
        $this->actingAs($this->benutzer)->post('/bestellungen/BST-2026-113/positionen', [
            'typ' => 'material', 'bezeichnung' => 'VSG 8 mm klar', 'menge' => 4,
        ])->assertSessionHas('toast', 'Position hinzugefügt');
        $material = $bestellung->positionen()->where('typ', 'material')->firstOrFail();
        $this->assertNotNull($material->artikel_id);
        $artikel = $material->artikel;
        $bestandVorher = $artikel->bestand;

        // Glas Trapez → Details persistiert, Skizze rendert
        $this->actingAs($this->benutzer)->post('/bestellungen/BST-2026-113/positionen', [
            'typ' => 'glas', 'bezeichnung' => 'Keilfeld links', 'form' => 'Trapez',
            'breite_mm' => 2000, 'hL' => 2200, 'hR' => 1800, 'menge' => 1, 'glas' => 'VSG 8 mm klar',
        ]);
        $glas = $bestellung->positionen()->where('typ', 'glas')->firstOrFail();
        $this->assertSame(['form' => 'Trapez', 'hL' => 2200, 'hR' => 1800, 'glas' => 'VSG 8 mm klar', 'quelle' => 'manuell'], $glas->details);
        $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-113')
            ->assertSee('Keilfeld links')
            ->assertSee('polygon', false); // GlasSkizze

        // Löschen + Ownership-Guard
        $fremd = \App\Models\Bestellung::query()->where('nr', 'BST-2026-111')->firstOrFail()->positionen()->firstOrFail();
        $this->actingAs($this->benutzer)
            ->post('/bestellungen/BST-2026-113/positionen/'.$fremd->id.'/loeschen')->assertNotFound();
        $this->actingAs($this->benutzer)
            ->post('/bestellungen/BST-2026-113/positionen/'.$glas->id.'/loeschen')
            ->assertSessionHas('toast', 'Position entfernt');

        // E2E: geliefert → automatische Einlagerung des Materials
        $this->actingAs($this->benutzer)->post('/bestellungen/BST-2026-113/status', ['status' => 'geliefert']);
        $this->assertSame($bestandVorher + 4, $artikel->fresh()->bestand);
        $this->assertTrue(\App\Models\Lagerbewegung::query()
            ->where('referenz', 'BST-2026-113')->where('typ', 'Eingang')->exists());

        // Nach «geliefert» keine Positionsänderungen mehr
        $this->actingAs($this->benutzer)->post('/bestellungen/BST-2026-113/positionen', [
            'typ' => 'material', 'bezeichnung' => 'Silikon', 'menge' => 1,
        ])->assertSessionHas('toast', 'Nur im Entwurf/Geprüft bearbeitbar');
    }
}
