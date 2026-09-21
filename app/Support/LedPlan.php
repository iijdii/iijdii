<?php

namespace App\Support;

/**
 * LED-Positionsplanung des Montage-Modus. Der Monteur tippt je Sparren
 * an, WIE VIELE Lampen dort sitzen (bis zu 3 Slots als Zähler); die
 * endgültigen Abstände rechnet das System aus der Sparrenlänge:
 * n Lampen teilen den Sparren in n+1 gleiche Stücke — eine Lampe sitzt
 * in der Mitte (L/2), zwei auf den Dritteln (L/3), drei auf den
 * Vierteln (L/4). Die Maßketten zeigen genau diese Abstände.
 */
final class LedPlan
{
    private const VW = 720;

    private const VH = 470;

    private const RL = 152;

    private const RR = 664;

    private const RT = 74;

    private const RB = 322;

    /** Geometrie-Basis aus dem Rechenkern-Ergebnis. */
    private static function geo(array $kalk): array
    {
        $p = $kalk['pcfg'];
        $W = (int) $p['width'];
        $D = (int) $p['depth'];
        $sparLen = max(0, $D - 110);
        $pitch = $sparLen / 3;

        return [
            'W' => $W, 'D' => $D,
            'rafters' => $kalk['rafters'],
            'spar' => $kalk['spar'],
            'sparLen' => $sparLen,
            'pitch' => $pitch,
            'edge' => $pitch / 2,
            'total' => $kalk['ledTot'],
        ];
    }

    private static function x(int $W, float $mm): float
    {
        return self::RL + ($W > 0 ? $mm / $W * (self::RR - self::RL) : 0);
    }

    private static function pct(float $x, float $y): string
    {
        return 'left:'.number_format($x / self::VW * 100, 3, '.', '').'%;'
            .'top:'.number_format($y / self::VH * 100, 3, '.', '').'%';
    }

    /**
     * Alle Kandidaten: innere Sparren (Index 1..rafters−2) × 3 Slots.
     *
     * @return list<array{key:string, sparren:int, slot:int, style:string}>
     */
    public static function kandidaten(array $kalk): array
    {
        $g = self::geo($kalk);
        $sTop = self::RT + 110 / max(1, $g['D']) * (self::RB - self::RT);
        $aus = [];

        for ($i = 1; $i <= $g['rafters'] - 2; $i++) {
            for ($k = 0; $k < 3; $k++) {
                $aus[] = [
                    'key' => 's'.$i.'.'.$k,
                    'sparren' => $i,
                    'slot' => $k,
                    'style' => self::pct(
                        self::x($g['W'], $i * $g['spar']),
                        self::yAbs($sTop, $g['sparLen'], $g['edge'] + $k * $g['pitch']),
                    ),
                ];
            }
        }

        return $aus;
    }

    /**
     * Finale Zeichnung + KPIs. $gesetzt = Liste gesetzter Keys ("s3.1").
     *
     * @return array{viewBox:string, sTop:float, rafterLines:array, ticks:array,
     *               lampen:array, ketten:array, texte:array,
     *               abstaende:list<array{sparren:int, anzahl:int, abstand:string}>,
     *               kpis:array{sparLen:string, abstand:?string, frei:int, gesetzt:int, total:int, voll:bool}}
     */
    public static function zeichnung(array $kalk, array $gesetzt): array
    {
        $g = self::geo($kalk);
        $F = fn ($n) => number_format(round($n), 0, ',', '.');
        $done = array_flip($gesetzt);
        $picks = range(1, max(1, $g['rafters'] - 2));

        // Je Sparren zählt nur die ANZAHL gesetzter Slots — daraus folgt
        // die gleichmäßige Teilung: n Lampen → Abstand = Sparrenlänge/(n+1)
        // (eine Lampe in der Mitte, zwei auf den Dritteln usw.).
        $proSparren = [];
        foreach ($picks as $i) {
            $n = 0;
            for ($k = 0; $k < 3; $k++) {
                if (isset($done['s'.$i.'.'.$k])) {
                    $n++;
                }
            }
            if ($n > 0) {
                $proSparren[$i] = $n;
            }
        }

        $sTop = self::RT + 110 / max(1, $g['D']) * (self::RB - self::RT);
        $rafterLines = [];
        $ticks = [];
        $lampen = [];
        $ketten = [];
        $texte = [];

        for ($i = 0; $i < $g['rafters']; $i++) {
            $x = self::x($g['W'], $i * $g['spar']);
            $st = isset($proSparren[$i]);
            $rafterLines[] = ['x' => round($x, 1), 'y1' => round($sTop, 1), 'y2' => self::RB,
                'w' => $st ? 3.2 : 1.6, 'c' => $st ? '#33507d' : '#c9d2de'];
        }

        // Graue Slot-Markierungen (Tippziele) + grüne Lampen an den
        // BERECHNETEN Positionen der gleichmäßigen Teilung.
        foreach ($picks as $i) {
            $x = self::x($g['W'], $i * $g['spar']);
            for ($k = 0; $k < 3; $k++) {
                $y = self::yAbs($sTop, $g['sparLen'], $g['edge'] + $k * $g['pitch']);
                $ticks[] = ['x1' => round($x - 9, 1), 'x2' => round($x + 9, 1), 'y' => round($y, 1),
                    'c' => '#d3dae4'];
            }
        }
        foreach ($proSparren as $i => $n) {
            $x = self::x($g['W'], $i * $g['spar']);
            $teilung = $g['sparLen'] / ($n + 1);
            for ($k = 1; $k <= $n; $k++) {
                $y = self::yAbs($sTop, $g['sparLen'], $k * $teilung);
                $ticks[] = ['x1' => round($x - 9, 1), 'x2' => round($x + 9, 1), 'y' => round($y, 1),
                    'c' => '#2E8C5A'];
                $lampen[] = ['cx' => round($x, 1), 'cy' => round($y, 1)];
            }
        }

        // Abstände je belegtem Sparren; bei einheitlicher Lampenzahl gibt es
        // links eine gemeinsame Maßkette, sonst trägt die Tabelle die Werte.
        $abstaende = [];
        foreach ($proSparren as $i => $n) {
            $abstaende[] = ['sparren' => $i, 'anzahl' => $n,
                'abstand' => $F($g['sparLen'] / ($n + 1))];
        }
        $einheitlich = count(array_unique($proSparren)) === 1 ? reset($proSparren) : null;
        $setCols = array_keys($proSparren);

        if ($einheitlich !== null) {
            $VX = self::RL - 56;
            $teilung = $g['sparLen'] / ($einheitlich + 1);
            $ketten[] = ['x1' => $VX, 'y1' => round($sTop, 1), 'x2' => $VX, 'y2' => self::RB];
            $prevY = $sTop;
            for ($k = 1; $k <= $einheitlich + 1; $k++) {
                $y = self::yAbs($sTop, $g['sparLen'], min($k * $teilung, $g['sparLen']));
                if ($k <= $einheitlich) {
                    $ketten[] = ['x1' => $VX - 6, 'y1' => round($y, 1), 'x2' => $VX + 6, 'y2' => round($y, 1)];
                }
                $texte[] = ['t' => $F($teilung).' mm', 'st' => self::pct($VX - 11, ($prevY + $y) / 2), 'al' => 'end'];
                $prevY = $y;
            }
        }

        if ($setCols !== []) {
            $HY = self::RB + 34;
            $ketten[] = ['x1' => self::RL, 'y1' => $HY, 'x2' => self::RR, 'y2' => $HY];
            $px = 0.0;
            $pxx = (float) self::RL;
            foreach ($setCols as $i) {
                $mmv = $i * $g['spar'];
                $x = self::x($g['W'], $mmv);
                $ketten[] = ['x1' => round($x, 1), 'y1' => $HY - 6, 'x2' => round($x, 1), 'y2' => $HY + 6];
                $texte[] = ['t' => $F($mmv - $px), 'st' => self::pct(($pxx + $x) / 2, $HY - 13), 'al' => 'center'];
                $px = $mmv;
                $pxx = $x;
            }
            $texte[] = ['t' => $F($g['W'] - $px), 'st' => self::pct(($pxx + self::RR) / 2, $HY - 13), 'al' => 'center'];
        }

        return [
            'viewBox' => '0 0 720 470',
            'sTop' => round($sTop, 1),
            'rafterLines' => $rafterLines,
            'ticks' => $ticks,
            'lampen' => $lampen,
            'ketten' => $ketten,
            'texte' => $texte,
            'abstaende' => $abstaende,
            'kpis' => [
                'sparLen' => $F($g['sparLen']),
                'abstand' => $einheitlich !== null ? $F($g['sparLen'] / ($einheitlich + 1)) : null,
                'frei' => count($picks),
                'gesetzt' => count($gesetzt),
                'total' => $g['total'],
                'voll' => count($gesetzt) >= $g['total'],
            ],
        ];
    }

    private static function yAbs(float $sTop, float $sparLen, float $mm): float
    {
        return $sTop + ($sparLen > 0 ? $mm / $sparLen * (self::RB - $sTop) : 0);
    }
}
