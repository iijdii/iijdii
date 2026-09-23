<!DOCTYPE html>
<html lang="de">
<head>
@include('partials.pdf-stil')
<style>
    /* Dokumentspezifische Bausteine des Abnahmeprotokolls */
    .erkl { border: 1px solid #D8DCE2; background: #FFFFFF; padding: 8px 10px; margin-bottom: 6px; }
    .erkl.aktiv { border-color: #2B2E34; background: #F4F6F8; }
    .erkl b { display: block; margin-bottom: 2px; color: #2B2E34; }
    .check { margin: 2px 0; }
    .sig-block { width: 48%; display: inline-block; vertical-align: bottom; margin-top: 14px; }
    .sig-block img { max-height: 70px; max-width: 100%; }
</style>
</head>
<body>
    @include('partials.pdf-fuss')
    @include('partials.pdf-kopf', [
        'titel' => 'Abnahmeprotokoll '.$protokoll->nr,
        'badges' => ['Terrassenüberdachung', 'gemäß § 640 BGB'],
    ])

    <h2>Vertragsparteien</h2>
    <table class="box-paar"><tr>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Auftraggeber</div>
                <table class="kv">
                    <tr><td class="k">Name</td><td>{{ $projekt->kunde->anzeigename }}</td></tr>
                    <tr><td class="k">Anschrift</td><td>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</td></tr>
                    <tr><td class="k">Telefon</td><td>{{ $projekt->kunde->telefon ?? '–' }}</td></tr>
                    <tr><td class="k">E-Mail</td><td>{{ $projekt->kunde->email ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
        <td class="spalte"></td>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Auftragnehmer</div>
                <table class="kv">
                    <tr><td class="k">Firma</td><td>{{ config('lea.firma') }}</td></tr>
                    <tr><td class="k">Anschrift</td><td>{{ config('lea.anschrift') }}</td></tr>
                    <tr><td class="k">Bauleitung</td><td>{{ config('lea.bauleitung') }}</td></tr>
                    <tr><td class="k">Montageteam</td><td>{{ config('lea.montageteam') }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <h2>Bauvorhaben &amp; Leistungsgegenstand</h2>
    <table class="kv">
        <tr><td class="k">Projekt-Nr.</td><td>{{ $projekt->nr }}</td>
            <td class="k">Leistung</td><td>{{ $projekt->titel }}</td></tr>
        <tr><td class="k">Angebots-Nr.</td><td>{{ $projekt->angebot?->nr ?? '–' }}</td>
            <td class="k">Maße</td><td>{{ number_format($projekt->konfiguration['width'] ?? 0, 0, ',', '.') }} × {{ number_format($projekt->konfiguration['depth'] ?? 0, 0, ',', '.') }} mm</td></tr>
        <tr><td class="k">Montageort</td><td>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</td>
            <td class="k">Fertiggestellt</td><td>{{ $protokoll->datum?->format('d.m.Y') }}</td></tr>
    </table>
    <table class="positions" style="margin-top:6px">
        <thead><tr><th style="width:34px">Pos</th><th>Ausgeführte Leistung</th><th>Menge</th><th>Ausgeführt</th></tr></thead>
        <tbody>
        @foreach ($positionen as $position)
            <tr><td>{{ $position['pos'] }}</td><td>{{ $position['name'] }}</td><td>{{ $position['menge'] }}</td><td>ja</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2>Erklärung des Auftraggebers</h2>
    @foreach ([
        ['ohne', 'Abnahme ohne Vorbehalt', 'Der Auftraggeber bestätigt, dass die Leistung vertragsgemäß, vollständig und mängelfrei ausgeführt wurde. Die Leistung wird hiermit ohne Vorbehalt abgenommen; Beanstandungen bestehen nicht.'],
        ['vorbehalt', 'Abnahme unter Vorbehalt', 'Die Leistung wird abgenommen, jedoch unter Vorbehalt der nachstehend aufgeführten Mängel. Der Auftragnehmer verpflichtet sich zur Nachbesserung innerhalb der genannten Frist.'],
        ['verweigert', 'Abnahme verweigert', 'Die Abnahme wird wegen der nachstehend aufgeführten wesentlichen Mängel verweigert. Ein neuer Abnahmetermin wird nach Mängelbeseitigung vereinbart.'],
    ] as [$wert, $label, $text])
        <div class="erkl {{ $protokoll->art->value === $wert ? 'aktiv' : '' }}">
            <b>{{ $protokoll->art->value === $wert ? '☒' : '☐' }} {{ $label }}</b>
            {{ $text }}
        </div>
    @endforeach

    @if ($maengel->isNotEmpty())
        <h2>{{ $protokoll->art->value === 'verweigert' ? 'Gründe der Abnahmeverweigerung' : 'Vorbehalte und Beanstandungen des Auftraggebers' }}</h2>
        <table class="positions">
            <thead><tr><th style="width:24px">#</th><th>Beschreibung</th><th>Frist</th></tr></thead>
            <tbody>
            @foreach ($maengel as $i => $mangel)
                <tr><td>{{ $i + 1 }}</td><td>{{ $mangel['text'] }}</td><td>{{ $mangel['frist'] ?? '–' }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <p class="legal">Die aufgeführten Mängel sind bis zum jeweils genannten Termin nachzubessern. Der Auftraggeber
            behält sich seine Rechte aus § 634 BGB ausdrücklich vor. Ein Zurückbehaltungsrecht in Höhe des doppelten
            Mängelbeseitigungsaufwands bleibt unberührt.</p>
    @endif

    <h2>Übergabe &amp; Einweisung</h2>
    @foreach ([
        'einweisung' => 'Einweisung in Bedienung von Beleuchtung, Markise und Schiebe-Elementen erfolgt',
        'pflege' => 'Pflege- und Wartungshinweise sowie Reinigungsanleitung ausgehändigt',
        'unterlagen' => 'Konformitätserklärung, Statik und Bedienungsanleitungen übergeben',
        'baustelle' => 'Montagestelle gereinigt, Verpackungs- und Restmaterial abgeführt',
    ] as $key => $label)
        <div class="check">{{ ($protokoll->checkliste[$key] ?? false) ? '☒' : '☐' }} {{ $label }}</div>
    @endforeach
    <p class="legal">Mit der Abnahme geht die Gefahr auf den Auftraggeber über. Die Verjährungsfrist für
        Mängelansprüche beginnt am Tag der Abnahme und beträgt fünf Jahre gemäß § 634a Abs. 1 Nr. 2 BGB.
        Die Schlussrechnung wird nach Abnahme gestellt und ist gemäß Zahlungsplan fällig.</p>

    <h2>Ort, Datum und Unterschriften</h2>
    <table class="kv">
        <tr><td class="k">Ort</td><td>{{ $protokoll->ort }}</td>
            <td class="k">Datum</td><td>{{ $protokoll->datum?->format('d.m.Y') }}</td></tr>
    </table>
    <div>
        <div class="sig-block">
            <img src="{{ $sigK }}" alt="">
            <div class="signatur-linie">Auftraggeber · {{ $projekt->kunde->anzeigename }}</div>
        </div>
        <div class="sig-block" style="margin-left:3%">
            <img src="{{ $sigM }}" alt="">
            <div class="signatur-linie">Auftragnehmer · {{ config('lea.monteur') }}</div>
        </div>
    </div>
</body>
</html>
