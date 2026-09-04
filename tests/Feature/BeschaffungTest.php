<?php

namespace Tests\Feature;

use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeschaffungTest extends TestCase
{
    use RefreshDatabase;

    private User $lagerist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->lagerist = User::query()->where('email', 'lager@lea.test')->firstOrFail();
    }

    public function test_bestellvorschlag_gruppiert_nach_lieferant_mit_formel_menge(): void
    {
        $niedrig = Artikel::query()->withSum('reservierungen', 'menge')->get()
            ->filter(fn (Artikel $a) => $a->bestandsstatus() !== 'ok');
        $lieferanten = $niedrig->pluck('lieferant_id')->unique()->count();
        $vorher = Bestellung::query()->count();

        $antwort = $this->actingAs($this->lagerist)->post('/lager/bestellvorschlag');
        $antwort->assertRedirect(route('bestellungen', ['status' => 'entwurf']));
        $this->assertStringContainsString('Bestellvorschläge angelegt', session('toast'));

        $this->assertSame($vorher + $lieferanten, Bestellung::query()->count());

        // Formel-Menge für einen bekannten Artikel: PF-110110 (Bestand 4, min 12) → max(20, 12) = 20
        $pfosten = Artikel::query()->where('art_nr', 'PF-110110')->firstOrFail();
        $position = Bestellung::query()->where('titel', 'like', 'Bestellvorschlag%')
            ->where('lieferant_id', $pfosten->lieferant_id)->firstOrFail()
            ->positionen()->where('artikel_id', $pfosten->id)->firstOrFail();
        $this->assertSame(
            (float) max($pfosten->min_bestand * 2 - $pfosten->bestand, $pfosten->min_bestand),
            (float) $position->menge,
        );
    }

    public function test_nachbestellen_inkrementiert_bestehende_position(): void
    {
        $pfosten = Artikel::query()->where('art_nr', 'PF-110110')->firstOrFail();
        $menge = max($pfosten->min_bestand * 2 - $pfosten->bestand, $pfosten->min_bestand);

        // Erster Klick legt einen Entwurf an
        $this->actingAs($this->lagerist)->post('/lager/artikel/'.$pfosten->id.'/nachbestellen');
        $this->assertStringContainsString('Nachbestellung PF-110110', session('toast'));
        $entwurf = Bestellung::query()->where('status', 'entwurf')
            ->where('lieferant_id', $pfosten->lieferant_id)->orderByDesc('nr')->firstOrFail();
        $position = $entwurf->positionen()->where('artikel_id', $pfosten->id)->firstOrFail();
        $this->assertSame((float) $menge, (float) $position->menge);

        // Zweiter Klick verdoppelt die Menge in derselben Position
        $this->actingAs($this->lagerist)->post('/lager/artikel/'.$pfosten->id.'/nachbestellen');
        $this->assertSame((float) ($menge * 2), (float) $position->fresh()->menge);
        $this->assertSame(1, $entwurf->positionen()->where('artikel_id', $pfosten->id)->count());
    }
}
