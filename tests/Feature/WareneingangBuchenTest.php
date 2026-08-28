<?php

namespace Tests\Feature;

use App\Enums\BestellungStatus;
use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\Lagerbewegung;
use App\Models\User;
use App\Models\Wareneingang;
use App\Services\LagerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WareneingangBuchenTest extends TestCase
{
    use RefreshDatabase;

    private LagerService $lager;

    private User $lagerist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->lager = app(LagerService::class);
        $this->lagerist = User::query()->where('email', 'lager@lea.test')->firstOrFail();
    }

    private function bestellung(string $nr): Bestellung
    {
        return Bestellung::query()->where('nr', $nr)->firstOrFail();
    }

    public function test_booking_a_mixed_order_stocks_all_resolved_articles(): void
    {
        // BST-2026-110 (geprueft): 2 Glasformen (VSG 8 mm klar), Schiebeanlage, Material.
        $bestellung = $this->bestellung('BST-2026-110');
        $glas = Artikel::query()->where('art_nr', 'GL-VSG8-K')->firstOrFail();
        $schiebe = Artikel::query()->where('art_nr', 'SCH-EL')->firstOrFail();
        $glasVorher = $glas->bestand;
        $schiebeVorher = $schiebe->bestand;

        $wareneingang = $this->lager->bucheWareneingang($bestellung, $this->lagerist);

        // Seed-Maximum ist LS-88214 → erste neue Nummer.
        $this->assertSame('LS-88215', $wareneingang->lieferschein_nr);

        // Beide Trapez-Glasfelder aggregieren über den Glastyp auf GL-VSG8-K.
        $glasMenge = (float) $wareneingang->positionen()->where('artikel_id', $glas->id)->sum('menge');
        $this->assertGreaterThanOrEqual(2, $glasMenge);
        $this->assertSame($glasVorher + (int) $glasMenge, $glas->fresh()->bestand);

        // Schiebe-Positionen laufen auf das Schiebe-Element.
        $this->assertTrue($wareneingang->positionen()->where('artikel_id', $schiebe->id)->exists());
        $this->assertGreaterThan($schiebeVorher, $schiebe->fresh()->bestand);

        // Eine Eingangs-Bewegung pro Artikel mit Referenz auf die Bestellung.
        $bewegungen = Lagerbewegung::query()->where('referenz', 'BST-2026-110')->get();
        $this->assertSame($wareneingang->positionen()->count(), $bewegungen->count());
        $this->assertTrue($bewegungen->every(fn ($b) => $b->menge > 0 && $b->typ->value === 'Eingang'));
        $this->assertSame($this->lagerist->id, $bewegungen->first()->benutzer_id);

        $bestellung->refresh();
        $this->assertSame(BestellungStatus::Geliefert, $bestellung->status);
        $this->assertTrue($bestellung->positionen->every->eingelagert);
    }

    public function test_booking_is_idempotent(): void
    {
        $bestellung = $this->bestellung('BST-2026-109');

        $erste = $this->lager->bucheWareneingang($bestellung, $this->lagerist);
        $bestandNachher = Artikel::query()->where('art_nr', 'PF-110110')->value('bestand');
        $bewegungen = Lagerbewegung::query()->count();

        $zweite = $this->lager->bucheWareneingang($bestellung->fresh(), $this->lagerist);

        $this->assertTrue($erste->is($zweite));
        $this->assertSame(1, $bestellung->wareneingaenge()->count());
        $this->assertSame($bestandNachher, Artikel::query()->where('art_nr', 'PF-110110')->value('bestand'));
        $this->assertSame($bewegungen, Lagerbewegung::query()->count());
    }

    public function test_unresolved_positions_never_touch_stock(): void
    {
        $bestellung = Bestellung::factory()->create(['status' => BestellungStatus::Bestellt]);
        $bestellung->positionen()->create([
            'typ' => 'material',
            'pos' => 1,
            'bezeichnung' => 'Völlig unbekanntes Sonderteil XY-99',
            'menge' => 5,
        ]);

        $bestandVorher = Artikel::query()->sum('bestand');
        $wareneingang = $this->lager->bucheWareneingang($bestellung, $this->lagerist);

        $this->assertSame(0, $wareneingang->positionen()->count());
        $this->assertEquals($bestandVorher, Artikel::query()->sum('bestand'));
        $this->assertSame(BestellungStatus::Geliefert, $bestellung->fresh()->status);
    }

    public function test_draft_and_cancelled_orders_cannot_be_booked(): void
    {
        $this->expectException(LogicException::class);

        $this->lager->bucheWareneingang($this->bestellung('BST-2026-107'), $this->lagerist);
    }

    public function test_status_rollback_does_not_reverse_the_booking(): void
    {
        $bestellung = $this->bestellung('BST-2026-109');
        $this->lager->bucheWareneingang($bestellung, $this->lagerist);

        $bestand = Artikel::query()->where('art_nr', 'PF-110110')->value('bestand');
        $bestellung->fresh()->update(['status' => BestellungStatus::Bestellt]);

        $this->assertSame(1, $bestellung->wareneingaenge()->count());
        $this->assertSame($bestand, Artikel::query()->where('art_nr', 'PF-110110')->value('bestand'));

        // Erneutes Geliefert bucht nicht doppelt (Idempotenz über Wareneingang).
        $this->lager->bucheWareneingang($bestellung->fresh(), $this->lagerist);
        $this->assertSame(1, Wareneingang::query()->where('bestellung_id', $bestellung->id)->count());
    }

    public function test_lieferschein_sequence_continues_from_seed(): void
    {
        $this->assertSame('LS-88215', $this->lager->naechsteLieferscheinNr());
    }
}
