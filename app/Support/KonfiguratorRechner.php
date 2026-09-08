<?php

namespace App\Support;

/**
 * Rechenkern des Konfigurators — exakter Port von defaultPcfg/_pvals
 * aus dem Design-Prototyp (design/LEA CRM.dc.html, das laut Handoff
 * „calculation core" ist und unverändert übernommen werden muss).
 */
final class KonfiguratorRechner
{
    public const PRODUKTE = ['Überdachung', 'Carport', 'Vordach', 'Kube'];

    public const FARBEN = [
        'Anthrazit · RAL 7016', 'Weiß · RAL 9016', 'Grau · RAL 7040',
        'DB703 · Eisenglimmer', 'RAL nach Wunsch',
    ];

    public const DECKUNGEN = ['VSG-Glas', 'VSG-Glas mattiert', 'Polycarbonat klar', 'Polycarbonat opal'];

    public const STAERKEN = ['8 mm', '10 mm', '16 mm'];

    public const EXTRAS = ['Keile', 'Festelemente', 'Schiebe-Elemente', 'Markisen', 'Sonnensegel'];

    public const SCHNEELAST = ['SLZ 1 · 0,65 kN/m²', 'SLZ 2 · 0,85 kN/m²', 'SLZ 3 · 1,10 kN/m²'];

    /**
     * Glas-Fertigungsregeln (Vorgabe des Betreibers, ersetzen die
     * vereinfachte 1080er-Rasterformel des Prototyps):
     * max. Glasbreite 750 mm; Breite = Achsmaß − 22 mm; Tiefe = T − 60 mm.
     */
    public const MAX_GLAS_BREITE = 750;

    public const GLAS_ABZUG_BREITE = 22;

    public const GLAS_ABZUG_TIEFE = 60;

    public const WINDZONE = ['WZ 1 · Binnenland', 'WZ 2 · Binnenland', 'WZ 3 · Küste', 'WZ 4 · Küste/Inseln'];

    public static function defaults(): array
    {
        return [
            'product' => 'Überdachung',
            'mounting' => 'an der Wand',
            'shape' => 'rechteck',
            'width' => 8630,
            'depth' => 3500,
            'wallH' => 2990,
            'gutterH' => 2500,
            'slope' => 8,
            'color' => 'Weiß · RAL 9016',
            'covering' => 'VSG-Glas',
            'glasTrans' => 'Klar',
            'thickness' => '8 mm',
            'postN' => '',
            'fieldN' => '',
            'snow' => 'SLZ 2 · 0,85 kN/m²',
            'wind' => 'WZ 2 · Binnenland',
            'extras' => ['Keile', 'Schiebe-Elemente', 'Markisen'],
            'keil' => ['count' => 1, 'hFront' => 120, 'side' => 'Links', 'material' => 'Glas', 'trans' => 'Klar'],
            'fest' => ['count' => 1, 'width' => 1000, 'height' => 2000, 'glas' => 'VSG-Glas', 'h2' => 2400],
            'schiebe' => ['width' => 4000, 'height' => 2200, 'count' => 3, 'dir' => 'left', 'glas' => 'Klar'],
            'markise' => ['modell' => 'Varisol T200', 'width' => 5000, 'ausfall' => 3000, 'felder' => 2],
            'segel' => ['count' => 1, 'size' => '4×4 m', 'color' => 'Sandbeige'],
            'drain' => ['post' => 1, 'height' => 1150, 'dir' => 'nach vorn'],
            'duebel' => ['typ' => 'Schlagdübel', 'size' => '10 × 80 mm', 'abstand' => 500],
            'led' => ['on' => true, 'total' => 12, 'color' => 'Warmweiß 3000K'],
        ];
    }

    /** Teil-Konfiguration über die Defaults legen; extras ersetzen, nicht mischen. */
    public static function merge(?array $pcfg): array
    {
        $basis = self::defaults();

        foreach ($pcfg ?? [] as $key => $wert) {
            if (is_array($wert) && isset($basis[$key]) && is_array($basis[$key]) && $key !== 'extras') {
                $basis[$key] = array_merge($basis[$key], $wert);
            } else {
                $basis[$key] = $wert;
            }
        }

        return $basis;
    }

    /**
     * Abgeleitete Werte + Positionsliste (_pvals). Referenz W=8630, T=3500:
     * rec 4, fields 12, rafters 13, spar 719, blende „659 mm",
     * Glas 697 × 3.440 mm (Minimum an Feldern, sodass Glasbreite ≤ 750).
     */
    public static function berechne(array $pcfg): array
    {
        $p = self::merge($pcfg);
        $I = fn ($v) => (int) $v;
        $W = $I($p['width']);
        $D = $I($p['depth']);

        $rec = $W > 0 ? (int) ceil($W / 4000) + 1 : 0;
        $pn = ($p['postN'] !== '' && $I($p['postN']) > 0) ? $I($p['postN']) : $rec;
        $maxAchsmass = self::MAX_GLAS_BREITE + self::GLAS_ABZUG_BREITE;
        $autoFields = $W > 0 ? (int) ceil($W / $maxAchsmass) : 0;
        // Wie postN: manuelle Feldanzahl gewinnt, der Rest rechnet daraus weiter.
        $fields = ($p['fieldN'] !== '' && $I($p['fieldN']) > 0) ? $I($p['fieldN']) : $autoFields;
        $rafters = $fields > 0 ? $fields + 1 : 0;
        $spar = $fields > 0 ? (int) round($W / $fields) : 0;
        $blende = max(0, $spar - 60);
        $glasB = max(0, $spar - self::GLAS_ABZUG_BREITE);
        $glasT = max(0, $D - self::GLAS_ABZUG_TIEFE);
        $ledTot = ($I($p['led']['total'] ?? 12) === 6) ? 6 : 12;

        $extras = $p['extras'] ?? [];
        $hat = fn (string $x) => in_array($x, $extras, true);
        $trapez = $p['shape'] === 'trapez';

        $positionen = [];
        $add = function (string $name, int|float $menge) use (&$positionen) {
            $positionen[] = ['pos' => count($positionen) + 1, 'name' => $name, 'menge' => $menge];
        };

        $add(($p['product'] ?: 'Überdachung').' '.($trapez ? 'Trapez' : 'Rechteck').' '.$W.'×'.$D.' mm', 1);
        $add('Pfosten 110×110 · '.$p['color'], $pn);
        $add('Dachsparren 80×60 mm', $rafters);
        $add('Dachfeld '.$p['covering'].' '.$p['thickness'], $fields);

        if ($hat('Keile')) {
            $k = $p['keil'];
            $add('Keil '.$k['side'].' · '.$k['material'].' ('.$k['trans'].')', $I($k['count']) ?: 1);
        }
        if ($hat('Festelemente')) {
            $f = $p['fest'];
            $add('Festfeld '.$I($f['width']).'×'.$I($f['height']).' · '.$f['glas'], $I($f['count']) ?: 1);
        }
        if ($hat('Schiebe-Elemente')) {
            $s = $p['schiebe'];
            $add('Schiebeanlage '.$I($s['width']).'×'.$I($s['height']).' · '.($I($s['count']) ?: 1).' Elem.', 1);
        }
        if ($hat('Markisen')) {
            $m = $p['markise'];
            $add($m['modell'].' B'.$I($m['width']).' · Ausf. '.$I($m['ausfall']), $I($m['felder']) ?: 1);
        }
        if ($hat('Sonnensegel')) {
            $sg = $p['segel'];
            $add('Sonnensegel '.$sg['size'].' · '.$sg['color'], $I($sg['count']) ?: 1);
        }

        return [
            'pcfg' => $p,
            'rec' => $rec,
            'pn' => $pn,
            'rafters' => $rafters,
            'fields' => $fields,
            'spar' => $spar,
            'autoFields' => $autoFields,
            'glasB' => $glasB,
            'glasT' => $glasT,
            'glasText' => $spar ? number_format($glasB, 0, ',', '.').' × '.number_format($glasT, 0, ',', '.').' mm' : '–',
            'glasZuBreit' => $glasB > self::MAX_GLAS_BREITE,
            'blende' => $blende,
            'blendeText' => number_format($blende, 0, ',', '.').' mm',
            'sparText' => $spar ? number_format($spar, 0, ',', '.').' mm' : '–',
            'ledTot' => $ledTot,
            'positionen' => $positionen,
            'warnung' => $D > 4000,
        ];
    }
}
