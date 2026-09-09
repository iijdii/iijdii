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
     * Fertigungsregeln nach der KD-Montageanleitung (Kap. 5/6):
     * Achsmaß = (Dachbreite − 60) / Felder (2×30 mm Seitenrand);
     * Eindeckungsbreite = Achsmaß − 22 mm (Glas max. 750 mm,
     * Polycarbonat-Stegplatte Standard 980 mm → Achsmaß 1002 mm);
     * Eindeckungslänge = Dachtiefe − 50 mm.
     */
    public const MAX_GLAS_BREITE = 750;

    public const POLY_PLATTE = 980;

    public const RAND_ABZUG = 60;

    public const GLAS_ABZUG_BREITE = 22;

    public const GLAS_ABZUG_TIEFE = 50;

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
            'terraceDepth' => '',
            'gutterOverhang' => '',
            'unterzug' => ['groesse' => '110×190'],
            'postLeftOffset' => '',
            'postRightOffset' => '',
            'postMiddle' => '',
            'postManual' => '',
            'trapez' => ['wand' => '', 'rinne' => '', 'offsetL' => '', 'offsetR' => ''],
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

    /**
     * Profilsegmente: Rinne/Wandprofil über 7.000 mm werden geteilt
     * (Transport/Fertigung); der Stoß liegt später über einem Pfosten.
     *
     * @return list<int>
     */
    public static function segmente(int $laenge, int $max = 7000): array
    {
        if ($laenge <= 0) {
            return [];
        }
        $anzahl = (int) ceil($laenge / $max);
        $segmente = array_fill(0, $anzahl - 1, $max);
        $segmente[] = $laenge - ($anzahl - 1) * $max;

        return $segmente;
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
     * Abgeleitete Werte + Positionsliste (_pvals). Referenz W=8630, T=3500,
     * VSG-Glas: rec 4, fields 12, rafters 13, spar 714 ((8630−60)/12),
     * blende „654 mm", Glas 692 × 3.450 mm (Minimum an Feldern, sodass
     * die Glasbreite ≤ 750 bleibt; Polycarbonat zielt auf die
     * 980er-Stegplatte, Achsmaß 1002).
     */
    public static function berechne(array $pcfg): array
    {
        $p = self::merge($pcfg);
        $I = fn ($v) => (int) $v;
        $W = $I($p['width']);
        $D = $I($p['depth']);

        $rec = $W > 0 ? (int) ceil($W / 4000) + 1 : 0;
        $pn = ($p['postN'] !== '' && $I($p['postN']) > 0) ? $I($p['postN']) : $rec;
        $poly = str_starts_with((string) $p['covering'], 'Polycarbonat');
        $maxPlatte = $poly ? self::POLY_PLATTE : self::MAX_GLAS_BREITE;
        $nutzbreite = max(0, $W - self::RAND_ABZUG);
        $manuelleFelder = ($p['fieldN'] !== '' && $I($p['fieldN']) > 0) ? $I($p['fieldN']) : null;

        // Polyrest nur im Automatikmodus: volle 1000er-Felder (Platte 980
        // ungeschnitten) + ein schmales Restfeld (Platte = Rest − 20).
        $polyRestPlatte = null;
        if ($poly && $nutzbreite > 0) {
            $volle = (int) floor($nutzbreite / 1000);
            $rest = $nutzbreite - $volle * 1000;
            $autoFields = max(1, $volle + ($rest > 0 ? 1 : 0));
        } else {
            $autoFields = $nutzbreite > 0
                ? (int) ceil($nutzbreite / (self::MAX_GLAS_BREITE + self::GLAS_ABZUG_BREITE))
                : 0;
        }
        // Wie postN: manuelle Feldanzahl gewinnt, der Rest rechnet daraus weiter.
        $fields = $manuelleFelder ?? $autoFields;
        $rafters = $fields > 0 ? $fields + 1 : 0;

        if ($poly && $manuelleFelder === null && $fields > 0) {
            $spar = min(1000, (int) round($nutzbreite / $fields));
            $glasB = self::POLY_PLATTE;
            $rest = $nutzbreite - ((int) floor($nutzbreite / 1000)) * 1000;
            if ($rest > 0 && $fields > 1) {
                $polyRestPlatte = max(0, $rest - 20);
            } elseif ($fields === 1) {
                $glasB = max(0, $nutzbreite - 20);
            }
        } else {
            $spar = $fields > 0 ? (int) round($nutzbreite / $fields) : 0;
            $glasB = max(0, $spar - self::GLAS_ABZUG_BREITE);
        }
        $blende = max(0, $spar - 60);
        $glasT = max(0, $D - self::GLAS_ABZUG_TIEFE);
        $ledTot = ($I($p['led']['total'] ?? 12) === 6) ? 6 : 12;

        // Höhen ↔ Neigung (v3.2): beide Höhen → realer Winkel aus atan;
        // nur eine Höhe → die andere folgt aus der Neigung.
        $wallH = $I($p['wallH']);
        $gutterH = $I($p['gutterH']);
        if ($wallH > 0 && $gutterH > 0 && $D > 0) {
            $slopeEff = round(rad2deg(atan(abs($wallH - $gutterH) / $D)), 1);
        } else {
            $slopeEff = (float) ($p['slope'] ?: 8);
            $diff = (int) round($D * tan(deg2rad($slopeEff)));
            if ($wallH > 0 && $gutterH <= 0) {
                $gutterH = max(0, $wallH - $diff);
            } elseif ($gutterH > 0 && $wallH <= 0) {
                $wallH = $gutterH + $diff;
            }
        }
        $gefaelleProzent = round(tan(deg2rad($slopeEff)) * 100, 1);

        // Unterzug (v3.2): Pflicht bei freistehend, Tiefe > 4000,
        // Dachüberstand oder Pfostenlinie vor der Traufe.
        $terrace = $I($p['terraceDepth'] ?? '');
        $overhang = $I($p['gutterOverhang'] ?? '');
        $gruende = [];
        if ($p['mounting'] === 'freistehend') {
            $gruende[] = 'freistehende Konstruktion';
        }
        if ($D > 4000) {
            $gruende[] = 'Tiefe über 4.000 mm';
        }
        if ($overhang > 0) {
            $gruende[] = 'Dachüberstand an der Rinne';
        }
        if ($terrace > 0 && $terrace < $D) {
            $gruende[] = 'Pfostenlinie vor der Traufe';
        }
        $unterzug = [
            'erforderlich' => $gruende !== [],
            'gruende' => $gruende,
            'position' => ($terrace > 0 && $terrace < $D) ? $terrace : $D,
            'ueberstand' => ($terrace > 0 && $terrace < $D) ? $D - $terrace : 0,
            'groesse' => $p['unterzug']['groesse'] ?? '110×190',
        ];

        // Pfosten-Positionen (v3.2): manuelle CSV-Positionen haben Vorrang;
        // sonst Randabstände (max. 500), optional Mittelpfosten-Position.
        $pfostenPositionen = [];
        $linksX = min(500, max(0, $I($p['postLeftOffset'] ?? '')));
        $rechtsX = max($linksX, $W - min(500, max(0, $I($p['postRightOffset'] ?? ''))));
        $manuell = trim((string) ($p['postManual'] ?? ''));
        if ($manuell !== '' && $W > 0) {
            foreach (explode(',', $manuell) as $wert) {
                $x = (int) trim($wert);
                if ($x >= 0 && $x <= $W) {
                    $pfostenPositionen[] = $x;
                }
            }
        }
        if ($pfostenPositionen === [] && $W > 0 && $pn >= 2) {
            $pfostenPositionen = [$linksX];
            if ($pn === 3) {
                $mitte = $I($p['postMiddle'] ?? '');
                $pfostenPositionen[] = ($mitte > $linksX && $mitte < $rechtsX) ? $mitte : (int) round($W / 2);
            } elseif ($pn > 3) {
                $schritt = ($rechtsX - $linksX) / ($pn - 1);
                for ($i = 1; $i <= $pn - 2; $i++) {
                    $pfostenPositionen[] = (int) round($linksX + $schritt * $i);
                }
            }
            $pfostenPositionen[] = $rechtsX;
        }
        $pfostenPositionen = array_values(array_unique($pfostenPositionen));
        sort($pfostenPositionen);
        $spannZuGross = false;
        for ($i = 1; $i < count($pfostenPositionen); $i++) {
            if ($pfostenPositionen[$i] - $pfostenPositionen[$i - 1] > 4000) {
                $spannZuGross = true;
            }
        }

        // Trapez-Geometrie (v3.2): Längendifferenz Wand/Rinne verteilt sich
        // auf die Seiten-Offsets (leer = automatisch symmetrisch).
        $trapezGeo = null;
        if ($p['shape'] === 'trapez') {
            $wandL = $I($p['trapez']['wand'] ?? '') ?: $W;
            $rinneL = $I($p['trapez']['rinne'] ?? '') ?: $W;
            $diffT = abs($wandL - $rinneL);
            $oL = (string) ($p['trapez']['offsetL'] ?? '');
            $oR = (string) ($p['trapez']['offsetR'] ?? '');
            if ($oL === '' && $oR === '') {
                $offL = intdiv($diffT, 2);
                $offR = $diffT - $offL;
            } elseif ($oL !== '' && $oR === '') {
                $offL = max(0, $I($oL));
                $offR = max(0, $diffT - $offL);
            } elseif ($oL === '' && $oR !== '') {
                $offR = max(0, $I($oR));
                $offL = max(0, $diffT - $offR);
            } else {
                $offL = max(0, $I($oL));
                $offR = max(0, $I($oR));
            }
            $trapezGeo = [
                'wand' => $wandL,
                'rinne' => $rinneL,
                'offsetLinks' => $offL,
                'offsetRechts' => $offR,
                'winkelLinks' => $D > 0 ? round(rad2deg(atan($offL / $D)), 1) : 0.0,
                'winkelRechts' => $D > 0 ? round(rad2deg(atan($offR / $D)), 1) : 0.0,
                'kurzeSeite' => $wandL < $rinneL ? 'Wandprofil' : 'Rinne',
            ];
        }

        // Profilsegmente (max. 7.000 mm Transport-/Fertigungslänge) und
        // Stoß-Empfehlung: Pfosten mittig unter dem Stoß (Stoß − 55).
        $segmente = self::segmente($W);
        $stossPfosten = [];
        $x = 0;
        for ($i = 0; $i < count($segmente) - 1; $i++) {
            $x += $segmente[$i];
            $empfohlen = $x - 55;
            $vorhanden = array_filter($pfostenPositionen, fn ($px) => abs($px - $empfohlen) <= 100);
            if ($empfohlen > $linksX && $empfohlen < $rechtsX && $vorhanden === []) {
                $stossPfosten[] = $empfohlen;
            }
        }

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
            'glasText' => $spar
                ? number_format($glasB, 0, ',', '.').' × '.number_format($glasT, 0, ',', '.').' mm'
                    .($polyRestPlatte !== null ? ' · Restfeld '.number_format($polyRestPlatte, 0, ',', '.').' mm' : '')
                : '–',
            'glasZuBreit' => $glasB > $maxPlatte,
            'maxPlatte' => $maxPlatte,
            'poly' => $poly,
            'polyRestPlatte' => $polyRestPlatte,
            'slopeEff' => $slopeEff,
            'gefaelleProzent' => $gefaelleProzent,
            'wallHEff' => $wallH,
            'gutterHEff' => $gutterH,
            'unterzug' => $unterzug,
            'postPositionen' => $pfostenPositionen,
            'spannZuGross' => $spannZuGross,
            'profilSegmente' => $segmente,
            'stossPfosten' => $stossPfosten,
            'trapez' => $trapezGeo,
            'blende' => $blende,
            'blendeText' => number_format($blende, 0, ',', '.').' mm',
            'sparText' => $spar ? number_format($spar, 0, ',', '.').' mm' : '–',
            'ledTot' => $ledTot,
            'positionen' => $positionen,
            'warnung' => $D > 4000,
        ];
    }
}
