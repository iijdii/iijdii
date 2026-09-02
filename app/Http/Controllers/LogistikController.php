<?php

namespace App\Http\Controllers;

use App\Enums\BestellungStatus;
use App\Models\Bestellung;
use App\Models\BestellungPosition;
use App\Models\Tour;
use App\Support\Nummern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Logistik — Kommissionierung & Touren. Port von _logiVals/_logiItems
 * (Prototyp Z. 1252–1298): relevant sind nur Bestellungen in
 * bestellt/bereit/geliefert; der Packfortschritt lebt pro Position
 * (kommissioniert_am), Kommentare als kommissionier_notiz.
 */
class LogistikController extends Controller
{
    private const RELEVANT = [BestellungStatus::Bestellt, BestellungStatus::Bereit, BestellungStatus::Geliefert];

    private const GRUPPEN = [
        'glas' => 'Glas-Positionen',
        'schiebe' => 'Schiebe-Elemente',
        'material' => 'Material-Positionen',
    ];

    private const STATUS_LABEL = [
        'offen' => ['Offen', 'b-gray'],
        'teil' => ['Teilweise', 'b-yellow'],
        'fertig' => ['Gepackt', 'b-green'],
    ];

    public function index(Request $request): View
    {
        $auftraege = $this->relevanteAuftraege();

        $filter = in_array($request->query('filter'), ['offen', 'teil', 'fertig'], true)
            ? $request->query('filter')
            : 'alle';

        $chips = collect([['alle', 'Alle'], ['offen', 'Offen'], ['teil', 'Teilweise'], ['fertig', 'Fertig']])
            ->map(fn (array $chip) => [
                'key' => $chip[0],
                'label' => $chip[1],
                'anzahl' => $chip[0] === 'alle'
                    ? $auftraege->count()
                    : $auftraege->where('status', $chip[0])->count(),
                'aktiv' => $filter === $chip[0],
            ]);

        return view('logistik.index', [
            'chips' => $chips,
            'filter' => $filter,
            'auftraege' => $filter === 'alle' ? $auftraege : $auftraege->where('status', $filter)->values(),
            'touren' => Tour::query()->with('bestellungen.positionen')->orderByDesc('nr')->get(),
        ]);
    }

    public function bestellung(Request $request, Bestellung $bestellung): View
    {
        $daten = $this->auftragsDaten($bestellung->load(['positionen', 'lieferant', 'projekt', 'kunde']));

        return view('logistik.bestellung', $daten + [
            'notizEdit' => (int) $request->query('notiz'),
        ]);
    }

    public function togglePosition(Bestellung $bestellung, BestellungPosition $position): RedirectResponse
    {
        abort_unless($position->bestellung_id === $bestellung->id, 404);
        $position->update(['kommissioniert_am' => $position->kommissioniert_am ? null : now()]);

        return back();
    }

    public function setzeAlle(Request $request, Bestellung $bestellung): RedirectResponse
    {
        $bestellung->positionen()->update([
            'kommissioniert_am' => $request->boolean('markieren') ? now() : null,
        ]);

        return back();
    }

    public function speichereNotiz(Request $request, Bestellung $bestellung, BestellungPosition $position): RedirectResponse
    {
        abort_unless($position->bestellung_id === $bestellung->id, 404);
        $text = trim((string) $request->input('notiz'));
        $position->update(['kommissionier_notiz' => $text !== '' ? $text : null]);

        return redirect()->route('logistik.bestellung', $bestellung)
            ->with('toast', $text !== '' ? 'Kommentar gespeichert' : 'Kommentar gelöscht');
    }

    public function schliesseAb(Bestellung $bestellung): RedirectResponse
    {
        $offen = $bestellung->positionen()->whereNull('kommissioniert_am')->count();
        if ($offen > 0) {
            return redirect()->route('logistik.bestellung', $bestellung)
                ->with('toast', 'Noch '.$offen.' Position(en) offen');
        }

        return redirect()->route('logistik')
            ->with('toast', 'Kommissionierung '.$bestellung->nr.' abgeschlossen');
    }

    public function erstelleTour(Request $request): RedirectResponse
    {
        $ids = Bestellung::query()->whereIn('id', (array) $request->input('bestellungen', []))->pluck('id');
        if ($ids->isEmpty()) {
            return redirect()->route('logistik')->with('toast', 'Keine Aufträge ausgewählt');
        }

        $nr = Nummern::tour();
        $tour = Tour::query()->create([
            'nr' => $nr,
            'fahrzeug' => 'Mercedes Sprinter',
            'kennzeichen' => 'B-LEA '.('90'.(int) substr($nr, -3)),
            'datum' => now()->addDays(3)->toDateString(),
            'fahrer' => 'Team Berlin K2',
        ]);
        $tour->bestellungen()->sync($ids);

        return redirect()->route('logistik.tour', $tour)
            ->with('toast', 'Tour mit '.$ids->count().' Aufträgen erstellt');
    }

    // ---------- Touren + Lade-Modus ----------

    public function tour(Tour $tour): View
    {
        return view('logistik.tour', $this->tourDaten($tour));
    }

    public function setzeTourAlle(Request $request, Tour $tour): RedirectResponse
    {
        BestellungPosition::query()
            ->whereIn('bestellung_id', $tour->bestellungen()->pluck('bestellungen.id'))
            ->update(['kommissioniert_am' => $request->boolean('markieren') ? now() : null]);

        return back();
    }

    public function schliesseTourAb(Tour $tour): RedirectResponse
    {
        $daten = $this->tourDaten($tour);
        if ($daten['offen'] > 0) {
            return redirect()->route('logistik.tour', $tour)
                ->with('toast', 'Noch '.$daten['offen'].' Position(en) offen');
        }

        return redirect()->route('logistik')
            ->with('toast', 'Ladung '.$tour->nr.' abgeschlossen — Fahrzeug beladen');
    }

    public function lade(Request $request, Tour $tour): View
    {
        $tab = $request->query('tab') === 'tour' ? 'tour' : 'laden';

        return view('logistik.lade', $this->tourDaten($tour) + ['tab' => $tab]);
    }

    public function bestaetigeAbfahrt(Tour $tour): RedirectResponse
    {
        $daten = $this->tourDaten($tour);
        if ($daten['offen'] > 0) {
            return redirect()->route('logistik.lade', $tour)
                ->with('toast', 'Noch '.$daten['offen'].' Position(en) nicht geladen');
        }

        return redirect()->route('logistik')
            ->with('toast', 'Abfahrt bestätigt · '.$tour->nr.' unterwegs');
    }

    /** @return array Tour + Aufträge (View-Modelle) + Gesamtfortschritt + Stopps */
    private function tourDaten(Tour $tour): array
    {
        $tour->load(['bestellungen.positionen', 'bestellungen.lieferant', 'bestellungen.projekt', 'bestellungen.kunde']);
        $auftraege = $tour->bestellungen->map(fn (Bestellung $b) => $this->auftragsDaten($b))->values();

        $done = $auftraege->sum('done');
        $tot = $auftraege->sum('tot');

        $stopps = $auftraege->map(function (array $a, int $i) {
            $kunde = $a['bestellung']->kunde;

            return [
                'n' => $i + 1,
                'auftrag' => $a,
                'kunde' => $kunde?->anzeigename ?? '—',
                'strasse' => $kunde?->strasse ?? '—',
                'ort' => trim(($kunde?->plz ?? '').' '.($kunde?->stadt ?? '')),
                'telefon' => $kunde?->telefon,
                'tel' => $kunde?->telefon ? 'tel:'.preg_replace('/[^+\d]/', '', $kunde->telefon) : null,
                'fertig' => $a['tot'] > 0 && $a['done'] === $a['tot'],
            ];
        });

        return [
            'tour' => $tour,
            'auftraege' => $auftraege,
            'done' => $done,
            'tot' => $tot,
            'offen' => $tot - $done,
            'pct' => $tot > 0 ? (int) round($done / $tot * 100) : 0,
            'stopps' => $stopps,
        ];
    }

    // ---------- Aufbereitung (Port von _logiItems/_logiVals) ----------

    /** @return Collection<int, array> relevante Bestellungen als View-Modelle */
    private function relevanteAuftraege(): Collection
    {
        return Bestellung::query()
            ->with(['positionen', 'lieferant', 'projekt', 'kunde'])
            ->whereIn('status', self::RELEVANT)
            ->orderByDesc('nr')
            ->get()
            ->map(fn (Bestellung $b) => $this->auftragsDaten($b))
            ->values();
    }

    /** @return array Auftrag + gruppierte Positionen + Fortschritt */
    public function auftragsDaten(Bestellung $bestellung): array
    {
        $positionen = $bestellung->positionen->map(fn (BestellungPosition $p) => [
            'position' => $p,
            'gruppe' => self::GRUPPEN[$p->typ->value] ?? 'Material-Positionen',
            'detail' => $this->detailText($p),
            'menge' => $this->mengeText($p),
        ]);

        $done = $bestellung->positionen->whereNotNull('kommissioniert_am')->count();
        $tot = $bestellung->positionen->count();
        $status = $done === 0 ? 'offen' : ($done < $tot ? 'teil' : 'fertig');

        $anzahl = fn (string $typ) => $bestellung->positionen->where('typ.value', $typ)->count();
        $nGlas = $anzahl('glas');
        $nSchiebe = $anzahl('schiebe');
        $nMat = $anzahl('material');
        $mix = implode(' · ', array_filter([
            $nGlas ? $nGlas.'× Glas' : null,
            $nSchiebe ? $nSchiebe.'× Schiebe' : null,
            $nMat ? $nMat.'× Material' : null,
        ]));

        return [
            'bestellung' => $bestellung,
            'positionen' => $positionen,
            'gruppen' => $positionen->groupBy('gruppe'),
            'done' => $done,
            'tot' => $tot,
            'offen' => $tot - $done,
            'pct' => $tot > 0 ? (int) round($done / $tot * 100) : 0,
            'status' => $status,
            'statusLabel' => self::STATUS_LABEL[$status][0],
            'statusBadge' => self::STATUS_LABEL[$status][1],
            'nGlas' => $nGlas,
            'nSchiebe' => $nSchiebe,
            'nMat' => $nMat,
            'mix' => $mix !== '' ? $mix : '–',
            'stk' => (int) $bestellung->positionen->sum(fn (BestellungPosition $p) => (int) $p->menge),
        ];
    }

    /** Detailzeile wie _logiItems: Glasform/Maße, Schiebe-Elemente, Material. */
    private function detailText(BestellungPosition $p): string
    {
        $d = $p->details ?? [];

        return match ($p->typ->value) {
            'glas' => ($d['form'] ?? 'Rechteck').' · '.$p->breite_mm.' × '.($d['hL'] ?? $p->hoehe_mm)
                .(($d['form'] ?? '') === 'Trapez' ? '/'.($d['hR'] ?? '') : '').' mm · '.($d['glas'] ?? ''),
            'schiebe' => $p->breite_mm.' × '.$p->hoehe_mm.' mm · '.($d['count'] ?? (int) $p->menge)
                .' Elemente · '.($d['glas'] ?? ''),
            default => 'Materialposition',
        };
    }

    private function mengeText(BestellungPosition $p): string
    {
        return $p->typ->value === 'schiebe'
            ? ((int) $p->menge).' Elem.'
            : ((int) $p->menge).' Stk';
    }
}
