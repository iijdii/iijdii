<?php

namespace Tests\Feature;

use App\Enums\AnfrageStatus;
use App\Models\Anfrage;
use App\Models\Angebot;
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

    public function test_detail_zeigt_projektpositionen_statt_legacy_karten(): void
    {
        // PRJ-2026-011 trägt seine Dach-Position (Einheitssystem) —
        // die Anfrage zeigt die Positions-Karte statt der Legacy-Ansicht.
        $this->actingAs($this->benutzer)->get('/anfragen/ANF-2026-012')
            ->assertOk()
            ->assertSee('Positionen — Projekt PRJ-2026-011')
            ->assertSee('Position 1 — Überdachung')
            ->assertSee('Projekt PRJ-2026-011 öffnen') // Seed verknüpft ANF-012 → PRJ-011
            ->assertDontSee('Projekt erstellen');

        // Anfrage ohne Projekt behält Legacy-Konstruktionskarten und Erstellen-CTA.
        $this->actingAs($this->benutzer)->get('/anfragen/ANF-2026-011')
            ->assertOk()
            ->assertSee('Projekt erstellen');
    }

    public function test_detail_shows_draufsicht_schema_comment_and_priority(): void
    {
        $anfrage = Anfrage::query()->where('nummer', 'ANF-2026-012')->firstOrFail();
        $anfrage->update(['kommentar_intern' => 'Rückruf erst ab 17 Uhr', 'prioritaet' => 'hoch']);

        $this->actingAs($this->benutzer)->get('/anfragen/ANF-2026-012')
            ->assertOk()
            ->assertSee('Draufsicht')
            ->assertSee('DRAUFSICHT')        // Zeichnungs-SVG (RoofZeichnung top)
            ->assertSee('Rinne (Traufe)')
            ->assertSee('Interner Kommentar')
            ->assertSee('Rückruf erst ab 17 Uhr')
            ->assertSee('Priorität Hoch');

        // Ohne Kommentar, Priorität Normal: Karten/Badge fehlen, Seite bleibt 200
        $anfrage->update(['kommentar_intern' => null, 'prioritaet' => 'normal']);
        $this->actingAs($this->benutzer)->get('/anfragen/ANF-2026-012')
            ->assertOk()
            ->assertDontSee('Interner Kommentar')
            ->assertDontSee('Priorität Hoch');
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
        // Seed-Verknüpfung lösen: ANF-2026-012 dient hier als „frische" Anfrage.
        Projekt::query()->where('nr', 'PRJ-2026-011')->update(['anfrage_id' => null]);

        $antwort = $this->actingAs($this->benutzer)->post('/anfragen/ANF-2026-012/projekt');

        $projekt = Projekt::query()->orderByDesc('id')->first();

        $antwort->assertRedirect(route('projekte.show', $projekt))
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
        $this->assertSame('ANF-2026-012', $projekt->anfrage?->nummer); // Herkunft verknüpft
    }

    public function test_projekt_erstellen_ist_idempotent(): void
    {
        // ANF-2026-012 ist per Seed mit PRJ-2026-011 verknüpft: der Klick
        // legt kein Duplikat an, sondern führt ins bestehende Projekt.
        $vorher = Projekt::query()->count();

        $this->actingAs($this->benutzer)->post('/anfragen/ANF-2026-012/projekt')
            ->assertRedirect(route('projekte.show', 'PRJ-2026-011'))
            ->assertSessionHas('toast', 'Projekt PRJ-2026-011 ist bereits verknüpft');

        $this->assertSame($vorher, Projekt::query()->count());

        // Kartenansicht verlinkt das Projekt direkt.
        $this->actingAs($this->benutzer)->get('/anfragen?ansicht=karten')
            ->assertSee('Projekt öffnen');
    }

    public function test_anfrage_mit_position_startet_projekt_und_angebot(): void
    {
        $antwort = $this->actingAs($this->benutzer)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'carport', 'felder' => [
                'width' => 5400, 'depth' => 5400, 'thickness' => '16 mm',
            ]],
        ]);

        $anfrage = Anfrage::query()->orderByDesc('id')->first();
        $projekt = $anfrage->projekt;
        $angebot = $projekt->angebot;

        $antwort->assertSessionHas('toast',
            'Anfrage '.$anfrage->nummer.' → Projekt '.$projekt->nr.' + Angebot '.$angebot->nr);

        // Kette vollständig verknüpft
        $this->assertSame($anfrage->id, $projekt->anfrage_id);
        $this->assertSame($anfrage->id, $angebot->anfrage_id);
        $this->assertSame($angebot->id, $projekt->angebot_id);
        $this->assertSame('Carport', $anfrage->produkt_notiz);

        // Position gespeichert und nach konfiguration gespiegelt
        $this->assertSame('carport', $projekt->positionen()->first()->produkt->value);
        $this->assertSame(5400, $projekt->konfiguration['width']);
        $this->assertSame('Carport', $projekt->konfiguration['product']);
        $this->assertSame(5400, $angebot->konfiguration['width']);

        // Detail zeigt die Positions-Karte und den Projekt-Link
        $this->actingAs($this->benutzer)->get('/anfragen/'.$anfrage->nummer)
            ->assertOk()
            ->assertSee('Positionen — Projekt '.$projekt->nr)
            ->assertSee('Position 1 — Carport')
            ->assertSee('Projekt '.$projekt->nr.' öffnen');
    }

    public function test_anfrage_ohne_position_bleibt_lead_karte(): void
    {
        $this->actingAs($this->benutzer)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu', 'produkt_notiz' => 'Nur Beratung',
        ]);

        $anfrage = Anfrage::query()->orderByDesc('id')->first();
        $this->assertNull($anfrage->projekt);
        $this->assertSame('Nur Beratung', $anfrage->produkt_notiz);
    }

    public function test_absage_loescht_projekt_und_angebot(): void
    {
        $this->actingAs($this->benutzer)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000]],
        ]);
        $anfrage = Anfrage::query()->orderByDesc('id')->first();
        $projektNr = $anfrage->projekt->nr;
        $angebotId = $anfrage->projekt->angebot_id;

        $this->actingAs($this->benutzer)->post('/anfragen/'.$anfrage->nummer.'/absage')
            ->assertSessionHas('toast', 'Absage erfasst — Projekt & Angebot gelöscht');

        $anfrage = $anfrage->fresh();
        $this->assertSame('kein_interesse', $anfrage->status->value);
        $this->assertNull(Projekt::query()->where('nr', $projektNr)->first());
        $this->assertNull(Angebot::query()->find($angebotId));
    }

    public function test_absage_blockiert_bei_bestellungen(): void
    {
        // PRJ-2026-011 (Seed, verknüpft mit ANF-2026-012) hat Bestellungen.
        $this->actingAs($this->benutzer)->post('/anfragen/ANF-2026-012/absage')
            ->assertSessionHas('toast', 'Absage nicht möglich — am Projekt hängen bereits Bestellungen/Reservierungen');

        $this->assertNotNull(Projekt::query()->where('nr', 'PRJ-2026-011')->first());
    }
}
