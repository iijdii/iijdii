<?php

namespace Tests\Feature;

use App\Models\Artikel;
use App\Models\Lieferant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LieferantenTest extends TestCase
{
    use RefreshDatabase;

    public function test_uebersicht_zeigt_stammdaten_und_kennzahlen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();

        $sunshineArtikel = Artikel::query()
            ->where('lieferant_id', Lieferant::query()->where('name', 'Sunshine')->value('id'))
            ->count();

        $this->actingAs($benutzer)->get('/lieferanten')
            ->assertOk()
            ->assertSeeInOrder(['Solarlux', 'Sunshine', 'Würth'])
            ->assertSee('Fr. Behrens')
            ->assertSee('order@solarlux.example')
            ->assertSee('49324 Melle')
            ->assertSee('Artikel im Katalog')
            ->assertSee((string) Artikel::query()->count())
            ->assertSee('Offene Bestellungen')
            ->assertSee('>'.$sunshineArtikel.'<', false)
            ->assertSee('Lagerwert');
    }

    public function test_rendert_ohne_seed_daten(): void
    {
        $benutzer = User::factory()->role(\App\Enums\Rolle::Admin)->create();

        $this->actingAs($benutzer)->get('/lieferanten')
            ->assertOk()
            ->assertSee('Lieferanten');
    }
}
