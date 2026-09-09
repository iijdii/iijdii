<?php

namespace Tests\Feature;

use App\Models\MontageAufgabe;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MontagePageTest extends TestCase
{
    use RefreshDatabase;

    private User $monteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();
    }

    private function projekt(): Projekt
    {
        return Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
    }

    public function test_all_sections_render_with_derived_values(): void
    {
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-011/montage')
            ->assertOk()
            ->assertSee('MONTAGE-MODUS')
            ->assertSee('Objekt &amp; Termin', false)
            ->assertSee('Adresse &amp; Anfahrt', false)
            ->assertSee('Kernmaße')
            ->assertSee('Technische Zeichnungen')
            ->assertSee('Pfosten &amp; Verankerung', false)
            ->assertSee('Profile &amp; Konstruktion', false)
            ->assertSee('Verglasung')
            ->assertSee('Beleuchtung · LED')
            ->assertSee('Endmaße der Extras')
            ->assertSee('Notiz / Problem')
            ->assertSee('Zusatzmaterial')
            ->assertSee('Montage-Checkliste · Aufgaben je Termin')
            ->assertSee('654 mm')            // Wandblende bei W=8630 (714−60)
            ->assertSee('Keil-Elemente')
            ->assertSee('Termin geplant von')
            ->assertSee('Glas nur zu zweit mit Saugheber einsetzen');
    }

    public function test_section_three_renders_roof_drawings_with_lightbox(): void
    {
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-011/montage')
            ->assertOk()
            ->assertSee('Tippen zum Vergrößern')
            ->assertSee('B = 8630')
            ->assertSee('DRAUFSICHT')
            ->assertSee('roofLightbox')
            ->assertDontSee('Zeichnungen folgen');
    }

    public function test_aufmass_post_persists_and_shows_tolerance_classes(): void
    {
        $antwort = $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/aufmass', [
            'mess' => ['keil.A' => '3502,4', 'keil.B' => '540', 'keil.C' => 'unsinn'],
            'duebel_size' => '12 × 100 mm',
        ]);

        $antwort->assertRedirect();
        $this->assertStringContainsString('Aufmaß übermittelt · 2/4 Maße', session('toast'));

        $projekt = $this->projekt();
        $this->assertSame(3502.4, $projekt->aufmass['mess']['keil.A']);
        $this->assertArrayNotHasKey('keil.C', $projekt->aufmass['mess']);
        $this->assertSame('12 × 100 mm', $projekt->konfiguration['duebel']['size']);
        $this->assertTrue($projekt->aktivitaeten()->where('titel', 'like', 'Aufmaß übermittelt%')->exists());

        // +2 mm → ok (grün), keil.B Soll 520 → +20 mm → bad (rot)
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-011/montage')
            ->assertSee('+2 mm')
            ->assertSee('+20 mm')
            ->assertSee('ex-f ok', false)
            ->assertSee('ex-f bad', false);
    }

    public function test_led_toggle_persists_and_cap_is_enforced(): void
    {
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/led', ['pos' => 's1.0'])
            ->assertRedirect();
        $this->assertSame(['s1.0'], $this->projekt()->aufmass['led']);

        // 12 setzen, 13. ablehnen
        $projekt = $this->projekt();
        $aufmass = $projekt->aufmass;
        $aufmass['led'] = ['s1.0', 's1.1', 's1.2', 's2.0', 's2.1', 's2.2', 's3.0', 's3.1', 's3.2', 's4.0', 's4.1', 's4.2'];
        $projekt->update(['aufmass' => $aufmass]);

        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/led', ['pos' => 's5.0']);
        $this->assertCount(12, $this->projekt()->aufmass['led']);
        $this->assertSame('Laut Konfiguration sind nur 12 Spots vorgesehen', session('toast'));

        // Abwählen und Reset funktionieren
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/led', ['pos' => 's1.0']);
        $this->assertCount(11, $this->projekt()->aufmass['led']);
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/led', ['aktion' => 'reset']);
        $this->assertSame([], $this->projekt()->aufmass['led']);
    }

    public function test_notes_and_material_can_be_added_and_deleted(): void
    {
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/notizen', [
            'typ' => 'problem', 'text' => 'Wandanker sitzt in Fuge',
        ]);
        $notiz = $this->projekt()->montageNotizen()->firstOrFail();
        $this->assertSame('problem', $notiz->typ);
        $this->assertSame($this->monteur->id, $notiz->erstellt_von);

        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/notizen', [
            'typ' => 'hinweis', 'text' => '   ',
        ]);
        $this->assertSame('Bitte Text eingeben', session('toast'));

        $this->actingAs($this->monteur)
            ->post('/projekte/PRJ-2026-011/montage/notizen/'.$notiz->id.'/loeschen');
        $this->assertSame(0, $this->projekt()->montageNotizen()->count());

        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/material', [
            'bezeichnung' => 'Silikon neutral', 'menge' => '2',
        ]);
        $this->assertSame(1, $this->projekt()->montageZusatzmaterial()->count());
    }

    public function test_task_toggle_sets_erledigt_and_inline_add_works(): void
    {
        $aufgabe = MontageAufgabe::query()->where('titel', 'Verglasung einsetzen')->firstOrFail();

        $this->actingAs($this->monteur)
            ->post('/projekte/PRJ-2026-011/montage/aufgaben/'.$aufgabe->id.'/erledigt');
        $aufgabe->refresh();
        $this->assertNotNull($aufgabe->erledigt_am);
        $this->assertSame($this->monteur->id, $aufgabe->erledigt_von);

        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/aufgaben', [
            'datum' => '2026-07-19', 'titel' => 'Restglas einlagern',
        ])->assertRedirect();
        $neu = $this->projekt()->montageAufgaben()->where('titel', 'Restglas einlagern')->firstOrFail();
        $this->assertSame('vom Büro ergänzt', $neu->beschreibung);
    }

    public function test_foreign_child_records_are_rejected(): void
    {
        $fremd = Projekt::factory()->create();
        $notiz = $fremd->montageNotizen()->create(['typ' => 'hinweis', 'text' => 'fremd']);

        $this->actingAs($this->monteur)
            ->post('/projekte/PRJ-2026-011/montage/notizen/'.$notiz->id.'/loeschen')
            ->assertNotFound();
    }

    public function test_neuer_termin_und_aufgabe_loeschen(): void
    {
        // Neuer Tag auf PRJ-2026-011 (bisher 18./19.07.) → «Tag 3»
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/aufgaben', [
            'datum' => '2026-07-20', 'titel' => 'Restarbeiten & Feinreinigung',
        ])->assertSessionHas('toast', 'Aufgabe zum Termin hinzugefügt');
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-011/montage')
            ->assertSee('Tag 3')
            ->assertSee('Restarbeiten &amp; Feinreinigung', false)
            ->assertSee('Neuen Termin anlegen');

        // Ohne Datum → Guard-Toast, nichts angelegt
        $anzahl = $this->projekt()->montageAufgaben()->count();
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/montage/aufgaben', [
            'titel' => 'Ohne Datum',
        ])->assertSessionHas('toast', 'Bitte Datum und Aufgabe angeben');
        $this->assertSame($anzahl, $this->projekt()->montageAufgaben()->count());

        // Erster Tag auf einem Projekt GANZ ohne Aufgaben (PRJ-2026-038)
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-038/montage/aufgaben', [
            'datum' => '2026-08-03', 'titel' => 'Fundamente prüfen',
        ]);
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-038/montage')
            ->assertSee('Tag 1')
            ->assertSee('Fundamente prüfen');

        // Löschen + Ownership
        $aufgabe = $this->projekt()->montageAufgaben()->where('titel', 'Restarbeiten & Feinreinigung')->firstOrFail();
        $this->actingAs($this->monteur)
            ->post('/projekte/PRJ-2026-038/montage/aufgaben/'.$aufgabe->id.'/loeschen')
            ->assertNotFound();
        $this->actingAs($this->monteur)
            ->post('/projekte/PRJ-2026-011/montage/aufgaben/'.$aufgabe->id.'/loeschen')
            ->assertSessionHas('toast', 'Aufgabe entfernt');
        $this->assertSame($anzahl - 1, $this->projekt()->montageAufgaben()->count());
    }
}
