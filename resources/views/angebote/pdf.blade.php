<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php
    use App\Support\Format;
    $kunde = $angebot->kunde;
    $anschrift = trim(($kunde->strasse ?? ''), ', ');
    $ort = trim(($kunde->plz ?? '').' '.($kunde->stadt ?? ''));
    $gruss = match ($kunde->anrede ?? null) {
        'Herr' => 'Sehr geehrter Herr '.($kunde->nachname ?? $kunde->anzeigename).',',
        'Frau' => 'Sehr geehrte Frau '.($kunde->nachname ?? $kunde->anzeigename).',',
        'Familie' => 'Sehr geehrte Familie '.($kunde->nachname ?? '').',',
        default => 'Guten Tag '.$kunde->anzeigename.',',
    };
@endphp
<body>
    @include('partials.pdf-fuss')

    {{-- ═══ Seite 1 · Angebot ═══ --}}
    @include('partials.pdf-kopf', [
        'titel' => 'Angebot '.$angebot->nr,
        'badges' => array_filter([
            'Status: '.$angebot->status->label(),
            Format::datum($angebot->datum),
            $angebot->gueltig_bis ? 'Gültig bis '.$angebot->gueltig_bis->format('d.m.Y') : null,
        ]),
    ])

    <table class="box-paar" style="margin-top:6px"><tr>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Anschrift</div>
                <table class="kv">
                    <tr><td>{{ $kunde->anzeigename }}</td></tr>
                    @if ($anschrift !== '')<tr><td>{{ $anschrift }}</td></tr>@endif
                    @if ($ort !== '')<tr><td>{{ $ort }}</td></tr>@endif
                </table>
            </div>
        </td>
        <td class="spalte"></td>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Angebotsdaten</div>
                <table class="kv">
                    <tr><td class="k">Datum</td><td>{{ Format::datum($angebot->datum) }}</td></tr>
                    <tr><td class="k">Gültig bis</td><td>{{ $angebot->gueltig_bis?->format('d.m.Y') ?? '30 Tage ab Datum' }}</td></tr>
                    <tr><td class="k">Projekt</td><td>{{ $angebot->projekt?->nr ?? '–' }}</td></tr>
                    <tr><td class="k">Kontakt</td><td>{{ config('lea.telefon') }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <p style="margin:14px 0 4px">{{ $gruss }}</p>
    <p style="margin:0 0 10px">vielen Dank für Ihre Anfrage. Gerne unterbreiten wir Ihnen folgendes Angebot:</p>

    <table class="positions">
        <thead><tr><th style="width:30px">Pos</th><th>Beschreibung</th>
            <th class="num" style="width:50px">Menge</th><th class="num" style="width:74px">Einzelpreis</th>
            <th class="num" style="width:48px">Rabatt</th><th class="num" style="width:80px">Gesamt</th></tr></thead>
        <tbody>
        @foreach ($rechnung['positionen'] as $position)
            <tr>
                <td>{{ $position['pos'] }}</td>
                <td>
                    <b>{{ $position['titel'] }}</b>
                    @foreach ($position['details'] as $detail)
                        <div class="pos-detail">– {{ $detail }}</div>
                    @endforeach
                </td>
                <td class="num">{{ $position['menge'] }} {{ $position['einheit'] }}</td>
                <td class="num">{{ $position['einzelpreis'] !== null ? Format::eur($position['einzelpreis']) : '–' }}</td>
                <td class="num">{{ $position['rabatt'] > 0 ? rtrim(rtrim(number_format($position['rabatt'], 2, ',', '.'), '0'), ',').' %' : '–' }}</td>
                <td class="num">{{ $position['gesamt'] !== null ? Format::eur($position['gesamt']) : '–' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="summen" style="width:52%;margin-left:48%;margin-top:8px">
        <tr><td>Zwischensumme</td><td class="num">{{ Format::eur($rechnung['zwischensumme']) }}</td></tr>
        @if ($rechnung['rabattBetrag'] > 0)
            <tr><td>Rabatt {{ rtrim(rtrim(number_format($rechnung['rabattProzent'], 2, ',', '.'), '0'), ',') }} %</td>
                <td class="num">−{{ Format::eur($rechnung['rabattBetrag']) }}</td></tr>
        @endif
        <tr class="gesamt"><td>Gesamtbetrag (brutto)</td><td class="num">{{ Format::eur($rechnung['gesamt']) }}</td></tr>
        <tr><td style="font-size:8.5px;color:#6F7682">darin enthaltene MwSt. 19 %</td>
            <td class="num" style="font-size:8.5px;color:#6F7682">{{ Format::eur($rechnung['mwst']) }}</td></tr>
    </table>

    <div class="hinweis">
        Alle Preise verstehen sich <b>zuzüglich Montage</b> — Montage- und Lieferkosten werden,
        soweit nicht als eigene Position aufgeführt, gesondert berechnet. Die endgültige
        Ausführung erfolgt nach verbindlichem Aufmaß vor Ort — geringfügige Maßabweichungen
        bleiben vorbehalten. Zahlung gemäß Zahlungsplan 30&nbsp;/&nbsp;40&nbsp;/&nbsp;30
        (Anzahlung · Materiallieferung · Abnahme).
    </div>

    {{-- ═══ Seite 2 · Technische Ausführung ═══ --}}
    <div class="seitenumbruch"></div>
    <h2>Technische Ausführung</h2>
    @if ($kalk !== null)
        @php
            $p = $kalk['pcfg'];
            $mm = fn ($wert) => number_format((int) $wert, 0, ',', '.');
            $zeilen = array_filter([
                'Produkt' => ($p['product'] ?: 'Überdachung').' ('.($p['shape'] === 'trapez' ? 'Trapez' : 'Rechteck').')',
                'Montageart' => ($p['mounting'] ?? '') === 'freistehend' ? 'freistehend' : 'Wandmontage',
                'Breite' => 'ca. '.$mm($p['width']).' mm',
                'Tiefe' => 'ca. '.$mm($p['depth']).' mm',
                'Höhen' => 'hinten '.$mm($kalk['wallHEff']).' / vorn '.$mm($kalk['gutterHEff']).' mm',
                'Dachneigung' => number_format((float) $kalk['slopeEff'], 1, ',', '.').'°',
                'Profilfarbe' => $p['color'] ?? null,
                'Pfosten' => $kalk['pn'].' Stück · 110×110 mm',
                'Dacheindeckung' => trim(($p['covering'] ?? '').' '.($p['thickness'] ?? '').' '.($p['glasTrans'] ?? '')),
                'Verglasung' => $kalk['glasText'] !== '–' ? $kalk['fields'].' Felder à '.$kalk['glasText'] : null,
                'Beleuchtung' => $kalk['ledTot'] > 0 ? $kalk['ledTot'].' LED-Spots (dimmbar)' : null,
                'Entwässerung' => 'über Fallrohr am Pfosten '.((($p['drain']['post'] ?? '') === 'rechts') ? 'rechts' : 'links'),
            ], fn ($wert) => $wert !== null && trim((string) $wert) !== '');
        @endphp
        <table class="kv">
            @foreach (array_chunk($zeilen, 2, true) as $paar)
                <tr>
                    @foreach ($paar as $label => $wert)
                        <td class="k">{{ $label }}</td><td>{{ $wert }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>

        <h2>Profilaufteilung</h2>
        <p style="margin:0">Die Dachkonstruktion wird mit {{ $kalk['rafters'] }} Dachsparren in
            {{ $kalk['fields'] }} Feldern à {{ $kalk['sparText'] }} ausgeführt.
            @if (count($kalk['profilSegmente']) > 1)
                Rinnen- und Wandprofil werden aus {{ count($kalk['profilSegmente']) }} Segmenten
                ({{ implode(' + ', array_map(fn ($s) => number_format($s, 0, ',', '.'), $kalk['profilSegmente'])) }} mm)
                mit Stoßverbindern montiert.
            @endif
            @if ($kalk['unterzug']['gewaehlt'] ?? false)
                Zusätzlich {{ $kalk['unterzug']['anzahl'] ?? 1 }} Unterzug ({{ $kalk['unterzug']['groesse'] ?? '110×190 mm' }}).
            @endif
        </p>
    @else
        <p class="legal">Noch keine Konfiguration hinterlegt.</p>
    @endif

    <h2>Entwässerung</h2>
    <p style="margin:0">Die Entwässerung erfolgt über die integrierte Dachrinne mit innenliegendem
        Fallrohr im Pfosten (DN&nbsp;75) inklusive Laubfänger. Der Anschluss an die bauseitige
        Grundleitung ist nicht Bestandteil dieses Angebots.</p>

    {{-- ═══ Seite 3 · Vor der Montage zu beachten ═══ --}}
    <div class="seitenumbruch"></div>
    <h2>Vor der Montage zu beachten</h2>
    <p>Damit die Montage reibungslos ablaufen kann, bitten wir Sie, folgende Punkte
        vor dem Montagetermin sicherzustellen:</p>
    @foreach ([
        'Freie Zufahrt und Zugang zum Montagebereich (Materialtransport bis zur Terrasse möglich)',
        'Stromanschluss 230 V und Wasser sind auf der Baustelle verfügbar',
        'Parkmöglichkeit für das Montagefahrzeug in unmittelbarer Nähe',
        'Der Untergrund im Pfostenbereich ist tragfähig; Fundamente gemäß Absprache vorbereitet',
        'Der Montagebereich ist frei von Möbeln, Pflanzkübeln und sonstigen Gegenständen',
        'Bei Wandmontage: die Fassade ist im Anschlussbereich zugänglich (kein Gerüst durch den Kunden erforderlich)',
    ] as $punkt)
        <div class="check">☐ {{ $punkt }}</div>
    @endforeach

    <h2>Nicht im Leistungsumfang enthalten</h2>
    @foreach ([
        'Elektroinstallation und Endanschluss der Beleuchtung durch eine Elektrofachkraft',
        'Erd-, Pflaster- und Fundamentarbeiten, soweit nicht ausdrücklich angeboten',
        'Baugenehmigungen bzw. Bauanzeigen (obliegen dem Auftraggeber)',
        'Anschluss der Entwässerung an die Grundleitung',
    ] as $punkt)
        <div class="check">– {{ $punkt }}</div>
    @endforeach

    {{-- ═══ Seite 4 · Annahme / Auftragserteilung ═══ --}}
    <div class="seitenumbruch"></div>
    <h2>Annahme / Auftragserteilung</h2>
    <div class="check">☐ Ich nehme das Angebot {{ $angebot->nr }} vom {{ Format::datum($angebot->datum) }} verbindlich an.</div>
    <div class="check">☐ Die technische Ausführung (Seite 2) habe ich geprüft und bestätige die Maße.</div>
    <div class="check">☐ Die Hinweise zur Montagevorbereitung (Seite 3) habe ich zur Kenntnis genommen.</div>
    <div class="check">☐ Die Widerrufsbelehrung habe ich gelesen.</div>

    <table class="unterschriften"><tr>
        <td><div class="signatur-linie">Ort, Datum, Unterschrift Auftraggeber</div></td>
        <td class="spalte-leer"></td>
        <td><div class="signatur-linie">{{ config('lea.firma') }}</div></td>
    </tr></table>

    <div class="hinweis">
        <b>Online-Annahme:</b> Sie können dieses Angebot auch bequem online annehmen —
        öffnen Sie dazu den folgenden Link:<br>
        <span style="font-family:'DejaVu Sans Mono',monospace;font-size:8.5px">{{ $annahmeUrl }}</span>
    </div>

    <p class="legal">Dieses Angebot ist freibleibend und gilt
        @if ($angebot->gueltig_bis) bis zum {{ $angebot->gueltig_bis->format('d.m.Y') }} @else 30 Tage ab Ausstellungsdatum @endif.
        Die Annahme erfolgt durch Unterschrift oder über die Online-Annahme; mit Zugang der
        Annahmeerklärung kommt gemäß §§&nbsp;145&nbsp;ff. BGB ein verbindlicher Vertrag zustande.
        Es gelten unsere Allgemeinen Geschäftsbedingungen.</p>

    <p class="legal"><b>Widerrufsbelehrung (Verbraucher):</b> Wurde der Vertrag außerhalb von
        Geschäftsräumen oder im Fernabsatz geschlossen, steht Ihnen ein 14-tägiges Widerrufsrecht
        ab Vertragsschluss zu. Der Widerruf ist zu richten an: {{ config('lea.firma') }},
        {{ config('lea.anschrift') }}, {{ config('lea.email') }}. Einzelheiten entnehmen Sie
        bitte der beigefügten Widerrufsbelehrung.</p>
</body>
</html>
