<?php

namespace App\Support;

/**
 * SVG-Geometrie der Glas- und Schiebe-Skizzen — 1:1-Port der
 * Generatoren gsk / mkPos / skSchiebe aus dem Design-Prototyp
 * (design/LEA CRM.dc.html). Reine Rechenklasse: liefert fertig
 * formatierte Koordinaten-Strings, die Blade nur noch ausgibt.
 */
final class GlasSkizze
{
    private static function f(float $n): string
    {
        return number_format($n, 1, '.', '');
    }

    private static function px(float $n): string
    {
        return self::f($n).'px';
    }

    /**
     * Mini-Kachel 120×118 für die Bestellkarten (gsk).
     *
     * @return array{pts:string,rx:string,ry:string,rw:string,rh:string,cap:string}
     */
    public static function kachel(int $w, int $hL, int $hR): array
    {
        $w = max(1, $w);
        $box = 120; $padX = 15; $padT = 17; $padB = 15;
        $aW = $box - 2 * $padX;
        $aH = 118 - $padT - $padB;
        $mH = max(1, $hL, $hR);
        $s = min($aW / $w, $aH / $mH);
        $pw = $w * $s; $ph = $mH * $s;
        $oX = ($box - $pw) / 2;
        $top = $padT + ($aH - $ph) / 2;
        $bY = $top + $ph;
        $tlY = $bY - $hL * $s;
        $trY = $bY - $hR * $s;

        return [
            'pts' => self::f($oX).','.self::f($tlY).' '.self::f($oX + $pw).','.self::f($trY).' '
                .self::f($oX + $pw).','.self::f($bY).' '.self::f($oX).','.self::f($bY),
            'rx' => self::f($oX), 'ry' => self::f($top),
            'rw' => self::f($pw), 'rh' => self::f($ph),
            'cap' => $w.'×'.$mH,
        ];
    }

    /**
     * Detail-Skizze 360×260 einer Glasposition mit Maßketten (mkPos).
     * $quelle: 'live' (aus Projekt) oder 'manuell'.
     */
    public static function position(int $w, int $hL, int $hR, bool $trapez): array
    {
        $w = max(1, $w);
        $mH = max(1, $hL, $hR);
        $BW = 360; $pL = 72; $pT = 44; $pB = 68;
        $aW = $BW - 2 * $pL;
        $aH = 260 - $pT - $pB;
        $s = min($aW / $w, $aH / $mH);
        $pw = $w * $s; $ph = $mH * $s;
        $oX = $pL + ($aW - $pw) / 2;
        $top = $pT + ($aH - $ph) / 2;
        $bY = $top + $ph;
        $tlY = $bY - $hL * $s;
        $trY = $bY - $hR * $s;

        $dl = []; $ar = [];
        $yD = $bY + 20; $xL = $oX - 18; $xR = $oX + $pw + 18;
        $L = function ($x1, $y1, $x2, $y2) use (&$dl) {
            $dl[] = ['x1' => self::f($x1), 'y1' => self::f($y1), 'x2' => self::f($x2), 'y2' => self::f($y2)];
        };
        $AH = function ($x, $y, $dx, $dy) use (&$ar) {
            $ar[] = self::f($x + $dx).','.self::f($y - $dy).' '.self::f($x).','.self::f($y).' '.self::f($x + $dx).','.self::f($y + $dy);
        };

        // Breiten-Maßkette unten
        $L($oX, $bY, $oX, $yD + 4);
        $L($oX + $pw, $bY, $oX + $pw, $yD + 4);
        $L($oX, $yD, $oX + $pw, $yD);
        $AH($oX, $yD, 6, 3);
        $AH($oX + $pw, $yD, -6, 3);
        // Höhe links
        $L($oX, $tlY, $xL - 4, $tlY);
        $L($oX, $bY, $xL - 4, $bY);
        $L($xL, $tlY, $xL, $bY);
        $ar[] = self::f($xL - 3).','.self::f($tlY + 6).' '.self::f($xL).','.self::f($tlY).' '.self::f($xL + 3).','.self::f($tlY + 6);
        $ar[] = self::f($xL - 3).','.self::f($bY - 6).' '.self::f($xL).','.self::f($bY).' '.self::f($xL + 3).','.self::f($bY - 6);
        // Trapez: zweite Höhe rechts
        if ($trapez) {
            $L($oX + $pw, $trY, $xR + 4, $trY);
            $L($oX + $pw, $bY, $xR + 4, $bY);
            $L($xR, $trY, $xR, $bY);
            $ar[] = self::f($xR - 3).','.self::f($trY + 6).' '.self::f($xR).','.self::f($trY).' '.self::f($xR + 3).','.self::f($trY + 6);
            $ar[] = self::f($xR - 3).','.self::f($bY - 6).' '.self::f($xR).','.self::f($bY).' '.self::f($xR + 3).','.self::f($bY - 6);
        }

        return [
            'dpts' => self::f($oX).','.self::f($tlY).' '.self::f($oX + $pw).','.self::f($trY).' '
                .self::f($oX + $pw).','.self::f($bY).' '.self::f($oX).','.self::f($bY),
            'drx' => self::f($oX), 'dry' => self::f($top),
            'drw' => self::f($pw), 'drh' => self::f($ph),
            'dl' => $dl, 'ar' => $ar,
            'shx1' => self::f($oX + 5), 'shy1' => self::f($bY - 6),
            'shx2' => self::f($oX + $pw * 0.62), 'shy2' => self::f(($top + $tlY) / 2 + 4),
            'sh2x1' => self::f($oX + $pw * 0.34), 'sh2y1' => self::f($bY - 6),
            'sh2x2' => self::f($oX + $pw * 0.94), 'sh2y2' => self::f(($top + $tlY) / 2 + 10),
            'showRoh' => $trapez,
            'dRt' => 'Rohmaß '.$w.'×'.$mH,
            'mass' => $trapez ? ($w.' × '.$hL.'/'.$hR.' mm') : ($w.' × '.$hL.' mm'),
            'sRoh' => 'left:'.self::px($oX + $pw / 2).';top:'.self::px($top - 9).';transform:translate(-50%,-50%)',
            'sW' => 'left:'.self::px($oX + $pw / 2).';top:'.self::px($yD + 11).';transform:translate(-50%,-50%)',
            'sHL' => 'left:'.self::px($xL - 7).';top:'.self::px(($tlY + $bY) / 2).';transform:translate(-50%,-50%) rotate(-90deg)',
            'sHR' => 'left:'.self::px($xR + 7).';top:'.self::px(($trY + $bY) / 2).';transform:translate(-50%,-50%) rotate(90deg)',
        ];
    }

    /**
     * Keil-Miniskizze 200×150 für die Anfrage-Detailkarte (kSk):
     * Trapez aus Höhe hinten / Höhe vorne / Breite unten.
     */
    public static function keil(int $hB, int $hF, int $bU): array
    {
        $bU = max(1, $bU);
        $BW = 200; $H = 150; $pL = 22; $pR = 22; $pT = 24; $pB = 26;
        $aw = $BW - $pL - $pR;
        $ah = $H - $pT - $pB;
        $mx = max(1, $hB, $hF);
        $s = min($aw / $bU, $ah / $mx);
        $w = $bU * $s; $hb = $hB * $s; $hf = $hF * $s;
        $oX = $pL + ($aw - $w) / 2;
        $baseY = $pT + $ah;
        $blX = $oX; $brX = $oX + $w;
        $tlY = $baseY - $hb; $trY = $baseY - $hf;

        return [
            'pts' => self::f($blX).','.self::f($baseY).' '.self::f($brX).','.self::f($baseY).' '
                .self::f($brX).','.self::f($trY).' '.self::f($blX).','.self::f($tlY),
            'hbL' => 'left:'.self::px($oX - 9).';top:'.self::px(($tlY + $baseY) / 2).';transform:translate(-50%,-50%) rotate(-90deg)',
            'hfL' => 'left:'.self::px($brX + 9).';top:'.self::px(($trY + $baseY) / 2).';transform:translate(-50%,-50%) rotate(90deg)',
            'buL' => 'left:'.self::px($oX + $w / 2).';top:'.self::px($baseY + 11).';transform:translate(-50%,-50%)',
        ];
    }

    /**
     * Schiebeanlagen-Skizze 360×260: Flügelteiler, Laufrichtungs-Pfeile,
     * Maßketten (skSchiebe). $richtung: 'left' | 'right' | 'center' | null.
     */
    public static function schiebe(int $w, int $h, int $n, ?string $richtung): array
    {
        $w = max(1, $w); $h = max(1, $h); $n = max(1, $n);
        $BW = 360; $pL = 70; $pT = 42; $pB = 66;
        $aW = $BW - 2 * $pL;
        $aH = 260 - $pT - $pB;
        $s = min($aW / $w, $aH / $h);
        $pw = $w * $s; $ph = $h * $s;
        $oX = $pL + ($aW - $pw) / 2;
        $top = $pT + ($aH - $ph) / 2;
        $bY = $top + $ph;
        $segW = $pw / $n;
        $cy = ($top + $bY) / 2;
        $mid = ($n + 1) / 2;

        $divs = []; $slines = []; $sheads = []; $nums = [];
        for ($i = 1; $i < $n; $i++) {
            $x = $oX + $segW * $i;
            $divs[] = ['x1' => self::f($x), 'y1' => self::f($top), 'x2' => self::f($x), 'y2' => self::f($bY)];
        }
        for ($i = 1; $i <= $n; $i++) {
            $cx = $oX + $segW * ($i - 0.5);
            $nums[] = ['i' => $i, 'st' => 'left:'.self::px($cx).';top:'.self::px($cy - 9).';transform:translate(-50%,-50%)'];
            if ($richtung === 'left') {
                $hl = true; $hr = false;
            } elseif ($richtung === 'right') {
                $hl = false; $hr = true;
            } elseif ($richtung === 'center') {
                $hl = $i <= $mid; $hr = $i >= $mid;
            } else {
                $hl = true; $hr = true;
            }
            $half = min(15, $segW / 3);
            $ay = $cy + 12; $lX = $cx - $half; $rX = $cx + $half;
            $slines[] = ['x1' => self::f($lX), 'y1' => self::f($ay), 'x2' => self::f($rX), 'y2' => self::f($ay)];
            if ($hl) {
                $sheads[] = self::f($lX + 4).','.self::f($ay - 3).' '.self::f($lX).','.self::f($ay).' '.self::f($lX + 4).','.self::f($ay + 3);
            }
            if ($hr) {
                $sheads[] = self::f($rX - 4).','.self::f($ay - 3).' '.self::f($rX).','.self::f($ay).' '.self::f($rX - 4).','.self::f($ay + 3);
            }
        }

        $dl = []; $ar = [];
        $yD = $bY + 18; $xL = $oX - 16;
        $dl[] = ['x1' => self::f($oX), 'y1' => self::f($bY), 'x2' => self::f($oX), 'y2' => self::f($yD + 4)];
        $dl[] = ['x1' => self::f($oX + $pw), 'y1' => self::f($bY), 'x2' => self::f($oX + $pw), 'y2' => self::f($yD + 4)];
        $dl[] = ['x1' => self::f($oX), 'y1' => self::f($yD), 'x2' => self::f($oX + $pw), 'y2' => self::f($yD)];
        $ar[] = self::f($oX + 6).','.self::f($yD - 3).' '.self::f($oX).','.self::f($yD).' '.self::f($oX + 6).','.self::f($yD + 3);
        $ar[] = self::f($oX + $pw - 6).','.self::f($yD - 3).' '.self::f($oX + $pw).','.self::f($yD).' '.self::f($oX + $pw - 6).','.self::f($yD + 3);
        $dl[] = ['x1' => self::f($oX), 'y1' => self::f($top), 'x2' => self::f($xL - 4), 'y2' => self::f($top)];
        $dl[] = ['x1' => self::f($oX), 'y1' => self::f($bY), 'x2' => self::f($xL - 4), 'y2' => self::f($bY)];
        $dl[] = ['x1' => self::f($xL), 'y1' => self::f($top), 'x2' => self::f($xL), 'y2' => self::f($bY)];
        $ar[] = self::f($xL - 3).','.self::f($top + 6).' '.self::f($xL).','.self::f($top).' '.self::f($xL + 3).','.self::f($top + 6);
        $ar[] = self::f($xL - 3).','.self::f($bY - 6).' '.self::f($xL).','.self::f($bY).' '.self::f($xL + 3).','.self::f($bY - 6);

        return [
            'rx' => self::f($oX), 'ry' => self::f($top),
            'rw' => self::f($pw), 'rh' => self::f($ph),
            'divs' => $divs, 'slines' => $slines, 'sheads' => $sheads, 'nums' => $nums,
            'dl' => $dl, 'ar' => $ar,
            'sW' => 'left:'.self::px($oX + $pw / 2).';top:'.self::px($yD + 11).';transform:translate(-50%,-50%)',
            'sHL' => 'left:'.self::px($xL - 7).';top:'.self::px(($top + $bY) / 2).';transform:translate(-50%,-50%) rotate(-90deg)',
        ];
    }
}
