<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
    /* Nur dompdf-gebündelte DejaVu-Fonts — keine externen Ressourcen. */
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1B1F23; margin: 28px 34px; }
    h1 { font-family: 'DejaVu Serif', serif; font-size: 20px; margin: 0 0 2px; }
    .sub { font-size: 8.5px; letter-spacing: 1px; text-transform: uppercase; color: #828a94; margin-bottom: 14px; }
    h2 { font-size: 9px; letter-spacing: 1px; text-transform: uppercase; color: #828a94; border-bottom: 1px solid #E2E5EA; padding-bottom: 4px; margin: 16px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 3px 6px; vertical-align: top; text-align: left; }
    .kv td.k { width: 110px; color: #828a94; }
    .pos-tabelle th { border-bottom: 1px solid #E2E5EA; font-size: 8.5px; text-transform: uppercase; color: #828a94; }
    .pos-tabelle td { border-bottom: 1px solid #edf0f4; }
    .erkl { border: 1px solid #E2E5EA; border-radius: 4px; padding: 8px 10px; margin-bottom: 6px; }
    .erkl.aktiv { border-color: #1B1F23; background: #f5f6f8; }
    .erkl b { display: block; margin-bottom: 2px; }
    .legal { font-size: 8px; color: #4b535d; margin: 6px 0 0; }
    .check { margin: 2px 0; }
    .sig-block { width: 48%; display: inline-block; vertical-align: bottom; margin-top: 14px; }
    .sig-block img { max-height: 70px; max-width: 100%; }
    .sig-line { border-top: 1px solid #1B1F23; padding-top: 3px; font-size: 8.5px; color: #4b535d; }
    .badge { font-size: 8px; font-weight: bold; }
</style>
</head>
<body>
    <h1>Abnahmeprotokoll</h1>
    <div class="sub">{{ $protokoll->nr }} · Terrassenüberdachung · gemäß § 640 BGB</div>

    <h2>1 · Vertragsparteien</h2>
    <table class="kv"><tr>
        <td style="width:50%">
            <table class="kv">
                <tr><td class="k">Auftraggeber</td><td>{{ $projekt->kunde->anzeigename }}</td></tr>
                <tr><td class="k">Anschrift</td><td>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</td></tr>
                <tr><td class="k">Telefon</td><td>{{ $projekt->kunde->telefon ?? '–' }}</td></tr>
                <tr><td class="k">E-Mail</td><td>{{ $projekt->kunde->email ?? '–' }}</td></tr>
            </table>
        </td>
        <td>
            <table class="kv">
                <tr><td class="k">Auftragnehmer</td><td>{{ config('lea.firma') }}</td></tr>
                <tr><td class="k">Anschrift</td><td>{{ config('lea.anschrift') }}</td></tr>
                <tr><td class="k">Bauleitung</td><td>{{ config('lea.bauleitung') }}</td></tr>
                <tr><td class="k">Montageteam</td><td>{{ config('lea.montageteam') }}</td></tr>
            </table>
        </td>
    </tr></table>

    <h2>2 · Bauvorhaben &amp; Leistungsgegenstand</h2>
    <table class="kv">
        <tr><td class="k">Projekt-Nr.</td><td>{{ $projekt->nr }}</td>
            <td class="k">Leistung</td><td>{{ $projekt->titel }}</td></tr>
        <tr><td class="k">Angebots-Nr.</td><td>{{ $projekt->angebot?->nr ?? '–' }}</td>
            <td class="k">Maße</td><td>{{ number_format($projekt->konfiguration['width'] ?? 0, 0, ',', '.') }} × {{ number_format($projekt->konfiguration['depth'] ?? 0, 0, ',', '.') }} mm</td></tr>
        <tr><td class="k">Montageort</td><td>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</td>
            <td class="k">Fertiggestellt</td><td>{{ $protokoll->datum?->format('d.m.Y') }}</td></tr>
    </table>
    <table class="pos-tabelle" style="margin-top:6px">
        <thead><tr><th>Pos</th><th>Ausgeführte Leistung</th><th>Menge</th><th>Ausgeführt</th></tr></thead>
        <tbody>
        @foreach ($positionen as $position)
            <tr><td>{{ $position['pos'] }}</td><td>{{ $position['name'] }}</td><td>{{ $position['menge'] }}</td><td>ja</td></tr>
        @endforeach
        </tbody>
    </table>

    <h2>3 · Erklärung des Auftraggebers</h2>
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
        <h2>4 · {{ $protokoll->art->value === 'verweigert' ? 'Gründe der Abnahmeverweigerung' : 'Vorbehalte und Beanstandungen des Auftraggebers' }}</h2>
        <table class="pos-tabelle">
            <thead><tr><th>#</th><th>Beschreibung</th><th>Frist</th></tr></thead>
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

    <h2>5 · Übergabe &amp; Einweisung</h2>
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

    <h2>6 · Ort, Datum und Unterschriften</h2>
    <table class="kv">
        <tr><td class="k">Ort</td><td>{{ $protokoll->ort }}</td>
            <td class="k">Datum</td><td>{{ $protokoll->datum?->format('d.m.Y') }}</td></tr>
    </table>
    <div>
        <div class="sig-block">
            <img src="{{ $sigK }}" alt="">
            <div class="sig-line">Auftraggeber · {{ $projekt->kunde->anzeigename }}</div>
        </div>
        <div class="sig-block" style="margin-left:3%">
            <img src="{{ $sigM }}" alt="">
            <div class="sig-line">Auftragnehmer · {{ config('lea.monteur') }}</div>
        </div>
    </div>
</body>
</html>
