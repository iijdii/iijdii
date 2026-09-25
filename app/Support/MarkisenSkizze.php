<?php

namespace App\Support;

/**
 * Schemazeichnungen zum Markisen-Bestellblatt (eigene Darstellung):
 * T200 Typ A / Typ B in Ansicht von außen mit Bestellmaß B, Ausfall und
 * den 22-mm-Überständen, F513 als Kastenquerschnitt mit den
 * Kabelabgängen 1–8. Liefert komplette SVG-Strings — inline fürs Web,
 * als Data-URI fürs PDF (dompdf).
 */
final class MarkisenSkizze
{
    private const LINIE = '#26374a';

    private const MASS = '#64748b';

    private const GOLD = '#d9a441';

    private const TUCH = '#e9eff6';

    /** T200 Ansicht von außen; Typ B mit Kopplung B1 | B2. */
    public static function t200(bool $typB, ?int $b = null, ?int $b1 = null, ?int $b2 = null, ?int $ausfall = null): string
    {
        $svg = self::auf(320, 190);
        $l = 50;
        $r = 270;
        $oben = 24;
        $unten = 140;

        // Kasten, Führungsschienen, Tuch, Fallprofil
        $svg .= '<rect x="'.($l - 6).'" y="'.$oben.'" width="'.($r - $l + 12).'" height="18" fill="#dbe2ea" stroke="'.self::LINIE.'" stroke-width="1.4"/>';
        $svg .= '<rect x="'.$l.'" y="'.($oben + 18).'" width="'.($r - $l).'" height="'.($unten - $oben - 26).'" fill="'.self::TUCH.'" stroke="none"/>';
        $pfosten = $typB ? [$l, ($l + $r) / 2, $r] : [$l, $r];
        foreach ($pfosten as $x) {
            $svg .= '<rect x="'.($x - 4).'" y="'.($oben + 18).'" width="8" height="'.($unten - $oben - 18).'" fill="#ffffff" stroke="'.self::LINIE.'" stroke-width="1.2"/>';
        }
        $svg .= '<rect x="'.$l.'" y="'.($unten - 16).'" width="'.($r - $l).'" height="7" fill="#ffffff" stroke="'.self::LINIE.'" stroke-width="1.2"/>';
        $svg .= self::text(($l + $r) / 2, $oben + 13, $typB ? 'Typ B (gekoppelt)' : 'Typ A', self::LINIE, 10, 'middle', true);

        // Bestellmaß B unten, 22-mm-Überstände
        $y = $unten + 22;
        $svg .= self::masskette($l, $r, $y, 'B'.($b ? ' = '.self::mm($b) : ''), true);
        $svg .= self::text($l - 14, $unten + 10, '22', self::MASS, 8, 'middle');
        $svg .= self::text($r + 14, $unten + 10, '22', self::MASS, 8, 'middle');
        if ($typB) {
            $mitte = ($l + $r) / 2;
            $svg .= self::masskette($l, $mitte, $unten + 8, 'B1'.($b1 ? ' = '.self::mm($b1) : ''), false);
            $svg .= self::masskette($mitte, $r, $unten + 8, 'B2'.($b2 ? ' = '.self::mm($b2) : ''), false);
        }

        // Ausfall / Höhe links
        $svg .= '<line x1="24" y1="'.$oben.'" x2="24" y2="'.$unten.'" stroke="'.self::MASS.'" stroke-width="1"/>';
        $svg .= self::pfeil(24, $oben, 'hoch').self::pfeil(24, $unten, 'runter');
        $svg .= self::text(20, ($oben + $unten) / 2 - 4, 'H', self::LINIE, 10, 'end', true);
        if ($ausfall) {
            $svg .= self::text(20, ($oben + $unten) / 2 + 9, self::mm($ausfall), self::MASS, 8, 'end');
        }

        return $svg.'</svg>';
    }

    /** F513 Kastenquerschnitt mit Kabelabgängen 1–8; gewählter Abgang goldfarben. */
    public static function f513Kabelabgang(?string $gewaehlt = null): string
    {
        $svg = self::auf(360, 190);
        // Kasten (Deckenmontage-Ansicht) + Führungsschiene
        $svg .= '<rect x="70" y="40" width="80" height="70" rx="6" fill="#dbe2ea" stroke="'.self::LINIE.'" stroke-width="1.4"/>';
        $svg .= '<rect x="98" y="110" width="22" height="55" fill="#ffffff" stroke="'.self::LINIE.'" stroke-width="1.2"/>';
        $svg .= self::text(110, 80, 'Kasten', self::LINIE, 9, 'middle');
        $svg .= '<line x1="40" y1="30" x2="40" y2="170" stroke="'.self::MASS.'" stroke-width="1" stroke-dasharray="4,3"/>';
        $svg .= self::text(36, 176, 'Wand', self::MASS, 8, 'start');
        $svg .= '<line x1="60" y1="30" x2="170" y2="30" stroke="'.self::MASS.'" stroke-width="1" stroke-dasharray="4,3"/>';
        $svg .= self::text(172, 33, 'Decke', self::MASS, 8, 'start');

        $punkte = [
            '1' => [140, 122], '2' => [56, 96], '3' => [80, 24], '4' => [56, 54],
            '5' => [120, 24], '6' => [110, 62], '7' => [166, 76], '8' => [109, 176],
        ];
        foreach ($punkte as $nr => [$x, $y]) {
            $aktiv = (string) $gewaehlt === (string) $nr;
            $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="8" fill="'.($aktiv ? self::GOLD : '#ffffff').'" stroke="'.self::LINIE.'" stroke-width="1.2"/>';
            $svg .= self::text($x, $y + 3.5, $nr, self::LINIE, 9, 'middle', true);
        }

        // Legende
        $y = 34;
        foreach (MarkisenFormular::KABELABGAENGE as $nr => $text) {
            $aktiv = (string) $gewaehlt === (string) $nr;
            $svg .= self::text(206, $y, $nr.'  '.$text, $aktiv ? self::LINIE : self::MASS, 9, 'start', $aktiv);
            $y += 18;
        }

        return $svg.'</svg>';
    }

    public static function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function auf(int $b, int $h): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$b.'" height="'.$h.'" viewBox="0 0 '.$b.' '.$h.'">'
            .'<rect x="0" y="0" width="'.$b.'" height="'.$h.'" fill="#ffffff"/>';
    }

    private static function masskette(float $von, float $bis, float $y, string $label, bool $gold): string
    {
        $farbe = $gold ? self::GOLD : self::MASS;

        return '<line x1="'.$von.'" y1="'.$y.'" x2="'.$bis.'" y2="'.$y.'" stroke="'.$farbe.'" stroke-width="1"/>'
            .'<line x1="'.$von.'" y1="'.($y - 5).'" x2="'.$von.'" y2="'.($y + 5).'" stroke="'.$farbe.'" stroke-width="1"/>'
            .'<line x1="'.$bis.'" y1="'.($y - 5).'" x2="'.$bis.'" y2="'.($y + 5).'" stroke="'.$farbe.'" stroke-width="1"/>'
            .self::text(($von + $bis) / 2, $y + ($gold ? 14 : -4), $label, self::LINIE, 9, 'middle', $gold);
    }

    private static function pfeil(float $x, float $y, string $richtung): string
    {
        $d = $richtung === 'hoch' ? 6 : -6;

        return '<polyline points="'.($x - 3).','.($y + $d).' '.$x.','.$y.' '.($x + 3).','.($y + $d).'" fill="none" stroke="'.self::MASS.'" stroke-width="1"/>';
    }

    private static function text(float $x, float $y, string $inhalt, string $farbe, int $groesse, string $anker, bool $fett = false): string
    {
        return '<text x="'.$x.'" y="'.$y.'" font-family="DejaVu Sans" font-size="'.$groesse.'" fill="'.$farbe.'" text-anchor="'.$anker.'"'
            .($fett ? ' font-weight="bold"' : '').'>'.htmlspecialchars($inhalt, ENT_XML1).'</text>';
    }

    private static function mm(int $wert): string
    {
        return number_format($wert, 0, ',', '.').' mm';
    }
}
