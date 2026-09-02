<?php

namespace App\Http\Controllers;

use App\Enums\BestellungStatus;
use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\Lieferant;
use Illuminate\View\View;

/**
 * Lieferanten-Stammdaten. Im Prototyp nicht entworfen (gemeinsamer
 * Leerzustand «Liste → Formular → Detailansicht … folgen als nächste
 * Ausbaustufe») — Aufbau nach dem Material-Katalog-Muster: KPI-Zeile +
 * Tabelle mit live abgeleiteten Kennzahlen je Lieferant.
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
}
