<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
