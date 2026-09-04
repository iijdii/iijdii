<?php

namespace Tests\Feature;

use App\Models\Artikel;
use App\Models\Lagerbewegung;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LagerKorrekturTest extends TestCase
{
    use RefreshDatabase;

    private User $lagerist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->lagerist = User::query()->where('email', 'lager@lea.test')->firstOrFail();
    }

    public function test_korrektur_bucht_bestand_und_journal(): void
    {
        $artikel = Artikel::query()->where('art_nr', 'PF-110110')->firstOrFail(); // Bestand 4
        $vorher = $artikel->bestand;

        $this->actingAs($this->lagerist)
            ->post('/lager/artikel/'.$artikel->id.'/korrektur', ['menge' => 5, 'grund' => 'Inventur KW 36'])
            ->assertRedirect(route('lager'))
            ->assertSessionHas('toast', 'Korrektur gebucht · +5');

        $this->assertSame($vorher + 5, $artikel->fresh()->bestand);
        $bewegung = Lagerbewegung::query()->where('artikel_id', $artikel->id)
            ->where('typ', 'Korrektur')->firstOrFail();
        $this->assertSame('Inventur KW 36', $bewegung->referenz);
        $this->assertSame(5, (int) $bewegung->menge);

        // Journal-Tab zeigt die Zeile
        $this->actingAs($this->lagerist)->get('/lager?tab=bewegungen')
            ->assertSee('Korrektur')
            ->assertSee('Inventur KW 36');
    }

    public function test_negative_korrektur_und_guard(): void
    {
        $artikel = Artikel::query()->where('art_nr', 'PF-110110')->firstOrFail(); // Bestand 4

        $this->actingAs($this->lagerist)
            ->post('/lager/artikel/'.$artikel->id.'/korrektur', ['menge' => -2, 'grund' => 'Bruch'])
            ->assertSessionHas('toast', 'Korrektur gebucht · −2');
        $this->assertSame(2, $artikel->fresh()->bestand);

        // Unter null geht nicht
        $this->actingAs($this->lagerist)
            ->post('/lager/artikel/'.$artikel->id.'/korrektur', ['menge' => -3, 'grund' => 'Fehler'])
            ->assertSessionHas('toast', 'Korrektur würde den Bestand negativ machen');
        $this->assertSame(2, $artikel->fresh()->bestand);

        // Menge 0 abgelehnt
        $this->actingAs($this->lagerist)
            ->post('/lager/artikel/'.$artikel->id.'/korrektur', ['menge' => 0, 'grund' => 'Nix'])
            ->assertSessionHasErrors('menge');
    }
}
