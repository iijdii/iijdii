<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Support\Format;
use App\Support\Nummern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KundeController extends Controller
{
    public function index(): View
    {
        return view('kunden.index', [
            'kunden' => Kunde::query()->orderBy('kunden_nr')->get(),
        ]);
    }

    public function create(): View
    {
        return view('kunden.form', ['kunde' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kunde = Kunde::query()->create($this->validiert($request) + ['kunden_nr' => Nummern::kunde()]);

        return redirect()->route('kunden.show', $kunde)
            ->with('toast', 'Kunde '.$kunde->kunden_nr.' angelegt');
    }

    public function edit(Kunde $kunde): View
    {
        return view('kunden.form', ['kunde' => $kunde]);
    }

    public function update(Request $request, Kunde $kunde): RedirectResponse
    {
        $kunde->update($this->validiert($request));

        return redirect()->route('kunden.show', $kunde)->with('toast', 'Kunde aktualisiert');
    }

    /** @return array<string, mixed> Whitelist + Normalisierung (tags: Komma-Text → Array). */
    private function validiert(Request $request): array
    {
        $daten = $request->validate([
            'anzeigename' => ['required', 'string', 'max:120'],
            'typ' => ['required', Rule::in(['privat', 'gewerbe'])],
            'status' => ['required', Rule::in(['Lead', 'Aktiv', 'Inaktiv'])],
            'vorname' => ['nullable', 'string', 'max:80'],
            'nachname' => ['nullable', 'string', 'max:80'],
            'firma' => ['nullable', 'string', 'max:120'],
            'ansprechpartner' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120'],
            'telefon' => ['nullable', 'string', 'max:40'],
            'strasse' => ['nullable', 'string', 'max:120'],
            'hausnummer' => ['nullable', 'string', 'max:16'],
            'plz' => ['nullable', 'string', 'max:10'],
            'stadt' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'quelle' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'string', 'max:200'],
            'notizen' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('tags', $daten)) {
            $daten['tags'] = $daten['tags'] !== null
                ? array_values(array_filter(array_map('trim', explode(',', $daten['tags']))))
                : null;
        }

        return $daten;
    }

    public function show(Kunde $kunde): View
    {
        $kunde->load(['anfragen', 'angebote', 'projekte.angebot']);

        // Aktivitäts-Timeline wie im Prototyp: Angebote, dann Anfragen,
        // zuletzt die Anlage des Kunden (bewusst nicht chronologisch).
        $timeline = collect();
        foreach ($kunde->angebote as $angebot) {
            $timeline->push([
                'titel' => 'Angebot '.$angebot->nr.' · '.($angebot->summe !== null ? Format::eur($angebot->summe) : 'in Konfiguration'),
                'sub' => 'Status '.$angebot->status->label(),
                'datum' => Format::datumKurz($angebot->datum),
                'status' => 'done',
            ]);
        }
        foreach ($kunde->anfragen as $anfrage) {
            $timeline->push([
                'titel' => 'Anfrage '.$anfrage->nummer.' erfasst',
                'sub' => $anfrage->produkt_notiz ?? '–',
                'datum' => Format::datumKurz($anfrage->besuchstermin_datum),
                'status' => 'done',
            ]);
        }
        $timeline->push([
            'titel' => 'Kunde angelegt',
            'sub' => 'Quelle '.($kunde->quelle ?? '–'),
            'datum' => Format::datumKurz($kunde->created_at),
            'status' => 'now',
        ]);

        return view('kunden.show', [
            'kunde' => $kunde,
            'timeline' => $timeline,
        ]);
    }
}
