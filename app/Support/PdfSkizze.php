<?php

namespace App\Support;

/**
 * Zuschnittskizzen als eigenständige SVG-Data-URIs für die PDF-Ausgabe
 * (dompdf). Die Web-Skizzen aus GlasSkizze legen ihre Beschriftungen als
 * absolut positionierte HTML-Spans über das SVG — das kann dompdf nicht.
 * Hier wird dieselbe Geometrie (GlasSkizze-Arrays) in ein komplettes SVG
 * inkl. <text>-Labels gerendert und wie der QR-Code als base64-<img>
 * eingebettet. Höhen-Labels stehen horizontal: gedrehter Text ist in
 * php-svg-lib nicht verlässlich.
 */
final class PdfSkizze
{
    private const TINTE = '#26374a';

    private const MASS = '#64748b';

    private const GOLD = '#d9a441';

    private const GLASFUELLUNG = '#e9eff6';

    /** Glasposition (GlasSkizze::position()-Array) als SVG-Data-URI. */
    public static function glas(array $sk): string
    {
        $w = $sk['wert']['w'];
        $hL = $sk['wert']['hL'];
        $hR = $sk['wert']['hR'];

        $svg = self::auf();
        if ($sk['showRoh']) {
            $svg .= '<rect x="'.$sk['drx'].'" y="'.$sk['dry'].'" width="'.$sk['drw'].'" height="'.$sk['drh'].'" fill="none" stroke="'.self::MASS.'" stroke-width="1" stroke-dasharray="4,3"/>';
            $svg .= self::text($sk['lRoh']['x'], $sk['lRoh']['y'], $sk['dRt'], self::MASS, 10, 'middle');
        }
        $svg .= '<polygon points="'.$sk['dpts'].'" fill="'.self::GLASFUELLUNG.'" stroke="'.self::TINTE.'" stroke-width="1.5"/>';
        $svg .= '<line x1="'.$sk['shx1'].'" y1="'.$sk['shy1'].'" x2="'.$sk['shx2'].'" y2="'.$sk['shy2'].'" stroke="#c3d0de" stroke-width="2"/>';
        $svg .= '<line x1="'.$sk['sh2x1'].'" y1="'.$sk['sh2y1'].'" x2="'.$sk['sh2x2'].'" y2="'.$sk['sh2y2'].'" stroke="#c3d0de" stroke-width="1.2"/>';
        $svg .= self::massketten($sk['dl'], $sk['ar']);
        $svg .= self::text($sk['lW']['x'], $sk['lW']['y'], number_format($w, 0, ',', '.'), self::TINTE, 11, 'middle');
        $svg .= self::text($sk['lHL']['x'], $sk['lHL']['y'], number_format($hL, 0, ',', '.'), self::TINTE, 11, 'end');
        if ($sk['showRoh']) {
            $svg .= self::text($sk['lHR']['x'], $sk['lHR']['y'], number_format($hR, 0, ',', '.'), self::TINTE, 11, 'start');
        }

        return self::zu($svg);
    }

    /** Schiebeanlage (GlasSkizze::schiebe()-Array) als SVG-Data-URI. */
    public static function schiebe(array $sk): string
    {
        $svg = self::auf();
        $svg .= '<rect x="'.$sk['rx'].'" y="'.$sk['ry'].'" width="'.$sk['rw'].'" height="'.$sk['rh'].'" fill="'.self::GLASFUELLUNG.'" stroke="'.self::TINTE.'" stroke-width="1.5"/>';
        foreach ($sk['divs'] as $d) {
            $svg .= '<line x1="'.$d['x1'].'" y1="'.$d['y1'].'" x2="'.$d['x2'].'" y2="'.$d['y2'].'" stroke="'.self::TINTE.'" stroke-width="1"/>';
        }
        foreach ($sk['nums'] as $n) {
            $svg .= self::text($n['x'], $n['y'], (string) $n['i'], self::MASS, 10, 'middle');
        }
        foreach ($sk['slines'] as $l) {
            $svg .= '<line x1="'.$l['x1'].'" y1="'.$l['y1'].'" x2="'.$l['x2'].'" y2="'.$l['y2'].'" stroke="'.self::TINTE.'" stroke-width="1"/>';
        }
        foreach ($sk['sheads'] as $p) {
            $svg .= '<polyline points="'.$p.'" fill="none" stroke="'.self::TINTE.'" stroke-width="1"/>';
        }
        $svg .= self::massketten($sk['dl'], $sk['ar']);
        $svg .= self::text($sk['lW']['x'], $sk['lW']['y'], number_format($sk['wert']['w'], 0, ',', '.'), self::TINTE, 11, 'middle');
        $svg .= self::text($sk['lHL']['x'], $sk['lHL']['y'], number_format($sk['wert']['h'], 0, ',', '.'), self::TINTE, 11, 'end');

        return self::zu($svg);
    }

    private static function auf(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="260" viewBox="0 0 360 260">'
            .'<rect x="0" y="0" width="360" height="260" fill="#ffffff"/>';
    }

    private static function zu(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg.'</svg>');
    }

    /**
     * Maßketten; die Breiten-Kette (die jeweils ersten drei Linien und
     * zwei Pfeilspitzen, siehe Aufbau in GlasSkizze) wird wie im
     * Design-Vorbild goldfarben hervorgehoben.
     *
     * @param  array<int,array{x1:string,y1:string,x2:string,y2:string}>  $dl
     */
    private static function massketten(array $dl, array $ar): string
    {
        $svg = '';
        foreach ($dl as $i => $l) {
            $farbe = $i < 3 ? self::GOLD : self::MASS;
            $svg .= '<line x1="'.$l['x1'].'" y1="'.$l['y1'].'" x2="'.$l['x2'].'" y2="'.$l['y2'].'" stroke="'.$farbe.'" stroke-width="1"/>';
        }
        foreach ($ar as $i => $p) {
            $farbe = $i < 2 ? self::GOLD : self::MASS;
            $svg .= '<polyline points="'.$p.'" fill="none" stroke="'.$farbe.'" stroke-width="1"/>';
        }

        return $svg;
    }

    private static function text(string $x, string $y, string $inhalt, string $farbe, int $groesse, string $anker): string
    {
        return '<text x="'.$x.'" y="'.$y.'" font-family="DejaVu Sans" font-size="'.$groesse.'" fill="'.$farbe.'" text-anchor="'.$anker.'">'
            .htmlspecialchars($inhalt, ENT_XML1).'</text>';
    }
}
