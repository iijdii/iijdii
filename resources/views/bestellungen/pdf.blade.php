<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil', ['palette' => 'blau'])</head>
@php use App\Support\Format; @endphp
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
        <table class="positions">
            <thead><tr><th style="width:24px">#</th><th>Bezeichnung</th><th>Form</th><th class="num">Breite</th><th class="num">Höhe L/R</th><th>Glas</th><th class="num">Menge</th></tr></thead>
            <tbody>
            @foreach ($glasPositionen as $g)
                @php $d = $g['position']->details ?? []; @endphp
                <tr>
                    <td>{{ $g['nr'] }}</td>
                    <td>{{ $g['position']->bezeichnung }}</td>
                    <td>{{ $g['form'] }}</td>
                    <td class="num">{{ number_format((int) $g['position']->breite_mm, 0, ',', '.') }} mm</td>
                    <td class="num">{{ number_format((int) ($d['hL'] ?? $g['position']->hoehe_mm), 0, ',', '.') }} / {{ number_format((int) ($d['hR'] ?? $d['hL'] ?? $g['position']->hoehe_mm), 0, ',', '.') }} mm</td>
                    <td>{{ $g['glas'] }}</td>
                    <td class="num">{{ Format::menge($g['position']->menge) }} {{ $g['position']->einheit }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($schiebePositionen->isNotEmpty())
        <h2>Schiebe-Elemente</h2>
        <table class="positions">
            <thead><tr><th>Bezeichnung</th><th class="num">Breite × Höhe</th><th class="num">Elemente</th><th>Glas</th></tr></thead>
            <tbody>
            @foreach ($schiebePositionen as $s)
                @php $d = $s['position']->details ?? []; @endphp
                <tr>
                    <td>{{ $s['position']->bezeichnung }}</td>
                    <td class="num">{{ number_format((int) $s['position']->breite_mm, 0, ',', '.') }} × {{ number_format((int) $s['position']->hoehe_mm, 0, ',', '.') }} mm</td>
                    <td class="num">{{ (int) ($d['count'] ?? $s['position']->menge) }}</td>
                    <td>{{ $d['glas'] ?? '–' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
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
