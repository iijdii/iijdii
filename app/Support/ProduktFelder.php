<?php

namespace App\Support;

use App\Enums\ProjektProdukt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validierung einer Produkt-Position (Diskriminator + produktspezifische
 * Regeln) — genutzt vom Positions-Editor des Projekts und vom
 * Anfrage-Formular (Auto-Start des Projekts).
 */
final class ProduktFelder
{
    /** @return array{produkt: ProjektProdukt, gruppe: string, phase: int, felder: array} */
    public static function daten(array $eingabe): array
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
            'felder' => self::felder($produkt, $eingabe['felder'] ?? []),
        ];
    }

    private static function felder(ProjektProdukt $produkt, array $felder): array
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
                'terraceDepth' => $mm, 'gutterOverhang' => $mm,
                'postLeftOffset' => ['nullable', 'integer', 'between:0,500'],
                'postRightOffset' => ['nullable', 'integer', 'between:0,500'],
                'postMiddle' => $mm,
                'postManual' => ['nullable', 'string', 'max:200', 'regex:/^[\d\s,]*$/'],
                'trapez' => ['nullable', 'array'],
                'trapez.wand' => $mm, 'trapez.rinne' => $mm,
                'trapez.offsetL' => $mm, 'trapez.offsetR' => $mm,
                'unterzug' => ['nullable', 'array'],
                'unterzug.groesse' => ['nullable', Rule::in(['110×190', '110×110', 'manuell'])],
                // Entwässerung, Dübel & LED — pcfg-verschachtelt (Montage-Modus liest sie).
                'drain' => ['nullable', 'array'],
                'drain.post' => $mm, 'drain.height' => $mm,
                'drain.dir' => ['nullable', Rule::in(['nach vorn', 'nach hinten', 'nach links', 'nach rechts'])],
                'duebel' => ['nullable', 'array'],
                'duebel.typ' => ['nullable', Rule::in(['Schlagdübel', 'Bolzenanker', 'Injektionsanker', 'Porenbetonanker'])],
                'duebel.size' => ['nullable', 'string', 'max:32'],
                'duebel.abstand' => $mm,
                'led' => ['nullable', 'array'],
                'led.total' => ['nullable', Rule::in([0, 6, 12, '0', '6', '12'])],
                'led.color' => ['nullable', Rule::in(['Warmweiß 3000K', 'Neutralweiß 4000K', 'RGBW'])],
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

        return self::ohneLeere($validiert);
    }

    /** Leere Werte rekursiv entfernen (auch leer gewordene Teil-Arrays). */
    private static function ohneLeere(array $werte): array
    {
        $ergebnis = [];
        foreach ($werte as $schluessel => $wert) {
            if (is_array($wert)) {
                $wert = self::ohneLeere($wert);
                if ($wert === []) {
                    continue;
                }
            } elseif ($wert === null || $wert === '') {
                continue;
            }
            $ergebnis[$schluessel] = $wert;
        }

        return $ergebnis;
    }
}
