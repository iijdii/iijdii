<?php

namespace App\Http\Controllers;

use App\Enums\ArtikelKategorie;
use App\Models\Artikel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialKatalogController extends Controller
{
    public function index(Request $request): View
    {
        $ansicht = $request->query('ansicht') === 'liste' ? 'liste' : 'karten';
        $kategorie = $request->query('kategorie', 'alle');
        $suche = trim((string) $request->query('q', ''));

        $alle = Artikel::query()
            ->with('lieferant')
            ->withSum('reservierungen', 'menge')
            ->orderBy('name')
            ->get();

        // Suche: Name, Art-Nr., Lieferant und Kategorie-Label —
        // PHP-seitig, weil sqlite-LIKE bei Umlauten case-sensitiv ist.
        $gesucht = $suche === '' ? $alle : $alle->filter(function (Artikel $a) use ($suche) {
            $heuhaufen = mb_strtolower(
                $a->name.' '.$a->art_nr.' '.$a->lieferant->name.' '.$a->kategorie->label()
            );

            return str_contains($heuhaufen, mb_strtolower($suche));
        })->values();

        // Chips zählen NACH der Suche (Prototyp-Semantik, anders als im Lager).
        $chips = collect([['alle', 'Alle']])
            ->concat(collect(ArtikelKategorie::cases())->map(fn ($k) => [$k->value, $k->label()]))
            ->map(fn (array $chip) => [
                'key' => $chip[0],
                'label' => $chip[1],
                'anzahl' => $chip[0] === 'alle'
                    ? $gesucht->count()
                    : $gesucht->filter(fn (Artikel $a) => $a->kategorie->value === $chip[0])->count(),
                'aktiv' => $chip[0] === $kategorie,
            ]);

        $gefiltert = $kategorie === 'alle'
            ? $gesucht
            : $gesucht->filter(fn (Artikel $a) => $a->kategorie->value === $kategorie)->values();

        return view('material-katalog.index', [
            'ansicht' => $ansicht,
            'kategorie' => $kategorie,
            'suche' => $suche,
            'chips' => $chips,
            'gefiltert' => $gefiltert,
            'gesamt' => $alle->count(),
            'gruppen' => $gefiltert->groupBy(fn (Artikel $a) => $a->kategorie->value),
            'kpi' => [
                'lieferanten' => $alle->sortBy('lieferant_id')->pluck('lieferant.name')->unique()->values(),
                'oEk' => $alle->avg(fn (Artikel $a) => (float) $a->ek_preis) ?? 0,
                'mitBild' => $alle->whereNotNull('bild')->count(),
            ],
        ]);
    }
}
