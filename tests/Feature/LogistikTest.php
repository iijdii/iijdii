<?php

namespace Tests\Feature;

use App\Models\Bestellung;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogistikTest extends TestCase
{
    use RefreshDatabase;

    private User $lagerist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->lagerist = User::query()->where('email', 'lager@lea.test')->firstOrFail();
    }

    private function bestellung(string $nr): Bestellung
    {
        return Bestellung::query()->where('nr', $nr)->firstOrFail();
    }

    public function test_ruestliste_zeigt_nur_relevante_bestellungen(): void
    {
        $this->actingAs($this->lagerist)->get('/logistik')
            ->assertOk()
            ->assertSeeInOrder(['Alle', '3', 'Offen', '3', 'Teilweise', '0', 'Fertig', '0'])
            ->assertSee('BST-2026-112')
            ->assertSee('BST-2026-111')
            ->assertSee('BST-2026-109')
            ->assertDontSee('BST-2026-110') // geprüft
            ->assertDontSee('BST-2026-107') // Entwurf
            ->assertSee('6× Material')
            ->assertSee('0/6')
            ->assertSee('Kommissionierung')
            // Geseedete Tour
            ->assertSee('Touren · Fahrzeugbeladung')
            ->assertSee('Mercedes Sprinter · B-LEA 1234')
            ->assertSee('TOUR-2026-042');
    }

    public function test_kommissionierung_detail_zeigt_gruppen_und_positionstexte(): void
    {
        $this->actingAs($this->lagerist)->get('/logistik/BST-2026-112')
            ->assertOk()
            ->assertSee('Glas-Positionen')
            ->assertSee('Rechteck · 1078 × 3480 mm · VSG 8 mm klar')
            ->assertSee('8 Stk')
            ->assertSee('Positionen gepackt')
            ->assertSee('Kommissionierung abschließen')
            ->assertSee('Zurück zur Rüstliste');
    }

    public function test_toggle_persistiert_und_filter_teilweise_greift(): void
    {
        $bestellung = $this->bestellung('BST-2026-111');
        $position = $bestellung->positionen()->first();

        $this->actingAs($this->lagerist)
            ->post('/logistik/BST-2026-111/positionen/'.$position->id.'/toggle', [], ['referer' => '/logistik/BST-2026-111'])
            ->assertRedirect('/logistik/BST-2026-111');

        $this->assertNotNull($position->fresh()->kommissioniert_am);

        $this->actingAs($this->lagerist)->get('/logistik?filter=teil')
            ->assertSee('BST-2026-111')
            // Zeile von 112 fehlt (Nr. taucht nur noch in der Tour-Karte auf)
            ->assertDontSee("logistik/BST-2026-112'", false)
            ->assertSee('Teilweise');

        // Zweiter Tap nimmt das Häkchen zurück
        $this->actingAs($this->lagerist)->post('/logistik/BST-2026-111/positionen/'.$position->id.'/toggle');
        $this->assertNull($position->fresh()->kommissioniert_am);
    }

    public function test_fremde_position_liefert_404(): void
    {
        $fremd = $this->bestellung('BST-2026-112')->positionen()->first();

        $this->actingAs($this->lagerist)
            ->post('/logistik/BST-2026-111/positionen/'.$fremd->id.'/toggle')
            ->assertNotFound();
    }

    public function test_abschliessen_verlangt_vollstaendigkeit(): void
    {
        $this->actingAs($this->lagerist)->post('/logistik/BST-2026-111/abschliessen')
            ->assertRedirect(route('logistik.bestellung', 'BST-2026-111'))
            ->assertSessionHas('toast', 'Noch 6 Position(en) offen');

        $this->actingAs($this->lagerist)->post('/logistik/BST-2026-111/alle', ['markieren' => 1]);
        $this->assertSame('Gepackt', $this->auftragStatus('BST-2026-111'));

        $this->actingAs($this->lagerist)->post('/logistik/BST-2026-111/abschliessen')
            ->assertRedirect(route('logistik'))
            ->assertSessionHas('toast', 'Kommissionierung BST-2026-111 abgeschlossen');

        // Zurücksetzen
        $this->actingAs($this->lagerist)->post('/logistik/BST-2026-111/alle', ['markieren' => 0]);
        $this->assertSame(0, $this->bestellung('BST-2026-111')->positionen()->whereNotNull('kommissioniert_am')->count());
    }

    public function test_notiz_speichern_und_loeschen(): void
    {
        $position = $this->bestellung('BST-2026-109')->positionen()->first();

        $this->actingAs($this->lagerist)
            ->post('/logistik/BST-2026-109/positionen/'.$position->id.'/notiz', ['notiz' => 'Vorsicht, Kante beschädigt'])
            ->assertRedirect(route('logistik.bestellung', 'BST-2026-109'))
            ->assertSessionHas('toast', 'Kommentar gespeichert');
        $this->assertSame('Vorsicht, Kante beschädigt', $position->fresh()->kommissionier_notiz);

        $this->actingAs($this->lagerist)->get('/logistik/BST-2026-109')
            ->assertSee('Vorsicht, Kante beschädigt');

        $this->actingAs($this->lagerist)
            ->post('/logistik/BST-2026-109/positionen/'.$position->id.'/notiz', ['notiz' => ' '])
            ->assertSessionHas('toast', 'Kommentar gelöscht');
        $this->assertNull($position->fresh()->kommissionier_notiz);
    }

    public function test_tour_erstellen(): void
    {
        $this->actingAs($this->lagerist)->post('/logistik/touren', [])
            ->assertRedirect(route('logistik'))
            ->assertSessionHas('toast', 'Keine Aufträge ausgewählt');
        $this->assertSame(1, Tour::query()->count());

        $this->actingAs($this->lagerist)->post('/logistik/touren', [
            'bestellungen' => [$this->bestellung('BST-2026-109')->id],
        ])->assertSessionHas('toast', 'Tour mit 1 Aufträgen erstellt');

        $tour = Tour::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('TOUR-2026-043', $tour->nr);
        $this->assertSame(['BST-2026-109'], $tour->bestellungen->pluck('nr')->all());
    }

    public function test_tour_ansicht_zeigt_auftraege_und_ladefortschritt(): void
    {
        $this->actingAs($this->lagerist)->get('/logistik/touren/TOUR-2026-042')
            ->assertOk()
            ->assertSee('TOUR-2026-042')
            ->assertSee('Mercedes Sprinter · B-LEA 1234')
            ->assertSee('2 Aufträge')
            ->assertSee('Ladedatum 15.07. · Team Berlin K1')
            ->assertSee('BST-2026-112 — Dachverglasung', false)
            ->assertSee('Positionen geladen')
            ->assertSee('0/7')
            ->assertSee('Lade-Modus öffnen')
            ->assertSee('Ladung abschließen');
    }

    public function test_lade_modus_tabs_und_lieferstopps(): void
    {
        $this->actingAs($this->lagerist)->get('/logistik/touren/TOUR-2026-042/lade')
            ->assertOk()
            ->assertSee('LADE-MODUS')
            ->assertSee('Beladung fertig · Abfahrt bestätigen');

        $this->actingAs($this->lagerist)->get('/logistik/touren/TOUR-2026-042/lade?tab=tour')
            ->assertOk()
            ->assertSee('Lieferstopps · 2 Adressen')
            ->assertSee('Musterstraße')
            ->assertSee('10115 Berlin')
            ->assertSee('tel:+491702345678')
            ->assertSee('Fahrzeug');
    }

    public function test_abfahrt_und_ladung_verlangen_vollstaendigkeit(): void
    {
        $this->actingAs($this->lagerist)->post('/logistik/touren/TOUR-2026-042/abfahrt')
            ->assertRedirect(route('logistik.lade', 'TOUR-2026-042'))
            ->assertSessionHas('toast', 'Noch 7 Position(en) nicht geladen');

        $this->actingAs($this->lagerist)->post('/logistik/touren/TOUR-2026-042/abschliessen')
            ->assertSessionHas('toast', 'Noch 7 Position(en) offen');

        $this->actingAs($this->lagerist)->post('/logistik/touren/TOUR-2026-042/alle', ['markieren' => 1]);

        $this->actingAs($this->lagerist)->post('/logistik/touren/TOUR-2026-042/abfahrt')
            ->assertRedirect(route('logistik'))
            ->assertSessionHas('toast', 'Abfahrt bestätigt · TOUR-2026-042 unterwegs');

        $this->actingAs($this->lagerist)->post('/logistik/touren/TOUR-2026-042/abschliessen')
            ->assertSessionHas('toast', 'Ladung TOUR-2026-042 abgeschlossen — Fahrzeug beladen');
    }

    /** Status-Badge der Tabellenzeile (Anker: onclick-URL der Zeile). */
    private function auftragStatus(string $nr): string
    {
        $html = $this->actingAs($this->lagerist)->get('/logistik')->getContent();
        preg_match('/logistik\/'.$nr.'\'.*?badge (b-\w+)">(\w+)</s', $html, $m);

        return $m[2] ?? '?';
    }
}
