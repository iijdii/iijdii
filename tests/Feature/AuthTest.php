<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'geheim1234']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'geheim1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'geheim1234']);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'falsch',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_monteur_cannot_open_einstellungen(): void
    {
        $monteur = User::factory()->role(Rolle::Monteur)->create();

        $this->actingAs($monteur)->get('/einstellungen')->assertForbidden();
    }

    public function test_projektleiter_and_admin_can_open_einstellungen(): void
    {
        $this->actingAs(User::factory()->role(Rolle::Projektleiter)->create())
            ->get('/einstellungen')->assertOk();

        $this->actingAs(User::factory()->role(Rolle::Admin)->create())
            ->get('/einstellungen')->assertOk();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
    public function test_rollen_matrix_beschraenkt_schreibrouten(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();
        $lager = User::query()->where('email', 'lager@lea.test')->firstOrFail();
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();

        // Monteur: keine Kundenpflege, aber Montage-Notizen
        $this->actingAs($monteur)->post('/kunden', [])->assertForbidden();
        $this->actingAs($monteur)->post('/projekte/PRJ-2026-011/montage/notizen', [
            'typ' => 'hinweis', 'text' => 'Rollen-Test',
        ])->assertRedirect();

        // Lager: keine Anfragen, aber Korrekturbuchung
        $this->actingAs($lager)->post('/anfragen', [])->assertForbidden();

        // Verkäufer: keine Lager-Korrektur, aber Angebots-Summe
        $artikel = \App\Models\Artikel::query()->firstOrFail();
        $this->actingAs($verkauf)
            ->post('/lager/artikel/'.$artikel->id.'/korrektur', ['menge' => 1, 'grund' => 'x'])
            ->assertForbidden();
        $this->actingAs($verkauf)
            ->post('/angebote/ANG-2026-069/summe', ['summe' => 1000])->assertRedirect();

        // Navigation: Monteur sieht Einstellungen nicht, Projektleitung schon
        $this->actingAs($monteur)->get('/dashboard')->assertDontSee('Einstellungen');
        $this->actingAs(User::query()->where('email', 'projekt@lea.test')->firstOrFail())
            ->get('/dashboard')->assertSee('Einstellungen');
    }
}
