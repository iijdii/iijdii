<?php

namespace Tests\Feature;

use App\Enums\Rolle;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BenutzerVerwaltungTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('email', 'admin@lea.test')->firstOrFail();
    }

    public function test_admin_sieht_benutzerliste_in_den_einstellungen(): void
    {
        $this->actingAs($this->admin)->get('/einstellungen')
            ->assertOk()
            ->assertSee('Benutzer &amp; Passwörter', false)
            ->assertSee('verkauf@lea.test')
            ->assertSee('Neuer Benutzer');
    }

    public function test_projektleiter_sieht_und_darf_keine_benutzerverwaltung(): void
    {
        $projektleiter = User::query()->where('email', 'projekt@lea.test')->firstOrFail();

        $this->actingAs($projektleiter)->get('/einstellungen')
            ->assertOk()
            ->assertDontSee('Neuer Benutzer');

        $this->actingAs($projektleiter)->post('/einstellungen/benutzer', [
            'name' => 'X', 'email' => 'x@lea.test', 'role' => 'admin',
            'password' => 'geheim123', 'password_confirmation' => 'geheim123',
        ])->assertForbidden();
    }

    public function test_admin_legt_benutzer_an_der_sich_anmelden_kann(): void
    {
        $this->actingAs($this->admin)->post('/einstellungen/benutzer', [
            'name' => 'Ion Monteur', 'email' => 'ion@lea.test', 'role' => 'monteur',
            'password' => 'montage2026', 'password_confirmation' => 'montage2026',
        ])->assertRedirect(route('einstellungen'))
            ->assertSessionHas('toast', 'Benutzer Ion Monteur angelegt');

        $neu = User::query()->where('email', 'ion@lea.test')->firstOrFail();
        $this->assertSame(Rolle::Monteur, $neu->role);

        auth()->logout();
        $this->post('/login', ['email' => 'ion@lea.test', 'password' => 'montage2026'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($neu);
    }

    public function test_anlegen_prueft_passwort_email_und_lieferant(): void
    {
        $eingabe = [
            'name' => 'Doppelt', 'email' => 'verkauf@lea.test', 'role' => 'lieferant',
            'password' => 'kurz', 'password_confirmation' => 'anders',
        ];

        $this->actingAs($this->admin)->from('/einstellungen')->post('/einstellungen/benutzer', $eingabe)
            ->assertSessionHasErrorsIn('benutzer_neu', ['email', 'lieferant_id', 'password']);

        // Fenster «Neuer Benutzer» bleibt mit Fehlermeldung offen
        $this->actingAs($this->admin)->from('/einstellungen')->followingRedirects()
            ->post('/einstellungen/benutzer', $eingabe)
            ->assertOk()
            ->assertSee('id="benutzer-neu"', false)
            ->assertDontSee('id="benutzer-neu" hidden', false);
        $this->assertDatabaseMissing('users', ['name' => 'Doppelt']);
    }

    public function test_admin_aendert_passwort_und_daten(): void
    {
        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();

        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$verkauf->id, [
            'name' => 'Max S.', 'email' => 'max@lea.test', 'role' => 'verkaeufer',
            'password' => 'neuesPass1', 'password_confirmation' => 'neuesPass1',
        ])->assertRedirect(route('einstellungen'))
            ->assertSessionHas('toast', 'Benutzer Max S. gespeichert · neues Passwort gesetzt');

        $verkauf->refresh();
        $this->assertSame('max@lea.test', $verkauf->email);
        $this->assertTrue(Hash::check('neuesPass1', $verkauf->password));

        // Leeres Passwort lässt es unverändert
        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$verkauf->id, [
            'name' => 'Max S.', 'email' => 'max@lea.test', 'role' => 'projektleiter',
            'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $verkauf->refresh();
        $this->assertSame(Rolle::Projektleiter, $verkauf->role);
        $this->assertTrue(Hash::check('neuesPass1', $verkauf->password));
    }

    public function test_eigener_zugang_bleibt_admin_und_aktiv(): void
    {
        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$this->admin->id, [
            'name' => 'Administrator', 'email' => 'admin@lea.test', 'role' => 'monteur',
        ])->assertSessionHasErrorsIn('benutzer_'.$this->admin->id, 'role');

        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$this->admin->id.'/sperren');
        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$this->admin->id.'/loeschen');

        $this->admin->refresh();
        $this->assertSame(Rolle::Admin, $this->admin->role);
        $this->assertFalse($this->admin->istGesperrt());
    }

    public function test_gesperrter_benutzer_kann_sich_nicht_anmelden_und_wird_abgemeldet(): void
    {
        $monteur = User::query()->where('email', 'monteur@lea.test')->firstOrFail();

        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$monteur->id.'/sperren')
            ->assertSessionHas('toast', 'Benutzer '.$monteur->name.' gesperrt');
        $this->assertTrue($monteur->refresh()->istGesperrt());

        // Offene Sitzung wird beendet
        $this->actingAs($monteur)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['email' => 'monteur@lea.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$monteur->id.'/sperren');
        $this->assertFalse($monteur->refresh()->istGesperrt());
    }

    public function test_loeschen_nur_ohne_eintraege(): void
    {
        $leer = User::factory()->create(['role' => Rolle::Monteur]);
        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$leer->id.'/loeschen')
            ->assertSessionHas('toast', 'Benutzer '.$leer->name.' gelöscht');
        $this->assertModelMissing($leer);

        $verkauf = User::query()->where('email', 'verkauf@lea.test')->firstOrFail();
        $this->actingAs($this->admin)->post('/einstellungen/benutzer/'.$verkauf->id.'/loeschen')
            ->assertSessionHas('toast', $verkauf->name.' hat bereits Einträge (Bestellungen, Aufmaß …) — bitte sperren statt löschen');
        $this->assertModelExists($verkauf);
    }
}
