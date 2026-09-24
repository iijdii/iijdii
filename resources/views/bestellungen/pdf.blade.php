<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil', ['palette' => 'blau'])</head>
@php use App\Support\Format; use App\Support\PdfSkizze; @endphp
<body>
    @include('partials.pdf-fuss')
    @include('partials.pdf-kopf', [
        'titel' => 'Bestellung '.$bestellung->nr,
        'badges' => array_filter([
            $bestellung->kategorieLabel(),
            'Status: '.$bestellung->status->label(),
            $bestellung->liefertermin ? 'Liefertermin '.Format::datum($bestellung->liefertermin) : null,
        ]),
    ])

    <h2>Besteller &amp; Lieferant</h2>
    <table class="box-paar"><tr>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Besteller</div>
                <table class="kv">
                    <tr><td class="k">Firma</td><td>{{ config('lea.firma') }}</td></tr>
                    <tr><td class="k">Anschrift</td><td>{{ config('lea.anschrift') }}</td></tr>
                    <tr><td class="k">Projekt</td><td>{{ $bestellung->projekt?->nr ?? '–' }} · {{ $bestellung->kunde?->anzeigename ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
        <td class="spalte"></td>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Lieferant</div>
                <table class="kv">
                    <tr><td class="k">Name</td><td>{{ $bestellung->lieferant?->name ?? '–' }}</td></tr>
                    <tr><td class="k">Anschrift</td><td>{{ trim(($bestellung->lieferant?->strasse ?? '').', '.($bestellung->lieferant?->plz ?? '').' '.($bestellung->lieferant?->stadt ?? ''), ', ') ?: '–' }}</td></tr>
                    <tr><td class="k">Liefertermin</td><td>{{ Format::datum($bestellung->liefertermin) }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    @if ($glasPositionen->isNotEmpty())
        <h2>Glas-Positionen</h2>
        @foreach ($glasPositionen as $g)
            @php
                $d = $g['position']->details ?? [];
                $hL = (int) ($d['hL'] ?? $g['position']->hoehe_mm);
                $hR = (int) ($d['hR'] ?? $hL);
                $trapez = $g['form'] === 'Trapez';
            @endphp
            <table style="width:100%;border-collapse:collapse;page-break-inside:avoid;margin-bottom:6px">
                <tr>
                    <td style="width:53%;text-align:center;vertical-align:middle;padding:2px 8px 6px 0">
                        <img src="{{ PdfSkizze::glas($g['skizze']) }}" style="width:78mm" alt="Skizze Position {{ $g['nr'] }}">
                    </td>
                    <td style="width:47%;vertical-align:middle;padding:2px 0 6px">
                        <div class="box">
                            <div class="box-titel">Pos. {{ $g['nr'] }} · {{ $g['position']->bezeichnung }}</div>
                            <table class="kv">
                                <tr><td class="k">Form</td><td>{{ $g['form'] }}</td></tr>
                                <tr><td class="k">Breite</td><td>{{ number_format((int) $g['position']->breite_mm, 0, ',', '.') }} mm</td></tr>
                                @if ($trapez)
                                    <tr><td class="k">Höhe links</td><td>{{ number_format($hL, 0, ',', '.') }} mm</td></tr>
                                    <tr><td class="k">Höhe rechts</td><td>{{ number_format($hR, 0, ',', '.') }} mm</td></tr>
                                    <tr><td class="k">Rohmaß</td><td>{{ number_format((int) $g['position']->breite_mm, 0, ',', '.') }} × {{ number_format(max($hL, $hR), 0, ',', '.') }} mm</td></tr>
                                @else
                                    <tr><td class="k">Höhe</td><td>{{ number_format($hL, 0, ',', '.') }} mm</td></tr>
                                @endif
                                <tr><td class="k">Glas</td><td>{{ $g['glas'] }}</td></tr>
                                <tr><td class="k">Menge</td><td>{{ Format::menge($g['position']->menge) }} {{ $g['position']->einheit }}</td></tr>
                                <tr><td class="k">Maße</td><td>{{ $g['quelle'] === 'live' ? 'aus Projekt übernommen' : 'manuell erfasst' }}</td></tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        @endforeach
        <p class="legal">Alle Maße in mm. Trapez-Positionen zeigen zusätzlich das Rohmaß (gestrichelt) mit beiden Höhen links/rechts.</p>
    @endif

    @if ($schiebePositionen->isNotEmpty())
        <h2>Schiebe-Elemente</h2>
        @foreach ($schiebePositionen as $s)
            <table style="width:100%;border-collapse:collapse;page-break-inside:avoid;margin-bottom:6px">
                <tr>
                    <td style="width:53%;text-align:center;vertical-align:middle;padding:2px 8px 6px 0">
                        <img src="{{ PdfSkizze::schiebe($s['skizze']) }}" style="width:78mm" alt="Skizze {{ $s['position']->bezeichnung }}">
                    </td>
                    <td style="width:47%;vertical-align:middle;padding:2px 0 6px">
                        <div class="box">
                            <div class="box-titel">{{ $s['position']->bezeichnung }}</div>
                            <table class="kv">
                                <tr><td class="k">Breite</td><td>{{ number_format((int) $s['position']->breite_mm, 0, ',', '.') }} mm</td></tr>
                                <tr><td class="k">Höhe</td><td>{{ number_format((int) $s['position']->hoehe_mm, 0, ',', '.') }} mm</td></tr>
                                <tr><td class="k">Elemente</td><td>{{ $s['anzahl'] }}</td></tr>
                                <tr><td class="k">Laufrichtung</td><td>{{ $s['richtung'] }}</td></tr>
                                <tr><td class="k">Glas</td><td>{{ $s['glas'] }}</td></tr>
                                <tr><td class="k">Menge</td><td>{{ Format::menge($s['position']->menge) }} {{ $s['position']->einheit }}</td></tr>
                                <tr><td class="k">Maße</td><td>{{ $s['quelle'] === 'live' ? 'aus Projekt übernommen' : 'manuell erfasst' }}</td></tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        @endforeach
        <p class="legal">Alle Maße in mm. Pfeile zeigen die Laufrichtung der nummerierten Flügel.</p>
    @endif

    @if ($materialPositionen->isNotEmpty())
        <h2>Material-Positionen</h2>
        <table class="positions">
            <thead><tr><th>Bezeichnung</th><th>Art.-Nr.</th><th class="num">Menge</th></tr></thead>
            <tbody>
            @foreach ($materialPositionen as $position)
                <tr>
                    <td>{{ $position->bezeichnung }}</td>
                    <td>{{ $position->artikel?->art_nr ?? '–' }}</td>
                    <td class="num">{{ Format::menge($position->menge) }} {{ $position->einheit }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($bestellung->notizen)
        <h2>Hinweise</h2>
        <div class="box">{{ $bestellung->notizen }}</div>
    @endif
</body>
</html>
