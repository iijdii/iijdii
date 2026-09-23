<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php use App\Support\Format; @endphp
<body>
    @include('partials.pdf-fuss')
    @include('partials.pdf-kopf', [
        'titel' => 'Lieferschein '.$wareneingang->lieferschein_nr,
        'badges' => array_filter([
            'Wareneingang zu '.$bestellung->nr,
            Format::datum($wareneingang->datum),
        ]),
    ])

    <h2>Empfänger &amp; Lieferant</h2>
    <table class="box-paar"><tr>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Empfänger</div>
                <table class="kv">
                    <tr><td class="k">Firma</td><td>{{ config('lea.firma') }}</td></tr>
                    <tr><td class="k">Anschrift</td><td>{{ config('lea.anschrift') }}</td></tr>
                    <tr><td class="k">Gebucht von</td><td>{{ $wareneingang->benutzer?->name ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
        <td class="spalte"></td>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Lieferant</div>
                <table class="kv">
                    <tr><td class="k">Name</td><td>{{ $bestellung->lieferant->name }}</td></tr>
                    <tr><td class="k">Eingang am</td><td>{{ Format::datum($wareneingang->datum) }}</td></tr>
                    <tr><td class="k">Projekt</td><td>{{ $bestellung->projekt?->nr ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <h2>Eingelagerte Positionen</h2>
    <table class="positions">
        <thead><tr><th style="width:24px">#</th><th>Bezeichnung</th><th>Art.-Nr.</th><th class="num">Menge</th></tr></thead>
        <tbody>
        @foreach ($positionen as $i => $zeile)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $zeile['bezeichnung'] }}</td>
                <td>{{ $zeile['artikel']?->art_nr ?? '–' }}</td>
                <td class="num">{{ Format::menge($zeile['menge']) }} {{ $zeile['einheit'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="legal">Automatisch gebuchter Wareneingang — jede Position wurde dem Lagerbestand zugeschrieben
        (Bewegungsjournal, Referenz {{ $bestellung->nr }}).</p>
</body>
</html>
