<?php

namespace App\Http\Controllers;

use App\Enums\ArtikelKategorie;
use App\Enums\BestellungStatus;
use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\Lagerbewegung;
use App\Models\Reservierung;
use App\Models\WareneingangPosition;
use App\Enums\LagerbewegungTyp;
use Illuminate\Support\Facades\DB;
use App\Services\LagerService;
use App\Support\PdfArchiv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LagerController extends Controller
{
    public function __construct(private readonly LagerService $lager)
    {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['bestand', 'wareneingang', 'reservierungen', 'bewegungen'], true)
            ? $request->query('tab')
            : 'bestand';

        $artikel = Artikel::query()
            ->with('lieferant')
            ->withSum('reservierungen', 'menge')
            ->orderBy('name')
            ->get();

        $letzterZugang = $this->letzterZugang();
        $low = $artikel->filter(fn (Artikel $a) => $a->bestandsstatus() !== 'ok')->values();

        $offeneBestellungen = Bestellung::query()
            ->whereIn('status', [BestellungStatus::Geprueft, BestellungStatus::Bestellt, BestellungStatus::Bereit])
            ->get();

        $daten = [
            'tab' => $tab,
            'artikel' => $artikel,
            'letzterZugang' => $letzterZugang,
            'low' => $low,
            'lagerwert' => $artikel->sum(fn (Artikel $a) => $a->lagerwert()),
            'kpiOffen' => [
                'anzahl' => $offeneBestellungen->count(),
                'artikel' => $offeneBestellungen->sum(
                    fn (Bestellung $b) => count($this->lager->aggregierePositionen($b))
                ),
            ],
        ];

        $daten += match ($tab) {
            'bestand' => $this->bestandTab($request, $artikel),
            'wareneingang' => $this->wareneingangTab(),
            'reservierungen' => $this->reservierungenTab($letzterZugang),
            'bewegungen' => ['bewegungen' => Lagerbewegung::query()
                ->with(['artikel', 'benutzer'])
                ->orderByDesc('datum')->orderByDesc('id')
                ->get()],
            default => [],
        };

        return view('lager.index', $daten);
    }

    /** Artikel-Modal als Layout-loses Fragment (per fetch geladen). */
    public function artikel(Artikel $artikel): View
    {
        return view('lager.partials.artikel-modal', [
            'artikel' => $artikel->loadMissing('lieferant'),
            'zugang' => $this->letzterZugang()->get($artikel->id),
            'reservierungen' => $artikel->reservierungen()->with('projekt.kunde')->get(),
            'bewegungen' => Lagerbewegung::query()
                ->where('artikel_id', $artikel->id)
                ->with('benutzer')
                ->orderByDesc('datum')->orderByDesc('id')
                ->get(),
        ]);
    }

    /** Manuelle Korrekturbuchung: Bestand ± Menge + eine Journal-Zeile. */
    public function bucheKorrektur(Request $request, Artikel $artikel): \Illuminate\Http\RedirectResponse
    {
        $daten = $request->validate([
            'menge' => ['required', 'integer', 'not_in:0', 'min:-9999', 'max:9999'],
            'grund' => ['required', 'string', 'max:120'],
        ], ['menge.not_in' => 'Menge darf nicht 0 sein.']);

        if ($artikel->bestand + $daten['menge'] < 0) {
            return redirect()->route('lager')
                ->with('toast', 'Korrektur würde den Bestand negativ machen');
        }

        DB::transaction(function () use ($artikel, $daten, $request) {
            $artikel->increment('bestand', $daten['menge']);
            Lagerbewegung::query()->create([
                'datum' => now(),
                'typ' => LagerbewegungTyp::Korrektur,
                'artikel_id' => $artikel->id,
                'menge' => $daten['menge'],
                'referenz' => $daten['grund'],
                'benutzer_id' => $request->user()->id,
                'benutzer_name' => $request->user()->name.' · Lager',
            ]);
        });

        return redirect()->route('lager')
            ->with('toast', 'Korrektur gebucht · '.\App\Support\Format::mengeSigniert((int) $daten['menge']));
    }

    /** Lieferschein-PDF zum gebuchten Wareneingang einer Bestellung. */
    public function lieferschein(Bestellung $bestellung): \Illuminate\Http\Response
    {
        $wareneingang = $bestellung->wareneingaenge()->with('benutzer')->latest('datum')->first();
        abort_unless($wareneingang !== null, 404);

        return PdfArchiv::liefere('lager.lieferschein-pdf', [
            'bestellung' => $bestellung->load(['lieferant', 'projekt']),
            'wareneingang' => $wareneingang,
            'positionen' => $this->lager->aggregierePositionen($bestellung),
        ], 'Lieferschein_'.$wareneingang->lieferschein_nr.'.pdf', $bestellung->projekt, 'lieferschein');
    }

    public function bucheWareneingang(Request $request, Bestellung $bestellung): RedirectResponse
    {
        if (in_array($bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Storniert], true)) {
            return redirect()->route('lager', ['tab' => 'wareneingang'])
                ->with('toast', 'Bestellung '.$bestellung->nr.' kann im Status '.$bestellung->status->label().' nicht gebucht werden');
        }

        $wareneingang = $this->lager->bucheWareneingang($bestellung, $request->user());

        return redirect()->route('lager', ['tab' => 'wareneingang'])->with(
            'toast',
            'Wareneingang '.$bestellung->nr.' gebucht · '.$wareneingang->positionen()->count().' Artikel eingelagert'
        );
    }

    /** Letzter Wareneingang je Artikel: artikel_id → {datum, nr, ls}. */
    private function letzterZugang(): Collection
    {
        return WareneingangPosition::query()
            ->with('wareneingang.bestellung')
            ->get()
            ->sortBy(fn (WareneingangPosition $p) => $p->wareneingang->datum)
            ->keyBy('artikel_id')
            ->map(fn (WareneingangPosition $p) => [
                'datum' => $p->wareneingang->datum,
                'nr' => $p->wareneingang->bestellung->nr,
                'ls' => $p->wareneingang->lieferschein_nr,
            ]);
    }

    private function bestandTab(Request $request, Collection $artikel): array
    {
        $filter = $request->query('kategorie', 'alle');

        $predicate = function (Artikel $a) use ($filter): bool {
            return match ($filter) {
                'alle' => true,
                'niedrig' => $a->bestandsstatus() !== 'ok',
                'leer' => $a->bestandsstatus() === 'leer',
                default => $a->kategorie->value === $filter,
            };
        };

        $chips = collect([['alle', 'Alle']])
            ->concat(collect(ArtikelKategorie::cases())->map(fn ($k) => [$k->value, $k->label()]))
            ->concat([['niedrig', 'Unter Mindestbestand'], ['leer', 'Leer']])
            ->map(fn (array $chip) => [
                'key' => $chip[0],
                'label' => $chip[1],
                'anzahl' => $artikel->filter(function (Artikel $a) use ($chip) {
                    return match ($chip[0]) {
                        'alle' => true,
                        'niedrig' => $a->bestandsstatus() !== 'ok',
                        'leer' => $a->bestandsstatus() === 'leer',
                        default => $a->kategorie->value === $chip[0],
                    };
                })->count(),
                'aktiv' => $chip[0] === $filter,
            ]);

        return [
            'filter' => $filter,
            'chips' => $chips,
            'gefiltert' => $artikel->filter($predicate)->values(),
        ];
    }

    private function wareneingangTab(): array
    {
        $karten = Bestellung::query()
            ->where('status', '!=', BestellungStatus::Storniert)
            ->with(['lieferant', 'projekt', 'kunde', 'wareneingaenge.benutzer', 'wareneingaenge.positionen'])
            ->orderBy('nr')
            ->get()
            ->map(function (Bestellung $b) {
                $wareneingang = $b->wareneingaenge->first();
                $zeilen = $this->lager->aggregierePositionen($b);

                return [
                    'bestellung' => $b,
                    'zeilen' => $zeilen,
                    'stueck' => array_sum(array_column($zeilen, 'menge')),
                    'wareneingang' => $wareneingang,
                    'gebucht' => $wareneingang !== null,
                    'entwurf' => $b->status === BestellungStatus::Entwurf,
                ];
            })
            ->sortBy(fn (array $karte) => $karte['gebucht'] ? 1 : 0)
            ->values();

        return ['karten' => $karten];
    }

    private function reservierungenTab(Collection $letzterZugang): array
    {
        $gruppen = Reservierung::query()
            ->with(['projekt.kunde', 'artikel'])
            ->get()
            ->groupBy('projekt_id')
            ->map(fn (Collection $reservierungen) => [
                'projekt' => $reservierungen->first()->projekt,
                'reservierungen' => $reservierungen,
                'stueck' => (int) $reservierungen->sum('menge'),
                // Deep-Link: erste Bestellung des Projekts in der Rüstliste
                'kommissionierung' => $reservierungen->first()->projekt?->bestellungen()
                    ->whereIn('status', [BestellungStatus::Bestellt, BestellungStatus::Bereit, BestellungStatus::Geliefert])
                    ->orderByDesc('nr')->first(),
            ])
            ->values();

        return ['gruppen' => $gruppen, 'zugaenge' => $letzterZugang];
    }
}
