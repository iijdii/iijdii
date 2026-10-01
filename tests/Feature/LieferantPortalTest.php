<?php

namespace Tests\Feature;

use App\Enums\BestellungStatus;
use App\Models\Bestellung;
use App\Models\Lieferant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LieferantPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $portal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->portal = User::query()->where('email', 'lieferant@lea.test')->firstOrFail();
    }

    private function eigene(BestellungStatus $status): Bestellung
    {
        return Bestellung::query()
            ->where('lieferant_id', $this->portal->lieferant_id)
            ->where('status', $status)->firstOrFail();
    }

    public function test_portal_zeigt_eigene_vorgaenge_ab_geprueft(): void
    {
        $eigene = $this->eigene(BestellungStatus::Bestellt);
        $eigenerEntwurf = $this->eigene(BestellungStatus::Entwurf);
        $fremde = Bestellung::query()
            ->where('lieferant_id', '!=', $this->portal->lieferant_id)
            ->whereNotNull('lieferant_id')->firstOrFail();

        // Sobald LEA «geprüft» setzt, erscheint die Bestellung als «Neu».
        $eigenerEntwurf->update(['status' => BestellungStatus::Geprueft]);

        $this->actingAs($this->portal)->get('/bestellungen')
            ->assertOk()
            ->assertSee($eigene->nr)
            ->assertSee($eigenerEntwurf->nr)
            ->assertSee('Neu')
            ->assertSee('In Arbeit')
            ->assertSee('Abholbereit')
            ->assertDontSee($fremde->nr)
            ->assertSee('Meine Bestellungen')
            ->assertDontSee('Neue Bestellung');
    }

    public function test_portal_wird_von_allen_anderen_seiten_umgeleitet(): void
    {
        foreach (['/dashboard', '/projekte', '/kunden', '/lager', '/bestellungen/neu'] as $url) {
            $this->actingAs($this->portal)->get($url)->assertRedirect(route('bestellungen'));
        }

        // Interne Rollen bleiben unberührt.
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
        $this->actingAs($verkauf)->get('/dashboard')->assertOk();
    }

    public function test_fremde_und_interne_bestellungen_sind_unsichtbar(): void
    {
        $fremde = Bestellung::query()
            ->where('lieferant_id', '!=', $this->portal->lieferant_id)
            ->whereNotNull('lieferant_id')->firstOrFail();

        $this->actingAs($this->portal)->get('/bestellungen/'.$fremde->nr)->assertNotFound();
        $this->actingAs($this->portal)
            ->get('/bestellungen/'.$this->eigene(BestellungStatus::Entwurf)->nr)
            ->assertNotFound();

        $eigene = $this->eigene(BestellungStatus::Bestellt);
        $this->actingAs($this->portal)->get('/bestellungen/'.$eigene->nr)
            ->assertOk()
            ->assertSee('Ihr Status')
            ->assertSee('Fertig · abholbereit')
            ->assertDontSee($eigene->kunde->anzeigename);
    }

    public function test_lieferant_meldet_in_arbeit_und_abholbereit(): void
    {
        $neu = $this->eigene(BestellungStatus::Entwurf);
        $neu->update(['status' => BestellungStatus::Geprueft]);
        $this->travelTo(now()->setDate(2026, 9, 28)->setTime(8, 15));

        $this->actingAs($this->portal)
            ->post('/bestellungen/'.$neu->nr.'/status', ['status' => 'bestellt'])
            ->assertSessionHas('toast', 'Gemeldet: In Arbeit — LEA sieht es sofort');
        $neu->refresh();
        $this->assertSame(BestellungStatus::Bestellt, $neu->status);
        $this->assertNotNull($neu->in_arbeit_am);

        $this->travel(2)->days();
        $this->actingAs($this->portal)
            ->post('/bestellungen/'.$neu->nr.'/status', ['status' => 'bereit'])
            ->assertSessionHas('toast', 'Gemeldet: Abholbereit — LEA sieht es sofort');
        $neu->refresh();
        $this->assertSame(BestellungStatus::Bereit, $neu->status);

        // LEA sieht beide Meldungen mit Zeitpunkt (deutsche Zeit) — in der
        // Bestellung, in der Bestellliste und im Projekt.
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
        $this->actingAs($verkauf)->get('/bestellungen/'.$neu->nr)
            ->assertSee('Vom Lieferanten gemeldet:')
            ->assertSee('In Arbeit / bestellt seit 28.09.2026 10:15')
            ->assertSee('Abholbereit seit 30.09.2026 10:15');
        $this->actingAs($verkauf)->get('/bestellungen?ansicht=tabelle')
            ->assertSee('✓ Bereit')
            ->assertSee('Lieferant · seit 30.09. 10:15');
        $this->actingAs($verkauf)->get('/projekte/'.$neu->projekt->nr.'?tab=material')
            ->assertSee('Lieferant · seit 30.09. 10:15');

        // Versehen: zurück auf «In Arbeit» nimmt die Abholbereit-Meldung zurück
        $this->actingAs($this->portal)->post('/bestellungen/'.$neu->nr.'/status', ['status' => 'bestellt']);
        $this->assertNull($neu->fresh()->bereit_am);

        // Setzt LEA den Status selbst, entfällt die Lieferanten-Kennzeichnung.
        $this->actingAs($verkauf)->post('/bestellungen/'.$neu->nr.'/status', ['status' => 'bereit']);
        $this->assertFalse($neu->fresh()->vom_lieferanten);
    }

    public function test_lieferant_sieht_nur_die_bestellung_ohne_positionspflege(): void
    {
        $neu = $this->eigene(BestellungStatus::Entwurf);
        $neu->update(['status' => BestellungStatus::Geprueft]);
        $anzahl = $neu->positionen()->count();

        $this->actingAs($this->portal)->get('/bestellungen/'.$neu->nr)
            ->assertOk()
            ->assertSee('Ihr Status')
            ->assertDontSee('Positionen erfassen')
            ->assertDontSee('Hinzufügen')
            ->assertDontSee('pos-edit-', false);

        // Auch direkt abgeschickt entstehen keine Positionen
        $this->actingAs($this->portal)->post('/bestellungen/'.$neu->nr.'/positionen', [
            'typ' => 'material', 'bezeichnung' => 'Fremdposition', 'menge' => 1,
        ]);
        $this->assertSame($anzahl, $neu->positionen()->count());

        // LEA (Geprüft) pflegt die Positionen weiterhin
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
        $this->actingAs($verkauf)->get('/bestellungen/'.$neu->nr)->assertSee('Positionen erfassen');
    }

    public function test_lieferant_darf_keine_internen_status_setzen(): void
    {
        $eigene = $this->eigene(BestellungStatus::Bestellt);

        foreach (['geliefert', 'montiert', 'storniert', 'entwurf', 'geprueft'] as $status) {
            $this->actingAs($this->portal)
                ->post('/bestellungen/'.$eigene->nr.'/status', ['status' => $status])
                ->assertSessionHas('toast', 'Im Portal nur möglich: «In Arbeit» oder «Abholbereit» melden');
        }
        $this->assertSame(BestellungStatus::Bestellt, $eigene->fresh()->status);

        // Nach der Abholung (intern «geliefert») ist das Portal raus.
        $eigene->update(['status' => BestellungStatus::Geliefert]);
        $this->actingAs($this->portal)
            ->post('/bestellungen/'.$eigene->nr.'/status', ['status' => 'bereit'])
            ->assertSessionHas('toast', 'Im Portal nur möglich: «In Arbeit» oder «Abholbereit» melden');
    }

    public function test_login_leitet_lieferanten_ins_portal(): void
    {
        $this->post('/login', ['email' => 'lieferant@lea.test', 'password' => 'password'])
            ->assertRedirect(route('bestellungen'));
        $this->assertSame(
            Lieferant::query()->where('name', 'Sunshine')->value('id'),
            auth()->user()->lieferant_id,
        );
    }
}
