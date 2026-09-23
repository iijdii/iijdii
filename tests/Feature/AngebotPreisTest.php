<?php

namespace Tests\Feature;

use App\Enums\AngebotStatus;
use App\Models\Angebot;
use App\Models\Projekt;
use App\Models\User;
use App\Support\AngebotsRechnung;
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
            ->assertSee('Position hinzufügen')
            ->assertSee('Online-Annahme')
            ->assertDontSee('Montage &amp; Lieferung', false);

        // Gesamtrabatt 10 % → 7.070 × 0,9 = 6.363 €
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/preise', [
            'rabatt_prozent' => 10,
        ])->assertSessionHas('toast', 'Preise gespeichert');
        $this->assertSame('6363.00', $angebot->fresh()->summe);

        // Dach-Override schlägt den Listenpreis; Rabatt je Position wirkt
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/preise', [
            'preise' => ['dach' => 8000],
            'rabatte' => ['dach' => 10],
            'rabatt_prozent' => 0,
        ]);
        $this->assertSame('7200.00', $angebot->fresh()->summe);
    }

    public function test_freie_positionen_hinzufuegen_und_entfernen(): void
    {
        $projekt = $this->projektMitDach();
        $angebot = $projekt->angebot;

        // Montage als freie Position: 7.070 + 500 = 7.570 €
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/positionen', [
            'titel' => 'Montage & Lieferung', 'menge' => 1, 'preis' => 500,
        ])->assertSessionHas('toast', 'Position hinzugefügt');
        $angebot->refresh();
        $this->assertSame('7570.00', $angebot->summe);
        $frei = $angebot->freie_positionen[0];

        // Dach lässt sich nicht entfernen, freie Position schon
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/positionen/entfernen', ['key' => 'dach'])
            ->assertSessionHas('toast', 'Die Dach-Position folgt dem Konfigurator und bleibt im Angebot');
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/positionen/entfernen', ['key' => 'f'.$frei['id']])
            ->assertSessionHas('toast', 'Position entfernt');
        $this->assertSame('7070.00', $angebot->fresh()->summe);
    }

    public function test_zuschlaege_nach_preisstandard(): void
    {
        // Freistehend + Konsolen + Milchglas → eigene Zuschlagspositionen
        $this->actingAs($this->verkauf)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => [
                'width' => 6000, 'depth' => 3000, 'mounting' => 'freistehend',
                'covering' => 'Glas', 'glasTrans' => 'Milch',
                'postMontage' => 'Pfostenhalter',
            ]],
        ]);
        $projekt = Projekt::query()->orderByDesc('id')->firstOrFail();
        $titel = array_column(AngebotsRechnung::fuer($projekt->angebot)['positionen'], 'titel');

        $this->assertTrue(collect($titel)->contains(fn (string $t) => str_contains($t, 'Zusätzliche Pfosten')));
        $this->assertTrue(collect($titel)->contains(fn (string $t) => str_starts_with($t, 'Unterzug')));
        $this->assertTrue(collect($titel)->contains(fn (string $t) => str_contains($t, 'Pfostenhalter')));
        $this->assertTrue(collect($titel)->contains(fn (string $t) => str_contains($t, 'Milchglas')));

        // Polycarbonat: kein Milchglas-Aufpreis
        $this->actingAs($this->verkauf)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => [
                'width' => 6000, 'depth' => 3000, 'covering' => 'Polycarbonat', 'glasTrans' => 'Milch',
            ]],
        ]);
        $poly = Projekt::query()->orderByDesc('id')->firstOrFail();
        $polyTitel = array_column(AngebotsRechnung::fuer($poly->angebot)['positionen'], 'titel');
        $this->assertFalse(collect($polyTitel)->contains(fn (string $t) => str_contains($t, 'Milchglas')));
    }

    public function test_status_laesst_sich_zuruecksetzen(): void
    {
        $projekt = $this->projektMitDach();
        $angebot = $projekt->angebot;
        $angebot->update(['status' => AngebotStatus::Angenommen]);

        // Angenommen → Entwurf hebt den Freeze auf
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/status', ['status' => 'entwurf'])
            ->assertSessionHas('toast', 'Status: Entwurf');
        $this->actingAs($this->verkauf)->post('/angebote/'.$angebot->nr.'/preise', [
            'preise' => ['dach' => 9000],
        ])->assertSessionHas('toast', 'Preise gespeichert');
        $this->assertSame('9000.00', $angebot->fresh()->summe);
    }

    public function test_extras_sind_eigene_positionen_statt_dach_beschreibung(): void
    {
        // Das Seed-Angebot trägt einen Keil als Extra in der Alt-Konfiguration.
        $angebot = Angebot::query()->where('nr', 'ANG-2026-010')
            ->firstOrFail()->load('projekt.positionen');
        $rechnung = AngebotsRechnung::fuer($angebot);

        // Dach beschreibt nur die eigenen Bauteile (Pfosten, Sparren, Dachfelder)
        $dach = $rechnung['positionen'][0];
        $this->assertSame('dach', $dach['key']);
        $this->assertCount(3, $dach['details']);
        $this->assertStringNotContainsString('Keil', implode(' · ', $dach['details']));

        // Das Extra (Keil) steht als eigene Position in der Liste
        $titel = array_column($rechnung['positionen'], 'titel');
        $this->assertTrue(collect($titel)->contains(fn (string $t) => str_starts_with($t, 'Keil')));
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
