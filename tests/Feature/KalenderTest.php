<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KalenderTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-15 09:00:00'); // Mittwoch, KW 29
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'projekt@lea.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_wochenraster_mit_live_ereignissen(): void
    {
        $this->actingAs($this->benutzer)->get('/kalender')
            ->assertOk()
            ->assertSee('KW 29')
            ->assertSee('13.07. – 19.07.2026')
            ->assertSee('cal-day today', false) // Mi 15.07.
            // Aufmaß-Besuchstermin ANF-2026-011 am 15.07., 10:00–12:00
            ->assertSee('ANF-2026-011')
            ->assertSee('Aufmaß')
            ->assertSee('10:00')
            // Montage-Spanne PRJ-2026-011: 18.–19.07. (Sa/So)
            ->assertSee('PRJ-2026-011')
            ->assertSee('ganztägig')
            ->assertSee('Keine Termine')
            ->assertSeeInOrder(['Montage', 'Aufmaß', 'Service', 'Puffer'])
            ->assertSee('Nächste Termine');
    }

    public function test_wochen_navigation_ueber_query_param(): void
    {
        $this->actingAs($this->benutzer)->get('/kalender?woche=2026-W27')
            ->assertOk()
            ->assertSee('KW 27')
            ->assertSee('PRJ-2026-033') // Montage 30.06.–02.07.
            ->assertDontSee('ANF-2026-011');
    }

    public function test_ungueltiger_param_faellt_auf_aktuelle_woche_zurueck(): void
    {
        $this->actingAs($this->benutzer)->get('/kalender?woche=quatsch')
            ->assertOk()
            ->assertSee('KW 29');
    }
    public function test_neuer_termin_setzt_projekt_spanne(): void
    {
        $this->actingAs($this->benutzer)->get('/kalender/termin')
            ->assertOk()
            ->assertSee('Neuer Montage-Termin')
            ->assertSee('PRJ-2026-035');

        $projekt = \App\Models\Projekt::query()->where('nr', 'PRJ-2026-035')->firstOrFail();
        $this->actingAs($this->benutzer)->post('/kalender/termin', [
            'projekt_id' => $projekt->id, 'termin_von' => '2026-08-12', 'termin_bis' => '2026-08-13',
        ])->assertRedirect(route('kalender', ['woche' => '2026-W33']))
            ->assertSessionHas('toast', 'Montage-Termin für PRJ-2026-035 eingetragen');

        $this->assertSame('2026-08-12', $projekt->fresh()->termin_von->toDateString());
        $this->actingAs($this->benutzer)->get('/kalender?woche=2026-W33')
            ->assertSee('PRJ-2026-035');

        // Ohne Bis-Datum: bis = von; Ende vor Beginn → Fehler
        $this->actingAs($this->benutzer)->post('/kalender/termin', [
            'projekt_id' => $projekt->id, 'termin_von' => '2026-08-20',
        ]);
        $this->assertSame('2026-08-20', $projekt->fresh()->termin_bis->toDateString());
        $this->actingAs($this->benutzer)->post('/kalender/termin', [
            'projekt_id' => $projekt->id, 'termin_von' => '2026-08-20', 'termin_bis' => '2026-08-01',
        ])->assertSessionHasErrors('termin_bis');
    }
}
