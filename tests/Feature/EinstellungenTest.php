<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EinstellungenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_formular_zeigt_geseedete_toleranzen(): void
    {
        $admin = User::query()->where('email', 'admin@lea.test')->firstOrFail();

        $this->actingAs($admin)->get('/einstellungen')
            ->assertOk()
            ->assertSee('Aufmaß-Toleranzen')
            ->assertSee('value="5"', false)
            ->assertSee('value="15"', false)
            ->assertSee('Firmenstammdaten')
            ->assertSee(config('lea.firma'));
    }

    public function test_speichern_aktualisiert_settings(): void
    {
        $projektleitung = User::query()->where('email', 'projekt@lea.test')->firstOrFail();

        $this->actingAs($projektleitung)->post('/einstellungen', [
            'toleranz_gruen_mm' => 7, 'toleranz_gelb_mm' => 20,
        ])->assertRedirect(route('einstellungen'))
            ->assertSessionHas('toast', 'Einstellungen gespeichert');

        $this->assertSame(7, Setting::wert('toleranz_gruen_mm'));
        $this->assertSame(20, Setting::wert('toleranz_gelb_mm'));

        // Montage-Modus liest die neuen Schwellen (data-tol-* am Aufmaß-Formular)
        $this->actingAs($projektleitung)->get('/projekte/PRJ-2026-011/montage')
            ->assertSee('data-tol-gruen="7"', false)
            ->assertSee('data-tol-gelb="20"', false);
    }

    public function test_gelb_unter_gruen_wird_abgelehnt(): void
    {
        $admin = User::query()->where('email', 'admin@lea.test')->firstOrFail();

        $this->actingAs($admin)->post('/einstellungen', [
            'toleranz_gruen_mm' => 10, 'toleranz_gelb_mm' => 5,
        ])->assertSessionHasErrors('toleranz_gelb_mm');

        $this->assertSame(5, Setting::wert('toleranz_gruen_mm'));
    }

    public function test_nur_admin_und_projektleitung(): void
    {
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();

        $this->actingAs($verkauf)->get('/einstellungen')->assertForbidden();
        $this->actingAs($verkauf)->post('/einstellungen', [
            'toleranz_gruen_mm' => 1, 'toleranz_gelb_mm' => 2,
        ])->assertForbidden();
    }
}
