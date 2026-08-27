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
}
