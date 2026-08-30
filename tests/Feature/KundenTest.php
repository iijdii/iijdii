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
}
