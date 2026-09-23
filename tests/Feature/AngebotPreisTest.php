<?php

namespace Tests\Feature;

use App\Enums\AngebotStatus;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AngebotPreisTest extends TestCase
{
    use RefreshDatabase;

    private User $verkauf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    private function projektMitDach(): Projekt
    {
        $this->actingAs($this->verkauf)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000, 'depth' => 3000]],
        ]);

        return Projekt::query()->orderByDesc('id')->firstOrFail();
    }

    public function test_autostart_setzt_den_listenpreis_als_angebotssumme(): void
    {
        $projekt = $this->projektMitDach();

        // 6.000 × 3.000 mm, Deckung Default VSG-Glas → 7.070 €
        $this->assertSame('7070.00', $projekt->angebot->summe);
    }

    public function test_dach_aenderung_aktualisiert_den_entwurf(): void
    {
        $projekt = $this->projektMitDach();
        $dach = $projekt->positionen()->where('gruppe', 'dach')->firstOrFail();

        // Poly-Deckung, gleiches Maß → Poly-Matrix (5.520 €)
        $this->actingAs($this->verkauf)->put('/projekte/'.$projekt->nr.'/positionen/'.$dach->id, [
            'position' => ['produkt' => 'ueberdachung', 'felder' => [
                'width' => 6000, 'depth' => 3000, 'covering' => 'Polycarbonat',
            ]],
        ]);
        $this->assertSame('5520.00', $projekt->angebot->fresh()->summe);

        // Versendetes Angebot wird nicht mehr überschrieben
        $projekt->angebot->update(['status' => AngebotStatus::Versendet]);
        $this->actingAs($this->verkauf)->put('/projekte/'.$projekt->nr.'/positionen/'.$dach->id, [
            'position' => ['produkt' => 'ueberdachung', 'felder' => [
                'width' => 8000, 'depth' => 4000, 'covering' => 'Polycarbonat',
            ]],
        ]);
        $this->assertSame('5520.00', $projekt->angebot->fresh()->summe);
    }

    public function test_angebot_zeigt_listenpreis_mit_uebernehmen_knopf(): void
    {
        $projekt = $this->projektMitDach();
        $angebot = $projekt->angebot;

        // Summe manuell verändert → Hinweis + Knopf zum Übernehmen
        $angebot->update(['summe' => 9999]);
        $this->actingAs($this->verkauf)->get('/angebote/'.$angebot->nr)
            ->assertOk()
            ->assertSee('Listenpreis laut Preisliste')
            ->assertSee('Listenpreis übernehmen');

        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/summe', ['summe' => 7070])
            ->assertSessionHas('toast', 'Angebotssumme gespeichert');
        $this->assertSame('7070.00', $angebot->fresh()->summe);
    }
}
