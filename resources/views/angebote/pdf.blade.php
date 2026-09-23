<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php use App\Support\Format; @endphp
<body>
    @include('partials.pdf-fuss')
    @include('partials.pdf-kopf', [
        'titel' => 'Angebot '.$angebot->nr,
        'badges' => array_filter([
            'Status: '.$angebot->status->label(),
            Format::datum($angebot->datum),
            $angebot->projekt?->nr,
        ]),
    ])

    <h2>Objekt &amp; Kunde</h2>
    <table class="box-paar"><tr>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Kunde</div>
                <table class="kv">
                    <tr><td class="k">Name</td><td>{{ $angebot->kunde->anzeigename }}</td></tr>
                    <tr><td class="k">Anschrift</td><td>{{ trim(($angebot->kunde->strasse ?? '').', '.($angebot->kunde->plz ?? '').' '.($angebot->kunde->stadt ?? ''), ', ') ?: '–' }}</td></tr>
                    <tr><td class="k">Projekt</td><td>{{ $angebot->projekt?->nr ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
        <td class="spalte"></td>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Anbieter</div>
                <table class="kv">
                    <tr><td class="k">Firma</td><td>{{ config('lea.firma') }}</td></tr>
                    <tr><td class="k">Anschrift</td><td>{{ config('lea.anschrift') }}</td></tr>
                    <tr><td class="k">Kontakt</td><td>{{ config('lea.telefon') }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <h2>Leistungspositionen</h2>
    @if ($positionen === [])
        <p class="legal">Noch keine Konfiguration hinterlegt — Positionen folgen aus dem Projekt-Konfigurator.</p>
    @else
        <table class="positions">
            <thead><tr><th style="width:34px">Pos</th><th>Bezeichnung</th><th class="num" style="width:70px">Menge</th></tr></thead>
            <tbody>
            @foreach ($positionen as $position)
                <tr><td>{{ $position['pos'] }}</td><td>{{ $position['name'] }}</td><td class="num">{{ $position['menge'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Summe</h2>
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

    <p class="legal">Dieses Angebot ist freibleibend (§&nbsp;145 BGB) und gültig 30 Tage ab Ausstellungsdatum.
        Zahlung gemäß Zahlungsplan 30&nbsp;/&nbsp;40&nbsp;/&nbsp;30 (Anzahlung · Materiallieferung · Abnahme).
        Es gelten unsere Allgemeinen Geschäftsbedingungen.</p>

    <table class="unterschriften"><tr>
        <td><div class="signatur-linie">Ort, Datum</div></td>
        <td class="spalte-leer"></td>
        <td><div class="signatur-linie">Auftrag erteilt — Unterschrift Auftraggeber</div></td>
    </tr></table>
</body>
</html>
