<?php

namespace Tests\Feature;

use App\Models\Bestellung;
use App\Models\Dokument;
use App\Models\Projekt;
use App\Models\User;
use App\Support\GlasSkizze;
use App\Support\KonfiguratorRechner;
use App\Support\PdfDachZeichnung;
use App\Support\PdfSkizze;
use App\Support\RoofZeichnung;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
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

    public function test_bestellung_pdf_ohne_hinweise_und_unterschriften(): void
    {
        Bestellung::query()->where('nr', 'BST-2026-112')->update(['notizen' => 'Interne Notiz nur für LEA']);
        $daten = null;
        View::composer('bestellungen.pdf', function ($view) use (&$daten) {
            $daten = $view->getData();
        });

        $this->actingAs($this->benutzer)->get('/bestellungen/BST-2026-112/pdf')->assertOk();
        $html = view('bestellungen.pdf', $daten)->render();

        $this->assertStringContainsString('BESTELLUNG', $html);
        $this->assertStringNotContainsString('Interne Notiz nur für LEA', $html);
        $this->assertStringNotContainsString('Bestätigung Produktion / Lieferant', $html);
        $this->assertStringNotContainsString('Datum / Bearbeiter LEA', $html);
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

    public function test_projektmappe_enthaelt_zuschnitte_zeichnungen_und_extras(): void
    {
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $projekt->positionen()->create([
            'pos' => 2, 'gruppe' => 'extra', 'produkt' => 'wand', 'phase' => 2,
            'felder' => ['breite_mm' => 3000, 'h_links_mm' => 2000, 'h_rechts_mm' => 2400, 'anzahl' => 3, 'glas' => 'VSG 8 mm'],
        ]);
        $daten = null;
        View::composer('projekte.pdf', function ($view) use (&$daten) {
            $daten = $view->getData();
        });

        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011/pdf')->assertOk();
        $html = view('projekte.pdf', $daten)->render();

        // Stückliste mit Zuschnittmaßen (Sparren = Tiefe − 110)
        $this->assertStringContainsString('Materialliste mit Zuschnittmaßen', $html);
        $this->assertStringContainsString('Sparren/Träger (Profil 47047)', $html);
        $this->assertStringContainsString('L 3.390 mm', $html);
        // Verglasung mit Skizze, Wand mit Glaszuschnitt je Feld
        $this->assertStringContainsString('12 Felder · Zuschnitt 692 × 3.450 mm', $html);
        $this->assertStringContainsString('Pos. 2 · Wand / Festelement', $html);
        $this->assertStringContainsString('985 mm', $html);
        // Fünf bemaßte Zeichnungen + Glasfeld-Skizze als eingebettete SVG-Bilder
        $this->assertStringContainsString('Technische Zeichnungen', $html);
        $this->assertSame(6, substr_count($html, 'data:image/svg+xml;base64,'));
    }

    public function test_pdf_dachzeichnung_ohne_css_klassen_und_muster(): void
    {
        $kalk = KonfiguratorRechner::berechne(Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail()->konfiguration);
        $svg = base64_decode(substr(PdfDachZeichnung::dataUri(RoofZeichnung::ansicht('top', $kalk)), 26));

        $this->assertStringContainsString('<text', $svg);
        $this->assertStringContainsString('8630', $svg);
        $this->assertStringNotContainsString('class=', $svg);
        $this->assertStringNotContainsString('url(#', $svg);
        $this->assertStringNotContainsString('rgba(', $svg);
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
