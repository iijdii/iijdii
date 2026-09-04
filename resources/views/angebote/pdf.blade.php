<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php use App\Support\Format; @endphp
<body>
    <h1>Angebot</h1>
    <div class="sub">{{ $angebot->nr }} · {{ Format::datum($angebot->datum) }} · {{ $angebot->status->label() }}</div>

    <h2>1 · Parteien</h2>
    <table class="kv"><tr>
        <td style="width:50%">
            <table class="kv">
                <tr><td class="k">Anbieter</td><td>{{ config('lea.firma') }}</td></tr>
                <tr><td class="k">Anschrift</td><td>{{ config('lea.anschrift') }}</td></tr>
            </table>
        </td>
        <td>
            <table class="kv">
                <tr><td class="k">Kunde</td><td>{{ $angebot->kunde->anzeigename }}</td></tr>
                <tr><td class="k">Anschrift</td><td>{{ trim(($angebot->kunde->strasse ?? '').', '.($angebot->kunde->plz ?? '').' '.($angebot->kunde->stadt ?? ''), ', ') ?: '–' }}</td></tr>
                <tr><td class="k">Projekt</td><td>{{ $angebot->projekt?->nr ?? '–' }}</td></tr>
            </table>
        </td>
    </tr></table>

    <h2>2 · Leistungspositionen</h2>
    @if ($positionen === [])
        <p class="legal">Noch keine Konfiguration hinterlegt — Positionen folgen aus dem Projekt-Konfigurator.</p>
    @else
        <table class="pos-tabelle">
            <thead><tr><th>Pos</th><th>Bezeichnung</th><th class="num">Menge</th></tr></thead>
            <tbody>
            @foreach ($positionen as $position)
                <tr><td>{{ $position['pos'] }}</td><td>{{ $position['name'] }}</td><td class="num">{{ $position['menge'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>3 · Summe</h2>
    @if ($angebot->summe !== null)
        @php $brutto = (float) $angebot->summe; $netto = $brutto / 1.19; @endphp
        <table class="summen" style="width:46%;margin-left:54%">
            <tr><td>Netto</td><td class="num">{{ Format::eur($netto) }}</td></tr>
            <tr><td>zzgl. 19&nbsp;% MwSt.</td><td class="num">{{ Format::eur($brutto - $netto) }}</td></tr>
            <tr class="gesamt"><td>Gesamtsumme (brutto)</td><td class="num">{{ Format::eur($brutto) }}</td></tr>
        </table>
    @else
        <p class="legal">Summe folgt.</p>
    @endif

    <p class="legal">Angebot freibleibend, gültig 30 Tage ab Ausstellungsdatum. Zahlung gemäß Zahlungsplan
        30&nbsp;/&nbsp;40&nbsp;/&nbsp;30 (Anzahlung · Materiallieferung · Abnahme).</p>
</body>
</html>
