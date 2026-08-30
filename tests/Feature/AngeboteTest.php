<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AngeboteTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    public function test_list_shows_quotes_with_status_badges(): void
    {
        $this->actingAs($this->benutzer)->get('/angebote')
            ->assertOk()
            ->assertSee('ANG-2026-010')
            ->assertSee('17.671,50 €')
            ->assertSee('Angenommen')
            ->assertSee('Versendet')
            ->assertSee('Abgelehnt');
    }

    public function test_linked_quote_row_points_to_the_project(): void
    {
        // ANG-2026-010 ist mit PRJ-2026-011 verknüpft; unverknüpfte Zeilen toasten.
        $this->actingAs($this->benutzer)->get('/angebote')
            ->assertSee('/projekte/PRJ-2026-011', false)
            ->assertSee('data-toast="Öffne ANG-2026-071"', false);
    }
}
