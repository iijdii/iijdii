<?php

namespace Tests\Feature;

use App\Models\Abnahmeprotokoll;
use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class AbnahmeTest extends TestCase
{
    use RefreshDatabase;

    /** 1×1-PNG als data-URL — Unterschrift-Ersatz für Tests. */
    private const SIG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $monteur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();
    }

    public function test_form_renders_all_sections_with_legal_texts(): void
    {
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-011/abnahme')
            ->assertOk()
            ->assertSee('Vertragsparteien')
            ->assertSee('Bauvorhaben &amp; Leistungsgegenstand', false)
            ->assertSee('Erklärung des Auftraggebers')
            ->assertSee('ohne Vorbehalt abgenommen; Beanstandungen bestehen nicht')
            ->assertSee('Übergabe &amp; Einweisung', false)
            ->assertSee('§ 634a Abs. 1 Nr. 2 BGB')
            ->assertSee('Hier unterschreiben')
            ->assertSee(config('lea.firma'))
            ->assertSee('Überdachung Trapez 8630×3500 mm'); // Positionen aus Konfiguration
    }

    public function test_both_signatures_are_required(): void
    {
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/abnahme', [
            'art' => 'ohne', 'ort' => 'Berlin', 'sig_auftraggeber' => self::SIG,
        ])->assertSessionHasErrors('sig_monteur');

        $this->assertSame(0, Abnahmeprotokoll::query()->count());
    }

    public function test_vorbehalt_without_defect_text_is_rejected(): void
    {
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/abnahme', [
            'art' => 'vorbehalt', 'ort' => 'Berlin',
            'maengel' => [['text' => '  ', 'frist' => '10.09.2026']],
            'sig_auftraggeber' => self::SIG, 'sig_monteur' => self::SIG,
        ])->assertRedirect(route('projekte.abnahme', 'PRJ-2026-011'));

        $this->assertSame('Mindestens eine Beanstandung erfassen', session('toast'));
        $this->assertSame(0, Abnahmeprotokoll::query()->count());
    }

    public function test_ohne_creates_signed_protocol_pdf_and_document(): void
    {
        $antwort = $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/abnahme', [
            'art' => 'ohne', 'ort' => 'Potsdam',
            'checkliste' => ['einweisung' => 1, 'pflege' => 1, 'unterlagen' => 1, 'baustelle' => 1],
            'sig_auftraggeber' => self::SIG, 'sig_monteur' => self::SIG,
        ]);

        $antwort->assertRedirect(route('projekte.show', ['PRJ-2026-011', 'tab' => 'dokumente']))
            ->assertSessionHas('toast', 'Abnahmeprotokoll unterschrieben und archiviert');

        $protokoll = Abnahmeprotokoll::query()->firstOrFail();
        $this->assertSame('AP-2026-0114', $protokoll->nr);
        $this->assertNotNull($protokoll->abgeschlossen_am);
        $this->assertTrue($protokoll->checkliste['einweisung']);

        Storage::assertExists('unterschriften/AP-2026-0114_auftraggeber.png');
        Storage::assertExists('unterschriften/AP-2026-0114_monteur.png');
        Storage::assertExists('dokumente/Abnahmeprotokoll_AP-2026-0114.pdf');
        $this->assertStringStartsWith('%PDF', Storage::get('dokumente/Abnahmeprotokoll_AP-2026-0114.pdf'));

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $dokument = $projekt->dokumente()->where('typ', 'abnahmeprotokoll')->firstOrFail();
        $this->assertSame('Abnahmeprotokoll_AP-2026-0114.pdf', $dokument->dateiname);
        $this->assertSame('abgenommen', $dokument->badge);
        $this->assertGreaterThan(0, $dokument->groesse);

        $this->assertSame('abgeschlossen', $projekt->status->value);
        $this->assertTrue($projekt->aktivitaeten()->where('titel', 'like', 'Abnahmeprotokoll%')->exists());

        // Download über den Auth-Stream-Endpunkt
        $this->actingAs($this->monteur)->get('/dokumente/'.$dokument->id)->assertOk();

        // Unveränderlich nach Unterzeichnung
        $this->expectException(LogicException::class);
        $protokoll->update(['ort' => 'Berlin']);
    }

    public function test_vorbehalt_stores_defects_with_suffix_and_badge(): void
    {
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/abnahme', [
            'art' => 'vorbehalt', 'ort' => 'Potsdam',
            'maengel' => [
                ['text' => 'Kratzer auf Glasfeld 3', 'frist' => '10.09.2026'],
                ['text' => 'Silikonfuge unsauber', 'frist' => '10.09.2026'],
            ],
            'sig_auftraggeber' => self::SIG, 'sig_monteur' => self::SIG,
        ])->assertSessionHas('toast', 'Abnahme unter Vorbehalt · 2 Mängel dokumentiert');

        $protokoll = Abnahmeprotokoll::query()->firstOrFail();
        $this->assertSame(2, $protokoll->maengel()->count());
        $this->assertSame('2026-09-10', $protokoll->maengel()->first()->frist->toDateString());

        Storage::assertExists('dokumente/Abnahmeprotokoll_AP-2026-0114_mit_Vorbehalt.pdf');
        $this->assertSame('2 Mängel', Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail()
            ->dokumente()->where('typ', 'abnahmeprotokoll')->value('badge'));

        // Kein Auto-Abschluss bei Vorbehalt
        $this->assertNotSame('abgeschlossen', Projekt::query()->where('nr', 'PRJ-2026-011')->value('status'));
    }

    public function test_verweigert_variant(): void
    {
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/abnahme', [
            'art' => 'verweigert', 'ort' => 'Potsdam',
            'maengel' => [['text' => 'Statik-Nachweis fehlt', 'frist' => '']],
            'sig_auftraggeber' => self::SIG, 'sig_monteur' => self::SIG,
        ])->assertSessionHas('toast', 'Abnahme verweigert — Protokoll archiviert');

        Storage::assertExists('dokumente/Abnahmeprotokoll_AP-2026-0114_verweigert.pdf');
        $this->assertSame('verweigert', Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail()
            ->dokumente()->where('typ', 'abnahmeprotokoll')->value('badge'));
    }
}
