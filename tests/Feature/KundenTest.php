<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KundenTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
    }

    public function test_list_shows_all_customers_with_status_badges(): void
    {
        $this->actingAs($this->benutzer)->get('/kunden')
            ->assertOk()
            ->assertSee('7 Einträge')
            ->assertSee('K-1071')
            ->assertSee('Bauer GmbH')
            ->assertSee('Gewerbe')
            ->assertSee('Lead');
    }

    public function test_detail_shows_contact_subtables_and_timeline(): void
    {
        $this->actingAs($this->benutzer)->get('/kunden/K-1071')
            ->assertOk()
            ->assertSee('DEMO Demo')
            ->assertSee('Kontaktdaten')
            ->assertSee('ANF-2026-012')        // verknüpfte Anfrage
            ->assertSee('ANG-2026-010')        // verknüpftes Angebot
            ->assertSee('PRJ-2026-011')        // verknüpftes Projekt
            ->assertSee('Kunde angelegt')      // Timeline-Abschluss
            ->assertSee('Neue Anfrage');
    }

    public function test_detail_handles_customer_without_relations(): void
    {
        // K-1060 (A. Neumann) hat Anfrage, aber keine Projekte.
        $this->actingAs($this->benutzer)->get('/kunden/K-1060')
            ->assertOk()
            ->assertSee('Keine Projekte vorhanden.');
    }
    public function test_kunde_anlegen_mit_laufender_nummer(): void
    {
        $erwartet = \App\Support\Nummern::kunde();

        $this->actingAs($this->benutzer)->post('/kunden', [
            'anzeigename' => 'Gartenwelt Nord GmbH', 'typ' => 'gewerbe', 'status' => 'Lead',
            'telefon' => '+49 30 1234567', 'stadt' => 'Berlin',
            'tags' => 'Neukunde, Carport',
        ])->assertRedirect(route('kunden.show', $erwartet))
            ->assertSessionHas('toast', 'Kunde '.$erwartet.' angelegt');

        $kunde = \App\Models\Kunde::query()->where('kunden_nr', $erwartet)->firstOrFail();
        $this->assertSame(['Neukunde', 'Carport'], $kunde->tags);

        // Validierung: ohne Anzeigename
        $this->actingAs($this->benutzer)->post('/kunden', ['typ' => 'privat', 'status' => 'Lead'])
            ->assertSessionHasErrors('anzeigename');
    }

    public function test_kunde_bearbeiten(): void
    {
        $kunde = \App\Models\Kunde::query()->where('kunden_nr', 'K-1071')->firstOrFail();

        $this->actingAs($this->benutzer)->put('/kunden/K-1071', [
            'anzeigename' => $kunde->anzeigename, 'typ' => $kunde->typ, 'status' => 'Aktiv',
            'telefon' => '+49 170 999000',
        ])->assertRedirect(route('kunden.show', 'K-1071'))
            ->assertSessionHas('toast', 'Kunde aktualisiert');

        $this->assertSame('+49 170 999000', $kunde->fresh()->telefon);
        $this->actingAs($this->benutzer)->get('/kunden/K-1071')->assertSee('+49 170 999000');
    }
}
