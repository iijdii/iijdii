<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\Artikel;
use App\Models\Lieferant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LieferantSeeder;
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
            ->assertSeeInOrder(['KD Überdachung GmbH', 'Rhein-Main Überdachungen', 'Solarlux', 'Sunshine', 'Würth'])
            ->assertSee('Profile · Glas · Schiebe-Elemente')
            ->assertSee('anfrage@rheinmain-ueberdachungen.de')
            ->assertSee('63477 Maintal')
            ->assertSee('info@kd-ueberdachung.de')
            ->assertSee('65428 Rüsselsheim am Main')
            ->assertSee('Fr. Behrens')
            ->assertSee('order@solarlux.example')
            ->assertSee('49324 Melle')
            ->assertSee('Artikel im Katalog')
            ->assertSee((string) Artikel::query()->count())
            ->assertSee('Offene Bestellungen')
            ->assertSee('>'.$sunshineArtikel.'<', false)
            ->assertSee('Lagerwert')
            // Deep-Link: Zeile filtert die Bestellungen nach Lieferant
            ->assertSee('bestellungen?lieferant=', false);
    }

    public function test_bestellungen_lassen_sich_nach_lieferant_filtern(): void
    {
        $this->seed(DatabaseSeeder::class);
        $benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
        $sunshine = Lieferant::query()->where('name', 'Sunshine')->firstOrFail();

        $this->actingAs($benutzer)->get('/bestellungen?lieferant='.$sunshine->id.'&ansicht=tabelle')
            ->assertOk()
            ->assertSee('BST-2026-111')  // Sunshine
            ->assertDontSee('BST-2026-112'); // Solarlux
    }

    public function test_rendert_ohne_seed_daten(): void
    {
        $benutzer = User::factory()->role(Rolle::Admin)->create();

        $this->actingAs($benutzer)->get('/lieferanten')
            ->assertOk()
            ->assertSee('Lieferanten');
    }

    public function test_verkaeufer_legt_lieferant_an_monteur_darf_nicht(): void
    {
        $this->seed(DatabaseSeeder::class);
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
        $monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();
        $daten = [
            'name' => 'Glas Müller', 'kundennummer' => 'K-777', 'sortiment' => 'VSG-Glas',
            'email' => 'bestellung@glas-mueller.example', 'plz' => '10115', 'stadt' => 'Berlin',
        ];

        $this->actingAs($monteur)->post('/lieferanten', $daten)->assertForbidden();

        $this->actingAs($verkauf)->post('/lieferanten', $daten)
            ->assertRedirect(route('lieferanten'))
            ->assertSessionHas('toast', 'Lieferant Glas Müller angelegt');

        $this->actingAs($verkauf)->get('/lieferanten')
            ->assertSee('Glas Müller')
            ->assertSee('Kundennr. K-777')
            ->assertSee('10115 Berlin');
    }

    public function test_bearbeiten_prueft_eindeutigen_namen_und_speichert(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@lea.test')->firstOrFail();
        $solarlux = Lieferant::query()->where('name', 'Solarlux')->firstOrFail();

        $this->actingAs($admin)->from('/lieferanten')->post('/lieferanten/'.$solarlux->id, ['name' => 'Sunshine'])
            ->assertSessionHasErrorsIn('lieferant_'.$solarlux->id, 'name');

        $this->actingAs($admin)->post('/lieferanten/'.$solarlux->id, [
            'name' => 'Solarlux AG', 'kundennummer' => 'SX-1', 'email' => 'neu@solarlux.example', 'telefon' => '0800 1', 'strasse' => '',
        ])->assertSessionHas('toast', 'Lieferant Solarlux AG gespeichert');

        $solarlux->refresh();
        $this->assertSame('SX-1', $solarlux->kundennummer);
        $this->assertSame('neu@solarlux.example', $solarlux->email);
        $this->assertNull($solarlux->strasse);
    }

    public function test_loeschen_nur_ohne_bestellungen_artikel_und_portal(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@lea.test')->firstOrFail();
        $sunshine = Lieferant::query()->where('name', 'Sunshine')->firstOrFail();
        $leer = Lieferant::query()->create(['name' => 'Testlieferant']);

        $this->actingAs($admin)->post('/lieferanten/'.$sunshine->id.'/loeschen')
            ->assertSessionHas('toast', 'Sunshine hat Bestellungen, Artikel oder Portal-Zugänge — Löschen nicht möglich');
        $this->assertModelExists($sunshine);

        $this->actingAs($admin)->post('/lieferanten/'.$leer->id.'/loeschen')
            ->assertSessionHas('toast', 'Lieferant Testlieferant gelöscht');
        $this->assertModelMissing($leer);
    }

    public function test_einrichtung_ueberschreibt_und_erneuert_gepflegte_lieferanten_nicht(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@lea.test')->firstOrFail();
        $solarlux = Lieferant::query()->where('name', 'Solarlux')->firstOrFail();
        $varisol = Lieferant::query()->where('name', 'like', '%Varisol%')->firstOrFail();

        $this->actingAs($admin)->post('/lieferanten/'.$solarlux->id, ['name' => 'Solarlux AG']);
        $this->actingAs($admin)->post('/lieferanten/'.$varisol->id, [
            'name' => $varisol->name, 'email' => 'eigene@varisol.example', 'kundennummer' => 'D.1',
        ]);
        $anzahl = Lieferant::query()->count();

        $this->seed(LieferantSeeder::class);

        $this->assertSame($anzahl, Lieferant::query()->count());
        $this->assertFalse(Lieferant::query()->where('name', 'Solarlux')->exists());
        $varisol->refresh();
        $this->assertSame('eigene@varisol.example', $varisol->email);
        $this->assertSame('D.1', $varisol->kundennummer);
    }
}
