<?php

namespace App\Support;

/**
 * Dach-Zeichnungen (RoofZeichnung) als eigenständige SVG-Data-URI für
 * dompdf: die CSS-Klassen der Web-Ansicht (.rd .ln, .dimt …) werden in
 * Inline-Attribute übersetzt, Schraffur-Muster und Pfeil-Marker (defs der
 * Seite) durch schlichte Flächen ersetzt, rgba() durch Hex + Deckkraft.
 */
final class PdfDachZeichnung
{
    /** Klasse → [Füllung, Deckkraft Füllung, Linie, Linienstärke, Strichmuster, Deckkraft] für Formen. */
    private const FORMEN = [
        'ln' => ['none', null, '#22375a', 1.7, null, null],
        'ln2' => ['none', null, '#22375a', 1.15, null, null],
        'mem' => ['#22375a', 0.07, '#22375a', 1.35, null, null],
        'glass' => ['#4a6fa5', 0.11, '#4a6fa5', 1.05, null, null],
        'glass2' => ['none', null, '#4a6fa5', 0.55, null, 0.7],
        'raf' => ['none', null, '#5c74a0', 0.9, null, null],
        'ctr' => ['none', null, '#c0694b', 0.7, '9 2.5 2 2.5', 0.8],
        'dim' => ['none', null, '#6f8098', 0.62, null, null],
        'lead' => ['none', null, '#93a0b2', 0.55, null, null],
        'grndln' => ['none', null, '#22375a', 1.4, null, null],
        'tbln' => ['#ffffff', null, '#c3ccd9', 0.8, null, null],
        'nsym' => ['none', null, '#556680', 1.0, null, null],
    ];

    /** Klasse → [Farbe, Schriftgröße, fett] für Texte. */
    private const TEXTE = [
        'dimt' => ['#33507d', 9.0, true],
        'lbl' => ['#41506b', 8.3, true],
        'lblu' => ['#748097', 7.6, false],
        'tbk' => ['#93a0b2', 6.2, true],
        'tbv' => ['#1b1f23', 7.8, true],
        'tbt' => ['#1b1f23', 8.4, true],
        'vlabel' => ['#22375a', 11.0, true],
        'nt' => ['#556680', 8.0, true],
    ];

    /** @param  array{viewBox:string, nodes:list<array<string,mixed>>}  $z */
    public static function dataUri(array $z): string
    {
        [$x, $y, $w, $h] = array_map('floatval', preg_split('/\s+/', trim($z['viewBox'])));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="'.$z['viewBox'].'">'
            .'<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="#ffffff"/>';

        foreach ($z['nodes'] as $n) {
            $svg .= self::knoten($n);
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg.'</svg>');
    }

    private static function knoten(array $n): string
    {
        $klassen = preg_split('/\s+/', trim((string) ($n['cls'] ?? '')));

        if ($n['tag'] === 'text') {
            [$farbe, $groesse, $fett] = self::TEXTE[$klassen[0] ?? ''] ?? ['#41506b', 8.0, false];
            // DejaVu läuft breiter als die Web-Schrift → etwas kleiner setzen.
            $groesse = round((($n['size'] ?? null) ? (float) $n['size'] : $groesse) * 0.88, 2);

            return '<text x="'.$n['x'].'" y="'.$n['y'].'" font-family="DejaVu Sans" font-size="'.$groesse.'" fill="'.$farbe.'"'
                .($fett ? ' font-weight="bold"' : '')
                .(($n['anchor'] ?? null) ? ' text-anchor="'.$n['anchor'].'"' : '')
                .(($n['rot'] ?? null) ? ' transform="rotate('.$n['rot'].')"' : '')
                .'>'.htmlspecialchars((string) $n['t'], ENT_XML1).'</text>';
        }

        $attr = self::extra((string) ($n['extra'] ?? ''));
        $stil = self::FORMEN[$klassen[0] ?? ''] ?? null;
        if ($stil !== null) {
            [$fill, $fillOp, $stroke, $sw, $dash, $op] = $stil;
            $attr += array_filter([
                'fill' => $fill, 'fill-opacity' => $fillOp, 'stroke' => $stroke, 'stroke-width' => $sw,
                'stroke-dasharray' => $dash, 'opacity' => $op,
            ], fn ($v) => $v !== null);
        }
        $attr += ['fill' => 'none', 'stroke' => '#22375a', 'stroke-width' => 1];
        if ($n['tag'] === 'line') {
            unset($attr['fill']);
        }
        $a = collect($attr)->map(fn ($v, $k) => $k.'="'.$v.'"')->join(' ');

        return match ($n['tag']) {
            'line' => '<line x1="'.$n['x1'].'" y1="'.$n['y1'].'" x2="'.$n['x2'].'" y2="'.$n['y2'].'" '.$a.'/>',
            'poly' => '<polygon points="'.$n['pts'].'" '.$a.'/>',
            'rect' => '<rect x="'.$n['x'].'" y="'.$n['y'].'" width="'.$n['w'].'" height="'.$n['h'].'" '.$a.'/>',
            'path' => '<path d="'.$n['d'].'" '.$a.'/>',
            'circle' => '<circle cx="'.$n['cx'].'" cy="'.$n['cy'].'" r="'.$n['r'].'" '.$a.'/>',
            default => '',
        };
    }

    /**
     * Zusätzliche Attribute der Web-Zeichnung übernehmen — ohne Marker,
     * Muster-Füllungen werden hellgrau, rgba() zu Hex + Deckkraft.
     *
     * @return array<string, string>
     */
    private static function extra(string $extra): array
    {
        preg_match_all('/([a-z-]+)="([^"]*)"/', $extra, $treffer, PREG_SET_ORDER);
        $attr = [];
        foreach ($treffer as [, $name, $wert]) {
            if (str_starts_with($name, 'marker')) {
                continue;
            }
            if ($name === 'style') {
                if (preg_match('/stroke-width:\s*([\d.]+)/', $wert, $sw)) {
                    $attr['stroke-width'] = $sw[1];
                }

                continue;
            }
            if (str_starts_with($wert, 'url(')) {
                $attr[$name] = '#dfe4ec';

                continue;
            }
            if (preg_match('/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+)\)/', $wert, $rgba)) {
                $attr[$name] = sprintf('#%02x%02x%02x', $rgba[1], $rgba[2], $rgba[3]);
                $attr[$name.'-opacity'] = $rgba[4];

                continue;
            }
            $attr[$name] = $wert;
        }

        return $attr;
    }
}
