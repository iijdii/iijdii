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
}
