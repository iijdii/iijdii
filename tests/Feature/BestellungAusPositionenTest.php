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

    /** Die beiden Phase-1-Entwürfe (Konstruktion / Glas) des Projekts. */
    private function entwuerfe(Projekt $projekt): array
    {
        return [
            $projekt->bestellungen()->where('titel', 'Projekt '.$projekt->nr.' · Phase 1 · Konstruktion (Überdachung)')->firstOrFail(),
            $projekt->bestellungen()->where('titel', 'Projekt '.$projekt->nr.' · Phase 1 · Glas (Dach)')->firstOrFail(),
        ];
    }

    public function test_entwuerfe_konstruktion_und_glas_aus_phase1_positionen(): void
    {
        $projekt = $this->frischesProjekt();
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);

        $antwort = $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');

        [$konstruktion, $glasBestellung] = $this->entwuerfe($projekt);
        $antwort->assertRedirect(route('projekte.show', [$projekt, 'tab' => 'material']))
            ->assertSessionHas('toast', 'Entwürfe '.$konstruktion->nr.' (Konstruktion) und '.$glasBestellung->nr.' (Glas) bereit — bitte Lieferanten wählen');
        $this->assertSame(2, $projekt->bestellungen()->count());

        foreach ([$konstruktion, $glasBestellung] as $b) {
            $this->assertSame('entwurf', $b->status->value);
            $this->assertNull($b->lieferant_id);
            $this->assertSame($projekt->kunde_id, $b->kunde_id);
        }
        $this->assertSame('aluminium', $konstruktion->kategorie);
        $this->assertSame('glas', $glasBestellung->kategorie);

        // Glas separat — KD-Regeln: W=6000 → 8 Felder, Glas 721 × 2.950.
        $this->assertSame(0, $konstruktion->positionen()->where('typ', 'glas')->count());
        $glas = $glasBestellung->positionen()->where('typ', 'glas')->firstOrFail();
        $this->assertSame(8.0, (float) $glas->menge);
        $this->assertSame(721, $glas->breite_mm);
        $this->assertSame(2950, $glas->hoehe_mm);
        $this->assertSame('live', $glas->details['quelle']);

        // Konstruktion = EINE Position «Überdachung» mit allen Bauteilen
        // und Zuschnittlängen innen.
        $this->assertSame(1, $konstruktion->positionen()->count());
        $ueberdachung = $konstruktion->positionen()->firstOrFail();
        $this->assertStringStartsWith('Überdachung', $ueberdachung->bezeichnung);
        $this->assertSame('Satz', $ueberdachung->einheit);
        $teile = collect($ueberdachung->details['komponenten']);
        $teil = fn (string $name) => $teile->first(fn ($t) => str_starts_with($t['name'], $name));
        $this->assertSame(3, $teil('Alu-Pfosten 110×110')['menge']);
        $this->assertSame(9, $teil('Sparren/Träger')['menge']);
        $this->assertSame(2890, $teil('Sparren/Träger')['laenge_mm']); // Tiefe − 110
        $this->assertSame(2950, $teil('Abdeckprofil Rundleiste')['laenge_mm']); // Tiefe − 50
        $this->assertSame(2950, $teil('Seitenabdeckprofil / Eckleiste')['laenge_mm']);
        $this->assertSame(7, $teil('Abdeckprofil Rundleiste')['menge']);
        $this->assertSame(6000, $teil('Gigarinne')['laenge_mm']);
        $this->assertSame(1, $teil('LED-Set 12 Spots')['menge']);

        // Rückverfolgbarkeit auf die Dach-Projektposition.
        $dach = $projekt->positionen()->where('gruppe', 'dach')->firstOrFail();
        $this->assertSame($dach->id, $ueberdachung->projekt_position_id);
        $this->assertSame($dach->id, $glas->projekt_position_id);

        // Detailseite zeigt die Bauteile mit Zuschnitt, ohne Lieferant.
        $this->actingAs($this->verkauf)->get('/bestellungen/'.$konstruktion->nr)
            ->assertOk()
            ->assertSee('— Lieferant wählen —')
            ->assertSee('Zuschnitt')
            ->assertSee('6.000 mm');
    }

    public function test_gesperrt_ohne_aufmass_und_neuaufbau_ohne_duplikate(): void
    {
        $projekt = $this->frischesProjekt();

        // Harte Sperre ohne Bestätigung
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen')
            ->assertRedirect(route('projekte.show', [$projekt, 'tab' => 'material']))
            ->assertSessionHas('toast', 'Aufmaß nicht bestätigt — Bestellung gesperrt');
        $this->assertSame(0, $projekt->bestellungen()->count());

        // Zweiter Klick baut die beiden Entwürfe neu auf, statt zu duplizieren;
        // manuell ergänzte Positionen bleiben.
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');
        [$konstruktion] = $this->entwuerfe($projekt);
        $this->actingAs($this->verkauf)->post('/bestellungen/'.$konstruktion->nr.'/positionen', [
            'typ' => 'material', 'bezeichnung' => 'Silikon', 'menge' => 2,
        ]);

        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');
        $this->assertSame(2, $projekt->bestellungen()->count());
        $this->assertSame(2, $konstruktion->positionen()->count()); // Überdachung + Silikon
    }

    public function test_verkaeufer_waehlt_lieferant_im_entwurf_dann_erst_status(): void
    {
        $projekt = $this->frischesProjekt();
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/bestellung-aus-positionen');
        [$bestellung] = $this->entwuerfe($projekt);

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
        [, $bestellung] = $this->entwuerfe($projekt);
        $dach = $projekt->positionen()->where('gruppe', 'dach')->firstOrFail();

        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/positionen/'.$dach->id.'/loeschen')
            ->assertSessionHas('toast', 'Position entfernt');

        $this->assertSame(0, $bestellung->positionen()->whereNotNull('projekt_position_id')->count());
        $this->assertSame(1, $bestellung->positionen()->count()); // Positionen selbst bleiben
    }

    public function test_material_tab_zeigt_bestellknopf_nur_mit_bestaetigung(): void
    {
        $projekt = $this->frischesProjekt();

        $this->actingAs($this->verkauf)->get('/projekte/'.$projekt->nr.'?tab=material')
            ->assertSee('Aufmaß offen')
            ->assertDontSee('Bestellung aus Positionen (Phase 1)');

        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);

        $this->actingAs($this->verkauf)->get('/projekte/'.$projekt->nr.'?tab=material')
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

        $this->assertSame(3, $projekt->bestellungen()->count());
        $this->assertCount(2, $this->entwuerfe($projekt));
    }
}
