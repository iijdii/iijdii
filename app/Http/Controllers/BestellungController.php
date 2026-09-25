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
use App\Support\ProduktFelder;
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

        $kalk = KonfiguratorRechner::berechne($projekt->konfiguration ?? []);
        if ($kalk['fields'] < 1) {
            return $zurueck->with('toast', 'Konfiguration unvollständig — bitte Breite/Tiefe pflegen');
        }
        if ($kalk['glasZuBreit']) {
            return $zurueck->with('toast', 'Eindeckungsbreite über '.$kalk['maxPlatte'].' mm — Felderzahl im Konfigurator prüfen');
        }
        $p = $kalk['pcfg'];

        // Zwei Entwürfe, weil Profil und Glas meist von verschiedenen
        // Lieferanten kommen: die Konstruktion als EINE Position
        // «Überdachung» mit allen Bauteilen und Zuschnittlängen, das Glas
        // des Daches separat. Erneuter Klick baut beide neu auf.
        [$konstruktion, $glas] = DB::transaction(function () use ($request, $projekt, $dach, $kalk, $p) {
            $konstruktion = $this->entwurfFuer($request, $projekt, 'Phase 1 · Konstruktion (Überdachung)', 'aluminium');
            $glas = $this->entwurfFuer($request, $projekt, 'Phase 1 · Glas (Dach)', 'glas');

            $komponenten = [];
            $glasPos = (int) $glas->positionen()->max('pos');
            foreach (Stueckliste::dach($kalk) as $zeile) {
                if ($zeile['typ'] === 'glas') {
                    $glasArtikel = Artikel::findeNachName($p['covering'].' '.$p['thickness'].' klar')
                        ?? Artikel::findeNachName($p['covering'].' '.$p['thickness']);
                    $glas->positionen()->create([
                        'typ' => 'glas', 'pos' => ++$glasPos, 'bezeichnung' => $zeile['name'],
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

                $komponenten[] = [
                    'name' => $zeile['name'],
                    'menge' => (int) $zeile['menge'],
                    'einheit' => $zeile['einheit'],
                    'laenge_mm' => $zeile['laenge_mm'] ?? null,
                ];
            }

            $konstruktion->positionen()->create([
                'typ' => 'material',
                'pos' => (int) $konstruktion->positionen()->max('pos') + 1,
                'bezeichnung' => ($kalk['positionen'][0]['name'] ?? 'Überdachung').' · '.$p['color'],
                'menge' => 1, 'einheit' => 'Satz',
                'projekt_position_id' => $dach->id,
                'details' => ['komponenten' => $komponenten],
            ]);

            $projekt->aktivitaeten()->create([
                'titel' => 'Bestell-Entwürfe '.$konstruktion->nr.' (Konstruktion) und '.$glas->nr.' (Glas) aus Positionen',
                'wer' => $request->user()->name,
                'datum' => now()->format('d.m.'),
                'status' => 'done',
            ]);

            return [$konstruktion, $glas];
        });

        return $zurueck->with('toast', 'Entwürfe '.$konstruktion->nr.' (Konstruktion) und '.$glas->nr.' (Glas) bereit — bitte Lieferanten wählen');
    }

    /**
     * Offener Entwurf dieses Projekts mit dem Titel-Suffix — oder neu.
     * Automatisch erzeugte Positionen (mit Projekt-Link) eines bestehenden
     * Entwurfs werden entfernt, damit der Aufrufer sie aus den aktuellen
     * Daten neu anlegt; manuell ergänzte Positionen bleiben stehen.
     */
    private function entwurfFuer(Request $request, Projekt $projekt, string $suffix, string $kategorie): Bestellung
    {
        $titel = 'Projekt '.$projekt->nr.' · '.$suffix;
        $entwurf = $projekt->bestellungen()
            ->where('status', BestellungStatus::Entwurf)
            ->where('titel', $titel)
            ->first();
        if ($entwurf !== null) {
            $entwurf->positionen()->whereNotNull('projekt_position_id')->delete();

            return $entwurf;
        }

        return Bestellung::query()->create([
            'nr' => Nummern::bestellung(),
            'titel' => $titel,
            'kategorie' => $kategorie,
            'projekt_id' => $projekt->id,
            'kunde_id' => $projekt->kunde_id,
            'ersteller_id' => $request->user()->id,
            'status' => BestellungStatus::Entwurf,
            'notizen' => 'Automatisch aus dem Projekt erstellt — Lieferant im Entwurf wählen.',
        ]);
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

        // Je Lieferanten-Gruppe ein eigener Entwurf (Betreiber-Vorgabe):
        // Glas & Schiebe meist ein Lieferant, Markisen ein anderer,
        // Sonnensegel wieder ein anderer. Bestehende Entwürfe werden mit
        // den AKTUELLEN Endmaßen neu aufgebaut (Auto-Positionen ersetzt,
        // manuell ergänzte bleiben stehen).
        $gruppen = [
            'glas' => ['Phase 2 · Glas & Schiebe', 'glas'],
            'markise' => ['Phase 2 · Markisen', 'markise'],
            'sonnensegel' => ['Phase 2 · Sonnensegel (Tuch)', 'sonnensegel'],
            'sonstiges' => ['Phase 2 · Sonstiges', 'gemischt'],
        ];
        $gruppeVon = fn (ProjektProdukt $produkt): string => match ($produkt) {
            ProjektProdukt::Wand, ProjektProdukt::Schiebe, ProjektProdukt::Keil => 'glas',
            ProjektProdukt::Markise => 'markise',
            ProjektProdukt::Sonnensegel => 'sonnensegel',
            default => 'sonstiges',
        };

        $entwuerfe = DB::transaction(function () use ($request, $projekt, $positionen, $gruppen, $gruppeVon) {
            // Alt-Entwurf «Phase 2 (Endmaße)» aus früheren Versionen:
            // manuelle Positionen wandern mit in den Glas-Entwurf.
            $alt = $projekt->bestellungen()
                ->where('status', BestellungStatus::Entwurf)
                ->where('titel', 'Projekt '.$projekt->nr.' · Phase 2 (Endmaße)')
                ->first();
            if ($alt !== null) {
                $alt->positionen()->whereNotNull('projekt_position_id')->delete();
                $alt->positionen()->exists()
                    ? $alt->update(['titel' => 'Projekt '.$projekt->nr.' · '.$gruppen['glas'][0], 'kategorie' => 'glas'])
                    : $alt->delete();
            }

            $entwuerfe = [];
            foreach ($positionen as $position) {
                $gruppe = $gruppeVon($position->produkt);
                $bestellung = $entwuerfe[$gruppe] ??= $this->entwurfFuer($request, $projekt, ...$gruppen[$gruppe]);
                $pos = (int) $bestellung->positionen()->max('pos');

                // Endmaß gewinnt, konfigurierte Felder füllen Lücken.
                $m = ($position->endmasse ?? []) + ($position->felder ?? []);
                $anzahl = max(1, (int) ($m['anzahl'] ?? $m['felder_n'] ?? 1));

                if ($position->produkt === ProjektProdukt::Wand && (int) ($m['breite_mm'] ?? 0) > 0) {
                    $hL = (int) ($m['h_links_mm'] ?? 0);
                    $hR = (int) ($m['h_rechts_mm'] ?? $hL);
                    $reihen = max(1, (int) ($m['reihen'] ?? 1));
                    foreach (SeitenwandRechner::raster((int) $m['breite_mm'], $hL, $hR, $anzahl, $reihen) as $panel) {
                        $bestellung->positionen()->create([
                            'typ' => 'glas', 'pos' => ++$pos,
                            'bezeichnung' => 'Seitenwand Feld '.$panel['spalte'].'.'.$panel['reihe'].' ('.$panel['form'].') — Pos. '.$position->pos,
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
                    $einbauort = trim((string) ($m['einbauort'] ?? ''));
                    $bestellung->positionen()->create([
                        'typ' => 'schiebe', 'pos' => ++$pos,
                        'bezeichnung' => 'Schiebeanlage nach Endmaß'.($einbauort !== '' ? ' ('.$einbauort.')' : '').' — Pos. '.$position->pos,
                        'menge' => $anzahl, 'einheit' => 'Stück',
                        'breite_mm' => (int) $m['breite_mm'], 'hoehe_mm' => (int) ($m['hoehe_mm'] ?? 0),
                        'projekt_position_id' => $position->id,
                        'details' => ['count' => $anzahl, 'glas' => ProduktFelder::fuellung($m), 'einbauort' => $einbauort, 'quelle' => 'live'],
                    ]);

                    continue;
                }

                // Keil = rechtwinkliges Trapez → Glas-Position, damit die
                // Zuschnittskizze in Bestellkarte und PDF gezeichnet wird.
                // Endmaß-Schlüssel: breite_unten_mm / hoehe_hinten_mm /
                // h_vorn_mm (AufmassRechner), Konfigurator-Felder füllen
                // Lücken. Seite Rechts spiegelt die Höhen.
                $keilBreite = (int) ($m['breite_unten_mm'] ?? $m['breite_mm'] ?? 0);
                if ($position->produkt === ProjektProdukt::Keil && $keilBreite > 0) {
                    $hh = (int) ($m['hoehe_hinten_mm'] ?? $m['h_hinten_mm'] ?? 0);
                    $hv = (int) ($m['h_vorn_mm'] ?? 0);
                    $rechts = ($m['seite'] ?? '') === 'Rechts';
                    $fuellung = ProduktFelder::fuellung($m);
                    $bestellung->positionen()->create([
                        'typ' => 'glas', 'pos' => ++$pos,
                        'bezeichnung' => 'Keil '.($m['seite'] ?? '').' nach Endmaß (Trapez) — Pos. '.$position->pos,
                        'menge' => $anzahl, 'einheit' => 'Stück',
                        'breite_mm' => $keilBreite, 'hoehe_mm' => max($hh, $hv),
                        'projekt_position_id' => $position->id,
                        'details' => [
                            'form' => 'Trapez',
                            'hL' => $rechts ? $hv : $hh, 'hR' => $rechts ? $hh : $hv,
                            'glas' => $fuellung, 'quelle' => 'live',
                        ],
                    ]);

                    continue;
                }

                if ($position->produkt === ProjektProdukt::Sonnensegel) {
                    // Sonnenschutz (Tuch): gleiche Maße werden zu EINER
                    // Position mit Stückzahl zusammengefasst — Breite je
                    // Segel aus dem Endmaß (breite_i_mm), Länge gilt für
                    // alle, Farbe aus dem Konfigurator.
                    $segelBreiten = [];
                    for ($i = 1; $i <= 24; $i++) {
                        if (isset($m['breite_'.$i.'_mm'])) {
                            $segelBreiten[$i] = (int) $m['breite_'.$i.'_mm'];
                        }
                    }
                    $stueck = max(1, count($segelBreiten) ?: (int) ($m['anzahl'] ?? 1));
                    $laenge = (int) ($m['laenge_mm'] ?? 0);
                    $farbe = trim((string) ($m['farbe'] ?? ''));

                    $gruppen = [];
                    for ($i = 1; $i <= $stueck; $i++) {
                        $breite = $segelBreiten[$i] ?? (int) ($m['breite_mm'] ?? 0);
                        $gruppen[$breite] = ($gruppen[$breite] ?? 0) + 1;
                    }
                    foreach ($gruppen as $breite => $menge) {
                        $bestellung->positionen()->create([
                            'typ' => 'material', 'pos' => ++$pos,
                            'bezeichnung' => 'Sonnensegel (Tuch) · Breite '.$breite.' mm × Länge '.$laenge.' mm'
                                .($farbe !== '' ? ' · Farbe '.$farbe : '').' — Pos. '.$position->pos,
                            'menge' => $menge, 'einheit' => 'Stück',
                            'breite_mm' => $breite ?: null, 'hoehe_mm' => $laenge ?: null,
                            'projekt_position_id' => $position->id,
                            // «art» steuert die eigene Segel-Tabelle in Karte und PDF.
                            'details' => ['art' => 'sonnensegel', 'breite_mm' => $breite, 'laenge_mm' => $laenge,
                                'farbe' => $farbe, 'projekt_pos' => $position->pos] + ($position->endmasse ?? []),
                        ]);
                    }

                    continue;
                }

                // Maße mit Namen in der Bezeichnung (Breite/Länge/Höhe/Ausfall).
                $masse = [];
                if (isset($m['breite_mm'])) {
                    $masse[] = 'Breite '.(int) $m['breite_mm'].' mm';
                } elseif (isset($m['laenge_mm'])) {
                    $masse[] = 'Länge '.(int) $m['laenge_mm'].' mm';
                }
                if (isset($m['hoehe_mm'])) {
                    $masse[] = 'Höhe '.(int) $m['hoehe_mm'].' mm';
                } elseif (isset($m['ausfall_mm'])) {
                    $masse[] = 'Ausfall '.(int) $m['ausfall_mm'].' mm';
                } elseif (isset($m['h_hinten_mm']) || isset($m['h_vorn_mm'])) {
                    $masse[] = 'Höhe hinten/vorn '.(int) ($m['h_hinten_mm'] ?? 0).'/'.(int) ($m['h_vorn_mm'] ?? 0).' mm';
                }
                $bestellung->positionen()->create([
                    'typ' => 'material', 'pos' => ++$pos,
                    'bezeichnung' => $position->produkt->label().' nach Endmaß'
                        .($masse !== [] ? ' · '.implode(' · ', $masse) : '').' — Pos. '.$position->pos,
                    'menge' => $anzahl, 'einheit' => 'Stück',
                    'projekt_position_id' => $position->id,
                    'details' => $position->endmasse,
                ]);
            }

            $projekt->aktivitaeten()->create([
                'titel' => 'Nachbestellung aus Endmaßen (Phase 2): '.implode(', ', array_map(
                    fn (Bestellung $b) => $b->nr, $entwuerfe,
                )),
                'wer' => $request->user()->name,
                'datum' => now()->format('d.m.'),
                'status' => 'done',
            ]);

            return $entwuerfe;
        });

        return $zurueck->with('toast', 'Phase 2 bereit: '.implode(' · ', array_map(
            fn (Bestellung $b) => $b->nr.' '.$b->kategorieLabel(), $entwuerfe,
        )).' — bitte Lieferanten wählen');
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

    /** Bestehende Position ändern — Validierung wie beim Anlegen, Pos.-Nr. bleibt. */
    public function aenderePosition(Request $request, Bestellung $bestellung, BestellungPosition $position): RedirectResponse
    {
        abort_unless($position->bestellung_id === $bestellung->id, 404);
        if (! $this->bearbeitbar($bestellung)) {
            return $this->nurEntwurf($bestellung);
        }

        $position->update(match ($position->typ) {
            BestellungPositionTyp::Glas => $this->glasPosition($request, (int) $position->pos),
            BestellungPositionTyp::Schiebe => $this->schiebePosition($request, (int) $position->pos),
            BestellungPositionTyp::Material => $this->materialPosition($request, (int) $position->pos),
        });

        return redirect()->route('bestellungen.show', $bestellung)->with('toast', 'Position '.$position->pos.' aktualisiert');
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
            'einbauort' => ['nullable', 'string', 'max:40'],
        ]);

        return [
            'typ' => 'schiebe', 'pos' => $pos, 'bezeichnung' => $d['bezeichnung'],
            'artikel_id' => Artikel::findeNachName('Schiebe-Element')?->id,
            'menge' => $d['count'], 'einheit' => 'Stück',
            'breite_mm' => (int) $d['breite_mm'], 'hoehe_mm' => (int) $d['hoehe_mm'],
            'details' => [
                'count' => (int) $d['count'], 'glas' => $d['glas'] ?? '',
                'einbauort' => trim((string) ($d['einbauort'] ?? '')), 'quelle' => 'manuell',
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
            'kategorie' => ['nullable', Rule::in(['glas', 'aluminium', 'gemischt', 'markise', 'sonnensegel'])],
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
        $bestellung->load(['lieferant', 'projekt', 'kunde', 'ersteller', 'positionen.artikel']);

        return PdfArchiv::liefere(
            'bestellungen.pdf',
            $this->positionsDaten($bestellung) + ['bestellung' => $bestellung],
            'Bestellung_'.$bestellung->nr,
            $bestellung->projekt,
            'bestellung',
            $bestellung->status->label(),
            $bestellung->kunde,
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
                    'einbauort' => $d['einbauort'] ?? '',
                    'quelle' => $d['quelle'] ?? 'manuell',
                    'skizze' => GlasSkizze::schiebe((int) $p->breite_mm, (int) $p->hoehe_mm, max(1, $anzahl), $richtung),
                ];
            });

        // Sonnensegel bekommen eine eigene Tabelle (Breite/Länge/Farbe);
        // ältere Positionen ohne «art» erkennt der Bezeichnungs-Präfix.
        $material = $bestellung->positionen->where('typ', BestellungPositionTyp::Material);
        $istSegel = fn ($p) => ($p->details['art'] ?? null) === 'sonnensegel'
            || str_starts_with((string) $p->bezeichnung, 'Sonnenschutz (Tuch)');

        return [
            'materialPositionen' => $material->reject($istSegel)->values(),
            'segelPositionen' => $material->filter($istSegel)->values(),
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
