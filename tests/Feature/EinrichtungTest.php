<?php

namespace Tests\Feature;

use App\Models\Kunde;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EinrichtungTest extends TestCase
{
    use RefreshDatabase;

    public function test_ohne_konfigurierten_token_immer_404(): void
    {
        config(['app.setup_token' => null]);

        $this->get('/einrichtung/irgendwas')->assertNotFound();
        $this->get('/einrichtung/')->assertNotFound();
    }

    public function test_falscher_token_404(): void
    {
        config(['app.setup_token' => 'geheim']);

        $this->get('/einrichtung/falsch')->assertNotFound();
    }

    public function test_korrekter_token_migriert_und_seedet(): void
    {
        config(['app.setup_token' => 'geheim']);

        $this->get('/einrichtung/geheim')
            ->assertOk()
            ->assertSee('Einrichtung abgeschlossen');

        $this->assertTrue(User::query()->where('email', 'admin@lea.test')->exists());
    }

    public function test_erneuter_aufruf_stellt_geloeschte_demo_daten_nicht_wieder_her(): void
    {
        config(['app.setup_token' => 'geheim']);
        $this->get('/einrichtung/geheim')->assertOk();

        // Betreiber räumt auf: Demo-Kunde gelöscht, Passwort geändert.
        $kunde = Kunde::query()->where('kunden_nr', 'K-1071')->firstOrFail();
        $admin = User::query()->where('email', 'admin@lea.test')->firstOrFail();
        $this->actingAs($admin)->post('/kunden/K-1071/loeschen', ['bestaetigt' => 1]);
        $admin->update(['password' => 'mein-neues-passwort']);

        // Nach einem Patch erneut aufgerufen: nur Migrationen, keine Demo-Daten.
        $this->get('/einrichtung/geheim')
            ->assertOk()
            ->assertSee('Demo-Daten übersprungen');

        $this->assertNull(Kunde::query()->find($kunde->id));
        $this->assertFalse(Kunde::query()->where('kunden_nr', 'K-1071')->exists());
        $this->assertTrue(Hash::check('mein-neues-passwort', $admin->fresh()->password));
    }
}
