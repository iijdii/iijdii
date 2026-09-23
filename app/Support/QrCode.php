<?php

namespace App\Support;

/**
 * Minimaler QR-Code-Generator ohne Fremdpaket (Shared Hosting ohne
 * Composer-Zugriff, dompdf ohne Netzwerk): Byte-Modus, Fehlerkorrektur L,
 * Versionen 1–5 (ein Reed-Solomon-Block, bis 106 Zeichen — reicht für
 * die Annahme-URL), festes Maskenmuster 0. Ausgabe als SVG-Data-URI für
 * <img> im PDF wie im Browser.
 */
final class QrCode
{
    /** @var array<int, array{daten: int, ec: int, align: int}> Kapazitäten je Version (Level L) */
    private const VERSIONEN = [
        1 => ['daten' => 19, 'ec' => 7, 'align' => 0],
        2 => ['daten' => 34, 'ec' => 10, 'align' => 18],
        3 => ['daten' => 55, 'ec' => 15, 'align' => 22],
        4 => ['daten' => 80, 'ec' => 20, 'align' => 26],
        5 => ['daten' => 108, 'ec' => 26, 'align' => 30],
    ];

    /** SVG-Data-URI (schwarz auf weiß, mit Ruhezone) oder null, wenn der Text zu lang ist. */
    public static function svgDataUri(string $text): ?string
    {
        $matrix = self::matrix($text);
        if ($matrix === null) {
            return null;
        }

        $n = count($matrix);
        $rand = 4; // Ruhezone in Modulen
        $gesamt = $n + 2 * $rand;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$gesamt.' '.$gesamt.'" shape-rendering="crispEdges">'
            .'<rect width="'.$gesamt.'" height="'.$gesamt.'" fill="#FFFFFF"/>';
        foreach ($matrix as $y => $reihe) {
            $x = 0;
            while ($x < $n) {
                if ($reihe[$x] === 1) {
                    $start = $x;
                    while ($x < $n && $reihe[$x] === 1) {
                        $x++;
                    }
                    $svg .= '<rect x="'.($start + $rand).'" y="'.($y + $rand).'" width="'.($x - $start).'" height="1" fill="#000000"/>';
                } else {
                    $x++;
                }
            }
        }
        $svg .= '</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** @return list<list<int>>|null Modulmatrix (1 = dunkel) */
    public static function matrix(string $text): ?array
    {
        $bytes = array_values(unpack('C*', $text));
        $version = null;
        foreach (self::VERSIONEN as $v => $spec) {
            if (count($bytes) + 2 <= $spec['daten']) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            return null;
        }
        $spec = self::VERSIONEN[$version];

        // Bitstrom: Modus 0100 + 8-Bit-Länge + Daten + Terminator + Padding
        $bits = '0100'.str_pad(decbin(count($bytes)), 8, '0', STR_PAD_LEFT);
        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $spec['daten'] * 8 - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }
        $pad = ['11101100', '00010001'];
        for ($i = 0; strlen($bits) < $spec['daten'] * 8; $i++) {
            $bits .= $pad[$i % 2];
        }

        $codewoerter = array_map(fn (string $b) => bindec($b), str_split($bits, 8));
        $alle = array_merge($codewoerter, self::reedSolomon($codewoerter, $spec['ec']));
        $datenBits = implode('', array_map(fn (int $c) => str_pad(decbin($c), 8, '0', STR_PAD_LEFT), $alle));

        // Matrix mit Funktionsmustern aufbauen
        $n = 17 + 4 * $version;
        $matrix = array_fill(0, $n, array_fill(0, $n, 0));
        $belegt = array_fill(0, $n, array_fill(0, $n, false));

        $setze = function (int $r, int $c, int $wert) use (&$matrix, &$belegt): void {
            $matrix[$r][$c] = $wert;
            $belegt[$r][$c] = true;
        };

        // Suchmuster + Trennzonen
        foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$r0, $c0]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $rr = $r0 + $r;
                    $cc = $c0 + $c;
                    if ($rr < 0 || $cc < 0 || $rr >= $n || $cc >= $n) {
                        continue;
                    }
                    $dunkel = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6
                        && ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                    $setze($rr, $cc, $dunkel ? 1 : 0);
                }
            }
        }
        // Taktmuster
        for ($i = 8; $i < $n - 8; $i++) {
            if (! $belegt[6][$i]) {
                $setze(6, $i, ($i + 1) % 2);
            }
            if (! $belegt[$i][6]) {
                $setze($i, 6, ($i + 1) % 2);
            }
        }
        // Ausrichtungsmuster (Version ≥ 2, ein Muster in der Ecke)
        if ($spec['align'] > 0) {
            $z = $spec['align'];
            for ($r = -2; $r <= 2; $r++) {
                for ($c = -2; $c <= 2; $c++) {
                    $dunkel = max(abs($r), abs($c)) !== 1;
                    $setze($z + $r, $z + $c, $dunkel ? 1 : 0);
                }
            }
        }
        // Dunkelmodul + Formatbereiche reservieren
        $setze(4 * $version + 9, 8, 1);
        foreach (range(0, 8) as $i) {
            if (! $belegt[8][$i]) {
                $setze(8, $i, 0);
            }
            if (! $belegt[$i][8]) {
                $setze($i, 8, 0);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            if (! $belegt[8][$n - 1 - $i]) {
                $setze(8, $n - 1 - $i, 0);
            }
            if (! $belegt[$n - 1 - $i][8]) {
                $setze($n - 1 - $i, 8, 0);
            }
        }

        // Datenbits im Zickzack platzieren, Maske 0: (r+c) % 2 === 0
        $bitIndex = 0;
        $hoch = true;
        for ($spalte = $n - 1; $spalte > 0; $spalte -= 2) {
            if ($spalte === 6) {
                $spalte = 5;
            }
            $reihen = $hoch ? range($n - 1, 0) : range(0, $n - 1);
            foreach ($reihen as $r) {
                foreach ([$spalte, $spalte - 1] as $c) {
                    if ($belegt[$r][$c]) {
                        continue;
                    }
                    $bit = $bitIndex < strlen($datenBits) ? (int) $datenBits[$bitIndex] : 0;
                    $bitIndex++;
                    $matrix[$r][$c] = ($r + $c) % 2 === 0 ? $bit ^ 1 : $bit;
                    $belegt[$r][$c] = true;
                }
            }
            $hoch = ! $hoch;
        }

        // Formatinformation Level L, Maske 0 (BCH-kodiert, XOR-Maske): 0x77C4
        $format = str_pad(decbin(0x77C4), 15, '0', STR_PAD_LEFT); // b14 … b0
        $b = fn (int $i) => (int) $format[14 - $i];               // Bit i (0 = LSB)
        foreach ([0, 1, 2, 3, 4, 5] as $i) {
            $matrix[8][$i] = $b(14 - $i);
        }
        $matrix[8][7] = $b(8);
        $matrix[8][8] = $b(7);
        $matrix[7][8] = $b(6);
        for ($i = 0; $i < 6; $i++) {
            $matrix[5 - $i][8] = $b(5 - $i);
        }
        for ($i = 0; $i < 7; $i++) {
            $matrix[$n - 1 - $i][8] = $b(14 - $i);
        }
        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$n - 8 + $i] = $b(7 - $i);
        }

        return $matrix;
    }

    /** @param list<int> $daten @return list<int> Reed-Solomon-Prüfzeichen (GF 256, 0x11D) */
    private static function reedSolomon(array $daten, int $anzahl): array
    {
        static $exp = null, $log = null;
        if ($exp === null) {
            $exp = [];
            $log = [];
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $x;
                $log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
        }
        $mul = fn (int $a, int $b) => ($a === 0 || $b === 0) ? 0 : $exp[($log[$a] + $log[$b]) % 255];

        // Generatorpolynom vom Grad $anzahl
        $gen = [1];
        for ($i = 0; $i < $anzahl; $i++) {
            $neu = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $koeff) {
                $neu[$j] ^= $koeff;
                $neu[$j + 1] ^= $mul($koeff, $exp[$i]);
            }
            $gen = $neu;
        }

        $rest = array_merge($daten, array_fill(0, $anzahl, 0));
        for ($i = 0; $i < count($daten); $i++) {
            $faktor = $rest[$i];
            if ($faktor === 0) {
                continue;
            }
            foreach ($gen as $j => $koeff) {
                $rest[$i + $j] ^= $mul($koeff, $faktor);
            }
        }

        return array_slice($rest, count($daten));
    }
}
