<?php

namespace App\Http\Controllers;

use App\Models\Anfrage;
use App\Models\Projekt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Kalender — Wochenraster (Prototyp Z. 1708–1727). Der Prototyp friert
 * KW 29 mit Demo-Terminen ein; hier echte Wochen-Navigation (?woche=)
 * und live abgeleitete Ereignisse: Montage aus Projekt-Terminspannen,
 * Aufmaß aus Anfrage-Besuchsterminen. Team-Filter des Prototyps entfällt
 * bewusst (kein Team-Feld im Datenmodell); Legende bleibt vollständig.
 */
class KalenderController extends Controller
{
    private const TAGE = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    private const TYPEN = [
        'Montage' => ['calm', 'b-blue'],
        'Aufmaß' => ['cala', 'b-green'],
        'Service' => ['cals', 'b-gray'],
        'Puffer' => ['calp', 'b-yellow'],
    ];

    public function index(Request $request): View
    {
        $start = $this->wochenstart($request->query('woche'));
        $ende = $start->copy()->addDays(6);

        $tage = collect(range(0, 6))->map(function (int $i) use ($start) {
            $datum = $start->copy()->addDays($i);

            return [
                'name' => self::TAGE[$i],
                'datum' => $datum,
                'label' => $datum->format('d.m.'),
                'heute' => $datum->isToday(),
                'events' => $this->eventsFuer($datum),
            ];
        });

        $kommende = $tage->flatMap(fn (array $tag) => collect($tag['events'])
            ->map(fn (array $e) => $e + ['tag' => $tag['name'], 'datum' => $tag['label']]))
            ->take(8);

        return view('kalender.index', [
            'tage' => $tage,
            'kommende' => $kommende,
            'kw' => 'KW '.$start->isoWeek(),
            'zeitraum' => $start->format('d.m.').' – '.$ende->format('d.m.Y'),
            'vorher' => $start->copy()->subWeek()->format('o-\WW'),
            'nachher' => $start->copy()->addWeek()->format('o-\WW'),
            'legende' => collect(self::TYPEN)->map(fn (array $t, string $label) => ['label' => $label, 'bc' => $t[1]])->values(),
        ]);
    }

    public function terminFormular(Request $request): View
    {
        return view('kalender.termin', [
            'projekte' => Projekt::query()->with('kunde')
                ->where('status', '!=', \App\Enums\ProjektStatus::Abgeschlossen)
                ->orderByDesc('nr')->get(),
            'datum' => $request->query('datum'),
        ]);
    }

    public function speichereTermin(Request $request): \Illuminate\Http\RedirectResponse
    {
        $daten = $request->validate([
            'projekt_id' => ['required', 'exists:projekte,id'],
            'termin_von' => ['required', 'date'],
            'termin_bis' => ['nullable', 'date', 'after_or_equal:termin_von'],
        ], ['termin_bis.after_or_equal' => 'Das Ende darf nicht vor dem Beginn liegen.']);

        $projekt = Projekt::query()->findOrFail($daten['projekt_id']);
        $projekt->update([
            'termin_von' => $daten['termin_von'],
            'termin_bis' => $daten['termin_bis'] ?? $daten['termin_von'],
        ]);
        $projekt->aktivitaeten()->create([
            'titel' => 'Montage-Termin '.\App\Support\Format::datumKurz($projekt->termin_von)
                .'–'.\App\Support\Format::datumKurz($projekt->termin_bis).' gesetzt',
            'wer' => $request->user()->name,
            'datum' => now()->format('d.m.'),
            'status' => 'done',
        ]);

        return redirect()->route('kalender', ['woche' => $projekt->termin_von->format('o-\WW')])
            ->with('toast', 'Montage-Termin für '.$projekt->nr.' eingetragen');
    }

    private function wochenstart(?string $woche): Carbon
    {
        if ($woche && preg_match('/^(\d{4})-W(\d{1,2})$/', $woche, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 53) {
            return Carbon::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek();
        }

        return Carbon::now()->startOfWeek();
    }

    /** @return list<array> Ereignisse eines Tages (Montage + Aufmaß) */
    private function eventsFuer(Carbon $datum): array
    {
        $events = [];

        foreach (Projekt::query()->with('kunde')->whereNotNull('termin_von')->get() as $projekt) {
            $bis = $projekt->termin_bis ?? $projekt->termin_von;
            if ($datum->betweenIncluded($projekt->termin_von->startOfDay(), $bis->endOfDay())) {
                $events[] = $this->event('Montage', 'ganztägig', $projekt->nr, $projekt->titel,
                    ($projekt->kunde->anzeigename).' · '.($projekt->objekt_stadt ?? '–'),
                    route('projekte.show', $projekt));
            }
        }

        foreach (Anfrage::query()->with('kunde')->whereDate('besuchstermin_datum', $datum->toDateString())->get() as $anfrage) {
            $events[] = $this->event('Aufmaß',
                $anfrage->besuchstermin_uhrzeit ? substr($anfrage->besuchstermin_uhrzeit, 0, 5) : '—',
                $anfrage->nummer, $anfrage->produkt_notiz ?? 'Aufmaßtermin',
                ($anfrage->kunde?->anzeigename ?? trim($anfrage->kunden_vorname.' '.$anfrage->kunden_nachname))
                .' · '.($anfrage->objekt_stadt ?? '–'),
                route('anfragen.show', $anfrage));
        }

        return $events;
    }

    private function event(string $typ, string $zeit, string $nr, string $titel, string $meta, string $url): array
    {
        return [
            'typ' => $typ,
            'cls' => self::TYPEN[$typ][0],
            'bc' => self::TYPEN[$typ][1],
            'zeit' => $zeit,
            'nr' => $nr,
            'titel' => $titel,
            'meta' => $meta,
            'url' => $url,
        ];
    }
}
