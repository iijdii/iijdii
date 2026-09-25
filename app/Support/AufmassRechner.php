<?php

namespace App\Support;

use App\Models\ProjektPosition;

/**
 * Soll/Ist/Δ-Gruppen der „Endmaße der Extras" — Port von _mmEnd aus dem
 * Prototyp. Toleranzschwellen werden übergeben (Settings-Werte
 * toleranz_gruen_mm / toleranz_gelb_mm — laut Handoff konfigurierbare
 * Geschäftsregel, nie hartkodiert).
 */
final class AufmassRechner
{
    /** '3.502,5' / '3502,5' / '3502.5' → 3502.5; leer/ungültig → null. */
    public static function parseIst(?string $eingabe): ?float
    {
        $eingabe = trim((string) $eingabe);
        if ($eingabe === '') {
            return null;
        }

        $norm = str_replace(',', '.', $eingabe);

        return is_numeric($norm) ? (float) $norm : null;
    }

    /** Ampel: |Δ| ≤ grün → ok, ≤ gelb → warn, sonst bad. */
    public static function ampel(float $delta, int $tolGruen, int $tolGelb): string
    {
        $ad = abs((int) round($delta));

        return $ad <= $tolGruen ? 'ok' : ($ad <= $tolGelb ? 'warn' : 'bad');
    }

    /** Ein Soll/Ist/Δ-Feld — geteilt von Legacy- und Positions-Gruppen. */
    private static function feld(string $tag, string $label, float $soll, string $name, ?string $istRoh, int $tolGruen, int $tolGelb): array
    {
        $ist = self::parseIst($istRoh);
        $delta = null;
        $ampel = null;
        $deltaText = '—';
        $badge = '';
        if ($ist !== null) {
            $delta = round($ist - $soll);
            $ampel = self::ampel($delta, $tolGruen, $tolGelb);
            $deltaText = ($delta > 0 ? '+' : '').(int) $delta.' mm';
            $badge = match ($ampel) {
                'ok' => 'b-green', 'warn' => 'b-yellow', 'bad' => 'b-red'
            };
        }

        return [
            'tag' => $tag, 'label' => $label, 'name' => $name,
            'soll' => (int) round($soll),
            'sollText' => number_format(round($soll), 0, ',', '.'),
            'ist' => $ist,
            'delta' => $delta, 'deltaText' => $deltaText,
            'ampel' => $ampel, 'badge' => $badge,
        ];
    }

    /**
     * Endmaße-Gruppen aus den Phase-2-Positionen (Einheitssystem): gleiche
     * Optik wie die Legacy-Gruppen (Tabs, Zeichnung, Soll/Ist/Δ) — die
     * Eingaben heißen aber endmasse[{position}][{feld}] und fließen in die
     * Nachbestellung. Rechenwerte (Diagonale, Flügelbreite) werden mit
     * gespeichert, die Nachbestellung liest nur die bekannten Maß-Felder.
     *
     * @param  iterable<ProjektPosition>  $positionen
     * @return list<array<string, mixed>>
     */
    public static function gruppenAusPositionen(iterable $positionen, array $kalk, int $tolGruen, int $tolGelb): array
    {
        $G = [];
        $I = fn ($v) => (int) $v;

        foreach ($positionen as $position) {
            $f = $position->felder ?? [];
            $em = $position->endmasse ?? [];
            $feld = fn (string $tag, string $label, string $key, float $soll): array => self::feld(
                $tag, $label, $soll, 'endmasse['.$position->id.']['.$key.']',
                isset($em[$key]) ? (string) $em[$key] : null, $tolGruen, $tolGelb,
            );
            $n = max(1, $I($f['anzahl'] ?? $f['felder_n'] ?? 1));
            $titel = 'Pos. '.$position->pos.' · '.$position->produkt->label();

            [$shape, $badge, $felder, $note, $extra] = match ($position->produkt->value) {
                'wand', 'gelaender' => (function () use ($feld, $f, $I, $n, $position) {
                    $b = $I($f['breite_mm'] ?? $f['laenge_mm'] ?? 0);
                    $h1 = $I($f['h_links_mm'] ?? $f['hoehe_mm'] ?? 0);
                    $h2 = $I($f['h_rechts_mm'] ?? 0) ?: $h1;

                    return ['fest', $n.' Stück', [
                        $feld('A', 'Breite', $position->produkt->value === 'gelaender' ? 'laenge_mm' : 'breite_mm', $b),
                        $feld('B', 'Höhe links', $position->produkt->value === 'gelaender' ? 'hoehe_mm' : 'h_links_mm', $h1),
                        $feld('C', 'Höhe rechts', 'h_rechts_mm', $h2),
                        $feld('D', 'Diagonale', 'diagonale_mm', sqrt($b * $b + max($h1, $h2) ** 2)),
                    ], 'Diagonale beidseitig prüfen — Differenz max. 4 mm. Bei H links ≠ H rechts als Trapez fertigen. Füllung '.($f['glas'] ?? '–').'.',
                        ['qty' => $n]];
                })(),
                'schiebe' => (function () use ($feld, $f, $I, $n) {
                    $sw = $I($f['breite_mm'] ?? 0);
                    $sh = $I($f['hoehe_mm'] ?? 0);
                    $fl = $n > 0 ? (int) round(($sw + ($n - 1) * 60) / $n) : 0;
                    $dir = match ($f['richtung'] ?? '') {
                        'Nach links' => 'left', 'Nach rechts' => 'right', default => '',
                    };

                    $einbauort = trim((string) ($f['einbauort'] ?? ''));

                    return ['schiebe', ($einbauort !== '' ? $einbauort.' · ' : '').$n.' Flügel · '.strtolower($f['richtung'] ?? 'mittig'), [
                        $feld('A', 'Anlage Breite', 'breite_mm', $sw),
                        $feld('B', 'Anlage Höhe', 'hoehe_mm', $sh),
                        $feld('C', 'Flügelbreite', 'fluegel_mm', $fl),
                        $feld('D', 'Laufschiene', 'laufschiene_mm', $sw),
                    ], ($einbauort !== '' ? 'Einbauort: '.$einbauort.'. ' : '').'Laufschiene auf Waage prüfen — max. 2 mm über die Gesamtbreite. Füllung '.($f['glas'] ?? '–').'.',
                        ['dir' => $dir, 'qty' => $n]];
                })(),
                'keil' => (function () use ($feld, $f, $I, $n, $kalk) {
                    // Eingegebene Maße der Position gewinnen; ohne Eingabe
                    // wie bisher aus Dachtiefe und Gefälle abgeleitet.
                    $D = $I($f['breite_mm'] ?? 0) ?: $I($kalk['pcfg']['depth'] ?? 0);
                    $hv = $I($f['h_vorn_mm'] ?? 0) ?: 120;
                    $hh = $I($f['h_hinten_mm'] ?? 0)
                        ?: max(0, $I($kalk['wallHEff']) - $I($kalk['gutterHEff'])) + $hv;

                    return ['keil', $n.' × '.($f['seite'] ?? '–'), [
                        $feld('A', 'Breite unten', 'breite_unten_mm', $D),
                        $feld('B', 'Höhe hinten', 'hoehe_hinten_mm', $hh),
                        $feld('C', 'Höhe vorne', 'h_vorn_mm', $hv),
                        $feld('D', 'Breite oben', 'breite_oben_mm', sqrt($D * $D + ($hh - $hv) ** 2)),
                    ], 'Schrägschnitt '.$kalk['slopeEff'].'° — erst nach dem Ausrichten der Pfosten messen. Füllung '.($f['material'] ?? '–').' · '.($f['transparenz'] ?? '–').'.',
                        ['side' => $f['seite'] ?? '', 'qty' => $n, 'unterzug' => '110×110']];
                })(),
                'markise' => (function () use ($feld, $f, $I, $kalk) {
                    $mw = $I($f['breite_mm'] ?? 0);
                    $fd = $I($f['felder_n'] ?? 1) ?: 1;
                    $kn = $fd + 1;
                    $ka = $kn > 1 ? (int) round(($mw - 300) / ($kn - 1)) : 0;

                    return ['markise', $f['modell'] ?? '–', [
                        $feld('A', 'Kassettenbreite', 'breite_mm', $mw),
                        $feld('B', 'Ausfall ausgefahren', 'ausfall_mm', $I($f['ausfall_mm'] ?? 0)),
                        $feld('C', 'Konsolen-Achsabstand', 'konsolen_mm', $ka),
                        $feld('D', 'Höhe Vorderkante', 'hoehe_vorderkante_mm', max(0, $I($kalk['gutterHEff']) - 250)),
                    ], $kn.' Konsolen · nur an Sparren oder Unterzug befestigen. Neigung 12–15°.', []];
                })(),
                'sonnensegel' => (function () use ($feld, $f, $I, $kalk) {
                    // Betreiber-Standard: Stückzahl = Anzahl der Dachfelder,
                    // Breite wie die Wandblende (Achsmaß − 60 mm), Länge =
                    // Dachtiefe. Positionsfelder übersteuern («alle sofort»),
                    // die Endmaße-Zeilen jedes Segel einzeln.
                    $stueck = min(24, $I($f['anzahl'] ?? 0) ?: max(1, (int) ($kalk['fields'] ?? 1)));
                    $breite = $I($f['breite_mm'] ?? 0) ?: (int) ($kalk['blende'] ?? 0);
                    $laenge = $I($f['laenge_mm'] ?? 0) ?: $I($kalk['pcfg']['depth'] ?? 0);

                    $felder = [];
                    for ($i = 1; $i <= $stueck; $i++) {
                        $felder[] = $feld((string) $i, 'Segel '.$i.' Breite', 'breite_'.$i.'_mm', $breite);
                    }
                    $felder[] = $feld('L', 'Länge (alle)', 'laenge_mm', $laenge);

                    return ['fest', $stueck.' Stück · '.$breite.' × '.$laenge.' mm', $felder,
                        'Breite wie Wandblende (Achsmaß − 60 mm), Länge = Dachtiefe. Jedes Segel einzeln messbar — die Länge gilt für alle.',
                        ['qty' => $stueck, 'noWall' => true,
                            'zfields' => [$breite, $laenge, $laenge, (int) round(sqrt($breite ** 2 + $laenge ** 2))]]];
                })(),
                default => (function () use ($feld, $f, $I, $n) {
                    $b = $I($f['breite_mm'] ?? $f['laenge_mm'] ?? 0);
                    $h = $I($f['hoehe_mm'] ?? 0);

                    return ['fest', $n.' Stück', [
                        $feld('A', 'Breite', 'breite_mm', $b),
                        $feld('B', 'Höhe', 'hoehe_mm', $h),
                    ], 'Maße nach Montage prüfen.', ['qty' => $n]];
                })(),
            };

            $gefuellt = count(array_filter($felder, fn ($x) => $x['ist'] !== null));
            $G[] = array_merge([
                'ek' => 'p'.$position->id, 'title' => $titel, 'badge' => $badge, 'shape' => $shape,
                'fields' => $felder, 'note' => $note,
                'gefuellt' => $gefuellt, 'gesamt' => count($felder),
                'progCls' => $gefuellt === 0 ? '' : ($gefuellt === count($felder) ? 'b-green' : 'b-yellow'),
            ], $extra);
        }

        return $G;
    }

    /**
     * @param  array<string, mixed>  $mess  aufmass['mess'] — Keys "gruppe.TAG"
     * @return list<array<string, mixed>> Gruppen mit fields[] {tag,label,soll,sollText,ist,delta,deltaText,ampel,badge}
     */
    public static function gruppen(array $pcfg, array $mess, int $tolGruen, int $tolGelb): array
    {
        $p = KonfiguratorRechner::merge($pcfg);
        $I = fn ($v) => (int) $v;
        $D = $I($p['depth']);
        $hK = max(0, $I($p['wallH']) - $I($p['gutterH']));
        $extras = $p['extras'] ?? [];
        $hat = fn (string $x) => in_array($x, $extras, true);
        $G = [];

        $feld = fn (string $ek, string $tag, string $label, float $soll): array => self::feld(
            $tag, $label, $soll, 'mess['.$ek.'.'.$tag.']',
            isset($mess[$ek.'.'.$tag]) ? (string) $mess[$ek.'.'.$tag] : null,
            $tolGruen, $tolGelb,
        );

        $gruppe = function (string $ek, string $title, string $badge, string $shape, array $felder, string $note, array $extra = []) use (&$G) {
            $gefuellt = count(array_filter($felder, fn ($f) => $f['ist'] !== null));
            $G[] = array_merge([
                'ek' => $ek, 'title' => $title, 'badge' => $badge, 'shape' => $shape,
                'fields' => $felder, 'note' => $note,
                'gefuellt' => $gefuellt, 'gesamt' => count($felder),
                'progCls' => $gefuellt === 0 ? '' : ($gefuellt === count($felder) ? 'b-green' : 'b-yellow'),
            ], $extra);
        };

        if ($hat('Keile')) {
            $k = $p['keil'];
            $n = $I($k['count']) ?: 1;
            $hv = $I($k['hFront']) ?: 120;
            $hh = $hK + $hv;
            $gruppe('keil', 'Keil-Elemente', $n.' × '.($k['side'] ?? '–'), 'keil', [
                $feld('keil', 'A', 'Breite unten', $D),
                $feld('keil', 'B', 'Höhe hinten', $hh),
                $feld('keil', 'C', 'Höhe vorne', $hv),
                $feld('keil', 'D', 'Breite oben', sqrt($D * $D + ($hh - $hv) ** 2)),
            ], 'Schrägschnitt '.$I($p['slope']).'° — erst nach dem Ausrichten der Pfosten messen. Füllung '.($k['material'] ?? '–').' · '.($k['trans'] ?? '–').'.',
                ['side' => $k['side'] ?? '', 'qty' => $n, 'unterzug' => '110×110']);
        }

        if ($hat('Festelemente')) {
            $w = $p['fest'];
            $bw = $I($w['width']);
            $h1 = $I($w['height']);
            $h2 = $I($w['h2']) ?: $h1;
            $n = $I($w['count']) ?: 1;
            $gruppe('fest', 'Fest- / Wandelemente', $n.' Stück', 'fest', [
                $feld('fest', 'A', 'Breite', $bw),
                $feld('fest', 'B', 'Höhe links', $h1),
                $feld('fest', 'C', 'Höhe rechts', $h2),
                $feld('fest', 'D', 'Diagonale', sqrt($bw * $bw + max($h1, $h2) ** 2)),
            ], 'Diagonale beidseitig prüfen — Differenz max. 4 mm. Bei H links ≠ H rechts als Trapez fertigen. Füllung '.($w['glas'] ?? '–').'.',
                ['qty' => $n]);
        }

        if ($hat('Schiebe-Elemente')) {
            $s = $p['schiebe'];
            $sw = $I($s['width']);
            $shh = $I($s['height']);
            $n = $I($s['count']) ?: 1;
            $fl = $n > 0 ? (int) round(($sw + ($n - 1) * 60) / $n) : 0;
            $richtung = match ($s['dir'] ?? '') {
                'left' => 'nach links', 'right' => 'nach rechts', default => 'mittig',
            };
            $gruppe('schiebe', 'Schiebe-Elemente', $n.' Flügel · '.$richtung, 'schiebe', [
                $feld('schiebe', 'A', 'Anlage Breite', $sw),
                $feld('schiebe', 'B', 'Anlage Höhe', $shh),
                $feld('schiebe', 'C', 'Flügelbreite', $fl),
                $feld('schiebe', 'D', 'Laufschiene', $sw),
            ], 'Laufschiene auf Waage prüfen — max. 2 mm über die Gesamtbreite. Füllung '.($s['glas'] ?? '–').'.',
                ['dir' => $s['dir'] ?? '', 'qty' => $n]);
        }

        if ($hat('Markisen')) {
            $m = $p['markise'];
            $mw = $I($m['width']);
            $fd = $I($m['felder']) ?: 1;
            $kn = $fd + 1;
            $ka = $kn > 1 ? (int) round(($mw - 300) / ($kn - 1)) : 0;
            $gruppe('markise', 'Markisen', $m['modell'] ?? '–', 'markise', [
                $feld('markise', 'A', 'Kassettenbreite', $mw),
                $feld('markise', 'B', 'Ausfall ausgefahren', $I($m['ausfall'])),
                $feld('markise', 'C', 'Konsolen-Achsabstand', $ka),
                $feld('markise', 'D', 'Höhe Vorderkante', max(0, $I($p['gutterH']) - 250)),
            ], $kn.' Konsolen · nur an Sparren oder Unterzug befestigen. Neigung 12–15°.');
        }

        if ($hat('Sonnensegel')) {
            $sg = $p['segel'];
            preg_match('/(\d+(?:[.,]\d+)?)\s*[×x]\s*(\d+(?:[.,]\d+)?)/u', (string) ($sg['size'] ?? ''), $mt);
            $a = $mt ? (float) str_replace(',', '.', $mt[1]) * 1000 : 4000;
            $b = $mt ? (float) str_replace(',', '.', $mt[2]) * 1000 : 4000;
            $n = $I($sg['count']) ?: 1;
            $dg = sqrt($a * $a + $b * $b);
            $gruppe('segel', 'Sonnensegel', $n.' × '.($sg['size'] ?? '–'), 'segel', [
                $feld('segel', 'A', 'Seite oben', $a),
                $feld('segel', 'B', 'Seite rechts', $b),
                $feld('segel', 'C', 'Seite unten', $a),
                $feld('segel', 'D', 'Seite links', $b),
                $feld('segel', 'E', 'Diagonale 1', $dg),
                $feld('segel', 'F', 'Diagonale 2', $dg),
            ], 'Achsmaß Befestigungspunkte inkl. Spanner + 400 mm je Achse. Farbe '.($sg['color'] ?? '–').'.',
                ['qty' => $n]);
        }

        return $G;
    }
}
