<?php

namespace App\Http\Controllers;

use App\Enums\Rolle;
use App\Models\Lieferant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Einstellungen (role:projektleiter). Im Prototyp nicht entworfen;
 * fachlich gefordert ist die Konfigurierbarkeit der Aufmaß-Toleranzen
 * (README: «Schwellen sind Geschäftsregeln — konfigurierbar halten»).
 * Genau die zwei geseedeten Schlüssel werden hier gepflegt. Der Admin
 * sieht zusätzlich die Benutzerverwaltung (BenutzerController).
 */
class EinstellungenController extends Controller
{
    public function zeige(Request $request): View
    {
        $istAdmin = $request->user()->role === Rolle::Admin;

        return view('einstellungen.index', [
            'gruen' => (int) Setting::wert('toleranz_gruen_mm', 5),
            'gelb' => (int) Setting::wert('toleranz_gelb_mm', 15),
            'benutzer' => $istAdmin ? User::query()->with('lieferant')->orderBy('role')->orderBy('name')->get() : null,
            'lieferanten' => $istAdmin ? Lieferant::query()->orderBy('name')->pluck('name', 'id') : collect(),
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
