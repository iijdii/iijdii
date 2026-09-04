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

    public function test_rows_link_to_quote_detail(): void
    {
        $this->actingAs($this->benutzer)->get('/angebote')
            ->assertSee('/angebote/ANG-2026-010', false)
            ->assertSee('/angebote/ANG-2026-071', false)
            ->assertDontSee('data-toast="Öffne', false);
    }

    public function test_detail_renders_positions_and_meta(): void
    {
        // ANG-2026-010 hängt am Projekt PRJ-2026-011 → Positionen aus dessen Konfiguration
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-010')
            ->assertOk()
            ->assertSee('ANG-2026-010')
            ->assertSee('17.671,50 €')
            ->assertSee('PRJ-2026-011')
            ->assertSee('Überdachung Trapez 8630×3500 mm')
            ->assertSee('Angenommen');

        // Unverknüpft und ohne Konfiguration: Hinweis statt Positionen
        $this->actingAs($this->benutzer)->get('/angebote/ANG-2026-071')
            ->assertOk()
            ->assertSee('Keine Konfiguration hinterlegt')
            ->assertSee('28.400,00 €');
    }
}
