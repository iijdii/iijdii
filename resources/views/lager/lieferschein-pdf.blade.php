<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php use App\Support\Format; @endphp
<body>
    <h1>Lieferschein</h1>
    <div class="sub">{{ $wareneingang->lieferschein_nr }} · Wareneingang zu {{ $bestellung->nr }}</div>

    <h2>1 · Kopf</h2>
    <table class="kv"><tr>
        <td style="width:50%">
            <table class="kv">
                <tr><td class="k">Empfänger</td><td>{{ config('lea.firma') }}</td></tr>
                <tr><td class="k">Anschrift</td><td>{{ config('lea.anschrift') }}</td></tr>
                <tr><td class="k">Gebucht von</td><td>{{ $wareneingang->benutzer?->name ?? '–' }}</td></tr>
            </table>
        </td>
        <td>
            <table class="kv">
                <tr><td class="k">Lieferant</td><td>{{ $bestellung->lieferant->name }}</td></tr>
                <tr><td class="k">Eingang am</td><td>{{ Format::datum($wareneingang->datum) }}</td></tr>
                <tr><td class="k">Projekt</td><td>{{ $bestellung->projekt?->nr ?? '–' }}</td></tr>
            </table>
        </td>
    </tr></table>

    <h2>2 · Eingelagerte Positionen</h2>
    <table class="pos-tabelle">
        <thead><tr><th>#</th><th>Bezeichnung</th><th>Art.-Nr.</th><th class="num">Menge</th></tr></thead>
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
