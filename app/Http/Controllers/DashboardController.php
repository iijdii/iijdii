<?php

namespace App\Http\Controllers;

use App\Enums\AnfrageStatus;
use App\Enums\AngebotStatus;
use App\Enums\ProjektStatus;
use App\Models\Anfrage;
use App\Models\Angebot;
use App\Models\Artikel;
use App\Models\Projekt;
use App\Models\ProjektAktivitaet;
use App\Support\Format;
use Illuminate\View\View;

/**
 * Dashboard — Layout/Klassen/Texte aus dem Prototyp (Z. 900, 1626–1647),
 * aber JEDE Zahl live berechnet: die Prototyp-Werte sind illustrativ und
 * widersprechen den Modulen, auf die die Karten verlinken.
 */
class DashboardController extends Controller
{
    private const MONATE = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    private const MONATE_LANG = [
        'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
        'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
    ];

    public function index(): View
    {
        $heute = now();

        // ---- KPI-Zeile ----
        $offeneAnfragen = Anfrage::query()->whereNotIn('status', [
            AnfrageStatus::Abgeschlossen, AnfrageStatus::Abgelehnt, AnfrageStatus::KeinInteresse,
        ])->get();
        $neueWoche = $offeneAnfragen->filter(fn (Anfrage $a) => $a->created_at?->gte($heute->copy()->subDays(7)))->count();

        $aktiveAngebote = Angebot::query()
            ->whereIn('status', [AngebotStatus::Entwurf, AngebotStatus::Versendet])->get();
        $angenommen = Angebot::query()->where('status', AngebotStatus::Angenommen)->get();
        $abgelehnt = Angebot::query()->where('status', AngebotStatus::Abgelehnt)->count();
        $akzeptiertMonat = $angenommen->filter(fn (Angebot $a) => $a->datum?->isSameMonth($heute))->count();
        $quote = $angenommen->count() + $abgelehnt > 0
            ? (int) round($angenommen->count() / ($angenommen->count() + $abgelehnt) * 100)
            : 0;

        $projekte = Projekt::query()->where('status', '!=', ProjektStatus::Abgeschlossen)->get();
        $inMontage = $projekte->where('status', ProjektStatus::InMontage)->count();

        $kpis = [
            ['k' => 'Offene Anfragen', 'v' => $offeneAnfragen->count(), 's' => '+'.$neueWoche.' diese Woche', 'c' => 'kp-blue'],
            ['k' => 'Aktive Angebote', 'v' => $aktiveAngebote->count(), 's' => Format::eur0((float) $aktiveAngebote->sum('summe')).' Volumen', 'c' => 'kp-gold'],
            ['k' => 'Akzeptiert (Monat)', 'v' => $akzeptiertMonat, 's' => 'Quote '.$quote.' %', 'c' => 'kp-green'],
            ['k' => 'Aktive Projekte', 'v' => $projekte->count(), 's' => $inMontage.' in Montage', 'c' => 'kp-dark'],
        ];

        // ---- Vertriebs-Trichter (Breiten aus den Live-Zahlen, conv aus dem Vorgänger) ----
        $stufen = [
            ['Anfragen', Anfrage::query()->count()],
            ['Angebote', Angebot::query()->count()],
            ['Projekte', Projekt::query()->count()],
            ['In Montage', Projekt::query()->where('status', ProjektStatus::InMontage)->count()],
        ];
        $maxStufe = max(1, ...array_column($stufen, 1));
        $funnel = [];
        foreach ($stufen as $i => [$label, $anzahl]) {
            $funnel[] = [
                'label' => $label,
                'count' => $anzahl,
                'breite' => (int) round($anzahl / $maxStufe * 100),
                'conv' => $i === 0 ? '—'
                    : ($stufen[$i - 1][1] > 0 ? round($anzahl / $stufen[$i - 1][1] * 100).' %' : '—'),
            ];
        }

        // ---- Auftragseingang: angenommene Angebote je Monat, letzte 6 Monate,
        //      DB-agnostisch in PHP gruppiert (SQLite/MySQL). ----
        $jeMonat = $angenommen->filter(fn (Angebot $a) => $a->datum !== null)
            ->groupBy(fn (Angebot $a) => $a->datum->format('Y-m'))
            ->map(fn ($gruppe) => (float) $gruppe->sum('summe'));
        $bars = [];
        for ($i = 5; $i >= 0; $i--) {
            $monat = $heute->copy()->subMonthsNoOverflow($i);
            $bars[] = [
                'm' => self::MONATE[$monat->month - 1],
                'v' => (int) round(($jeMonat[$monat->format('Y-m')] ?? 0) / 1000),
                'on' => $i === 0,
            ];
        }
        $barsMax = max(1, ...array_column($bars, 'v'));
        foreach ($bars as &$bar) {
            $bar['hoehe'] = (int) round($bar['v'] / $barsMax * 100);
        }
        unset($bar);

        // ---- Heutige Termine: Projekt-Montagespannen + Aufmaß-Besuchstermine ----
        $termine = collect();
        foreach (Projekt::query()->with('kunde')->whereNotNull('termin_von')->get() as $projekt) {
            $bis = $projekt->termin_bis ?? $projekt->termin_von;
            if ($heute->betweenIncluded($projekt->termin_von->startOfDay(), $bis->endOfDay())) {
                $termine->push([
                    'zeit' => '—', 'kunde' => $projekt->kunde->anzeigename,
                    'sub' => $projekt->titel, 'typ' => 'Montage', 'bc' => 'b-blue',
                    'url' => route('projekte.show', $projekt),
                ]);
            }
        }
        foreach (Anfrage::query()->with('kunde')->whereDate('besuchstermin_datum', $heute->toDateString())->get() as $anfrage) {
            $termine->push([
                'zeit' => $anfrage->besuchstermin_uhrzeit ? substr($anfrage->besuchstermin_uhrzeit, 0, 5) : '—',
                'kunde' => $anfrage->kunde?->anzeigename ?? trim($anfrage->kunden_vorname.' '.$anfrage->kunden_nachname),
                'sub' => $anfrage->produkt_notiz ?? 'Aufmaßtermin', 'typ' => 'Aufmaß', 'bc' => 'b-green',
                'url' => route('anfragen.show', $anfrage),
            ]);
        }
        $termine = $termine->sortBy('zeit')->values();

        // ---- Lager-Warnungen: dasselbe Prädikat wie LagerController ($low) ----
        $niedrig = Artikel::query()->withSum('reservierungen', 'menge')->get()
            ->filter(fn (Artikel $a) => $a->bestandsstatus() !== 'ok')->values();

        return view('dashboard.index', [
            'kpis' => $kpis,
            'funnel' => $funnel,
            'funnelMonat' => self::MONATE_LANG[$heute->month - 1].' '.$heute->year,
            'bars' => $bars,
            'termine' => $termine,
            'heute' => $heute->format('d.m.Y'),
            'niedrig' => $niedrig,
            'aktivitaeten' => ProjektAktivitaet::query()->with('projekt')->latest('id')->take(5)->get(),
            'letzteAngebote' => Angebot::query()->with('kunde')->orderByDesc('nr')->take(5)->get(),
        ]);
    }
}
