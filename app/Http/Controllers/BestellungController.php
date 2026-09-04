<?php

namespace App\Http\Controllers;

use App\Enums\BestellungPositionTyp;
use App\Enums\BestellungStatus;
use App\Models\Bestellung;
use App\Services\LagerService;
use App\Support\Nummern;
use App\Support\PdfArchiv;
use App\Support\GlasSkizze;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BestellungController extends Controller
{
    public function __construct(private readonly LagerService $lager)
    {
    }

    public function index(Request $request): View
    {
        $ansicht = $request->query('ansicht') === 'tabelle' ? 'tabelle' : 'karten';
        $filter = $request->query('status', 'alle');

        // Optionaler Lieferanten-Filter (Deep-Link aus der Lieferanten-Übersicht);
        // die Status-Chips zählen innerhalb des gefilterten Satzes.
        $alle = Bestellung::query()
            ->with(['lieferant', 'projekt', 'kunde', 'positionen'])
            ->when($request->integer('lieferant'), fn ($q, $id) => $q->where('lieferant_id', $id))
            ->orderByDesc('nr')
            ->get();

        $chips = collect([['alle', 'Alle']])
            ->concat(collect([
                BestellungStatus::Entwurf, BestellungStatus::Geprueft, BestellungStatus::Bestellt,
                BestellungStatus::Bereit, BestellungStatus::Geliefert,
            ])->map(fn ($s) => [$s->value, $s->label()]))
            ->map(fn (array $chip) => [
                'key' => $chip[0],
                'label' => $chip[1],
                'anzahl' => $chip[0] === 'alle'
                    ? $alle->count()
                    : $alle->where('status.value', $chip[0])->count(),
                'aktiv' => $chip[0] === $filter,
            ]);

        $bestellungen = $filter === 'alle'
            ? $alle
            : $alle->filter(fn (Bestellung $b) => $b->status->value === $filter)->values();

        return view('bestellungen.index', [
            'ansicht' => $ansicht,
            'filter' => $filter,
            'chips' => $chips,
            'karten' => $bestellungen->map(fn (Bestellung $b) => $this->karte($b)),
        ]);
    }

    public function create(): View
    {
        return $this->formular(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $bestellung = Bestellung::query()->create($this->kopfDaten($request) + [
            'nr' => Nummern::bestellung(),
            'status' => BestellungStatus::Entwurf,
            'ersteller_id' => $request->user()->id,
        ]);

        return redirect()->route('bestellungen.show', $bestellung)
            ->with('toast', 'Bestellung '.$bestellung->nr.' angelegt (Entwurf)');
    }

    public function edit(Bestellung $bestellung): View
    {
        if (! $this->bearbeitbar($bestellung)) {
            return $this->nurEntwurf($bestellung);
        }

        return $this->formular($bestellung);
    }

    public function update(Request $request, Bestellung $bestellung): RedirectResponse
    {
        if (! $this->bearbeitbar($bestellung)) {
            return $this->nurEntwurf($bestellung);
        }
        $bestellung->update($this->kopfDaten($request));

        return redirect()->route('bestellungen.show', $bestellung)->with('toast', 'Bestellung aktualisiert');
    }

    /** Kopf editierbar nur solange nichts beim Lieferanten ausgelöst ist. */
    private function bearbeitbar(Bestellung $bestellung): bool
    {
        return in_array($bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Geprueft], true);
    }

    private function nurEntwurf(Bestellung $bestellung): RedirectResponse
    {
        return redirect()->route('bestellungen.show', $bestellung)
            ->with('toast', 'Nur im Entwurf/Geprüft bearbeitbar');
    }

    private function formular(?Bestellung $bestellung): View|RedirectResponse
    {
        return view('bestellungen.form', [
            'bestellung' => $bestellung,
            'lieferanten' => \App\Models\Lieferant::query()->orderBy('name')->get(),
            'projekte' => \App\Models\Projekt::query()->with('kunde')->orderByDesc('nr')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function kopfDaten(Request $request): array
    {
        $daten = $request->validate([
            'lieferant_id' => ['required', 'exists:lieferanten,id'],
            'titel' => ['required', 'string', 'max:150'],
            'kategorie' => ['nullable', Rule::in(['glas', 'aluminium', 'gemischt'])],
            'projekt_id' => ['nullable', 'exists:projekte,id'],
            'liefertermin' => ['nullable', 'date'],
            'notizen' => ['nullable', 'string', 'max:2000'],
        ]);

        // Kunde folgt dem Projekt (eine Quelle der Wahrheit).
        $daten['kunde_id'] = isset($daten['projekt_id'])
            ? \App\Models\Projekt::query()->find($daten['projekt_id'])?->kunde_id
            : null;

        return $daten;
    }

    public function show(Bestellung $bestellung): View
    {
        $bestellung->load([
            'lieferant', 'projekt', 'kunde', 'ersteller',
            'positionen.artikel', 'wareneingaenge.benutzer', 'wareneingaenge.positionen',
        ]);

        return view('bestellungen.show', $this->positionsDaten($bestellung) + [
            'bestellung' => $bestellung,
            'wareneingang' => $bestellung->wareneingaenge->first(),
        ]);
    }

    /** PDF im Abnahme-Muster (PdfArchiv): Download + Dokument bei Projektbezug. */
    public function pdf(Bestellung $bestellung): Response
    {
        $bestellung->load(['lieferant', 'projekt', 'kunde', 'positionen.artikel']);

        return PdfArchiv::liefere(
            'bestellungen.pdf',
            $this->positionsDaten($bestellung) + ['bestellung' => $bestellung],
            'Bestellung_'.$bestellung->nr.'.pdf',
            $bestellung->projekt,
            'bestellung',
            $bestellung->status->label(),
        );
    }

    /** Glas-/Schiebe-/Material-Positionen als View-Modelle (show + PDF). */
    private function positionsDaten(Bestellung $bestellung): array
    {
        $glasPositionen = $bestellung->positionen
            ->where('typ', BestellungPositionTyp::Glas)
            ->values()
            ->map(function ($p, $i) {
                $d = $p->details ?? [];
                $hL = (int) ($d['hL'] ?? $p->hoehe_mm ?? 0);
                $hR = (int) ($d['hR'] ?? $hL);
                $trapez = ($d['form'] ?? 'Rechteck') === 'Trapez';

                return [
                    'position' => $p,
                    'nr' => $i + 1,
                    'form' => $d['form'] ?? 'Rechteck',
                    'glas' => $d['glas'] ?? '–',
                    'quelle' => $d['quelle'] ?? 'manuell',
                    'skizze' => GlasSkizze::position((int) $p->breite_mm, $hL, $hR, $trapez),
                ];
            });

        $schiebePositionen = $bestellung->positionen
            ->where('typ', BestellungPositionTyp::Schiebe)
            ->values()
            ->map(function ($p, $i) {
                $d = $p->details ?? [];
                $anzahl = (int) ($d['count'] ?? $p->menge);
                $richtung = match (mb_strtolower($d['dir'] ?? '')) {
                    'links', 'left' => 'left',
                    'rechts', 'right' => 'right',
                    'mittig', 'center' => 'center',
                    default => null,
                };

                return [
                    'position' => $p,
                    'nr' => $i + 1,
                    'glas' => $d['glas'] ?? '–',
                    'anzahl' => $anzahl,
                    'richtung' => $d['dir'] ?? '–',
                    'quelle' => $d['quelle'] ?? 'manuell',
                    'skizze' => GlasSkizze::schiebe((int) $p->breite_mm, (int) $p->hoehe_mm, max(1, $anzahl), $richtung),
                ];
            });

        return [
            'materialPositionen' => $bestellung->positionen->where('typ', BestellungPositionTyp::Material)->values(),
            'glasPositionen' => $glasPositionen,
            'schiebePositionen' => $schiebePositionen,
        ];
    }

    public function setzeStatus(Request $request, Bestellung $bestellung): RedirectResponse
    {
        $validiert = $request->validate([
            'status' => ['required', Rule::enum(BestellungStatus::class)],
        ]);

        $status = BestellungStatus::from($validiert['status']);
        $bestellung->update(['status' => $status]);

        // Zentrale Regel: Geliefert/Montiert lagert automatisch ein.
        if (in_array($status, [BestellungStatus::Geliefert, BestellungStatus::Montiert], true)
            && ! $this->lager->istGebucht($bestellung)) {
            $wareneingang = $this->lager->bucheWareneingang($bestellung, $request->user());
            $toast = 'Wareneingang '.$bestellung->nr.' gebucht · '.$wareneingang->positionen()->count().' Artikel eingelagert';
        } else {
            $toast = 'Status: '.$status->label();
        }

        return redirect()->route('bestellungen.show', $bestellung)->with('toast', $toast);
    }

    private function karte(Bestellung $bestellung): array
    {
        $glas = $bestellung->positionen->where('typ', BestellungPositionTyp::Glas)->values();
        $material = $bestellung->positionen->where('typ', BestellungPositionTyp::Material)->values();
        $schiebe = $bestellung->positionen->where('typ', BestellungPositionTyp::Schiebe)->values();

        return [
            'bestellung' => $bestellung,
            'tiles' => $glas->take(4)->map(function ($p) {
                $d = $p->details ?? [];
                $hL = (int) ($d['hL'] ?? $p->hoehe_mm ?? 0);

                return [
                    'skizze' => GlasSkizze::kachel((int) $p->breite_mm, $hL, (int) ($d['hR'] ?? $hL)),
                    'trapez' => ($d['form'] ?? '') === 'Trapez',
                    'menge' => (int) $p->menge,
                ];
            }),
            'mehr' => max(0, $glas->count() - 4),
            'materialChips' => $material->take(6)->map(
                fn ($p) => \App\Support\Format::menge($p->menge).'× '.$p->bezeichnung
            ),
            'posCount' => (int) $glas->sum('menge') + (int) $schiebe->sum('menge') + $material->count(),
        ];
    }
}
