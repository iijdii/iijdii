<?php

namespace App\Support;

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

    /**
     * @param array<string, mixed> $mess  aufmass['mess'] — Keys "gruppe.TAG"
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

        $feld = function (string $ek, string $tag, string $label, float $soll) use ($mess, $tolGruen, $tolGelb): array {
            $ist = self::parseIst(isset($mess[$ek.'.'.$tag]) ? (string) $mess[$ek.'.'.$tag] : null);
            $delta = null;
            $ampel = null;
            $deltaText = '—';
            $badge = '';
            if ($ist !== null) {
                $delta = round($ist - $soll);
                $ampel = self::ampel($delta, $tolGruen, $tolGelb);
                $deltaText = ($delta > 0 ? '+' : '').(int) $delta.' mm';
                $badge = match ($ampel) { 'ok' => 'b-green', 'warn' => 'b-yellow', 'bad' => 'b-red' };
            }

            return [
                'tag' => $tag, 'label' => $label,
                'soll' => (int) round($soll),
                'sollText' => number_format(round($soll), 0, ',', '.'),
                'ist' => $ist,
                'delta' => $delta, 'deltaText' => $deltaText,
                'ampel' => $ampel, 'badge' => $badge,
            ];
        };

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
