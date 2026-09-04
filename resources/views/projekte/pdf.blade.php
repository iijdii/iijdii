<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php
    use App\Support\Format;
    $k = $kalk['pcfg'];
@endphp
<body>
    <h1>Projektmappe</h1>
    <div class="sub">{{ $projekt->nr }} · {{ $projekt->titel }} · {{ $projekt->status->label() }}</div>

    <h2>1 · Kunde &amp; Objekt</h2>
    <table class="kv"><tr>
        <td style="width:50%">
            <table class="kv">
                <tr><td class="k">Kunde</td><td>{{ $projekt->kunde->anzeigename }}</td></tr>
                <tr><td class="k">Telefon</td><td>{{ $projekt->kunde->telefon ?? '–' }}</td></tr>
                <tr><td class="k">E-Mail</td><td>{{ $projekt->kunde->email ?? '–' }}</td></tr>
            </table>
        </td>
        <td>
            <table class="kv">
                <tr><td class="k">Montageort</td><td>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</td></tr>
                <tr><td class="k">Angebot</td><td>{{ $projekt->angebot?->nr ?? '–' }}</td></tr>
                <tr><td class="k">Montage</td><td>{{ Format::datumKurz($projekt->termin_von) }}–{{ Format::datumKurz($projekt->termin_bis) }}</td></tr>
            </table>
        </td>
    </tr></table>

    <h2>2 · Technische Daten</h2>
    <table class="kv">
        <tr><td class="k">Maße</td><td>{{ number_format((int) $k['width'], 0, ',', '.') }} × {{ number_format((int) $k['depth'], 0, ',', '.') }} mm</td>
            <td class="k">Form</td><td>{{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }}</td></tr>
        <tr><td class="k">Höhen</td><td>hinten {{ number_format((int) $k['wallH'], 0, ',', '.') }} / vorn {{ number_format((int) $k['gutterH'], 0, ',', '.') }} mm</td>
            <td class="k">Neigung</td><td>{{ (int) $k['slope'] }}°</td></tr>
        <tr><td class="k">Dach</td><td>{{ $k['covering'] }} {{ $k['thickness'] }}</td>
            <td class="k">Farbe</td><td>{{ $k['color'] }}</td></tr>
        <tr><td class="k">Konstruktion</td><td>{{ $kalk['pn'] }} Pfosten · {{ $kalk['rafters'] }} Sparren · {{ $kalk['fields'] }} Felder à {{ $kalk['sparText'] }}</td>
            <td class="k">Statik</td><td>{{ $k['snow'] }} · {{ $k['wind'] }}</td></tr>
    </table>

    <h2>3 · Positionen</h2>
    <table class="pos-tabelle">
        <thead><tr><th>Pos</th><th>Bezeichnung</th><th class="num">Menge</th></tr></thead>
        <tbody>
        @foreach ($kalk['positionen'] as $position)
            <tr><td>{{ $position['pos'] }}</td><td>{{ $position['name'] }}</td><td class="num">{{ $position['menge'] }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2>4 · Materialliste (Reservierungen)</h2>
    @if ($materialListe === [])
        <p class="legal">Keine Reservierungen erfasst.</p>
    @else
        <table class="pos-tabelle">
            <thead><tr><th>Pos</th><th>Artikel</th><th>Art.-Nr.</th><th class="num">Menge</th><th>Status</th><th>Beleg</th></tr></thead>
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
