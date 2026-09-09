<?php

namespace Tests\Feature;

use App\Models\Projekt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjektFotosTest extends TestCase
{
    use RefreshDatabase;

    private User $monteur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(DatabaseSeeder::class);
        $this->monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();
    }

    public function test_foto_upload_und_anzeige_nach_phasen(): void
    {
        $projekt = Projekt::query()->where('nr', 'PRJ-2026-011')->firstOrFail();

        // Monteur lädt ein Vorher-Foto hoch
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/fotos', [
            'foto' => UploadedFile::fake()->image('baustelle.jpg', 800, 600),
            'phase' => 'vorher',
        ])->assertRedirect(route('projekte.show', [$projekt, 'tab' => 'fotos']))
            ->assertSessionHas('toast', 'Foto hochgeladen (vor der Montage)');

        $foto = $projekt->dokumente()->where('typ', 'foto_vorher')->firstOrFail();
        $this->assertTrue(Storage::exists($foto->pfad));

        // Tab zeigt das Foto samt Vorschau-Route; Nachher-Gruppe bleibt leer
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-011?tab=fotos')
            ->assertOk()
            ->assertSee('Vor der Montage')
            ->assertSee('Noch keine Fotos nach der Montage')
            ->assertSee(route('dokumente.ansicht', $foto), false);

        // Inline-Auslieferung für <img src>
        $this->actingAs($this->monteur)->get(route('dokumente.ansicht', $foto))->assertOk();

        // Fotos erscheinen NICHT im Dokumente-Tab (eigener Tab)
        $this->actingAs($this->monteur)->get('/projekte/PRJ-2026-011?tab=dokumente')
            ->assertDontSee('baustelle.jpg');
    }

    public function test_nur_bilder_erlaubt(): void
    {
        $this->actingAs($this->monteur)->post('/projekte/PRJ-2026-011/fotos', [
            'foto' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
            'phase' => 'vorher',
        ])->assertSessionHasErrors(['foto']);
    }

    public function test_material_tab_listet_bestellungen_des_projekts(): void
    {
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();

        // PRJ-2026-011 hat Seed-Bestellungen → Tabelle mit Verweisen
        $this->actingAs($verkauf)->get('/projekte/PRJ-2026-011?tab=material')
            ->assertOk()
            ->assertSee('Bestellungen zu diesem Objekt')
            ->assertSee('BST-2026-112')
            ->assertSee('Materialliste aus Konfiguration')
            ->assertSee('Reserviertes Material');
    }
}
