<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php
    use App\Support\Format;
    $k = $kalk['pcfg'];
    $mm = fn ($wert) => number_format((int) $wert, 0, ',', '.');
    $drainSeite = ($k['drain']['post'] ?? '') === 'rechts' ? 'Pfosten rechts' : 'Pfosten links';
    $unterzug = $kalk['unterzug'] ?? [];
    $unterzugText = ($unterzug['gewaehlt'] ?? false)
        ? ($unterzug['anzahl'] ?? 1).' Stück · '.($unterzug['groesse'] ?? '110×190 mm')
        : 'ohne';
@endphp
<body>
    @include('partials.pdf-fuss')
    @include('partials.pdf-kopf', [
        'titel' => 'Projektmappe '.$projekt->nr,
        'badges' => array_filter([$projekt->titel, 'Status: '.$projekt->status->label()]),
    ])

    <h2>Kunde &amp; Objekt</h2>
    <table class="box-paar"><tr>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Kunde</div>
                <table class="kv">
                    <tr><td class="k">Name</td><td>{{ $projekt->kunde->anzeigename }}</td></tr>
                    <tr><td class="k">Telefon</td><td>{{ $projekt->kunde->telefon ?? '–' }}</td></tr>
                    <tr><td class="k">E-Mail</td><td>{{ $projekt->kunde->email ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
        <td class="spalte"></td>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Objekt</div>
                <table class="kv">
                    <tr><td class="k">Montageort</td><td>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</td></tr>
                    <tr><td class="k">Angebot</td><td>{{ $projekt->angebot?->nr ?? '–' }}</td></tr>
                    <tr><td class="k">Montage</td><td>{{ Format::datumKurz($projekt->termin_von) }}–{{ Format::datumKurz($projekt->termin_bis) }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <h2>Technische Daten</h2>
    <table class="kv">
        <tr><td class="k">Maße</td><td>{{ $mm($k['width']) }} × {{ $mm($k['depth']) }} mm</td>
            <td class="k">Form</td><td>{{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }}</td></tr>
        <tr><td class="k">Höhen</td><td>hinten {{ $mm($kalk['wallHEff']) }} / vorn {{ $mm($kalk['gutterHEff']) }} mm</td>
            <td class="k">Neigung</td><td>{{ number_format((float) $kalk['slopeEff'], 1, ',', '.') }}° ({{ number_format((float) $kalk['gefaelleProzent'], 1, ',', '.') }} %)</td></tr>
        <tr><td class="k">Dach</td><td>{{ $k['covering'] }} {{ $k['thickness'] }}</td>
            <td class="k">Farbe</td><td>{{ $k['color'] }}</td></tr>
        <tr><td class="k">Konstruktion</td><td>{{ $kalk['pn'] }} Pfosten · {{ $kalk['rafters'] }} Sparren · {{ $kalk['fields'] }} Felder à {{ $kalk['sparText'] }}</td>
            <td class="k">Unterzug</td><td>{{ $unterzugText }}</td></tr>
        <tr><td class="k">Verglasung</td><td>{{ $kalk['glasText'] }}</td>
            <td class="k">Entwässerung</td><td>{{ $drainSeite }}</td></tr>
    </table>

    <h2>Positionen</h2>
    <table class="positions">
        <thead><tr><th style="width:34px">Pos</th><th>Bezeichnung</th><th class="num" style="width:70px">Menge</th></tr></thead>
        <tbody>
        @foreach ($kalk['positionen'] as $position)
            <tr><td>{{ $position['pos'] }}</td><td>{{ $position['name'] }}</td><td class="num">{{ $position['menge'] }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2>Materialliste (Reservierungen)</h2>
    @if ($materialListe === [])
        <p class="legal">Keine Reservierungen erfasst.</p>
    @else
        <table class="positions">
            <thead><tr><th style="width:34px">Pos</th><th>Artikel</th><th>Art.-Nr.</th><th class="num">Menge</th><th>Status</th><th>Beleg</th></tr></thead>
            <tbody>
            @foreach ($materialListe as $zeile)
                <tr>
                    <td>{{ $zeile['pos'] }}</td>
                    <td>{{ $zeile['artikel']->name }}</td>
                    <td>{{ $zeile['artikel']->art_nr }}</td>
                    <td class="num">{{ Format::menge($zeile['menge']) }} {{ $zeile['artikel']->einheit->value }}</td>
                    <td>{{ $zeile['status'] }}</td>
                    <td>{{ $zeile['beleg'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
