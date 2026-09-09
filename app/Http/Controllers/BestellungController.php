<?php

namespace App\Http\Controllers;

use App\Enums\BestellungPositionTyp;
use App\Enums\BestellungStatus;
use App\Enums\ProjektProdukt;
use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\BestellungPosition;
use App\Models\Lieferant;
use App\Models\Projekt;
use App\Services\LagerService;
use App\Support\Format;
use App\Support\GlasSkizze;
use App\Support\KonfiguratorRechner;
use App\Support\Nummern;
use App\Support\PdfArchiv;
use App\Support\SeitenwandRechner;
use App\Support\Stueckliste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BestellungController extends Controller
{
    public function __construct(private readonly LagerService $lager) {}

    public function index(Request $request): View
    {
        $ansicht = $request->query('ansicht') === 'karten' ? 'karten' : 'tabelle';
        $filter = $request->query('status', 'alle');
        $portal = $request->user()->istLieferant();

        // Optionaler Lieferanten-Filter (Deep-Link aus der Lieferanten-Übersicht);
        // die Status-Chips zählen innerhalb des gefilterten Satzes.
        // Portal (M14): Lieferanten sehen nur die eigenen, bereits
        // bestellten Vorgänge — interne Entwürfe bleiben unsichtbar.
        $alle = Bestellung::query()
            ->with(['lieferant', 'projekt', 'kunde', 'positionen'])
            ->when($portal ? 0 : $request->integer('lieferant'), fn ($q, $id) => $q->where('lieferant_id', $id))
            ->when($portal, fn ($q) => $q
                ->where('lieferant_id', $request->user()->lieferant_id)
                ->whereNotIn('status', [BestellungStatus::Entwurf, BestellungStatus::Geprueft]))
            ->orderByDesc('nr')
            ->get();

        $chipStatus = $portal
            ? [BestellungStatus::Bestellt, BestellungStatus::Bereit, BestellungStatus::Geliefert]
            : [
                BestellungStatus::Entwurf, BestellungStatus::Geprueft, BestellungStatus::Bestellt,
                BestellungStatus::Bereit, BestellungStatus::Geliefert,
            ];
        $chips = collect([['alle', 'Alle']])
            ->concat(collect($chipStatus)->map(fn ($s) => [$s->value, $s->label()]))
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

    /**
     * Bestell-Entwurf aus den Phase-1-Projektpositionen (M10): Dachfelder
     * aus der Glas-Kalkulation, Pfosten und Sparren aus dem Rechenkern.
     * Der Lieferant bleibt offen — der Verkäufer wählt ihn im Entwurf.
     */
    public function ausProjektPositionen(Request $request, Projekt $projekt): RedirectResponse
    {
        $zurueck = redirect()->route('projekte.show', [$projekt, 'tab' => 'material']); // Karte «Aufmaß & Bestellung»

        if (! $projekt->aufmassBestaetigt()) {
            return $zurueck->with('toast', 'Aufmaß nicht bestätigt — Bestellung gesperrt');
        }

        $dach = $projekt->positionen()->where('gruppe', 'dach')->first();
        if ($dach === null) {
            return $zurueck->with('toast', 'Keine Dachposition — Phase 1 hat nichts zu bestellen');
        }

        // Kein Duplikat: ein offener Entwurf aus Positionen wird geöffnet.
        $entwurf = $projekt->bestellungen()
            ->where('status', BestellungStatus::Entwurf)
            ->whereHas('positionen', fn ($q) => $q->whereNotNull('projekt_position_id'))
            ->first();
        if ($entwurf !== null) {
            return redirect()->route('bestellungen.show', $entwurf)
                ->with('toast', 'Entwurf '.$entwurf->nr.' aus Positionen existiert bereits');
        }

        $kalk = KonfiguratorRechner::berechne($projekt->konfiguration ?? []);
        if ($kalk['fields'] < 1) {
            return $zurueck->with('toast', 'Konfiguration unvollständig — bitte Breite/Tiefe pflegen');
        }
        if ($kalk['glasZuBreit']) {
            return $zurueck->with('toast', 'Eindeckungsbreite über '.$kalk['maxPlatte'].' mm — Felderzahl im Konfigurator prüfen');
        }
        $p = $kalk['pcfg'];

        $bestellung = DB::transaction(function () use ($request, $projekt, $dach, $kalk, $p) {
            $bestellung = Bestellung::query()->create([
                'nr' => Nummern::bestellung(),
                'titel' => 'Projekt '.$projekt->nr.' · Phase 1',
                'kategorie' => 'gemischt',
                'projekt_id' => $projekt->id,
                'kunde_id' => $projekt->kunde_id,
                'ersteller_id' => $request->user()->id,
                'status' => BestellungStatus::Entwurf,
                'notizen' => 'Automatisch aus den Projektpositionen erstellt — Lieferant im Entwurf wählen.',
            ]);

            // Vollständige Hauptpositionen nach KD-Stückliste; Kleinteile
            // ergänzt der Verkäufer im Entwurf.
            foreach (Stueckliste::dach($kalk) as $i => $zeile) {
                if ($zeile['typ'] === 'glas') {
                    $glasArtikel = Artikel::findeNachName($p['covering'].' '.$p['thickness'].' klar')
                        ?? Artikel::findeNachName($p['covering'].' '.$p['thickness']);
                    $bestellung->positionen()->create([
                        'typ' => 'glas', 'pos' => $i + 1, 'bezeichnung' => $zeile['name'],
                        'artikel_id' => $glasArtikel?->id,
                        'menge' => $zeile['menge'], 'einheit' => $zeile['einheit'],
                        'breite_mm' => $zeile['breite_mm'], 'hoehe_mm' => $zeile['hoehe_mm'],
                        'projekt_position_id' => $dach->id,
                        'details' => [
                            'form' => 'Rechteck', 'hL' => $zeile['hoehe_mm'], 'hR' => $zeile['hoehe_mm'],
                            'glas' => $p['covering'].' '.$p['thickness'], 'quelle' => 'live',
                        ],
                    ]);

                    continue;
                }

                $artikel = isset($zeile['such']) ? Artikel::findeNachName($zeile['such']) : null;
                $bestellung->positionen()->create([
                    'typ' => 'material', 'pos' => $i + 1, 'bezeichnung' => $zeile['name'],
                    'artikel_id' => $artikel?->id, 'menge' => $zeile['menge'],
                    'einheit' => $artikel?->einheit->value ?? $zeile['einheit'],
                    'projekt_position_id' => $dach->id,
                    'details' => isset($zeile['laenge_mm']) ? ['laenge_mm' => $zeile['laenge_mm']] : null,
                ]);
            }

            $projekt->aktivitaeten()->create([
                'titel' => 'Bestell-Entwurf '.$bestellung->nr.' aus Positionen erstellt',
                'wer' => $request->user()->name,
                'datum' => now()->format('d.m.'),
                'status' => 'done',
            ]);

            return $bestellung;
        });

        return redirect()->route('bestellungen.show', $bestellung)
            ->with('toast', 'Entwurf '.$bestellung->nr.' erstellt — bitte Lieferant wählen');
    }

    /**
     * Nachbestellung Phase 2 (M11): Elemente aus den vom Monteur
     * erfassten Endmaßen — Seitenwände als fertiger Glaszuschnitt
     * (SeitenwandRechner), Schiebeanlagen als Schiebe-Positionen.
     */
    public function nachbestellungAusEndmassen(Request $request, Projekt $projekt): RedirectResponse
    {
        $zurueck = redirect()->route('projekte.show', [$projekt, 'tab' => 'material']);

        $positionen = $projekt->positionen()->where('phase', 2)->whereNotNull('endmasse_am')->orderBy('pos')->get();
        if ($positionen->isEmpty()) {
            return $zurueck->with('toast', 'Keine Endmaße erfasst — zuerst im Montage-Modus eintragen');
        }

        $entwurf = $projekt->bestellungen()
            ->where('status', BestellungStatus::Entwurf)
            ->where('titel', 'like', '%Phase 2%')
            ->first();
        if ($entwurf !== null) {
            return redirect()->route('bestellungen.show', $entwurf)
                ->with('toast', 'Entwurf '.$entwurf->nr.' (Phase 2) existiert bereits');
        }

        $bestellung = DB::transaction(function () use ($request, $projekt, $positionen) {
            $bestellung = Bestellung::query()->create([
                'nr' => Nummern::bestellung(),
                'titel' => 'Projekt '.$projekt->nr.' · Phase 2 (Endmaße)',
                'kategorie' => 'gemischt',
                'projekt_id' => $projekt->id,
                'kunde_id' => $projekt->kunde_id,
                'ersteller_id' => $request->user()->id,
                'status' => BestellungStatus::Entwurf,
                'notizen' => 'Nachbestellung aus den Endmaßen — Lieferant im Entwurf wählen.',
            ]);

            $pos = 0;
            foreach ($positionen as $position) {
                // Endmaß gewinnt, konfigurierte Felder füllen Lücken.
                $m = ($position->endmasse ?? []) + ($position->felder ?? []);
                $anzahl = max(1, (int) ($m['anzahl'] ?? $m['felder_n'] ?? 1));

                if ($position->produkt === ProjektProdukt::Wand && (int) ($m['breite_mm'] ?? 0) > 0) {
                    $hL = (int) ($m['h_links_mm'] ?? 0);
                    $hR = (int) ($m['h_rechts_mm'] ?? $hL);
                    foreach (SeitenwandRechner::panels((int) $m['breite_mm'], $hL, $hR, $anzahl) as $panel) {
                        $bestellung->positionen()->create([
                            'typ' => 'glas', 'pos' => ++$pos,
                            'bezeichnung' => 'Seitenwand Panel '.$panel['nr'].' ('.$panel['form'].') — Pos. '.$position->pos,
                            'menge' => 1, 'einheit' => 'Feld',
                            'breite_mm' => $panel['breite'], 'hoehe_mm' => max($panel['hLinks'], $panel['hRechts']),
                            'projekt_position_id' => $position->id,
                            'details' => [
                                'form' => $panel['form'], 'hL' => $panel['hLinks'], 'hR' => $panel['hRechts'],
                                'glas' => (string) ($m['glas'] ?? ''), 'quelle' => 'live',
                            ],
                        ]);
                    }

                    continue;
                }

                if ($position->produkt === ProjektProdukt::Schiebe && (int) ($m['breite_mm'] ?? 0) > 0) {
                    $bestellung->positionen()->create([
                        'typ' => 'schiebe', 'pos' => ++$pos,
                        'bezeichnung' => 'Schiebeanlage nach Endmaß — Pos. '.$position->pos,
                        'menge' => $anzahl, 'einheit' => 'Stück',
                        'breite_mm' => (int) $m['breite_mm'], 'hoehe_mm' => (int) ($m['hoehe_mm'] ?? 0),
                        'projekt_position_id' => $position->id,
                        'details' => ['count' => $anzahl, 'glas' => (string) ($m['glas'] ?? ''), 'quelle' => 'live'],
                    ]);

                    continue;
                }

                $masse = array_filter([
                    $m['breite_mm'] ?? $m['laenge_mm'] ?? null,
                    $m['hoehe_mm'] ?? $m['ausfall_mm'] ?? $m['h_vorn_mm'] ?? null,
                ]);
                $bestellung->positionen()->create([
                    'typ' => 'material', 'pos' => ++$pos,
                    'bezeichnung' => $position->produkt->label().' nach Endmaß'
                        .($masse !== [] ? ' '.implode('×', $masse).' mm' : '').' — Pos. '.$position->pos,
                    'menge' => $anzahl, 'einheit' => 'Stück',
                    'projekt_position_id' => $position->id,
                    'details' => $position->endmasse,
                ]);
            }

            $projekt->aktivitaeten()->create([
                'titel' => 'Nachbestellung '.$bestellung->nr.' aus Endmaßen erstellt (Phase 2)',
                'wer' => $request->user()->name,
                'datum' => now()->format('d.m.'),
                'status' => 'done',
            ]);

            return $bestellung;
        });

        return redirect()->route('bestellungen.show', $bestellung)
            ->with('toast', 'Entwurf '.$bestellung->nr.' (Phase 2) erstellt — bitte Lieferant wählen');
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

    public function speicherePosition(Request $request, Bestellung $bestellung): RedirectResponse
    {
        if (! $this->bearbeitbar($bestellung)) {
            return $this->nurEntwurf($bestellung);
        }

        $typ = $request->validate(['typ' => ['required', Rule::in(['glas', 'schiebe', 'material'])]])['typ'];
        $pos = (int) $bestellung->positionen()->max('pos') + 1;

        $bestellung->positionen()->create(match ($typ) {
            'glas' => $this->glasPosition($request, $pos),
            'schiebe' => $this->schiebePosition($request, $pos),
            'material' => $this->materialPosition($request, $pos),
        });

        return redirect()->route('bestellungen.show', $bestellung)->with('toast', 'Position hinzugefügt');
    }

    public function loeschePosition(Bestellung $bestellung, BestellungPosition $position): RedirectResponse
    {
        abort_unless($position->bestellung_id === $bestellung->id, 404);
        if (! $this->bearbeitbar($bestellung)) {
            return $this->nurEntwurf($bestellung);
        }
        $position->delete();

        return redirect()->route('bestellungen.show', $bestellung)->with('toast', 'Position entfernt');
    }

    /** @return array<string, mixed> */
    private function glasPosition(Request $request, int $pos): array
    {
        $d = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:150'],
            'form' => ['required', Rule::in(['Rechteck', 'Trapez'])],
            'breite_mm' => ['required', 'integer', 'min:100', 'max:20000'],
            'hL' => ['required', 'integer', 'min:100', 'max:20000'],
            'hR' => ['nullable', 'integer', 'min:100', 'max:20000'],
            'menge' => ['required', 'numeric', 'min:0.5'],
            'glas' => ['nullable', 'string', 'max:80'],
        ]);
        $hR = $d['form'] === 'Trapez' ? (int) ($d['hR'] ?? $d['hL']) : (int) $d['hL'];

        return [
            'typ' => 'glas', 'pos' => $pos, 'bezeichnung' => $d['bezeichnung'],
            'artikel_id' => Artikel::findeNachName($d['glas'] ?? $d['bezeichnung'])?->id,
            'menge' => $d['menge'], 'einheit' => 'Feld',
            'breite_mm' => (int) $d['breite_mm'], 'hoehe_mm' => max((int) $d['hL'], $hR),
            'details' => [
                'form' => $d['form'], 'hL' => (int) $d['hL'], 'hR' => $hR,
                'glas' => $d['glas'] ?? '', 'quelle' => 'manuell',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function schiebePosition(Request $request, int $pos): array
    {
        $d = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:150'],
            'breite_mm' => ['required', 'integer', 'min:100', 'max:20000'],
            'hoehe_mm' => ['required', 'integer', 'min:100', 'max:20000'],
            'count' => ['required', 'integer', 'min:1', 'max:12'],
            'glas' => ['nullable', 'string', 'max:80'],
        ]);

        return [
            'typ' => 'schiebe', 'pos' => $pos, 'bezeichnung' => $d['bezeichnung'],
            'artikel_id' => Artikel::findeNachName('Schiebe-Element')?->id,
            'menge' => $d['count'], 'einheit' => 'Stück',
            'breite_mm' => (int) $d['breite_mm'], 'hoehe_mm' => (int) $d['hoehe_mm'],
            'details' => [
                'count' => (int) $d['count'], 'glas' => $d['glas'] ?? '', 'quelle' => 'manuell',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function materialPosition(Request $request, int $pos): array
    {
        $d = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:150'],
            'menge' => ['required', 'numeric', 'min:0.5'],
        ]);
        $artikel = Artikel::findeNachName($d['bezeichnung']);

        return [
            'typ' => 'material', 'pos' => $pos, 'bezeichnung' => $d['bezeichnung'],
            'artikel_id' => $artikel?->id, 'menge' => $d['menge'],
            'einheit' => $artikel?->einheit->value ?? 'Stück',
        ];
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
            'lieferanten' => Lieferant::query()->orderBy('name')->get(),
            'projekte' => Projekt::query()->with('kunde')->orderByDesc('nr')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function kopfDaten(Request $request): array
    {
        $daten = $request->validate([
            // Entwürfe aus Positionen starten ohne Lieferant; ohne ihn
            // verlässt setzeStatus den Entwurf nicht.
            'lieferant_id' => ['nullable', 'exists:lieferanten,id'],
            'titel' => ['required', 'string', 'max:150'],
            'kategorie' => ['nullable', Rule::in(['glas', 'aluminium', 'gemischt'])],
            // Harte Sperre (M10): Projektbezug nur mit bestätigtem Aufmaß.
            'projekt_id' => ['nullable', 'exists:projekte,id',
                function (string $attribut, mixed $wert, \Closure $fehler) {
                    if (! Projekt::query()->find($wert)?->aufmassBestaetigt()) {
                        $fehler('Aufmaß nicht bestätigt — erst am Projekt bestätigen, dann bestellen.');
                    }
                }],
            'liefertermin' => ['nullable', 'date'],
            'notizen' => ['nullable', 'string', 'max:2000'],
        ]);

        // Kunde folgt dem Projekt (eine Quelle der Wahrheit).
        $daten['kunde_id'] = isset($daten['projekt_id'])
            ? Projekt::query()->find($daten['projekt_id'])?->kunde_id
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
        if ($bestellung->lieferant_id === null && $status !== BestellungStatus::Entwurf) {
            return redirect()->route('bestellungen.show', $bestellung)
                ->with('toast', 'Bitte zuerst einen Lieferanten wählen');
        }

        // Portal (M14): der Lieferant meldet ausschließlich «Bereit» auf
        // einer bestellten Bestellung — alle anderen Übergänge sind intern.
        if ($request->user()->istLieferant()
            && ! ($bestellung->status === BestellungStatus::Bestellt && $status === BestellungStatus::Bereit)) {
            return redirect()->route('bestellungen.show', $bestellung)
                ->with('toast', 'Im Portal nur möglich: Bestellt → Bereit melden');
        }
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
                fn ($p) => Format::menge($p->menge).'× '.$p->bezeichnung
            ),
            'posCount' => (int) $glas->sum('menge') + (int) $schiebe->sum('menge') + $material->count(),
        ];
    }
}
