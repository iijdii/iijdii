<?php

namespace App\Http\Controllers;

use App\Enums\BestellungStatus;
use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\Lieferant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lieferanten-Stammdaten: KPI-Zeile + Tabelle mit live abgeleiteten
 * Kennzahlen je Lieferant; Anlegen, Bearbeiten und Löschen über Fenster
 * auf derselben Seite. Gelöscht wird nur ohne Bestellungen, Artikel und
 * Portal-Zugänge; gelöschte und umbenannte Namen merkt sich «lieferanten_entfernt»,
 * damit /einrichtung (LieferantSeeder) sie nicht zurückholt.
 */
class LieferantController extends Controller
{
    private const OFFEN = [BestellungStatus::Geprueft, BestellungStatus::Bestellt, BestellungStatus::Bereit];

    public function index(): View
    {
        $lieferanten = Lieferant::query()
            ->withCount('artikel')
            ->with('artikel')
            ->orderBy('name')
            ->get()
            ->map(fn (Lieferant $lieferant) => [
                'lieferant' => $lieferant,
                'offeneBestellungen' => Bestellung::query()
                    ->where('lieferant_id', $lieferant->id)
                    ->whereIn('status', self::OFFEN)
                    ->count(),
                'lagerwert' => $lieferant->artikel->sum(fn (Artikel $artikel) => $artikel->lagerwert()),
            ]);

        return view('lieferanten.index', [
            'lieferanten' => $lieferanten,
            'artikelGesamt' => Artikel::query()->count(),
            'offenGesamt' => Bestellung::query()->whereIn('status', self::OFFEN)->count(),
            'lagerwertGesamt' => $lieferanten->sum('lagerwert'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $daten = $request->validateWithBag('lieferant_neu', $this->regeln());
        $lieferant = Lieferant::query()->create($daten);
        $this->vergiss($lieferant->name);

        return redirect()->route('lieferanten')->with('toast', 'Lieferant '.$lieferant->name.' angelegt');
    }

    public function update(Request $request, Lieferant $lieferant): RedirectResponse
    {
        $daten = $request->validateWithBag('lieferant_'.$lieferant->id, $this->regeln($lieferant));
        $alterName = $lieferant->name;
        $lieferant->update($daten);
        if ($alterName !== $lieferant->name) {
            $this->merke($alterName);
        }
        $this->vergiss($lieferant->name);

        return redirect()->route('lieferanten')->with('toast', 'Lieferant '.$lieferant->name.' gespeichert');
    }

    public function loesche(Lieferant $lieferant): RedirectResponse
    {
        $inGebrauch = $lieferant->bestellungen()->exists()
            || $lieferant->artikel()->exists()
            || User::query()->where('lieferant_id', $lieferant->id)->exists();
        if ($inGebrauch) {
            return redirect()->route('lieferanten')
                ->with('toast', $lieferant->name.' hat Bestellungen, Artikel oder Portal-Zugänge — Löschen nicht möglich');
        }

        $lieferant->delete();
        $this->merke($lieferant->name);

        return redirect()->route('lieferanten')->with('toast', 'Lieferant '.$lieferant->name.' gelöscht');
    }

    /** Gelöschte/umbenannte Namen legt der Seeder nicht erneut an. */
    private function merke(string $name): void
    {
        $entfernt = (array) Setting::wert('lieferanten_entfernt', []);
        Setting::setzeWert('lieferanten_entfernt', array_values(array_unique([...$entfernt, $name])));
    }

    /** Ein wieder angelegter Name darf auch vom Seeder wieder gepflegt werden. */
    private function vergiss(string $name): void
    {
        $entfernt = (array) Setting::wert('lieferanten_entfernt', []);
        if (in_array($name, $entfernt, true)) {
            Setting::setzeWert('lieferanten_entfernt', array_values(array_diff($entfernt, [$name])));
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function regeln(?Lieferant $lieferant = null): array
    {
        return [
            'name' => ['required', 'string', 'max:190', Rule::unique('lieferanten', 'name')->ignore($lieferant?->id)],
            'kundennummer' => ['nullable', 'string', 'max:40'],
            'sortiment' => ['nullable', 'string', 'max:190'],
            'ansprechpartner' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'telefon' => ['nullable', 'string', 'max:60'],
            'strasse' => ['nullable', 'string', 'max:190'],
            'plz' => ['nullable', 'string', 'max:10'],
            'stadt' => ['nullable', 'string', 'max:190'],
        ];
    }
}
