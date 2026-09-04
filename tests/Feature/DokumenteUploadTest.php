<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DokumenteUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $benutzer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->benutzer = User::query()->where('email', 'projekt@lea.test')->firstOrFail();
    }

    public function test_upload_speichert_datei_und_dokument_zeile(): void
    {
        $datei = UploadedFile::fake()->create('Statik-Nachweis.pdf', 120, 'application/pdf');

        $this->actingAs($this->benutzer)
            ->post('/projekte/PRJ-2026-011/dokumente', ['datei' => $datei])
            ->assertRedirect(route('projekte.show', ['PRJ-2026-011', 'tab' => 'dokumente']))
            ->assertSessionHas('toast', 'Dokument hochgeladen');

        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();
        $dokument = $projekt->dokumente()->where('typ', 'upload')->firstOrFail();
        $this->assertSame('Statik-Nachweis.pdf', $dokument->dateiname);
        $this->assertGreaterThan(0, $dokument->groesse);
        Storage::assertExists($dokument->pfad);

        // Download über den bestehenden Auth-Endpunkt
        $this->actingAs($this->benutzer)->get('/dokumente/'.$dokument->id)->assertOk();

        // Tab zeigt Datei + Upload-Formular
        $this->actingAs($this->benutzer)->get('/projekte/PRJ-2026-011?tab=dokumente')
            ->assertSee('Statik-Nachweis.pdf')
            ->assertSee('Hochladen');
    }

    public function test_unzulaessige_datei_wird_abgelehnt(): void
    {
        $this->actingAs($this->benutzer)
            ->post('/projekte/PRJ-2026-011/dokumente', [
                'datei' => UploadedFile::fake()->create('setup.exe', 10, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('datei');

        $this->assertSame(0, Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail()
            ->dokumente()->where('typ', 'upload')->count());
    }
}
