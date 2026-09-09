<?php

namespace App\Http\Controllers;

use App\Enums\AnfrageStatus;
use App\Enums\AngebotStatus;
use App\Enums\ProjektStatus;
use App\Models\Anfrage;
use App\Models\Kunde;
use App\Support\AnfrageKonfigMapper;
use App\Support\KonfigurationSync;
use App\Support\KonfiguratorRechner;
use App\Support\Nummern;
use App\Support\ProduktFelder;
use App\Support\RoofZeichnung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnfrageController extends Controller
{
    /** Die vier UI-Stufen des Prototyp-Steppers → repräsentativer Status. */
    private const STUFEN = [
        1 => AnfrageStatus::Neu,
        2 => AnfrageStatus::InBearbeitung,
        3 => AnfrageStatus::TerminVereinbart,
        4 => AnfrageStatus::AngebotErstellt,
    ];

    public function index(Request $request): View
    {
        $ansicht = $request->query('ansicht') === 'karten' ? 'karten' : 'tabelle';
        $filter = $request->query('stufe', 'alle');

        $alle = Anfrage::query()->with(['kunde', 'projekt'])->orderByDesc('nummer')->get();

        $chips = collect([
            ['alle', 'Alle'], ['1', 'Neu'], ['2', 'In Bearbeitung'], ['3', 'Aufmaß'], ['4', 'Angebot'],
        ])->map(fn (array $chip) => [
            'key' => $chip[0],
            'label' => $chip[1],
            'anzahl' => $chip[0] === 'alle'
                ? $alle->count()
                : $alle->filter(fn (Anfrage $a) => $a->status->uiStufe() === (int) $chip[0])->count(),
            'aktiv' => $chip[0] === $filter,
        ]);

        return view('anfragen.index', [
            'ansicht' => $ansicht,
            'filter' => $filter,
            'chips' => $chips,
            'anfragen' => $filter === 'alle'
                ? $alle
                : $alle->filter(fn (Anfrage $a) => $a->status->uiStufe() === (int) $filter)->values(),
        ]);
    }

    public function show(Anfrage $anfrage): View
    {
        // Statische Draufsicht aus den Anfrage-Maßen (Prototyp: Anfrage-Detail).
        $draufsicht = RoofZeichnung::ansicht('top', KonfiguratorRechner::berechne(
            AnfrageKonfigMapper::pcfg($anfrage),
        ), ['projekt' => $anfrage->nummer.' · '.$anfrage->kunde->anzeigename]);

        return view('anfragen.show', [
            'anfrage' => $anfrage->load(['kunde', 'aktivitaeten', 'projekt.positionen']),
            'stufen' => self::STUFEN,
            'draufsicht' => $draufsicht,
        ]);
    }

    public function create(Request $request): View
    {
        return view('anfragen.form', [
            'anfrage' => null,
            'kunden' => Kunde::query()->orderBy('anzeigename')->get(),
            'vorausgewaehlt' => $request->query('kunde'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $daten = $this->validiert($request);
        $daten['nummer'] = Nummern::anfrage();
        $daten['erstellt_von'] = $request->user()->id;

        // Einheitssystem: mit Konfigurator-Position startet die Anfrage
        // automatisch Projekt + Angebot (Betreiber-Prozess; Absage löscht
        // beide wieder). Ohne Position bleibt es eine reine Lead-Karte.
        $position = $request->filled('position.produkt')
            ? ProduktFelder::daten((array) $request->input('position'))
            : null;
        if ($position && empty($daten['produkt_notiz'])) {
            $daten['produkt_notiz'] = $position['produkt']->label();
        }

        $anfrage = DB::transaction(function () use ($daten, $position, $request) {
            $anfrage = Anfrage::query()->create($daten);
            $anfrage->aktivitaeten()->create([
                'typ' => 'angelegt',
                'von_user_id' => $request->user()->id,
            ]);

            if ($position === null) {
                return $anfrage;
            }

            $kunde = $anfrage->kunde;
            $projekt = $kunde->projekte()->create([
                'nr' => Nummern::projekt(),
                'titel' => $position['produkt']->label().' '.$kunde->anzeigename,
                'anfrage_id' => $anfrage->id,
                'objekt_strasse' => $anfrage->objekt_strasse ?? $kunde->strasse,
                'objekt_hausnummer' => $anfrage->objekt_hausnummer ?? $kunde->hausnummer,
                'objekt_plz' => $anfrage->objekt_plz ?? $kunde->plz,
                'objekt_stadt' => $anfrage->objekt_stadt ?? $kunde->stadt,
                'status' => ProjektStatus::InPlanung,
            ]);
            $projekt->positionen()->create($position + ['pos' => 1]);
            KonfigurationSync::spiegleDach($projekt);

            $angebot = $kunde->angebote()->create([
                'nr' => Nummern::angebot(),
                'titel' => $projekt->titel,
                'anfrage_id' => $anfrage->id,
                'status' => AngebotStatus::Entwurf,
                'datum' => now()->toDateString(),
                'konfiguration' => $projekt->fresh()->konfiguration,
            ]);
            $projekt->update(['angebot_id' => $angebot->id]);

            $projekt->aktivitaeten()->create([
                'titel' => 'Projekt aus '.$anfrage->nummer.' erstellt · Angebot '.$angebot->nr,
                'wer' => $request->user()->name,
                'datum' => now()->format('d.m.'),
                'status' => 'done',
            ]);
            $anfrage->aktivitaeten()->create([
                'typ' => 'projekt_erstellt',
                'von_user_id' => $request->user()->id,
                'details' => ['projekt' => $projekt->nr, 'angebot' => $angebot->nr],
            ]);

            return $anfrage;
        });

        $projekt = $anfrage->projekt;

        return redirect()->route('anfragen.show', $anfrage)
            ->with('toast', $projekt
                ? 'Anfrage '.$anfrage->nummer.' → Projekt '.$projekt->nr.' + Angebot '.$projekt->angebot->nr
                : 'Anfrage gespeichert · '.$anfrage->nummer);
    }

    public function edit(Anfrage $anfrage): View
    {
        return view('anfragen.form', [
            'anfrage' => $anfrage,
            'kunden' => Kunde::query()->orderBy('anzeigename')->get(),
            'vorausgewaehlt' => null,
        ]);
    }

    public function update(Request $request, Anfrage $anfrage): RedirectResponse
    {
        $anfrage->update($this->validiert($request));

        return redirect()->route('anfragen.show', $anfrage)
            ->with('toast', 'Anfrage '.$anfrage->nummer.' aktualisiert');
    }

    public function setzeStatus(Request $request, Anfrage $anfrage): RedirectResponse
    {
        $stufe = (int) $request->validate(['stufe' => ['required', 'integer', 'between:1,4']])['stufe'];
        $neu = self::STUFEN[$stufe];

        $anfrage->aktivitaeten()->create([
            'typ' => 'status_geaendert',
            'von_user_id' => $request->user()->id,
            'details' => ['von' => $anfrage->status->value, 'nach' => $neu->value],
        ]);
        $anfrage->update(['status' => $neu]);

        return redirect()->route('anfragen.show', $anfrage)
            ->with('toast', 'Status: '.$neu->label());
    }

    public function erstelleProjekt(Request $request, Anfrage $anfrage): RedirectResponse
    {
        if ($anfrage->projekt) {
            return redirect()->route('projekte.show', $anfrage->projekt)
                ->with('toast', 'Projekt '.$anfrage->projekt->nr.' ist bereits verknüpft');
        }

        $kunde = $anfrage->kunde;
        $pcfg = AnfrageKonfigMapper::pcfg($anfrage);
        $produkt = $pcfg['product'] === 'Überdachung' ? 'Terrassenüberdachung' : $pcfg['product'];

        $projekt = $kunde->projekte()->create([
            'nr' => Nummern::projekt(),
            'titel' => $produkt.' '.$kunde->anzeigename,
            'anfrage_id' => $anfrage->id,
            // Existiert zur Anfrage schon ein (noch projektloses) Angebot,
            // wird es gleich mit verknüpft — die Kette bleibt geschlossen.
            'angebot_id' => $anfrage->angebot?->projekt ? null : $anfrage->angebot?->id,
            'objekt_strasse' => $anfrage->objekt_strasse ?? $kunde->strasse,
            'objekt_hausnummer' => $anfrage->objekt_hausnummer ?? $kunde->hausnummer,
            'objekt_plz' => $anfrage->objekt_plz ?? $kunde->plz,
            'objekt_stadt' => $anfrage->objekt_stadt ?? $kunde->stadt,
            'status' => ProjektStatus::InPlanung,
            'konfiguration' => $pcfg,
        ]);

        $projekt->aktivitaeten()->create([
            'titel' => 'Projekt aus '.$anfrage->nummer.' erstellt',
            'wer' => $request->user()->name,
            'datum' => now()->format('d.m.'),
            'status' => 'done',
        ]);
        $anfrage->aktivitaeten()->create([
            'typ' => 'projekt_erstellt',
            'von_user_id' => $request->user()->id,
            'details' => ['projekt' => $projekt->nr],
        ]);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
            ->with('toast', 'Projekt '.$projekt->nr.' aus '.$anfrage->nummer.' erstellt');
    }

    /**
     * Kunde hat abgesagt: Projekt + Angebot werden gelöscht (Betreiber-
     * Prozess), die Anfrage bleibt als kein_interesse erhalten. Sobald am
     * Projekt schon Bestellungen oder Reservierungen hängen, ist die
     * Absage blockiert — dann muss zuerst aufgeräumt werden.
     */
    public function absage(Request $request, Anfrage $anfrage): RedirectResponse
    {
        $projekt = $anfrage->projekt;

        if ($projekt && ($projekt->bestellungen()->exists() || $projekt->reservierungen()->exists()
            || $projekt->abnahmeprotokolle()->exists())) {
            return redirect()->route('anfragen.show', $anfrage)
                ->with('toast', 'Absage nicht möglich — am Projekt hängen bereits Bestellungen/Reservierungen');
        }

        DB::transaction(function () use ($anfrage, $projekt, $request) {
            $geloescht = [];
            if ($projekt) {
                $angebot = $projekt->angebot;
                $geloescht[] = $projekt->nr;
                // Positionen explizit löschen — Shared-Hosting-Datenbanken
                // tragen die Kaskaden-Fremdschlüssel nicht immer.
                $projekt->positionen()->delete();
                $projekt->delete(); // Aktivitäten/Dokumente/Aufgaben kaskadieren
                if ($angebot) {
                    $geloescht[] = $angebot->nr;
                    $angebot->delete();
                }
            } elseif ($anfrage->angebot) {
                $geloescht[] = $anfrage->angebot->nr;
                $anfrage->angebot->delete();
            }

            $anfrage->update(['status' => AnfrageStatus::KeinInteresse]);
            $anfrage->aktivitaeten()->create([
                'typ' => 'status_geaendert',
                'von_user_id' => $request->user()->id,
                'details' => ['nach' => 'kein_interesse', 'geloescht' => $geloescht],
            ]);
        });

        return redirect()->route('anfragen.show', $anfrage)
            ->with('toast', 'Absage erfasst — Projekt & Angebot gelöscht');
    }

    private function validiert(Request $request): array
    {
        $daten = $request->validate([
            'kunde_id' => ['required', Rule::exists('kunden', 'id')],
            'status' => ['required', Rule::enum(AnfrageStatus::class)],
            'anfrage_quelle' => ['nullable', 'string', 'max:64'],
            'besuchstermin_datum' => ['nullable', 'date'],
            'besuchstermin_uhrzeit' => ['nullable', 'date_format:H:i'],
            'produkt_notiz' => ['nullable', 'string', 'max:255'],
            'interessierte_produkte' => ['nullable', 'array'],
            'befestigung_art' => ['nullable', Rule::in(['wandmontage', 'freistehend', 'kombination'])],
            'profil_farbe_name' => ['nullable', 'string', 'max:64'],
            'form' => ['nullable', Rule::in(['rechteckig', 'trapezfoermig', 'l-form', 'individuell'])],
            'breite_cm' => ['nullable', 'integer', 'min:0'],
            'tiefe_cm' => ['nullable', 'integer', 'min:0'],
            'hoehe_cm' => ['nullable', 'integer', 'min:0'],
            'dachneigung_grad' => ['nullable', 'integer', 'between:0,45'],
            'verglasung_typ' => ['nullable', 'string', 'max:32'],
            'dach_material' => ['nullable', 'string', 'max:32'],
            'anzahl_stuetzen' => ['nullable', 'integer', 'min:0'],
            'kommentar_intern' => ['nullable', 'string'],
        ]);

        $kunde = Kunde::query()->find($daten['kunde_id']);
        $daten['kunden_vorname'] = $kunde->vorname;
        $daten['kunden_nachname'] = $kunde->nachname ?? $kunde->anzeigename;
        $daten['kunden_telefon'] = $kunde->telefon;
        $daten['kunden_email'] = $kunde->email;
        $daten['besuchstermin_status'] = ($daten['besuchstermin_datum'] ?? null) ? 'geplant' : null;

        return $daten;
    }
}
