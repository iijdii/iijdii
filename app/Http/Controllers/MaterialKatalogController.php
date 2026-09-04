<?php

namespace App\Http\Controllers;

use App\Enums\ArtikelKategorie;
use App\Enums\Einheit;
use App\Models\Artikel;
use App\Models\ArtikelAlias;
use App\Models\Lieferant;
use App\Support\AliasNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function create(): View
    {
        return $this->formular(null);
    }

    public function store(Request $request): RedirectResponse
    {
        $artikel = Artikel::query()->create($this->validiert($request, null));

        return redirect()->route('material-katalog.edit', $artikel)
            ->with('toast', 'Artikel '.$artikel->art_nr.' angelegt');
    }

    public function edit(Artikel $artikel): View
    {
        return $this->formular($artikel->load('aliase'));
    }

    public function update(Request $request, Artikel $artikel): RedirectResponse
    {
        // Bestand nur per Korrekturbuchung ändern (Journal-Invariante) —
        // ein mitgesendetes bestand-Feld wird bewusst ignoriert.
        $artikel->update($this->validiert($request, $artikel));

        return redirect()->route('material-katalog.edit', $artikel)->with('toast', 'Artikel aktualisiert');
    }

    public function speichereAlias(Request $request, Artikel $artikel): RedirectResponse
    {
        $daten = $request->validate(['alias' => ['required', 'string', 'max:120']]);
        $normalized = AliasNormalizer::normalize($daten['alias']);
        if (ArtikelAlias::query()->where('alias_normalized', $normalized)->exists()) {
            return redirect()->route('material-katalog.edit', $artikel)->with('toast', 'Alias existiert bereits');
        }

        $artikel->aliase()->create(['alias' => $daten['alias']]);

        return redirect()->route('material-katalog.edit', $artikel)->with('toast', 'Alias gespeichert');
    }

    public function loescheAlias(Artikel $artikel, ArtikelAlias $alias): RedirectResponse
    {
        abort_unless($alias->artikel_id === $artikel->id, 404);
        $alias->delete();

        return redirect()->route('material-katalog.edit', $artikel)->with('toast', 'Alias entfernt');
    }

    private function formular(?Artikel $artikel): View
    {
        return view('material-katalog.form', [
            'artikel' => $artikel,
            'kategorien' => ArtikelKategorie::cases(),
            'einheiten' => Einheit::cases(),
            'lieferanten' => Lieferant::query()->orderBy('name')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validiert(Request $request, ?Artikel $artikel): array
    {
        $daten = $request->validate([
            'art_nr' => ['required', 'string', 'max:64', Rule::unique('artikel', 'art_nr')->ignore($artikel?->id)],
            'name' => ['required', 'string', 'max:150'],
            'kategorie' => ['required', Rule::enum(ArtikelKategorie::class)],
            'einheit' => ['required', Rule::enum(Einheit::class)],
            'lagerort' => ['nullable', 'string', 'max:32'],
            'min_bestand' => ['required', 'integer', 'min:0'],
            'ek_preis' => ['required', 'numeric', 'min:0'],
            'lieferant_id' => ['required', 'exists:lieferanten,id'],
            'bestand' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($artikel !== null) {
            unset($daten['bestand']); // nach Anlage nur noch Korrekturbuchung
        } else {
            $daten['bestand'] = (int) ($daten['bestand'] ?? 0);
        }

        return $daten;
    }
}
