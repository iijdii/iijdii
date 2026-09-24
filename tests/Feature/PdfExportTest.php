<?php

namespace Tests\Feature;

use App\Models\Dokument;
use App\Models\Projekt;
use App\Models\User;
use App\Support\GlasSkizze;
use App\Support\PdfSkizze;
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

    public function test_angebot_pdf_ersetzt_die_geseedete_dokument_zeile(): void
    {
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $vorher = $projekt->dokumente()->where('dateiname', 'Angebot_ANG-2026-010.pdf')->firstOrFail();
        $this->assertNull($vorher->pfad); // Seed-Platzhalter ohne Datei

        $antwort = $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010/pdf');
        $antwort->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('Angebot_ANG-2026-010_DEMO-Demo.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());

        // Idempotent: Platzhalter ohne Kunden-Suffix ersetzt, kein Duplikat
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010/pdf');
        $this->assertSame(0, $projekt->dokumente()->where('dateiname', 'Angebot_ANG-2026-010.pdf')->count());
        $dokumente = $projekt->dokumente()->where('dateiname', 'Angebot_ANG-2026-010_DEMO-Demo.pdf')->get();
        $this->assertCount(1, $dokumente);
        $this->assertNotNull($dokumente->first()->pfad);
        Storage::assertExists($dokumente->first()->pfad);
    }

    public function test_angebot_pdf_ohne_projekt_streamt_ohne_dokument(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-071/pdf');
        $antwort->assertOk();
        $this->assertStringStartsWith('%PDF', $antwort->getContent());
        $this->assertSame(0, Dokument::query()->where('dateiname', 'Angebot_ANG-2026-071_Bauer-GmbH.pdf')->count());
    }

    public function test_bestellung_pdf(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-112/pdf');
        $antwort->assertOk()->assertDownload('Bestellung_BST-2026-112_DEMO-Demo.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());
        $this->assertSame(1, Dokument::query()->where('dateiname', 'Bestellung_BST-2026-112_DEMO-Demo.pdf')->count());
    }

    public function test_pdf_skizze_glas_traegt_masse_und_rohmass(): void
    {
        // Zuschnittskizze (Trapez) im Bestellungs-PDF: Polygon + beide Höhen + Rohmaß
        $uri = PdfSkizze::glas(GlasSkizze::position(1200, 2600, 2150, true));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('<polygon', $svg);
        $this->assertStringContainsString('stroke-dasharray', $svg);
        $this->assertStringContainsString('Rohmaß 1200×2600</text>', $svg);
        $this->assertStringContainsString('>1.200</text>', $svg);
        $this->assertStringContainsString('>2.600</text>', $svg);
        $this->assertStringContainsString('>2.150</text>', $svg);

        // Rechteck: kein Rohmaß, keine zweite Höhe
        $svg = base64_decode(substr(PdfSkizze::glas(GlasSkizze::position(1078, 3480, 3480, false)), 30));
        $this->assertStringNotContainsString('Rohmaß', $svg);
        $this->assertStringContainsString('>3.480</text>', $svg);
    }

    public function test_pdf_skizze_schiebe_traegt_fluegel_und_masse(): void
    {
        $svg = base64_decode(substr(PdfSkizze::schiebe(GlasSkizze::schiebe(4200, 2400, 4, 'center')), 30));
        // Nummerierte Flügel 1–4, Laufrichtungs-Pfeile, beide Maßketten-Labels
        $this->assertStringContainsString('>1</text>', $svg);
        $this->assertStringContainsString('>4</text>', $svg);
        $this->assertStringContainsString('>4.200</text>', $svg);
        $this->assertStringContainsString('>2.400</text>', $svg);
        $this->assertStringContainsString('<polyline', $svg);
    }

    public function test_lieferschein_pdf_nur_fuer_gebuchte_wareneingaenge(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/lager/wareneingang/BST-2026-112/lieferschein');
        $antwort->assertOk()->assertDownload('Lieferschein_LS-88214_DEMO-Demo.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());

        // Ohne Wareneingang: 404
        $this->actingAs($this->benutzer)->get('/lager/wareneingang/BST-2026-111/lieferschein')
            ->assertNotFound();
    }

    public function test_projektmappe_pdf(): void
    {
        $antwort = $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011/pdf');
        $antwort->assertOk()->assertDownload('Projektmappe_PRJ-2026-011_DEMO-Demo.pdf');
        $this->assertStringStartsWith('%PDF', $antwort->getContent());

        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011/pdf');
        $this->assertSame(1, Dokument::query()->where('dateiname', 'Projektmappe_PRJ-2026-011_DEMO-Demo.pdf')->count());
    }

    public function test_pdf_vorschau_liefert_inline_statt_download(): void
    {
        // ?ansicht=1 → inline (iframe-Vorschau im Fenster), Standard bleibt Download
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010/pdf?ansicht=1')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="Angebot_ANG-2026-010_DEMO-Demo.pdf"');

        // Die PDF-Knöpfe tragen das Vorschau-Attribut
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010')
            ->assertSee('data-pdf ', false);
        $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-112')
            ->assertSee('data-pdf ', false);
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
