<?php

namespace Tests\Feature;


use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Vor dem Seed einfrieren: created_at der Anfragen = „heute".
        Carbon::setTestNow('2026-07-08 09:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'admin@lea.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private User $benutzer;

    public function test_kpis_are_computed_from_live_data(): void
    {
        $this->actingAs($this->benutzer)->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['Offene Anfragen', '6', '+3 diese Woche'], false)
            ->assertSeeInOrder(['Aktive Angebote', '3', '52.450 € Volumen'], false)
            ->assertSeeInOrder(['Akzeptiert (Monat)', '0', 'Quote 67 %'], false)
            ->assertSeeInOrder(['Aktive Projekte', '4', '1 in Montage'], false);
    }

    public function test_funnel_uses_live_counts_and_conversions(): void
    {
        $this->actingAs($this->benutzer)->get('/dashboard')
            ->assertSee('Vertriebs-Trichter')
            ->assertSee('Juli 2026')
            ->assertSeeInOrder(['Anfragen', 'Angebote', 'Projekte', 'In Montage'])
            ->assertSeeInOrder(['100 %', '67 %', '25 %']);
    }

    public function test_order_intake_bars_group_accepted_quotes_by_month(): void
    {
        // Angenommen: ANG-2026-010 (12.05., 17.671,50) + ANG-2026-064 (22.06., 42.100)
        $this->actingAs($this->benutzer)->get('/dashboard')
            ->assertSee('Auftragseingang')
            ->assertSee('6 Monate · k€')
            ->assertSeeInOrder(['Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul'])
            ->assertSee('>18</span>', false)  // Mai
            ->assertSee('>42</span>', false); // Juni
    }

    public function test_quotes_warnings_and_activity_cards(): void
    {
        $this->actingAs($this->benutzer)->get('/dashboard')
            ->assertSee('Letzte Angebote')
            ->assertSee('ANG-2026-071')
            ->assertSee('28.400,00 €')
            ->assertSee('Lager-Warnungen')
            ->assertSee('kritisch')
            ->assertSee('Alu-Pfosten 110×110')
            ->assertSee('Letzte Aktivität')
            ->assertSee('Anzahlung eingegangen');
    }

    public function test_termine_card_is_empty_today_but_shows_montage_on_the_18th(): void
    {
        $this->actingAs($this->benutzer)->get('/dashboard')
            ->assertSee('Heutige Termine')
            ->assertSee('08.07.2026')
            ->assertSee('Keine Termine heute.');

        Carbon::setTestNow('2026-07-18 09:00:00'); // PRJ-2026-011: Montage 18.–19.07.
        $this->actingAs($this->benutzer)->get('/dashboard')
            ->assertSee('DEMO Demo')
            ->assertSee('Montage')
            ->assertDontSee('Keine Termine heute.');
    }

    public function test_empty_states_render(): void
    {
        // Vollständig ungeseedet deckt NavigationTest ab; hier die Leerzustände.
        \App\Models\ProjektAktivitaet::query()->delete();

        $this->actingAs($this->benutzer)->get('/dashboard')
            ->assertOk()
            ->assertSee('Keine Termine heute.')
            ->assertSee('Noch keine Aktivitäten.');
    }
}
