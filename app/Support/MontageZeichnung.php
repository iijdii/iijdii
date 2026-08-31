<?php

namespace App\Support;

/**
 * Extra-Zeichnungen des Montage-Modus — 1:1-Port von _mmDraw aus dem
 * Prototyp: Keil / Fest / Schiebe / Markise / Segel als SVG-Polygone
 * mit Maßketten, Rohmaß-Rahmen, WAND-Schraffur (url(#dwhx)) und
 * auto-fit-viewBox inkl. geschätzter Label-Boxen. Reine Geometrie;
 * Blade gibt nur noch aus.
 */
final class MontageZeichnung
{
    private const INK = '#26374a';

    private const GOLD = '#d9a441';

    private const GLB = '#eef4fb';

    /**
     * @param array{shape:string, fields:list<int|float>, side?:string, dir?:string,
     *              qty?:int, unterzug?:string, noWall?:bool, title?:string} $cur
     * @return array{lines:array, polys:array, labels:array, viewBox:string, aspect:string, sub:string, note:string}
     */
    public static function extra(array $cur): array
    {
        $F = fn ($n) => number_format(round($n), 0, ',', '.');
        $fl = $cur['fields'];
        $R = fn (int $i) => (float) ($fl[$i] ?? 0);
        $bx = 150; $by = 96; $bw = 600; $bhMax = 390;

        $pts = []; $wallSide = ''; $sub = ''; $qty = $cur['qty'] ?? 0;
        $divs = 0; $dir = ''; $cass = 0.0; $rohmass = false;
        $oblique = []; $vDims = []; $hDims = []; $notes = []; $cons = [];

        switch ($cur['shape']) {
            case 'keil':
                $A = $R(0); $B = $R(1); $C = $R(2);
                $mir = (bool) preg_match('/rechts/iu', $cur['side'] ?? '');
                $pts = $mir
                    ? [[0, $B - $C], [$A, 0], [$A, $B], [0, $B]]
                    : [[0, 0], [$A, $B - $C], [$A, $B], [0, $B]];
                $wallSide = $mir ? 'right' : 'left';
                $rohmass = true;
                $hDims = [['a' => 0, 'b' => $A, 'l' => 'A', 'v' => $A]];
                $vDims = [
                    ['side' => $mir ? 'right' : 'left', 'a' => 0, 'b' => $B, 'l' => 'B', 'v' => $B],
                    ['side' => $mir ? 'left' : 'right', 'a' => $B - $C, 'b' => $B, 'l' => 'C', 'v' => $C],
                ];
                $oblique = [['p' => $mir ? [[$A, 0], [0, $B - $C]] : [[0, 0], [$A, $B - $C]], 'l' => 'D', 'v' => $R(3)]];
                $notes = [['x' => $A * 0.5, 'y' => $B, 'dy' => 76, 't' => 'UNTERZUG '.($cur['unterzug'] ?? '110×110')]];
                $sub = 'Seitenansicht · rechtwinkliges Trapez';
                break;

            case 'fest':
                $A = $R(0); $B = $R(1); $C = $R(2) ?: $R(1);
                $mx = max($B, $C);
                $tz = abs($B - $C) > 2;
                $pts = [[0, $mx - $B], [$A, $mx - $C], [$A, $mx], [0, $mx]];
                $wallSide = ($cur['noWall'] ?? false) ? '' : 'left';
                $rohmass = $tz;
                $hDims = [['a' => 0, 'b' => $A, 'l' => 'A', 'v' => $A]];
                $vDims = [['side' => 'left', 'a' => $mx - $B, 'b' => $mx, 'l' => 'B', 'v' => $B]];
                if ($tz) {
                    $vDims[] = ['side' => 'right', 'a' => $mx - $C, 'b' => $mx, 'l' => 'C', 'v' => $C];
                    $oblique[] = ['p' => [[0, $mx - $B], [$A, $mx - $C]], 'l' => '', 'v' => 0];
                }
                $oblique[] = ['p' => [[0, $mx], [$A, $mx - $C]], 'l' => 'D', 'v' => $R(3), 'dash' => 1];
                $sub = 'Ansicht außen · '.($tz ? 'Trapez' : 'Rechteck');
                break;

            case 'schiebe':
                $A = $R(0); $B = $R(1); $C = $R(2);
                $pts = [[0, 0], [$A, 0], [$A, $B], [0, $B]];
                $divs = max(1, (int) round($A / max(1, $C)));
                $dir = $cur['dir'] ?? '';
                $hDims = [
                    ['a' => 0, 'b' => $A, 'l' => 'A', 'v' => $A, 'lvl' => 1],
                    ['a' => 0, 'b' => $C, 'l' => 'C', 'v' => $C, 'lvl' => 0],
                ];
                $vDims = [['side' => 'left', 'a' => 0, 'b' => $B, 'l' => 'B', 'v' => $B]];
                $notes = [['x' => $A * 0.5, 'y' => 0, 'dy' => -42, 't' => 'LAUFSCHIENE '.$F($R(3)).' MM']];
                $sub = $divs.' Flügel · Ansicht außen';
                break;

            case 'markise':
                $A = $R(0); $B = $R(1); $C = $R(2);
                $pts = [[0, 0], [$A, 0], [$A, $B], [0, $B]];
                $cass = max(140, $A * 0.05);
                $hDims = [['a' => 0, 'b' => $A, 'l' => 'A', 'v' => $A, 'lvl' => 1]];
                $vDims = [['side' => 'right', 'a' => 0, 'b' => $B, 'l' => 'B', 'v' => $B]];
                $n = max(2, (int) round(($A - 300) / max(1, $C)) + 1);
                $off = ($A - $C * ($n - 1)) / 2;
                for ($i = 0; $i < $n; $i++) {
                    $cons[] = $off + $i * $C;
                }
                $hDims[] = ['a' => $off, 'b' => $off + $C, 'l' => 'C', 'v' => $C, 'lvl' => 0, 'top' => 1];
                $notes = [['x' => $A * 0.5, 'y' => $B, 'dy' => 52, 't' => 'D = '.$F($R(3)).' MM HÖHE VORDERKANTE']];
                $sub = 'Draufsicht · Tuch ausgefahren · '.$n.' Konsolen';
                break;

            default: // segel
                $A = $R(0); $B = $R(1); $C = $R(2); $Dd = $R(3);
                $pts = [[0, 20], [$A, 0], [$A - 16, $B], [28, $B - 26]];
                $rohmass = true;
                $oblique = [
                    ['p' => [[0, 20], [$A, 0]], 'l' => 'A', 'v' => $A],
                    ['p' => [[$A, 0], [$A - 16, $B]], 'l' => 'B', 'v' => $B],
                    ['p' => [[$A - 16, $B], [28, $B - 26]], 'l' => 'C', 'v' => $C],
                    ['p' => [[28, $B - 26], [0, 20]], 'l' => 'D', 'v' => $Dd],
                    ['p' => [[0, 20], [$A - 16, $B]], 'l' => 'E', 'v' => $R(4), 'dash' => 1],
                    ['p' => [[$A, 0], [28, $B - 26]], 'l' => 'F', 'v' => $R(5), 'dash' => 1],
                ];
                $sub = 'Draufsicht · 4 Befestigungspunkte';
        }

        // Skalierung ins Layout-Fenster
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);
        $mnx = min($xs); $mny = min($ys);
        $w = (max($xs) - $mnx) ?: 1;
        $h = (max($ys) - $mny) ?: 1;
        $s = $bw / $w;
        if ($h * $s > $bhMax) {
            $s = $bhMax / $h;
        }
        $ox = $bx - $mnx * $s;
        $oy = $by - $mny * $s;
        $X = fn ($v) => $ox + $v * $s;
        $Y = fn ($v) => $oy + $v * $s;

        $lines = []; $polys = []; $labels = [];
        $L = function ($x1, $y1, $x2, $y2, $c = null, $wd = null, $d = '') use (&$lines) {
            $lines[] = ['x1' => round($x1, 1), 'y1' => round($y1, 1), 'x2' => round($x2, 1), 'y2' => round($y2, 1),
                'c' => $c ?? self::INK, 'w' => $wd ?? 0.9, 'd' => $d];
        };
        $T = function ($x, $y, $t, array $o = []) use (&$labels) {
            $labels[] = ['t' => $t, 'cls' => $o['cls'] ?? '', 'x' => $x, 'y' => $y,
                'rot' => $o['rot'] ?? 0, 'al' => $o['al'] ?? 'center'];
        };

        $P2 = array_map(fn ($p) => [$X($p[0]), $Y($p[1])], $pts);
        $px = array_column($P2, 0);
        $py = array_column($P2, 1);
        $gx0 = min($px); $gx1 = max($px); $gy0 = min($py); $gy1 = max($py);

        if ($rohmass) {
            $L($gx0, $gy0, $gx1, $gy0, self::GOLD, 0.9, '5 4');
            $L($gx1, $gy0, $gx1, $gy1, self::GOLD, 0.9, '5 4');
            $L($gx1, $gy1, $gx0, $gy1, self::GOLD, 0.9, '5 4');
            $L($gx0, $gy1, $gx0, $gy0, self::GOLD, 0.9, '5 4');
            $T(($gx0 + $gx1) / 2, $gy0 - 17, 'Rohmaß '.$F(($gx1 - $gx0) / $s).' × '.$F(($gy1 - $gy0) / $s).' mm', ['cls' => 'dw-roh']);
        }

        if ($wallSide !== '') {
            $bwd = 26; $gap = 11;
            $xe = $wallSide === 'left' ? $gx0 : $gx1;
            $x1w = $wallSide === 'left' ? $xe - $gap - $bwd : $xe + $gap;
            $polys[] = ['pts' => collect([[$x1w, $gy0], [$x1w + $bwd, $gy0], [$x1w + $bwd, $gy1], [$x1w, $gy1]])
                ->map(fn ($p) => round($p[0], 1).','.round($p[1], 1))->join(' '),
                'fill' => 'url(#dwhx)', 'stroke' => self::INK, 'w' => 1];
            $T($x1w + $bwd / 2, ($gy0 + $gy1) / 2, 'WAND', ['cls' => 'dw-ann', 'rot' => -90]);
        }

        $polys[] = ['pts' => collect($P2)->map(fn ($p) => round($p[0], 1).','.round($p[1], 1))->join(' '),
            'fill' => self::GLB, 'stroke' => self::INK, 'w' => 1.7];

        if ($cass > 0) {
            $ch = $cass * $s;
            $polys[] = ['pts' => collect([[$gx0, $gy0], [$gx1, $gy0], [$gx1, $gy0 + $ch], [$gx0, $gy0 + $ch]])
                ->map(fn ($p) => round($p[0], 1).','.round($p[1], 1))->join(' '),
                'fill' => '#dde5f0', 'stroke' => self::INK, 'w' => 1.4];
            $T(($gx0 + $gx1) / 2, $gy0 + $ch / 2, 'KASSETTE', ['cls' => 'dw-ann']);
            foreach ($cons as $cx) {
                $L($X($cx), $gy0 - 9, $X($cx), $gy0 + $ch + 3, self::INK, 2.4);
            }
        }

        if ($divs > 1) {
            $seg = ($gx1 - $gx0) / $divs;
            $cy = ($gy0 + $gy1) / 2;
            $mid = ($divs + 1) / 2;
            for ($i = 1; $i < $divs; $i++) {
                $L($gx0 + $seg * $i, $gy0, $gx0 + $seg * $i, $gy1, self::INK, 1);
            }
            for ($i = 1; $i <= $divs; $i++) {
                $cx = $gx0 + $seg * ($i - 0.5);
                $T($cx, $cy - 8, (string) $i, ['cls' => 'dw-pnl']);
                $half = min(20, $seg / 3);
                $ay = $cy + 16; $lX = $cx - $half; $rX = $cx + $half;
                $hl = $dir === 'left' || ($dir === 'center' && $i <= $mid) || $dir === '';
                $hr = $dir === 'right' || ($dir === 'center' && $i >= $mid) || $dir === '';
                $L($lX, $ay, $rX, $ay, '#7a8aa3', 1);
                if ($hl) {
                    $L($lX + 5, $ay - 4, $lX, $ay, '#7a8aa3', 1);
                    $L($lX, $ay, $lX + 5, $ay + 4, '#7a8aa3', 1);
                }
                if ($hr) {
                    $L($rX - 5, $ay - 4, $rX, $ay, '#7a8aa3', 1);
                    $L($rX, $ay, $rX - 5, $ay + 4, '#7a8aa3', 1);
                }
            }
        }

        foreach ($hDims as $d) {
            $top = (bool) ($d['top'] ?? false);
            $lvl = $d['lvl'] ?? 0;
            $off = ($top ? -1 : 1) * (26 + $lvl * 30);
            $yb = $top ? $gy0 + $off : $gy1 + $off;
            $xa = $X($d['a']); $xbb = $X($d['b']);
            $L($xa, $top ? $gy0 : $gy1, $xa, $yb + ($top ? -5 : 5), self::GOLD, 0.8);
            $L($xbb, $top ? $gy0 : $gy1, $xbb, $yb + ($top ? -5 : 5), self::GOLD, 0.8);
            $L($xa, $yb, $xbb, $yb, self::GOLD, 0.9);
            $T(($xa + $xbb) / 2, $yb + ($top ? -13 : 15), ($d['l'] !== '' ? $d['l'].'  ' : '').$F($d['v']).' mm', ['cls' => 'dw-lbl']);
        }

        foreach ($vDims as $d) {
            $lf = $d['side'] === 'left';
            $xx = $lf ? $gx0 - ($wallSide === 'left' ? 54 : 22) : $gx1 + ($wallSide === 'right' ? 54 : 22);
            $ya = $Y($d['a']); $yb = $Y($d['b']);
            $L($xx, $ya, $xx, $yb, self::INK, 0.8);
            $L($lf ? $gx0 : $gx1, $ya, $xx + ($lf ? -4 : 4), $ya, self::INK, 0.8);
            $L($lf ? $gx0 : $gx1, $yb, $xx + ($lf ? -4 : 4), $yb, self::INK, 0.8);
            $T($xx + ($lf ? -11 : 11), ($ya + $yb) / 2, ($d['l'] !== '' ? $d['l'].'  ' : '').$F($d['v']).' mm', ['cls' => 'dw-lbls', 'rot' => -90]);
        }

        $cxm = array_sum($px) / count($P2);
        $cym = array_sum($py) / count($P2);
        foreach ($oblique as $o) {
            $p1 = [$X($o['p'][0][0]), $Y($o['p'][0][1])];
            $p2 = [$X($o['p'][1][0]), $Y($o['p'][1][1])];
            $dash = (bool) ($o['dash'] ?? false);
            if ($dash) {
                $L($p1[0], $p1[1], $p2[0], $p2[1], '#9aa6b8', 0.9, '5 4');
            }
            if (($o['l'] ?? '') === '') {
                continue;
            }
            $mx2 = ($p1[0] + $p2[0]) / 2;
            $my = ($p1[1] + $p2[1]) / 2;
            $dx = $p2[0] - $p1[0];
            $dy = $p2[1] - $p1[1];
            $ln = hypot($dx, $dy) ?: 1;
            $nx = $dy / $ln;
            $ny = -$dx / $ln;
            if ($nx * ($mx2 - $cxm) + $ny * ($my - $cym) < 0) {
                $nx = -$nx; $ny = -$ny;
            }
            $d2 = $dash ? -16 : 15;
            $ang = atan2($dy, $dx) * 180 / M_PI;
            if ($ang > 90) {
                $ang -= 180;
            }
            if ($ang < -90) {
                $ang += 180;
            }
            $T($mx2 + $nx * $d2, $my + $ny * $d2, $o['l'].'  '.$F($o['v']).' mm', ['cls' => $dash ? 'dw-lbls' : 'dw-lbl', 'rot' => $ang]);
        }

        foreach ($notes as $n) {
            $T($X($n['x']), $Y($n['y']) + $n['dy'], $n['t'], ['cls' => 'dw-ann']);
        }
        if ($qty > 1) {
            $T($gx1, $gy0 - 34, '× '.$qty.' Stk', ['cls' => 'dw-qty', 'al' => 'end']);
        }

        // Auto-fit-viewBox inkl. geschätzter Label-Boxen (Prototyp-Heuristik).
        $x0 = 1e9; $y0 = 1e9; $x1b = -1e9; $y1b = -1e9;
        $acc = function ($x, $y) use (&$x0, &$y0, &$x1b, &$y1b) {
            $x0 = min($x0, $x); $x1b = max($x1b, $x);
            $y0 = min($y0, $y); $y1b = max($y1b, $y);
        };
        foreach ($lines as $l) {
            $acc($l['x1'], $l['y1']);
            $acc($l['x2'], $l['y2']);
        }
        foreach ($polys as $p) {
            foreach (explode(' ', $p['pts']) as $q) {
                [$qx, $qy] = explode(',', $q);
                $acc((float) $qx, (float) $qy);
            }
        }
        foreach ($labels as $l) {
            $tw = mb_strlen((string) $l['t']) * 7 + 10;
            $th = 17;
            $r = ($l['rot'] ?? 0) * M_PI / 180;
            $ca = abs(cos($r)); $sa = abs(sin($r));
            $hw = ($ca * $tw + $sa * $th) / 2;
            $hh = ($sa * $tw + $ca * $th) / 2;
            $cx2 = $l['al'] === 'end' ? $l['x'] - $tw / 2 : $l['x'];
            $acc($cx2 - $hw, $l['y'] - $hh);
            $acc($cx2 + $hw, $l['y'] + $hh);
        }
        $pad = 14;
        $x0 -= $pad; $y0 -= $pad; $x1b += $pad; $y1b += $pad;
        $SW = max(1, $x1b - $x0);
        $SH = max(1, $y1b - $y0);

        $ausgabe = [];
        foreach ($labels as $l) {
            $tx = $l['al'] === 'end' ? '-100%,-50%' : '-50%,-50%';
            $ausgabe[] = [
                't' => $l['t'], 'cls' => $l['cls'],
                'st' => 'left:'.number_format(($l['x'] - $x0) / $SW * 100, 3, '.', '').'%;'
                    .'top:'.number_format(($l['y'] - $y0) / $SH * 100, 3, '.', '').'%;'
                    .'transform:translate('.$tx.')'.($l['rot'] ? ' rotate('.number_format($l['rot'], 1, '.', '').'deg)' : ''),
            ];
        }

        return [
            'lines' => $lines,
            'polys' => $polys,
            'labels' => $ausgabe,
            'viewBox' => round($x0, 1).' '.round($y0, 1).' '.round($SW, 1).' '.round($SH, 1),
            'aspect' => 'aspect-ratio:'.round($SW, 1).'/'.round($SH, 1),
            'sub' => $sub,
            'note' => 'Maße in mm',
        ];
    }
}
