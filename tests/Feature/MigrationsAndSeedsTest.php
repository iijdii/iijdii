<?php

namespace Tests\Feature;

use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\Lieferant;
use App\Models\Projekt;
use App\Models\Reservierung;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationsAndSeedsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_catalogue_is_fully_imported(): void
    {
        $this->assertSame(41, Artikel::count());
        $this->assertSame(3, Lieferant::count());
    }

    public function test_alias_lookup_resolves_free_text(): void
    {
        // 'Pfosten 90×90' ist ein Alias von PF-110110 (siehe Handoff).
        $artikel = Artikel::findeNachName('Pfosten 90×90');

        $this->assertNotNull($artikel);
        $this->assertSame('PF-110110', $artikel->art_nr);

        // Auch mit ASCII-x und wildem Whitespace.
        $this->assertSame('PF-110110', Artikel::findeNachName('  pfosten   90x90 ')?->art_nr);

        // Konfigurator-Ausgabe → Artikel (Bestellzeilen-Matching).
        $this->assertSame('35732', Artikel::findeNachName('Dachsparren 80×60 mm')?->art_nr);
    }

    public function test_delivered_order_is_booked_into_stock(): void
    {
        $bestellung = Bestellung::query()->where('nr', 'BST-2026-112')->firstOrFail();

        $this->assertSame('geliefert', $bestellung->status->value);
        $this->assertTrue($bestellung->positionen->every->eingelagert);
        $this->assertSame(1, $bestellung->wareneingaenge()->count());
        $this->assertSame('LS-88214', $bestellung->wareneingaenge()->first()->lieferschein_nr);
    }

    public function test_reservations_are_grouped_per_project(): void
    {
        $this->assertSame(20, Reservierung::count());
    }

    public function test_projektleiter_wird_geseedet(): void
    {
        $this->seed(DatabaseSeeder::class);

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $this->assertSame('verkauf@lea.test', $projekt->projektleiter?->email);
        $this->assertSame('Max Schneider', $projekt->projektleiter?->name);
    }

    public function test_demo_projekt_kennt_seine_anfrage(): void
    {
        $this->seed(DatabaseSeeder::class);

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $this->assertNotNull($projekt->anfrage_id);
        $this->assertSame($projekt->angebot?->anfrage_id, $projekt->anfrage_id);
    }
}
