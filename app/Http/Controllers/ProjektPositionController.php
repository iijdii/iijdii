<?php

namespace App\Http\Controllers;

use App\Models\BestellungPosition;
use App\Models\Projekt;
use App\Models\ProjektPosition;
use App\Support\KonfigurationSync;
use App\Support\ProduktFelder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Positions-Editor des Projekt-Konfigurators (Einheitssystem): pro Produkt
 * ein Validierungssatz (Muster BestellungController), genau eine
 * Dach-Position pro Projekt, jede Änderung spiegelt die Dach-Position
 * nach projekte.konfiguration.
 */
class ProjektPositionController extends Controller
{
    public function store(Request $request, Projekt $projekt): RedirectResponse
    {
        $daten = ProduktFelder::daten((array) $request->input('position'));

        if ($daten['produkt']->istDach() && $projekt->positionen()->where('gruppe', 'dach')->exists()) {
            return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
                ->with('toast', 'Nur eine Dachposition pro Projekt — bestehende bearbeiten');
        }

        $projekt->positionen()->create($daten + [
            'pos' => ((int) $projekt->positionen()->max('pos')) + 1,
        ]);
        KonfigurationSync::spiegleDach($projekt);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
            ->with('toast', 'Position hinzugefügt · '.$daten['produkt']->label());
    }

    public function update(Request $request, Projekt $projekt, ProjektPosition $position): RedirectResponse
    {
        abort_unless($position->projekt_id === $projekt->id, 404);

        $daten = ProduktFelder::daten((array) $request->input('position'));

        if ($daten['produkt']->istDach() && ! $position->produkt->istDach()
            && $projekt->positionen()->where('gruppe', 'dach')->exists()) {
            return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
                ->with('toast', 'Nur eine Dachposition pro Projekt');
        }

        $position->update($daten);
        KonfigurationSync::spiegleDach($projekt);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
            ->with('toast', 'Position '.$position->pos.' aktualisiert');
    }

    public function loeschen(Projekt $projekt, ProjektPosition $position): RedirectResponse
    {
        abort_unless($position->projekt_id === $projekt->id, 404);

        // Verweise aus Bestellpositionen lösen — nicht jede Datenbank
        // trägt den nullOnDelete-Fremdschlüssel (Shared Hosting).
        BestellungPosition::query()
            ->where('projekt_position_id', $position->id)
            ->update(['projekt_position_id' => null]);
        $position->delete();
        KonfigurationSync::spiegleDach($projekt);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
            ->with('toast', 'Position entfernt');
    }
}
