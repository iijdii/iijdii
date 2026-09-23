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

    public function test_preise_je_position_und_rabatt_ergeben_die_summe(): void
    {
        $projekt = $this->projektMitDach();
        $angebot = $projekt->angebot;

        $this->actingAs($this->verkauf)->get('/angebote/'.$angebot->nr)
            ->assertOk()
            ->assertSee('Positionen &amp; Preise', false)
            ->assertSee('Listenpreis laut Preisliste')
            ->assertSee('Montage &amp; Lieferung', false)
            ->assertSee('Online-Annahme');

        // Montage 500 € + Rabatt 10 % → (7.070 + 500) × 0,9 = 6.813 €
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/preise', [
            'preise' => ['montage' => 500],
            'rabatt_prozent' => 10,
        ])->assertSessionHas('toast', 'Preise gespeichert');
        $this->assertSame('6813.00', $angebot->fresh()->summe);

        // Dach-Override schlägt den Listenpreis
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/preise', [
            'preise' => ['dach' => 8000, 'montage' => 500],
            'rabatt_prozent' => 0,
        ]);
        $this->assertSame('8500.00', $angebot->fresh()->summe);
    }

    public function test_online_annahme_per_token_friert_das_angebot_ein(): void
    {
        $projekt = $this->projektMitDach();
        $angebot = $projekt->angebot;
        $token = $angebot->accept_token;
        $this->assertNotNull($token);

        // Öffentliche Seite ohne Login
        $this->get('/angebot-annahme/'.$token)
            ->assertOk()
            ->assertSee('Angebot '.$angebot->nr)
            ->assertSee('Angebot verbindlich annehmen');
        $this->get('/angebot-annahme/falscher-token')->assertNotFound();

        // Annahme: Status, Zeitpunkt, IP + Aktivität am Projekt
        $this->post('/angebot-annahme/'.$token)
            ->assertRedirect(route('angebote.annahme', $token));
        $angebot->refresh();
        $this->assertSame(AngebotStatus::Angenommen, $angebot->status);
        $this->assertNotNull($angebot->angenommen_am);
        $this->assertNotNull($angebot->angenommen_ip);
        $this->get('/angebot-annahme/'.$token)->assertSee('Angebot angenommen');

        // Freeze: weder Summe noch Preise noch Konfigurator ändern die Summe
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/summe', ['summe' => 1])
            ->assertSessionHas('toast', 'Angenommene Angebote sind eingefroren');
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/preise', ['preise' => ['dach' => 1]])
            ->assertSessionHas('toast', 'Angenommene Angebote sind eingefroren');
        $this->assertSame('7070.00', $angebot->fresh()->summe);
    }

    public function test_abgelaufenes_angebot_kann_nicht_angenommen_werden(): void
    {
        $projekt = $this->projektMitDach();
        $angebot = $projekt->angebot;
        $angebot->update(['gueltig_bis' => now()->subDay()->toDateString()]);

        $this->get('/angebot-annahme/'.$angebot->accept_token)
            ->assertOk()->assertSee('Angebot abgelaufen');
        $this->post('/angebot-annahme/'.$angebot->accept_token);
        $this->assertNotSame(AngebotStatus::Angenommen, $angebot->fresh()->status);
    }
}
