<?php

namespace App\Support;

/**
 * Die fünf technischen Zeichnungen des Dachs — Port von
 * design/RoofDrawing.dc.html. Der Prototyp ist NICHT parametrisch
 * (alle Koordinaten sind auf W=8630/D=3500/H2=2500/8° eingefroren);
 * hier sind die Regeln rekonstruiert und an KonfiguratorRechner
 * gekoppelt. Bewusste Abweichungen vom eingefrorenen Prototyp,
 * jeweils zugunsten des Rechenkerns:
 *   - Pfosten aus pn (Demo: 4 statt gezeichneter 3, Kette 3 × 2877
 *     statt 2 × 4315),
 *   - Innensparren rafters−2 (Iso zeichnete fälschlich 8 statt 7),
 *   - Fußnote „à {spar}" (1079 statt hartkodierter 1071),
 *   - H1 aus wallH (Iso zeigte 3050, Konfiguration sagt 2900/2990),
 *   - Detail-Maß = spar, Glaslabel/Farbe aus pcfg, Firma aus config.
 *
 * Rückgabe: fertige Knotenlisten in Zeichenreihenfolge; Blade gibt
 * nur aus (partials/roof-zeichnung.blade.php). Defs (rd-da, rd-hatch,
 * rd-hatchd) liegen im Icon-Sprite.
 */
final class RoofZeichnung
{
    public const ANSICHTEN = ['iso', 'top', 'front', 'side', 'detail'];

    public const TITEL = [
        'iso' => 'Montageübersicht',
        'top' => 'Draufsicht',
        'front' => 'Vorderansicht',
        'side' => 'Seitenansicht',
        'detail' => 'Detailschnitt A–A',
    ];

    /** Iso-Basisvektoren pro mm, aus den eingefrorenen Eckpunkten rekonstruiert. */
    private const UXX = 290.1 / 8630;
    private const UXY = 91.6 / 8630;
    private const UDX = 81.7 / 3500;
    private const UDY = -54.5 / 3500;
    private const UHY = -88.5 / 2500;

    private const PFEILE = 'marker-start="url(#rd-da)" marker-end="url(#rd-da)"';

    /** @return array<string, array{viewBox:string, nodes:list<array>}> */
    public static function alle(array $kalk, array $meta = []): array
    {
        $z = [];
        foreach (self::ANSICHTEN as $ansicht) {
            $z[$ansicht] = self::ansicht($ansicht, $kalk, $meta);
        }

        return $z;
    }

    /** @return array{viewBox:string, nodes:list<array>} */
    public static function ansicht(string $view, array $kalk, array $meta = []): array
    {
        return match ($view) {
            'iso' => self::iso($kalk),
            'top' => self::top($kalk, $meta),
            'front' => self::front($kalk, $meta),
            'side' => self::side($kalk, $meta),
            'detail' => self::detail($kalk, $meta),
            default => ['viewBox' => '0 0 440 330', 'nodes' => []],
        };
    }

    // ---------- Iso (Montageübersicht), viewBox 470×340 ----------

    private static function iso(array $kalk): array
    {
        [$W, $D, $wallH, $gutterH] = self::masse($kalk);
        $n = [];
        $n[] = self::text(20, 24, 'MONTAGEÜBERSICHT', 'vlabel');
        $n[] = self::text(20, 36, 'isometrische Darstellung · Wandmontage · Maße in mm', 'lblu anno');

        if ($W <= 0 || $D <= 0) {
            return ['viewBox' => '0 0 470 340', 'nodes' => $n];
        }

        // Budgets = Ausdehnung der Referenzkonfiguration; k schrumpft
        // größere Dächer uniform in den Rahmen (Defaults: k = 1).
        $xExt = $W * self::UXX + $D * self::UDX;
        $yUp = -($D * self::UDY) - $wallH * self::UHY;
        $yDn = $W * self::UXY;
        $k = min(1.0, 371.8 / max(1e-6, $xExt), 162.4 / max(1e-6, $yUp), 91.6 / max(1e-6, $yDn));
        $P = fn (float $w, float $d, float $h) => [
            54.0 + $k * ($w * self::UXX + $d * self::UDX),
            196.4 + $k * ($w * self::UXY + $d * self::UDY + $h * self::UHY),
        ];

        $flg = $P(0, 0, 0);
        $frg = $P($W, 0, 0);
        $fle = $P(0, 0, $gutterH);
        $fre = $P($W, 0, $gutterH);
        $blg = $P(0, $D, 0);
        $brg = $P($W, $D, 0);
        $blr = $P(0, $D, $wallH);
        $brr = $P($W, $D, $wallH);

        // Hauswand-Ebene, Terrassenboden, Glasdach
        $n[] = self::poly([$blr, $brr, $brg, $blg], '', 'fill="url(#rd-hatchd)" stroke="#22375a" stroke-width="1" opacity=".7"');
        $n[] = self::poly([$flg, $frg, $brg, $blg], '', 'fill="rgba(34,55,90,.04)" stroke="#b9c2d1" stroke-width=".7"');
        $n[] = self::poly([$fle, $fre, $brr, $blr], 'glass');

        // Innensparren: i = 1 … rafters−2 im Abstand W/fields
        $fields = (int) ($kalk['fields'] ?? 0);
        for ($i = 1; $fields > 1 && $i <= $fields - 1; $i++) {
            $w = $i * $W / $fields;
            $n[] = self::linie($P($w, 0, $gutterH), $P($w, $D, $wallH), 'raf');
        }

        // Dekorative Glasstoß-Linie (Proto: d ≈ 0,149·D … 0,499·D)
        $hDach = fn (float $d) => $gutterH + ($wallH - $gutterH) * $d / $D;
        $n[] = self::linie($P(0, 0.149 * $D, $hDach(0.149 * $D)), $P($W, 0.4994 * $D, $hDach(0.4994 * $D)), 'glass2');

        // Dachkontur
        foreach ([[$fle, $fre], [$blr, $brr], [$fle, $blr], [$fre, $brr]] as [$a, $b]) {
            $n[] = self::linie($a, $b, 'ln');
        }

        // Pfosten (alle pn, nicht nur die Eckpfosten des Prototyps)
        $pn = (int) ($kalk['pn'] ?? 0);
        for ($i = 0; $pn >= 2 && $i < $pn; $i++) {
            $w = $i * $W / ($pn - 1);
            $g = $P($w, 0, 0);
            $e = $P($w, 0, $gutterH);
            $n[] = self::linie($g, $e, '', 'stroke="#22375a" stroke-width="4.5" stroke-linecap="round"');
            $n[] = self::linie([$g[0] + 3, $g[1]], [$e[0] + 3, $e[1]], '', 'stroke="#4a6fa5" stroke-width="1" opacity=".5"');
        }
        for ($i = 0; $pn >= 2 && $i < $pn; $i++) {
            $g = $P($i * $W / ($pn - 1), 0, 0);
            $n[] = self::poly([
                [$g[0] - 5, $g[1]], [$g[0], $g[1] + 3], [$g[0] + 5, $g[1]], [$g[0], $g[1] - 3],
            ], 'ln2');
        }

        // Maßketten B / D / H1 / H2 (rohe Ganzzahlen, Schrift 11 px)
        $n[] = self::linie($flg, [$flg[0], $flg[1] + 34], 'dim');
        $n[] = self::linie($frg, [$frg[0], $frg[1] + 34], 'dim');
        $n[] = self::linie([$flg[0], $flg[1] + 30], [$frg[0], $frg[1] + 30], 'dim', self::PFEILE);
        $n[] = self::text(($flg[0] + $frg[0]) / 2, ($flg[1] + $frg[1]) / 2 + 44, 'B = '.$W, 'dimt', 'middle', 0, 11);

        $n[] = self::linie($flg, [$flg[0] - 30, $flg[1]], 'dim');
        $n[] = self::linie($blg, [$blg[0] - 30, $blg[1]], 'dim');
        $n[] = self::linie([$flg[0] - 26, $flg[1]], [$blg[0] - 26, $blg[1]], 'dim', self::PFEILE);
        $n[] = self::text(($flg[0] + $blg[0]) / 2 - 34, ($flg[1] + $blg[1]) / 2, 'D = '.$D, 'dimt', 'end', 0, 11);

        $n[] = self::linie($blr, [$blr[0] - 34, $blr[1]], 'dim');
        $n[] = self::linie($blg, [$blg[0] - 34, $blg[1]], 'dim');
        $n[] = self::linie([$blr[0] - 30, $blr[1]], [$blg[0] - 30, $blg[1]], 'dim', self::PFEILE);
        $n[] = self::text($blr[0] - 36, ($blr[1] + $blg[1]) / 2, 'H1 = '.$wallH, 'dimt', 'middle', -90, 11);

        $n[] = self::linie($fre, [$fre[0] + 34, $fre[1]], 'dim');
        $n[] = self::linie($frg, [$frg[0] + 34, $frg[1]], 'dim');
        $n[] = self::linie([$fre[0] + 30, $fre[1]], [$frg[0] + 30, $frg[1]], 'dim', self::PFEILE);
        $n[] = self::text($fre[0] + 37, ($fre[1] + $frg[1]) / 2, 'H2 = '.$gutterH, 'dimt', 'middle', 90, 11);

        return ['viewBox' => '0 0 470 340', 'nodes' => $n];
    }

    // ---------- Draufsicht, viewBox 440×330 ----------

    private static function top(array $kalk, array $meta): array
    {
        [$W, $D] = self::masse($kalk);
        $n = [];
        $n[] = self::text(20, 24, 'DRAUFSICHT', 'vlabel');

        if ($W <= 0 || $D <= 0) {
            $n = array_merge($n, self::titelblock('Draufsicht', '1:50', '1 / 4', $meta));

            return ['viewBox' => '0 0 440 330', 'nodes' => $n];
        }

        $s = min(330 / $W, 134 / $D);
        $wp = $W * $s;
        $dp = $D * $s;
        $xL = 58.0;
        $yT = 40.0;
        $xR = $xL + $wp;
        $yB = $yT + $dp;
        $fields = (int) ($kalk['fields'] ?? 0);
        $pn = (int) ($kalk['pn'] ?? 0);

        // Schnittmarke A–A auf der Dachmitte
        $xc = $xL + $wp / 2;
        $n[] = self::linie([$xc, 27], [$xc, $yB + 15], 'ctr anno', 'style="stroke-width:1;opacity:.95"');
        $n[] = self::pfad(sprintf('M%s,33 L%s,27 L%s,33', self::f($xc - 4), self::f($xc), self::f($xc + 4)), 'ln2 anno');
        $n[] = self::pfad(sprintf('M%s,%s L%s,%s L%s,%s', self::f($xc - 4), self::f($yB + 9), self::f($xc), self::f($yB + 15), self::f($xc + 4), self::f($yB + 9)), 'ln2 anno');
        $n[] = self::text($xc, 22, 'A', 'dimt anno', 'middle');
        $n[] = self::text($xc, $yB + 29, 'A', 'dimt anno', 'middle');

        // Grundriss: Rahmen, Wandanschlussprofil, Glas, Rinne
        $n[] = self::rechteck($xL, $yT, $wp, $dp, 'mem');
        $n[] = self::rechteck($xL, $yT, $wp, 7, 'ln2', 'fill="rgba(34,55,90,.05)"');
        $n[] = self::rechteck($xL, $yT + 7, $wp, max(0, $dp - 14), 'glass');
        $n[] = self::rechteck($xL, $yB - 7, $wp, 7, 'mem');

        for ($i = 1; $fields > 1 && $i <= $fields - 1; $i++) {
            $x = $xL + $i * $wp / $fields;
            $n[] = self::linie([$x, $yT + 7], [$x, $yB - 7], 'raf');
        }

        // Hauswand-Balken mit 14-px-Überstand
        $n[] = self::rechteck($xL - 14, 30, $wp + 28, 7, '', 'fill="url(#rd-hatchd)" stroke="#22375a" stroke-width="1.15"');

        // Pfosten + Achslinien
        for ($i = 0; $pn >= 2 && $i < $pn; $i++) {
            $x = $xL + $i * $wp / ($pn - 1);
            $n[] = self::rechteck($x - 3.5, $yB - 7.5, 7, 7, 'mem');
        }
        for ($i = 0; $pn >= 2 && $i < $pn; $i++) {
            $x = $xL + $i * $wp / ($pn - 1);
            $n[] = self::linie([$x, 32], [$x, $yB + 8], 'ctr');
        }

        // Nordpfeil + Beschriftungen
        $n[] = self::kreis(412, 55, 11, 'nsym anno');
        $n[] = self::pfad('M412,64 L412,46', 'nsym anno', 'marker-end="url(#rd-da)"');
        $n[] = self::text(412, 40, 'N', 'nt anno', 'middle');
        $n[] = self::text(398, 27, 'Hauswand', 'lblu anno', 'end');
        $n[] = self::text($xL + 5, $yB - 11, 'Rinne (Traufe)', 'lblu anno');
        $n[] = self::text($xL + 5, $yT + 15, 'Wandanschlussprofil', 'lblu anno');

        // Maße: Tiefe links, Breite unten, Pfostenkette Ebene 2
        $n[] = self::linie([$xL - 2, $yT], [37, $yT], 'dim');
        $n[] = self::linie([$xL - 2, $yB], [37, $yB], 'dim');
        $n[] = self::linie([40, $yT], [40, $yB], 'dim', self::PFEILE);
        $n[] = self::text(31, ($yT + $yB) / 2, (string) $D, 'dimt', 'middle', -90);

        $n[] = self::linie([$xL, $yB], [$xL, $yB + 27], 'dim');
        $n[] = self::linie([$xR, $yB], [$xR, $yB + 27], 'dim');
        $n[] = self::linie([$xL, $yB + 23], [$xR, $yB + 23], 'dim', self::PFEILE);
        $n[] = self::text(($xL + $xR) / 2, $yB + 20, (string) $W, 'dimt', 'middle');

        if ($pn >= 2) {
            $achse = (int) round($W / ($pn - 1));
            $n[] = self::linie([$xL, $yB + 23], [$xL, $yB + 42], 'dim');
            for ($i = 1; $i < $pn - 1; $i++) {
                $x = $xL + $i * $wp / ($pn - 1);
                $n[] = self::linie([$x, $yB], [$x, $yB + 42], 'dim');
            }
            $n[] = self::linie([$xR, $yB + 23], [$xR, $yB + 42], 'dim');
            for ($i = 0; $i < $pn - 1; $i++) {
                $a = $xL + $i * $wp / ($pn - 1);
                $b = $xL + ($i + 1) * $wp / ($pn - 1);
                $n[] = self::linie([$a, $yB + 38], [$b, $yB + 38], 'dim', self::PFEILE);
                $n[] = self::text(($a + $b) / 2, $yB + 35, (string) $achse, 'dimt', 'middle');
            }
        }

        $n[] = self::text(24, 252, 'Maße in mm · '.$fields.' Felder à '.(int) ($kalk['spar'] ?? 0).' · Sparren 80×60', 'lblu anno');
        $n = array_merge($n, self::titelblock('Draufsicht', '1:50', '1 / 4', $meta));

        return ['viewBox' => '0 0 440 330', 'nodes' => $n];
    }

    // ---------- Vorderansicht, viewBox 440×330 ----------

    private static function front(array $kalk, array $meta): array
    {
        [$W, , , $gutterH] = self::masse($kalk);
        $pcfg = $kalk['pcfg'] ?? KonfiguratorRechner::defaults();
        $n = [];
        $n[] = self::text(20, 24, 'VORDERANSICHT', 'vlabel');

        if ($W <= 0 || $gutterH <= 0) {
            $n = array_merge($n, self::titelblock('Vorderansicht', '1:50', '2 / 4', $meta));

            return ['viewBox' => '0 0 440 330', 'nodes' => $n];
        }

        $s = min(330 / $W, 95 / $gutterH);
        $wp = $W * $s;
        $xL = 58.0;
        $xR = $xL + $wp;
        $yG = 210.0;
        $yPost = $yG - $gutterH * $s;
        $yFascia = $yPost - 11;
        $yEdge = $yFascia - 8;
        $pn = (int) ($kalk['pn'] ?? 0);

        // Boden + Geländelinie
        $n[] = self::rechteck($xL - 14, $yG, $wp + 30, 8, '', 'fill="url(#rd-hatch)"');
        $n[] = self::linie([$xL - 20, $yG], [$xR + 22, $yG], 'grndln');

        // Dachkante + Rinnenblende (6-px-Überstand über die Eckpfosten)
        $n[] = self::linie([$xL - 6, $yEdge], [$xR + 6, $yEdge], 'ln2');
        $n[] = self::linie([$xL - 6, $yEdge], [$xL - 6, $yFascia], 'ln2');
        $n[] = self::linie([$xR + 6, $yEdge], [$xR + 6, $yFascia], 'ln2');
        $n[] = self::rechteck($xL - 6, $yFascia, $wp + 12, 11, 'mem');

        // Pfosten, Fußplatten, Achsen
        $xi = [];
        for ($i = 0; $pn >= 2 && $i < $pn; $i++) {
            $xi[] = $xL + $i * $wp / ($pn - 1);
        }
        foreach ($xi as $x) {
            $n[] = self::rechteck($x - 3, $yPost, 6, $yG - $yPost, 'mem');
        }
        foreach ($xi as $x) {
            $n[] = self::rechteck($x - 7, $yG - 3, 14, 3, 'ln2');
        }
        foreach ($xi as $x) {
            $n[] = self::linie([$x, $yEdge], [$x, $yG + 4], 'ctr');
        }

        // Fallrohr rechts
        $n[] = self::linie([$xR + 5, $yPost], [$xR + 5, $yG - 4], 'ln2');
        $n[] = self::linie([$xR + 8.5, $yPost], [$xR + 8.5, $yG - 4], 'ln2');
        $n[] = self::text($xR + 12, 168, 'Fallrohr Ø60', 'lblu anno', '', -90);
        $n[] = self::text(($xL + $xR) / 2, $yFascia + 7, 'Regenrinne integriert · '.($pcfg['color'] ?? ''), 'lblu anno', 'middle');

        // Maße: H2 links, Breite unten, Pfostenkette
        $n[] = self::linie([$xL - 6, $yPost], [37, $yPost], 'dim');
        $n[] = self::linie([$xL - 6, $yG], [37, $yG], 'dim');
        $n[] = self::linie([40, $yPost], [40, $yG], 'dim', self::PFEILE);
        $n[] = self::text(31, ($yPost + $yG) / 2, (string) $gutterH, 'dimt', 'middle', -90);

        $n[] = self::linie([$xL - 6, $yG], [$xL - 6, $yG + 31], 'dim');
        $n[] = self::linie([$xR + 6, $yG], [$xR + 6, $yG + 31], 'dim');
        $n[] = self::linie([$xL - 6, $yG + 27], [$xR + 6, $yG + 27], 'dim', self::PFEILE);
        $n[] = self::text(($xL + $xR) / 2, $yG + 24, (string) $W, 'dimt', 'middle');

        if ($pn >= 2) {
            $achse = (int) round($W / ($pn - 1));
            foreach ($xi as $x) {
                $n[] = self::linie([$x, $yG], [$x, $yG + 46], 'dim');
            }
            for ($i = 0; $i < $pn - 1; $i++) {
                $n[] = self::linie([$xi[$i], $yG + 42], [$xi[$i + 1], $yG + 42], 'dim', self::PFEILE);
                $n[] = self::text(($xi[$i] + $xi[$i + 1]) / 2, $yG + 39, (string) $achse, 'dimt', 'middle');
            }
        }

        $n[] = self::text(24, 278, 'Maße in mm · Durchgangshöhe an Traufe', 'lblu anno');
        $n = array_merge($n, self::titelblock('Vorderansicht', '1:50', '2 / 4', $meta));

        return ['viewBox' => '0 0 440 330', 'nodes' => $n];
    }

    // ---------- Seitenansicht, viewBox 440×330 ----------

    private static function side(array $kalk, array $meta): array
    {
        [, $D, $wallH, $gutterH] = self::masse($kalk);
        $pcfg = $kalk['pcfg'] ?? KonfiguratorRechner::defaults();
        $slope = (int) ($pcfg['slope'] ?? 8);
        $n = [];
        $n[] = self::text(20, 24, 'SEITENANSICHT', 'vlabel');

        if ($D <= 0 || $gutterH <= 0) {
            $n = array_merge($n, self::titelblock('Seitenansicht', '1:50', '3 / 4', $meta));

            return ['viewBox' => '0 0 440 330', 'nodes' => $n];
        }

        $tan = tan(deg2rad($slope));
        $s = min(157 / $D, 134 / max(1, $wallH));
        $x0 = 120.0;                       // Dachbeginn an der Wand
        $xPost = $x0 + $D * $s;            // Pfostenachse
        $xEnd = $xPost + 7;                // Dachüberstand vorn
        $yG = 210.0;
        $yEave = $yG - $gutterH * $s - 3;  // Dachoberkante vorn
        $yWand = $yEave - $tan * ($xEnd - $x0); // Dachoberkante an der Wand

        // Boden + Hauswand
        $n[] = self::rechteck(96, $yG, $xEnd + 34 - 96, 8, '', 'fill="url(#rd-hatch)"');
        $n[] = self::linie([90, $yG], [$xEnd + 38, $yG], 'grndln');
        $n[] = self::rechteck(100, $yWand - 21, 18, $yG - ($yWand - 21), '', 'fill="url(#rd-hatchd)" stroke="#22375a" stroke-width="1.35"');

        // Dachplatte (5 px stark) + Glasfuge + Sparrenlinie
        $n[] = self::poly([[$x0, $yWand], [$xEnd, $yEave], [$xEnd, $yEave + 5], [$x0, $yWand + 5]], 'glass');
        $n[] = self::linie([$x0, $yWand + 2.5], [$xEnd, $yEave + 2.5], 'glass2');
        $n[] = self::linie([$x0 + 2, $yWand + 6], [$xEnd - 2, $yEave + 6], 'raf');

        // Wandanschlussprofil, Gigarinne, Pfosten, Fußplatte, Achse, Fallrohr
        $n[] = self::rechteck(116, $yWand - 1, 16, 9, 'mem');
        $gx = $xPost + 1;
        $n[] = self::pfad(sprintf(
            'M%s,%s L%s,%s L%s,%s L%s,%s L%s,%s L%s,%s Z',
            self::f($gx), self::f($yEave), self::f($gx + 15), self::f($yEave + 1.5),
            self::f($gx + 15), self::f($yEave + 11), self::f($gx + 8), self::f($yEave + 13),
            self::f($gx + 5), self::f($yEave + 6), self::f($gx), self::f($yEave + 5)
        ), 'mem');
        $n[] = self::rechteck($xPost - 10, $yEave + 4, 6, $yG - ($yEave + 4), 'mem');
        $n[] = self::rechteck($xPost - 14, $yG - 3, 14, 3, 'ln2');
        $n[] = self::linie([$xPost - 7, $yEave - 1], [$xPost - 7, $yG + 4], 'ctr');
        $n[] = self::linie([$xPost + 12, $yEave + 13], [$xPost + 12, $yG - 4], 'ln2');
        $n[] = self::linie([$xPost + 15.5, $yEave + 13], [$xPost + 15.5, $yG - 4], 'ln2');

        // Neigungswinkel + Beschriftungen
        $n[] = self::linie([$x0 + 30, $yWand + 4], [$x0 + 86, $yWand + 4], 'dim anno', 'stroke-dasharray="3 2"');
        $n[] = self::pfad(sprintf('M%s,%s A 20 20 0 0 1 %s,%s', self::f($x0 + 30), self::f($yWand + 4), self::f($x0 + 36), self::f($yWand + 8)), 'ln2 anno', 'style="stroke-width:.9"');
        $n[] = self::text($x0 + 60, $yWand + 1, $slope.'°', 'dimt anno', 'middle');
        $n[] = self::text($x0 + 85, $yEave + 23, 'Gefälle '.$slope.'° ≈ '.(int) round($tan * 1000).' mm/m → Rinne', 'lblu anno', 'middle');
        $n[] = self::text(103, $yWand - 24, 'Hauswand', 'lblu anno');
        $n[] = self::text(133, $yWand - 6, 'Wandanschlussprofil', 'lblu anno');

        // Maße: Tiefe unten, H2 rechts, H1 (Wandhöhe) links
        $n[] = self::linie([$x0, $yG], [$x0, $yG + 31], 'dim');
        $n[] = self::linie([$xPost, $yG], [$xPost, $yG + 31], 'dim');
        $n[] = self::linie([$x0, $yG + 27], [$xPost, $yG + 27], 'dim', self::PFEILE);
        $n[] = self::text(($x0 + $xPost) / 2, $yG + 24, (string) $D, 'dimt', 'middle');

        $n[] = self::linie([$xPost - 4, $yEave + 4], [$xPost + 32 + 4, $yEave + 4], 'dim');
        $n[] = self::linie([$xPost + 2, $yG], [$xPost + 36, $yG], 'dim');
        $n[] = self::linie([$xPost + 32, $yEave + 4], [$xPost + 32, $yG], 'dim', self::PFEILE);
        $n[] = self::text($xPost + 41, ($yEave + 4 + $yG) / 2, (string) $gutterH, 'dimt', 'middle', -90);

        $yWallDim = $yG - $wallH * $s;
        $n[] = self::linie([100, $yWallDim], [86, $yWallDim], 'dim');
        $n[] = self::linie([100, $yG], [86, $yG], 'dim');
        $n[] = self::linie([90, $yWallDim], [90, $yG], 'dim', self::PFEILE);
        $n[] = self::text(81, ($yWallDim + $yG) / 2, (string) $wallH, 'dimt', 'middle', -90);

        $n[] = self::text(24, 278, 'Maße in mm · Pultdach an Hauswand, Neigung '.$slope.'°', 'lblu anno');
        $n = array_merge($n, self::titelblock('Seitenansicht', '1:50', '3 / 4', $meta));

        return ['viewBox' => '0 0 440 330', 'nodes' => $n];
    }

    // ---------- Detail A–A (1:5), viewBox 440×330 — statisch, Texte live ----------

    private static function detail(array $kalk, array $meta): array
    {
        $pcfg = $kalk['pcfg'] ?? KonfiguratorRechner::defaults();
        $glas = trim(str_replace('-Glas', '', (string) ($pcfg['covering'] ?? 'VSG-Glas')))
            .' '.($pcfg['thickness'] ?? '8 mm')
            .' '.mb_strtolower((string) ($pcfg['glasTrans'] ?? 'klar'));
        $spar = (int) ($kalk['spar'] ?? 0);

        $n = [];
        $n[] = self::text(20, 24, 'DETAIL A–A', 'vlabel');
        $n[] = self::text(20, 36, 'Glasfalz · Rundleiste', 'lblu anno');

        // Sparrenschnitt mit Hohlkammer + Schraubkanal
        $n[] = self::rechteck(188, 166, 64, 92, '', 'fill="url(#rd-hatchd)" stroke="#22375a" stroke-width="1.6"');
        $n[] = self::rechteck(197, 184, 46, 66, 'ln2');
        $n[] = self::linie([188, 166], [252, 166], 'ln2');
        $n[] = self::rechteck(212, 136, 16, 30, 'mem');
        $n[] = self::kreis(220, 150, 4, 'ln2');

        // Glasscheiben links/rechts
        $n[] = self::poly([[98, 150], [212, 150], [212, 162], [98, 162]], 'glass');
        $n[] = self::linie([98, 156], [212, 156], 'glass2');
        $n[] = self::poly([[228, 150], [342, 150], [342, 162], [228, 162]], 'glass');
        $n[] = self::linie([228, 156], [342, 156], 'glass2');

        // EPDM-Dichtungen
        foreach ([[196, 162, 16], [228, 162, 16], [200, 145.5, 12], [228, 145.5, 12]] as [$x, $y, $w]) {
            $n[] = self::rechteck($x, $y, $w, 4.5, '', 'fill="#2c3542" stroke="none" rx="1.4"');
        }

        // Rundleiste + Schraube + Gewindemarken
        $n[] = self::pfad('M202,150 L202,140 Q202,132 210,132 L230,132 Q238,132 238,140 L238,150 Z', 'mem');
        $n[] = self::linie([220, 135], [220, 162], 'ln2');
        $n[] = self::rechteck(214, 131, 12, 4, 'ln2');
        foreach ([142, 148, 154] as $y) {
            $n[] = self::linie([216.5, $y], [223.5, $y], 'raf');
        }

        // Führungslinien + Beschriftungen
        foreach (['M235,135 L288,116', 'M224,131 L300,100', 'M300,156 L328,150', 'M246,206 L300,206', 'M204,164 L150,190', 'M226,144 L262,178'] as $d) {
            $n[] = self::pfad($d, 'lead anno');
        }
        $n[] = self::text(290, 114, 'Abdeckprofil (Rundleiste)', 'lbl anno');
        $n[] = self::text(302, 98, 'Torx-Schraube 19×32', 'lbl anno');
        $n[] = self::text(330, 148, $glas, 'lbl anno');
        $n[] = self::text(302, 209, 'Sparren 80 × 60 mm', 'lbl anno');
        $n[] = self::text(74, 196, 'Gummi 4 mm (Glas)', 'lbl anno', 'end');
        $n[] = self::text(264, 182, 'Schraubkanal', 'lbl anno');

        // Feldbreite (aus dem Rechenkern, Proto zeigte veraltete 1050)
        $n[] = self::linie([98, 150], [98, 128], 'dim');
        $n[] = self::linie([212, 150], [212, 128], 'dim');
        $n[] = self::linie([98, 132], [212, 132], 'dim', self::PFEILE);
        $n[] = self::text(155, 129, $spar > 0 ? (string) $spar : '–', 'dimt', 'middle');

        $n = array_merge($n, self::titelblock('Detail A–A', '1:5', '4 / 4', $meta));

        return ['viewBox' => '0 0 440 330', 'nodes' => $n];
    }

    // ---------- Helfer ----------

    /** @return array{0:int,1:int,2:int,3:int} W, D, wallH, gutterH */
    private static function masse(array $kalk): array
    {
        $p = $kalk['pcfg'] ?? KonfiguratorRechner::defaults();

        return [(int) $p['width'], (int) $p['depth'], (int) $p['wallH'], (int) $p['gutterH']];
    }

    /** @return list<array> Titelblock unten rechts (nicht auf der Iso) */
    private static function titelblock(string $ansicht, string $massstab, string $blatt, array $meta): array
    {
        $n = [];
        $n[] = self::rechteck(240, 270, 190, 52, 'tbln anno');
        foreach ([[240, 285, 430, 285], [240, 303, 430, 303], [360, 285, 360, 303], [330, 303, 330, 322], [386, 303, 386, 322]] as [$x1, $y1, $x2, $y2]) {
            $n[] = self::linie([$x1, $y1], [$x2, $y2], 'tbln anno');
        }
        $n[] = self::text(247, 281, $meta['firma'] ?? config('lea.firma'), 'tbt anno');
        $n[] = self::text(247, 293, 'Projekt', 'tbk anno');
        $n[] = self::text(247, 300.5, $meta['projekt'] ?? '—', 'tbv anno');
        $n[] = self::text(366, 293, 'Datum', 'tbk anno');
        $n[] = self::text(366, 300.5, $meta['datum'] ?? now()->format('d.m.Y'), 'tbv anno');
        $n[] = self::text(247, 311, 'Ansicht', 'tbk anno');
        $n[] = self::text(247, 319, $ansicht, 'tbv anno');
        $n[] = self::text(336, 311, 'Maßstab', 'tbk anno');
        $n[] = self::text(336, 319, $massstab, 'tbv anno');
        $n[] = self::text(391, 311, 'Blatt', 'tbk anno');
        $n[] = self::text(391, 319, $blatt, 'tbv anno');

        return $n;
    }

    private static function f(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    /** @param array{0:float,1:float} $a */
    private static function linie(array $a, array $b, string $cls = '', string $extra = ''): array
    {
        return ['tag' => 'line', 'cls' => $cls, 'extra' => $extra,
            'x1' => self::f($a[0]), 'y1' => self::f($a[1]), 'x2' => self::f($b[0]), 'y2' => self::f($b[1])];
    }

    /** @param list<array{0:float,1:float}> $pts */
    private static function poly(array $pts, string $cls = '', string $extra = ''): array
    {
        return ['tag' => 'poly', 'cls' => $cls, 'extra' => $extra,
            'pts' => implode(' ', array_map(fn ($p) => self::f($p[0]).','.self::f($p[1]), $pts))];
    }

    private static function rechteck(float $x, float $y, float $w, float $h, string $cls = '', string $extra = ''): array
    {
        return ['tag' => 'rect', 'cls' => $cls, 'extra' => $extra,
            'x' => self::f($x), 'y' => self::f($y), 'w' => self::f($w), 'h' => self::f($h)];
    }

    private static function pfad(string $d, string $cls = '', string $extra = ''): array
    {
        return ['tag' => 'path', 'cls' => $cls, 'extra' => $extra, 'd' => $d];
    }

    private static function kreis(float $cx, float $cy, float $r, string $cls = ''): array
    {
        return ['tag' => 'circle', 'cls' => $cls, 'extra' => '',
            'cx' => self::f($cx), 'cy' => self::f($cy), 'r' => self::f($r)];
    }

    private static function text(float $x, float $y, string $t, string $cls, string $anchor = '', float $rot = 0, int $size = 0): array
    {
        return ['tag' => 'text', 'cls' => $cls, 'extra' => '', 't' => $t,
            'x' => self::f($x), 'y' => self::f($y), 'anchor' => $anchor,
            'rot' => $rot !== 0.0 ? self::f($rot).' '.self::f($x).' '.self::f($y) : '',
            'size' => $size];
    }
}
