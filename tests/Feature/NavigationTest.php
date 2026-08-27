<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Alle Modulrouten der Navigations-Shell antworten mit 200. */
    public function test_all_module_routes_render(): void
    {
        $user = User::factory()->role(Rolle::Admin)->create();

        $routen = [
            'dashboard', 'kunden', 'anfragen', 'angebote', 'projekte',
            'bestellungen', 'logistik', 'kalender', 'lager',
            'material-katalog', 'lieferanten', 'einstellungen',
        ];

        foreach ($routen as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')->assertRedirect(route('dashboard'));
    }
}
