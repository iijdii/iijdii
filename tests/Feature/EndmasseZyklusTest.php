<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndmasseZyklusTest extends TestCase
{
    use RefreshDatabase;

    private User $verkauf;

    private User $monteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
        $this->monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();
    }

    /** Projekt mit Dach + Wand + Schiebe (Phase 2) über den Autostart. */
    private function projektMitElementen(): Projekt
    {
        $this->actingAs($this->verkauf)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'ueberdachung', 'felder' => ['width' => 6000, 'depth' => 3000]],
        ]);
        $projekt = Projekt::query()->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/positionen', [
            'position' => ['produkt' => 'wand', 'felder' => [
                'breite_mm' => 3000, 'h_links_mm' => 2000, 'h_rechts_mm' => 2400, 'anzahl' => 3, 'glas' => 'VSG-Glas',
            ]],
        ]);
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/positionen', [
            'position' => ['produkt' => 'schiebe', 'felder' => [
                'breite_mm' => 4000, 'hoehe_mm' => 2200, 'anzahl' => 3,
            ]],
        ]);

        return $projekt;
    }

    public function test_monteur_erfasst_endmasse_je_position(): void
    {
        $projekt = $this->projektMitElementen();
        $wand = $projekt->positionen()->where('produkt', 'wand')->firstOrFail();

        // Montage-Modus zeigt die Positions-Sektion mit Sollwerten
        $this->actingAs($this->monteur)->get('/projekte/'.$projekt->nr.'/montage')
            ->assertOk()
            ->assertSee('Endmaße der Extras')
            ->assertSee('Position 2 — Wand / Festelement')
            ->assertSee('Endmaße speichern');

        // Endmaße speichern (Wand final 3010 breit, Höhen 2005/2420)
        $this->actingAs($this->monteur)->post('/projekte/'.$projekt->nr.'/montage/endmasse', [
            'endmasse' => [$wand->id => ['breite_mm' => 3010, 'h_links_mm' => 2005, 'h_rechts_mm' => 2420]],
        ])->assertSessionHas('toast', 'Endmaße gespeichert · 1 Position(en)');

        $wand->refresh();
        $this->assertSame(3010, $wand->endmasse['breite_mm']);
        $this->assertSame($this->monteur->id, $wand->endmasse_von);
        $this->assertNotNull($wand->endmasse_am);
    }

    public function test_nachbestellung_erzeugt_zuschnitt_aus_endmassen(): void
    {
        $projekt = $this->projektMitElementen();
        $wand = $projekt->positionen()->where('produkt', 'wand')->firstOrFail();
        $schiebe = $projekt->positionen()->where('produkt', 'schiebe')->firstOrFail();

        // Ohne Endmaße gesperrt
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/nachbestellung')
            ->assertSessionHas('toast', 'Keine Endmaße erfasst — zuerst im Montage-Modus eintragen');

        $this->actingAs($this->monteur)->post('/projekte/'.$projekt->nr.'/montage/endmasse', [
            'endmasse' => [
                $wand->id => ['breite_mm' => 3000, 'h_links_mm' => 2000, 'h_rechts_mm' => 2420],
                $schiebe->id => ['breite_mm' => 4050, 'hoehe_mm' => 2210],
            ],
        ]);

        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/nachbestellung');
        $bestellung = $projekt->bestellungen()->firstOrFail();
        $this->assertSame('Projekt '.$projekt->nr.' · Phase 2 (Endmaße)', $bestellung->titel);
        $this->assertNull($bestellung->lieferant_id);

        // Wand → 3 Zuschnitt-Panels (SeitenwandRechner: 985/970/985, Trapez)
        $panels = $bestellung->positionen()->where('typ', 'glas')->orderBy('pos')->get();
        $this->assertCount(3, $panels);
        $this->assertSame([985, 970, 985], $panels->pluck('breite_mm')->all());
        $this->assertSame('Trapez', $panels[0]->details['form']);
        $this->assertSame(2000, $panels[0]->details['hL']);
        $this->assertSame(2420, $panels[2]->details['hR']);
        $this->assertSame($wand->id, $panels[0]->projekt_position_id);

        // Schiebe → Schiebe-Position mit Endmaß
        $sp = $bestellung->positionen()->where('typ', 'schiebe')->firstOrFail();
        $this->assertSame(4050, $sp->breite_mm);
        $this->assertSame($schiebe->id, $sp->projekt_position_id);

        // Idempotent: zweiter Klick öffnet den bestehenden Entwurf.
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/nachbestellung')
            ->assertRedirect(route('bestellungen.show', $bestellung));
        $this->assertSame(1, $projekt->bestellungen()->count());

        // Material-Tab zeigt den Phase-2-Knopf
        $this->actingAs($this->verkauf)
            ->post('/projekte/'.$projekt->nr.'/aufmass-bestaetigung', ['aktion' => 'bestaetigen']);
        $this->actingAs($this->verkauf)->get('/projekte/'.$projekt->nr.'?tab=material')
            ->assertSee('Nachbestellung aus Endmaßen (Phase 2)');
    }

    public function test_abschluss_erst_nach_zweiter_abnahme(): void
    {
        $projekt = $this->projektMitElementen();

        // Mit Phase-2-Positionen und ohne zwei Abnahmen bleibt der Abschluss gesperrt.
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/status', ['status' => 'abgeschlossen'])
            ->assertSessionHas('toast', 'Abschluss erst nach der zweiten Abnahme (Phase 2)');
        $this->assertNotSame('abgeschlossen', $projekt->fresh()->status->value);

        // Zwei Abnahmeprotokolle → Abschluss möglich.
        $projekt->abnahmeprotokolle()->create(['nr' => 'AP-T-001', 'art' => 'ohne', 'datum' => now()->toDateString()]);
        $projekt->abnahmeprotokolle()->create(['nr' => 'AP-T-002', 'art' => 'ohne', 'datum' => now()->toDateString()]);
        $this->actingAs($this->verkauf)->post('/projekte/'.$projekt->nr.'/status', ['status' => 'abgeschlossen'])
            ->assertSessionHas('toast', 'Status: Abgeschlossen');

        // Ohne Phase 2 (nur Dach) reicht die erste Abnahme.
        $this->actingAs($this->verkauf)->post('/anfragen', [
            'kunde_id' => 1, 'status' => 'neu',
            'position' => ['produkt' => 'carport', 'felder' => ['width' => 5000, 'depth' => 3000]],
        ]);
        $nurDach = Projekt::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($this->verkauf)->post('/projekte/'.$nurDach->nr.'/status', ['status' => 'abgeschlossen'])
            ->assertSessionHas('toast', 'Status: Abgeschlossen');
    }
}
