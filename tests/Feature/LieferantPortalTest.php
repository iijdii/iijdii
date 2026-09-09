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

    public function test_portal_zeigt_nur_eigene_bestellte_vorgaenge(): void
    {
        $eigene = $this->eigene(BestellungStatus::Bestellt);
        $eigenerEntwurf = $this->eigene(BestellungStatus::Entwurf);
        $fremde = Bestellung::query()
            ->where('lieferant_id', '!=', $this->portal->lieferant_id)
            ->whereNotNull('lieferant_id')->firstOrFail();

        $this->actingAs($this->portal)->get('/bestellungen')
            ->assertOk()
            ->assertSee($eigene->nr)
            ->assertDontSee($eigenerEntwurf->nr)
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
            ->assertSee('Bereit melden')
            ->assertDontSee($eigene->kunde->anzeigename);
    }

    public function test_lieferant_meldet_bereit_aber_nichts_anderes(): void
    {
        $eigene = $this->eigene(BestellungStatus::Bestellt);

        // Nur Bestellt → Bereit ist erlaubt …
        $this->actingAs($this->portal)
            ->post('/bestellungen/'.$eigene->nr.'/status', ['status' => 'geliefert'])
            ->assertSessionHas('toast', 'Im Portal nur möglich: Bestellt → Bereit melden');
        $this->assertSame(BestellungStatus::Bestellt, $eigene->fresh()->status);

        $this->actingAs($this->portal)
            ->post('/bestellungen/'.$eigene->nr.'/status', ['status' => 'bereit']);
        $this->assertSame(BestellungStatus::Bereit, $eigene->fresh()->status);

        // … und auf einer bereits gemeldeten Bestellung gar nichts mehr.
        $this->actingAs($this->portal)
            ->post('/bestellungen/'.$eigene->nr.'/status', ['status' => 'bereit'])
            ->assertSessionHas('toast', 'Im Portal nur möglich: Bestellt → Bereit melden');
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
