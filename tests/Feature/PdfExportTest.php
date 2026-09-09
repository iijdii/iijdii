<?php

namespace Tests\Feature;

use App\Models\Dokument;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'admin@lea.test')->firstOrFail();
    }

    public function test_angebot_pdf_heilt_die_geseedete_dokument_zeile(): void
    {
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $vorher = $projekt->dokumente()->where('dateiname', 'Angebot_ANG-2026-010.pdf')->firstOrFail();
        $this->assertNull($vorher->pfad); // Seed-Platzhalter ohne Datei

        $antwort = $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010/pdf');
        $antwort->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('Angebot_ANG-2026-010.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());

        // Idempotent: gleiche Zeile aktualisiert, kein Duplikat
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010/pdf');
        $dokumente = $projekt->dokumente()->where('dateiname', 'Angebot_ANG-2026-010.pdf')->get();
        $this->assertCount(1, $dokumente);
        $this->assertNotNull($dokumente->first()->pfad);
        Storage::assertExists($dokumente->first()->pfad);
    }

    public function test_angebot_pdf_ohne_projekt_streamt_ohne_dokument(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-071/pdf');
        $antwort->assertOk();
        $this->assertStringStartsWith('%PDF', $antwort->getContent());
        $this->assertSame(0, Dokument::query()->where('dateiname', 'Angebot_ANG-2026-071.pdf')->count());
    }

    public function test_bestellung_pdf(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-112/pdf');
        $antwort->assertOk()->assertDownload('Bestellung_BST-2026-112.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());
        $this->assertSame(1, Dokument::query()->where('dateiname', 'Bestellung_BST-2026-112.pdf')->count());
    }

    public function test_lieferschein_pdf_nur_fuer_gebuchte_wareneingaenge(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/lager/wareneingang/BST-2026-112/lieferschein');
        $antwort->assertOk()->assertDownload('Lieferschein_LS-88214.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());

        // Ohne Wareneingang: 404
        $this->actingAs($this->benutzer)->get('/lager/wareneingang/BST-2026-111/lieferschein')
            ->assertNotFound();
    }

    public function test_projektmappe_pdf(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011/pdf');
        $antwort->assertOk()->assertDownload('Projektmappe_PRJ-2026-011.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());

        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011/pdf');
        $this->assertSame(1, Dokument::query()->where('dateiname', 'Projektmappe_PRJ-2026-011.pdf')->count());
    }

    public function test_stub_toasts_der_pdf_buttons_sind_ersetzt(): void
    {
        $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-112')
            ->assertDontSee('data-toast="PDF', false)
            ->assertSee('/bestellungen/BST-2026-112/pdf', false);
        $this->actingAs($this->benutzer)->get('/lager?tab=wareneingang')
            ->assertDontSee('data-toast="Lieferschein', false)
            ->assertSee('/bestellungen/BST-2026-112', false);
        // «PDF exportieren» lebt jetzt auf dem Dokumente-Tab.
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=dokumente')
            ->assertSee('/projekte/PRJ-2026-011/pdf', false);
    }
}
