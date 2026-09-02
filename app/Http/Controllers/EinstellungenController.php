<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Einstellungen (role:projektleiter). Im Prototyp nicht entworfen;
 * fachlich gefordert ist die Konfigurierbarkeit der Aufmaß-Toleranzen
 * (README: «Schwellen sind Geschäftsregeln — konfigurierbar halten»).
 * Genau die zwei geseedeten Schlüssel werden hier gepflegt.
 */
class EinstellungenController extends Controller
{
    public function zeige(): View
    {
        return view('einstellungen.index', [
            'gruen' => (int) Setting::wert('toleranz_gruen_mm', 5),
            'gelb' => (int) Setting::wert('toleranz_gelb_mm', 15),
        ]);
    }

    public function speichere(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'toleranz_gruen_mm' => ['required', 'integer', 'min:0', 'max:100'],
            'toleranz_gelb_mm' => ['required', 'integer', 'min:0', 'max:100', 'gte:toleranz_gruen_mm'],
        ], [
            'toleranz_gelb_mm.gte' => 'Die Gelb-Schwelle muss mindestens so groß wie die Grün-Schwelle sein.',
        ]);

        Setting::setzeWert('toleranz_gruen_mm', (int) $daten['toleranz_gruen_mm']);
        Setting::setzeWert('toleranz_gelb_mm', (int) $daten['toleranz_gelb_mm']);

        return redirect()->route('einstellungen')->with('toast', 'Einstellungen gespeichert');
    }
}
