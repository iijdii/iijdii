<?php

namespace Tests\Feature;

use App\Models\Bestellung;
use App\Models\Lieferant;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BestellungAusPositionenTest extends TestCase
{
    use RefreshDatabase;

    private User $verkauf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    /** Frisches Projekt über den Anfrage-Autostart, W=6000 / T=3000. */
    private function frischesProjekt(): Projekt
    {
        $this->actingAs($this->verkauf)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000, 'depth' => 3000]],
        ]);

        return Projekt::query()->orderByDesc('id')->firstOrFail();
    }

    public function test_entwurf_entsteht_aus_phase1_positionen(): void
    {
        $projekt = $this->frischesProjekt();
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);

        $antwort = $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');

        $bestellung = $projekt->bestellungen()->firstOrFail();
        $antwort->assertRedirect(route('bestellungen.show', $bestellung))
            ->assertSessionHas('toast', 'Entwurf '.$bestellung->nr.' erstellt — bitte Lieferant wählen');

        $this->assertSame('entwurf', $bestellung->status->value);
        $this->assertNull($bestellung->lieferant_id);
        $this->assertSame($projekt->kunde_id, $bestellung->kunde_id);

        // Glasregeln des Betreibers: W=6000 → 8 Felder, Achsmaß 750,
        // Glas 728 × 2.940; Pfosten rec=3, Sparren 9.
        $glas = $bestellung->positionen()->where('typ', 'glas')->firstOrFail();
        $this->assertSame(8.0, (float) $glas->menge);
        $this->assertSame(728, $glas->breite_mm);
        $this->assertSame(2940, $glas->hoehe_mm);
        $this->assertSame('live', $glas->details['quelle']); // Badge «aus Projekt»

        $material = $bestellung->positionen()->where('typ', 'material')->orderBy('pos')->get();
        $this->assertSame(['Pfosten 110×110 · Weiß · RAL 9016', 'Dachsparren 80×60 mm'], $material->pluck('bezeichnung')->all());
        $this->assertSame([3.0, 9.0], $material->map(fn ($p) => (float) $p->menge)->all());
        $this->assertNotNull($material[0]->artikel_id); // Alias «Pfosten 110×110»

        // Rückverfolgbarkeit: alle Positionen zeigen auf die Dach-Projektposition.
        $dach = $projekt->positionen()->where('gruppe', 'dach')->firstOrFail();
        $this->assertSame([$dach->id], $bestellung->positionen()->pluck('projekt_position_id')->unique()->all());

        $this->assertTrue($projekt->aktivitaeten()
            ->where('titel', 'Bestell-Entwurf '.$bestellung->nr.' aus Positionen erstellt')->exists());

        // Detailseite rendert ohne Lieferant
        $this->actingAs($this->verkauf)->get('/bestellungen/'.$bestellung->nr)
            ->assertOk()
            ->assertSee('— Lieferant wählen —');
    }

    public function test_gesperrt_ohne_aufmass_und_ohne_duplikate(): void
    {
        $projekt = $this->frischesProjekt();

        // Harte Sperre ohne Bestätigung
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen')
            ->assertRedirect(route('projekte.show', [$projekt, 'tab' => 'konfig']))
            ->assertSessionHas('toast', 'Aufmaß nicht bestätigt — Bestellung gesperrt');
        $this->assertSame(0, $projekt->bestellungen()->count());

        // Nach Bestätigung: erster Klick erzeugt, zweiter öffnet den Entwurf.
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');
        $entwurf = $projekt->bestellungen()->firstOrFail();

        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen')
            ->assertRedirect(route('bestellungen.show', $entwurf))
            ->assertSessionHas('toast', 'Entwurf '.$entwurf->nr.' aus Positionen existiert bereits');
        $this->assertSame(1, $projekt->bestellungen()->count());
    }

    public function test_verkaeufer_waehlt_lieferant_im_entwurf_dann_erst_status(): void
    {
        $projekt = $this->frischesProjekt();
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');
        $bestellung = $projekt->bestellungen()->firstOrFail();

        // Ohne Lieferant verlässt der Status den Entwurf nicht.
        $this->actingAs($this->verkauf)->post('/bestellungen/'.$bestellung->nr.'/status', ['status' => 'geprueft'])
            ->assertSessionHas('toast', 'Bitte zuerst einen Lieferanten wählen');
        $this->assertSame('entwurf', $bestellung->fresh()->status->value);

        // Verkäufer weist den Lieferanten im Entwurf zu (Rollenfreigabe M10).
        $lieferant = Lieferant::query()->firstOrFail();
        $this->actingAs($this->verkauf)->put('/bestellungen/'.$bestellung->nr, [
            'titel' => $bestellung->titel, 'lieferant_id' => $lieferant->id,
            'kategorie' => 'gemischt', 'projekt_id' => $projekt->id,
        ])->assertSessionHas('toast', 'Bestellung aktualisiert');

        $bestellung->refresh();
        $this->assertSame($lieferant->id, $bestellung->lieferant_id);
        $this->assertSame($projekt->kunde_id, $bestellung->kunde_id); // Kunde folgt dem Projekt

        $this->actingAs($this->verkauf)->post('/bestellungen/'.$bestellung->nr.'/status', ['status' => 'geprueft'])
            ->assertSessionHas('toast', 'Status: Geprüft');
    }

    public function test_positionsloeschung_loest_verweise_in_bestellungen(): void
    {
        // App-seitiges nullOnDelete: nicht jede Server-Datenbank trägt
        // den Fremdschlüssel (Shared Hosting, errno 150).
        $projekt = $this->frischesProjekt();
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');
        $bestellung = $projekt->bestellungen()->firstOrFail();
        $dach = $projekt->positionen()->where('gruppe', 'dach')->firstOrFail();

        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/positionen/'.$dach->id.'/loeschen')
            ->assertSessionHas('toast', 'Position entfernt');

        $this->assertSame(0, $bestellung->positionen()->whereNotNull('projekt_position_id')->count());
        $this->assertSame(3, $bestellung->positionen()->count()); // Positionen selbst bleiben
    }

    public function test_konfig_tab_zeigt_bestellknopf_nur_mit_bestaetigung(): void
    {
        $projekt = $this->frischesProjekt();

        $this->actingAs($this->verkauf)->get('/projekte/'.$projekt->nr.'?tab=konfig')
            ->assertSee('Aufmaß offen')
            ->assertDontSee('Bestellung aus Positionen (Phase 1)');

        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);

        $this->actingAs($this->verkauf)->get('/projekte/'.$projekt->nr.'?tab=konfig')
            ->assertSee('Bestellung aus Positionen (Phase 1)');
    }

    public function test_manuelle_bestellung_ohne_positionslink_blockiert_keinen_entwurf(): void
    {
        $projekt = $this->frischesProjekt();
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);

        // Manuelle Bestellung am Projekt (ohne projekt_position_id) —
        // zählt nicht als Positions-Entwurf und blockiert die Erzeugung nicht.
        $this->actingAs($this->verkauf)->post('/bestellungen', [
            'titel' => 'Manuell nachbestellt', 'projekt_id' => $projekt->id,
            'lieferant_id' => Lieferant::query()->firstOrFail()->id,
        ]);
        $this->assertSame(1, $projekt->bestellungen()->count());

        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');

        $this->assertSame(2, $projekt->bestellungen()->count());
        $neu = Bestellung::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('Projekt '.$projekt->nr.' · Phase 1', $neu->titel);
    }
}
