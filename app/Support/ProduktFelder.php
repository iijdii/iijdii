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
                'slope' => ['nullable', 'numeric', 'between:0,45'],
                'postN' => $mm, 'fieldN' => $mm,
                'color' => ['nullable', Rule::in(KonfiguratorRechner::FARBEN)],
                'covering' => ['nullable', Rule::in(array_merge(KonfiguratorRechner::DECKUNGEN, KonfiguratorRechner::DECKUNGEN_LEGACY))],
                'thickness' => ['nullable', Rule::in(KonfiguratorRechner::STAERKEN)],
                'glasTrans' => ['nullable', Rule::in(['Klar', 'Milch'])],
                'terraceDepth' => $mm, 'gutterOverhang' => $mm,
                'postLeftOffset' => ['nullable', 'integer', 'between:0,500'],
                'postRightOffset' => ['nullable', 'integer', 'between:0,500'],
                'postMiddle' => $mm,
                'postManual' => ['nullable', 'string', 'max:200', 'regex:/^[\d\s,]*$/'],
                // Pfosten-Befestigung: global oder je Pfosten einzeln.
                'postMontage' => ['nullable', Rule::in(['Beton', 'U-Profil', 'Pfostenhalter'])],
                'postMontageJe' => ['nullable', Rule::in(['1', 1, 'ja'])],
                'postMontageListe' => ['nullable', 'array', 'max:12'],
                'postMontageListe.*' => [Rule::in(['Beton', 'U-Profil', 'Pfostenhalter'])],
                // Pfosten-Abstände und Profilsegmente sind hinter Checkboxen.
                'postAdvanced' => ['nullable', Rule::in(['1', 1, 'ja'])],
                'profilManuell' => ['nullable', Rule::in(['1', 1, 'ja'])],
                'profilSegmenteListe' => ['nullable', 'string', 'max:200', 'regex:/^[\d\s,]*$/'],
                'trapez' => ['nullable', 'array'],
                'trapez.wand' => $mm, 'trapez.rinne' => $mm,
                'trapez.offsetL' => $mm, 'trapez.offsetR' => $mm,
                'unterzug' => ['nullable', 'array'],
                'unterzug.on' => ['nullable', Rule::in(['nein', 'ja'])],
                'unterzug.anzahl' => ['nullable', 'integer', 'between:1,3'],
                'unterzug.ueberstand' => $mm,
                'unterzug.groesse' => ['nullable', Rule::in(['110×190', '110×110', 'manuell'])],
                // Wandanschluss: Belag + Isolierung bestimmen Dübel-Vorschlag.
                'wand' => ['nullable', 'array'],
                'wand.belag' => ['nullable', Rule::in(['Klinker', 'Putz', 'Holz'])],
                'wand.isolierung' => ['nullable', Rule::in(['nein', 'ja'])],
                'wand.daemmstaerke' => $mm,
                // Entwässerung, Dübel & LED — pcfg-verschachtelt (Montage-Modus liest sie).
                'drain' => ['nullable', 'array'],
                // Seite (links/rechts) seit v26; Bestandsdaten tragen Nummern.
                'drain.post' => ['nullable', function (string $attribut, mixed $wert, \Closure $fehler) {
                    if (! in_array($wert, ['links', 'rechts'], true) && ! is_numeric($wert)) {
                        $fehler('Ablauf: links, rechts oder Pfostennummer.');
                    }
                }],
                'drain.height' => $mm,
                'drain.dir' => ['nullable', Rule::in(['nach vorn', 'nach hinten', 'nach links', 'nach rechts'])],
                'duebel' => ['nullable', 'array'],
                'duebel.typ' => ['nullable', Rule::in(['Schlagdübel', 'Bolzenanker', 'Injektionsanker', 'Porenbetonanker', 'Stockschrauben'])],
                'duebel.size' => ['nullable', 'string', 'max:32'],
                'duebel.abstand' => $mm,
                'led' => ['nullable', 'array'],
                'led.total' => ['nullable', Rule::in([0, 6, 12, '0', '6', '12'])],
                'led.color' => ['nullable', Rule::in(['Warmweiß 3000K', 'Neutralweiß 4000K', 'RGBW'])],
            ]
            : match ($produkt) {
                ProjektProdukt::Wand => [
                    'anzahl' => $mm, 'reihen' => $mm, 'breite_mm' => $mm, 'h_links_mm' => $mm, 'h_rechts_mm' => $mm,
                    'glas' => ['nullable', 'string', 'max:64'],
                ],
                ProjektProdukt::Schiebe => [
                    'breite_mm' => $mm, 'hoehe_mm' => $mm, 'anzahl' => $mm,
                    'richtung' => ['nullable', Rule::in(['Nach links', 'Nach rechts', 'Mittig'])],
                    'glas' => ['nullable', 'string', 'max:64'],
                ],
                ProjektProdukt::Keil => [
                    'anzahl' => $mm, 'breite_mm' => $mm, 'h_hinten_mm' => $mm, 'h_vorn_mm' => $mm,
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
