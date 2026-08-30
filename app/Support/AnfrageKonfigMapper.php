<?php

namespace App\Support;

use App\Models\Anfrage;

/**
 * Anfrage → Konfigurator-Payload: Port der _createProjekt-Zuordnung aus
 * dem Prototyp, mit den dort fehlenden Normalisierungen (Laufrichtung
 * auf left/right/center, Whitelists für Deckung/Stärke).
 */
final class AnfrageKonfigMapper
{
    public static function pcfg(Anfrage $anfrage): array
    {
        $d = $anfrage->details ?? [];
        $produktart = $d['produktart'] ?? ($anfrage->produkt_notiz ?? '');
        $prod = implode(' ', $anfrage->interessierte_produkte ?? []).' '.$produktart;

        $product = match (true) {
            (bool) preg_match('/carport/iu', $produktart) => 'Carport',
            (bool) preg_match('/vordach/iu', $produktart) => 'Vordach',
            (bool) preg_match('/kube/iu', $produktart) => 'Kube',
            default => 'Überdachung',
        };

        $keile = $d['keils'] ?? [];
        $sidewalls = $d['sidewalls'] ?? [];
        $sliding = $d['sliding'] ?? [];

        $extras = [];
        if ($keile !== [] || preg_match('/keil/iu', $prod) || $anfrage->keil_links || $anfrage->keil_rechts) {
            $extras[] = 'Keile';
        }
        if ($sidewalls !== [] || preg_match('/seitenwand|fest/iu', $prod)
            || $anfrage->seitenwand_links || $anfrage->seitenwand_rechts) {
            $extras[] = 'Festelemente';
        }
        if ($sliding !== [] || preg_match('/schiebe|glaswand/iu', $prod) || $anfrage->schiebesystem) {
            $extras[] = 'Schiebe-Elemente';
        }
        if (preg_match('/marki|sonnenschutz/iu', $prod) || $anfrage->markise) {
            $extras[] = 'Markisen';
        }
        if (preg_match('/segel/iu', $prod) || $anfrage->sonnensegel) {
            $extras[] = 'Sonnensegel';
        }

        $mm = fn ($wert) => ((int) preg_replace('/[^\d]/', '', (string) $wert)) ?: 0;

        $pcfg = [
            'product' => $product,
            'mounting' => preg_match('/frei/iu', $d['montage'] ?? ($anfrage->befestigung_art ?? '')) ? 'freistehend' : 'an der Wand',
            'shape' => preg_match('/trapez/iu', $d['form'] ?? ($anfrage->form ?? '')) ? 'trapez' : 'rechteck',
            'width' => $mm($d['breite'] ?? null) ?: (($anfrage->breite_cm ?? 0) * 10 ?: 6000),
            'depth' => $mm($d['tiefe'] ?? null) ?: (($anfrage->tiefe_cm ?? 0) * 10 ?: 3500),
            'wallH' => $mm($d['wandH'] ?? null) ?: 2900,
            'gutterH' => $mm($d['rinneH'] ?? null) ?: 2500,
            'slope' => (int) str_replace(',', '.', (string) ($d['neigung'] ?? ($anfrage->dachneigung_grad ?? 8))) ?: 8,
            'color' => $d['farbe'] ?? ($anfrage->profil_farbe_name ?? KonfiguratorRechner::defaults()['color']),
            'covering' => in_array($d['dachMat'] ?? '', KonfiguratorRechner::DECKUNGEN, true)
                ? $d['dachMat'] : 'VSG-Glas',
            'thickness' => in_array($d['dachStk'] ?? '', KonfiguratorRechner::STAERKEN, true)
                ? $d['dachStk'] : '8 mm',
            'postN' => $mm($d['pfAnzahl'] ?? null) ?: '',
            'extras' => $extras,
        ];

        if ($keile !== []) {
            [$side, $material, $trans] = array_pad($keile[0], 3, null);
            $pcfg['keil'] = array_filter([
                'side' => $side, 'material' => $material, 'trans' => $trans,
            ], fn ($v) => $v !== null);
        }

        if ($sidewalls !== []) {
            [, $anz, $material] = array_pad($sidewalls[0], 3, null);
            $pcfg['fest'] = [
                'count' => $mm($anz) ?: 1,
                'glas' => preg_match('/poly/iu', (string) $material) ? 'Polycarbonat klar' : 'VSG-Glas',
            ];
        }

        if ($sliding !== []) {
            [$elemente, , $breite, $hoehe, $richtung] = array_pad($sliding[0], 5, null);
            $pcfg['schiebe'] = array_filter([
                'width' => $mm($breite) ?: null,
                'height' => $mm($hoehe) ?: null,
                'count' => $mm($elemente) ?: null,
                'dir' => self::richtung($richtung),
            ], fn ($v) => $v !== null);
        }

        return $pcfg;
    }

    /** „Nach links / Nach rechts / Mittig" → left / right / center. */
    public static function richtung(?string $wert): ?string
    {
        return match (true) {
            $wert === null => null,
            (bool) preg_match('/links|left/iu', $wert) => 'left',
            (bool) preg_match('/rechts|right/iu', $wert) => 'right',
            (bool) preg_match('/mittig|center|beidseitig/iu', $wert) => 'center',
            default => null,
        };
    }
}
