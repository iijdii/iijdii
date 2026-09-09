<?php

namespace App\Http\Controllers;

use App\Enums\ProjektProdukt;
use App\Models\Projekt;
use App\Models\ProjektPosition;
use App\Support\KonfigurationSync;
use App\Support\KonfiguratorRechner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        $daten = $this->positionsDaten((array) $request->input('position'));

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

        $daten = $this->positionsDaten((array) $request->input('position'));

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

        $position->delete();
        KonfigurationSync::spiegleDach($projekt);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
            ->with('toast', 'Position entfernt');
    }

    /** @return array{produkt: ProjektProdukt, gruppe: string, phase: int, felder: array} */
    private function positionsDaten(array $eingabe): array
    {
        $basis = Validator::make($eingabe, [
            'produkt' => ['required', Rule::enum(ProjektProdukt::class)],
            'felder' => ['nullable', 'array'],
        ])->validate();
        $produkt = ProjektProdukt::from($basis['produkt']);

        return [
            'produkt' => $produkt,
            'gruppe' => $produkt->gruppe(),
            'phase' => $produkt->phase(),
            'felder' => $this->produktFelder($produkt, $eingabe['felder'] ?? []),
        ];
    }

    private function produktFelder(ProjektProdukt $produkt, array $felder): array
    {
        $mm = ['nullable', 'integer', 'min:0'];
        $regeln = $produkt->istDach()
            ? [
                'mounting' => ['nullable', Rule::in(['an der Wand', 'freistehend'])],
                'shape' => ['nullable', Rule::in(['rechteck', 'trapez'])],
                'width' => $mm, 'depth' => $mm, 'wallH' => $mm, 'gutterH' => $mm,
                'slope' => ['nullable', 'integer', 'between:0,45'],
                'postN' => $mm, 'fieldN' => $mm,
                'color' => ['nullable', Rule::in(KonfiguratorRechner::FARBEN)],
                'covering' => ['nullable', Rule::in(KonfiguratorRechner::DECKUNGEN)],
                'thickness' => ['nullable', Rule::in(KonfiguratorRechner::STAERKEN)],
                'glasTrans' => ['nullable', Rule::in(['Klar', 'Milch'])],
                'snow' => ['nullable', Rule::in(KonfiguratorRechner::SCHNEELAST)],
                'wind' => ['nullable', Rule::in(KonfiguratorRechner::WINDZONE)],
            ]
            : match ($produkt) {
                ProjektProdukt::Wand => [
                    'anzahl' => $mm, 'breite_mm' => $mm, 'h_links_mm' => $mm, 'h_rechts_mm' => $mm,
                    'glas' => ['nullable', 'string', 'max:64'],
                ],
                ProjektProdukt::Schiebe => [
                    'breite_mm' => $mm, 'hoehe_mm' => $mm, 'anzahl' => $mm,
                    'richtung' => ['nullable', Rule::in(['Nach links', 'Nach rechts', 'Mittig'])],
                    'glas' => ['nullable', 'string', 'max:64'],
                ],
                ProjektProdukt::Keil => [
                    'anzahl' => $mm, 'h_vorn_mm' => $mm,
                    'seite' => ['nullable', Rule::in(['Links', 'Rechts', 'Beidseitig'])],
                    'material' => ['nullable', Rule::in(['Glas', 'Aluminium', 'Polycarbonat'])],
                    'transparenz' => ['nullable', Rule::in(['Klar', 'Opal', 'Matt'])],
                ],
                ProjektProdukt::Gelaender => [
                    'laenge_mm' => $mm, 'hoehe_mm' => $mm, 'felder_n' => $mm,
                    'material' => ['nullable', Rule::in(['Aluminium', 'Glas', 'Edelstahl'])],
                ],
                ProjektProdukt::Markise => [
                    'modell' => ['nullable', 'string', 'max:64'], 'breite_mm' => $mm,
                    'ausfall_mm' => $mm, 'felder_n' => $mm,
                    'antrieb' => ['nullable', Rule::in(['Motor', 'Kurbel'])],
                ],
                default => [
                    'anzahl' => $mm,
                    'groesse' => ['nullable', 'string', 'max:32'], 'farbe' => ['nullable', 'string', 'max:32'],
                ],
            };

        $validiert = Validator::make($felder, $regeln)->validate();

        return array_filter($validiert, fn ($wert) => $wert !== null && $wert !== '');
    }
}
