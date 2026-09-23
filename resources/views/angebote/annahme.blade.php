<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Angebot {{ $angebot->nr }} · {{ config('lea.firma') }}</title>
@php use App\Support\Format; @endphp
<style>
    /* Elektronische Angebotsansicht im Dokumentendesign (Spez. v3.2) —
       gleiche Optik wie das PDF, aber interaktiv (Checkboxen, Annahme). */
    :root {
        --anthracite: #2B2E34; --text: #171A1F; --muted: #6F7682;
        --line: #D8DCE2; --soft: #F4F6F8; --accent: #2f3e50;
        --ok: #1e7a3e; --warn: #c0392b;
    }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.45;
           color: var(--text); background: #e9edf4; margin: 0; padding: 18px 10px 90px; }
    .page { max-width: 210mm; margin: 0 auto 24px; padding: 14mm 16mm; background: #fff;
            box-shadow: 0 8px 28px rgba(16,24,40,.12); }
    @media (max-width: 640px) { .page { padding: 20px 14px; } }

    .kopf { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
            flex-wrap: wrap; margin-bottom: 12px; }
    .logo { font-size: 28px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
            color: var(--anthracite); }
    .logo .akzent { color: var(--accent); }
    .logo-untertitel { font-size: 9px; text-transform: uppercase; letter-spacing: .2em; color: var(--muted); }
    .firmen-daten { text-align: right; font-size: 10px; color: var(--muted); line-height: 1.35; }
    h1 { font-size: 21px; line-height: 1.15; color: var(--anthracite); margin: 12px 0 6px; }
    .badge { display: inline-block; border: 1px solid var(--line); background: var(--soft);
             padding: 5px 9px; font-size: 10px; font-weight: 700; color: var(--anthracite);
             text-transform: uppercase; letter-spacing: .06em; margin: 0 4px 4px 0; }
    h2 { font-size: 14px; color: var(--anthracite); border-bottom: 1px solid var(--line);
         padding-bottom: 5px; margin: 22px 0 10px; }
    .box { border: 1px solid var(--line); background: var(--soft); padding: 10px; }
    .box-titel { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
                 color: var(--anthracite); margin-bottom: 5px; }
    .box-paar { display: flex; gap: 10px; flex-wrap: wrap; }
    .box-paar .box { flex: 1 1 240px; }
    .kv { width: 100%; border-collapse: collapse; font-size: 12px; }
    .kv td { padding: 2px 6px 2px 0; vertical-align: top; }
    .kv td.k { width: 110px; color: var(--muted); }

    .tabelle-scroll { overflow-x: auto; }
    .positions { width: 100%; border-collapse: collapse; min-width: 520px; }
    .positions th { background: var(--anthracite); color: #fff; font-size: 10px; text-align: left;
                    padding: 7px 6px; font-weight: 700; }
    .positions td { border-bottom: 1px solid var(--line); padding: 6px; font-size: 12px; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .pos-detail { font-size: 10.5px; color: var(--muted); }
    .summen { width: 100%; max-width: 330px; margin-left: auto; border-collapse: collapse; margin-top: 8px; }
    .summen td { padding: 4px 6px; font-size: 12.5px; }
    .summen .gesamt td { border-top: 1.5px solid var(--anthracite); font-weight: 700; font-size: 14px; }
    .hinweis { border: 1px solid var(--line); background: var(--soft); padding: 9px 11px;
               font-size: 11.5px; margin-top: 12px; }
    .check { margin: 6px 0; font-size: 12.5px; }
    label.check { display: flex; gap: 8px; align-items: flex-start; cursor: pointer; }
    label.check input { width: 17px; height: 17px; margin-top: 1px; flex: none; }

    .status-box { border: 1px solid var(--line); padding: 12px 14px; margin-top: 14px; }
    .status-box.ok { border-color: var(--ok); background: #eef8f0; color: var(--ok); }
    .status-box.warn { border-color: var(--warn); background: #fdf1ef; color: var(--warn); }
    .status-box b { display: block; margin-bottom: 3px; }

    textarea { width: 100%; border: 1px solid var(--line); padding: 9px; font: inherit;
               min-height: 84px; resize: vertical; }
    .aktionen { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    button { font: inherit; font-weight: 700; padding: 12px 18px; border: 1px solid var(--line);
             background: #fff; color: var(--anthracite); cursor: pointer; }
    button.gruen { background: var(--ok); border-color: var(--ok); color: #fff; flex: 1 1 220px; }
    button.rot { color: var(--warn); border-color: var(--warn); }
    .fuss { border-top: 1px solid var(--anthracite); margin-top: 26px; padding-top: 8px;
            display: flex; gap: 12px; flex-wrap: wrap; justify-content: space-between;
            font-size: 9px; color: var(--muted); }
    @media print {
        body { background: #fff; padding: 0; }
        .page { box-shadow: none; margin: 0 auto; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .no-print, button, textarea { display: none !important; }
    }
</style>
</head>
<body>

@php
    $fussblock = '<div class="fuss"><span><b>'.e(config('lea.firma')).'</b> · '.e(config('lea.anschrift'))
        .'</span><span>Geschäftsführung: '.e(config('lea.geschaeftsfuehrer')).' · '.e(config('lea.register'))
        .'</span><span>'.e(config('lea.telefon')).' · '.e(config('lea.email')).'</span></div>';
    $angenommen = $angebot->status === \App\Enums\AngebotStatus::Angenommen;
@endphp

{{-- ═══ Seite 1 · Angebot ═══ --}}
<section class="page">
    <header class="kopf">
        <div>
            <div class="logo">LEA<span class="akzent">.</span></div>
            <div class="logo-untertitel">Terrassendach · Montage · Service</div>
        </div>
        <div class="firmen-daten">
            {{ config('lea.firma') }}<br>{{ config('lea.anschrift') }}<br>
            {{ config('lea.telefon') }} · {{ config('lea.email') }}<br>
            USt-IdNr. {{ config('lea.ustid') }}
        </div>
    </header>

    <h1>Angebot {{ $angebot->nr }}</h1>
    <span class="badge">Status: {{ $angebot->status->label() }}</span>
    <span class="badge">{{ Format::datum($angebot->datum) }}</span>
    @if ($angebot->gueltig_bis)<span class="badge">Gültig bis {{ $angebot->gueltig_bis->format('d.m.Y') }}</span>@endif

    <div class="box-paar" style="margin-top:10px">
        <div class="box">
            <div class="box-titel">Anschrift</div>
            <table class="kv">
                <tr><td>{{ $angebot->kunde->anzeigename }}</td></tr>
                @if (trim((string) $angebot->kunde->strasse) !== '')<tr><td>{{ $angebot->kunde->strasse }}</td></tr>@endif
                @if (trim(($angebot->kunde->plz ?? '').' '.($angebot->kunde->stadt ?? '')) !== '')
                    <tr><td>{{ trim(($angebot->kunde->plz ?? '').' '.($angebot->kunde->stadt ?? '')) }}</td></tr>
                @endif
            </table>
        </div>
        <div class="box">
            <div class="box-titel">Angebotsdaten</div>
            <table class="kv">
                <tr><td class="k">Datum</td><td>{{ Format::datum($angebot->datum) }}</td></tr>
                <tr><td class="k">Gültig bis</td><td>{{ $angebot->gueltig_bis?->format('d.m.Y') ?? '30 Tage ab Datum' }}</td></tr>
                <tr><td class="k">Kontakt</td><td>{{ config('lea.telefon') }}</td></tr>
            </table>
        </div>
    </div>

    <h2>Positionen</h2>
    <div class="tabelle-scroll">
        <table class="positions">
            <thead><tr><th style="width:30px">Pos</th><th>Beschreibung</th><th class="num">Menge</th>
                <th class="num">Einzelpreis</th><th class="num">Rabatt</th><th class="num">Gesamt</th></tr></thead>
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
    </div>
    <table class="summen">
        <tr><td>Zwischensumme</td><td class="num">{{ Format::eur($rechnung['zwischensumme']) }}</td></tr>
        @if ($rechnung['rabattBetrag'] > 0)
            <tr><td>Rabatt {{ rtrim(rtrim(number_format($rechnung['rabattProzent'], 2, ',', '.'), '0'), ',') }} %</td>
                <td class="num">−{{ Format::eur($rechnung['rabattBetrag']) }}</td></tr>
        @endif
        <tr class="gesamt"><td>Gesamtbetrag (brutto)</td><td class="num">{{ Format::eur($rechnung['gesamt']) }}</td></tr>
        <tr><td style="font-size:10px;color:var(--muted)">darin enthaltene MwSt. 19 %</td>
            <td class="num" style="font-size:10px;color:var(--muted)">{{ Format::eur($rechnung['mwst']) }}</td></tr>
    </table>

    <div class="hinweis">
        Alle Preise verstehen sich <b>zuzüglich Montage</b> — Montage- und Lieferkosten werden, soweit
        nicht als eigene Position aufgeführt, gesondert berechnet. Die endgültige Ausführung erfolgt
        nach verbindlichem Aufmaß vor Ort. Zahlung gemäß Zahlungsplan 30 / 40 / 30
        (Anzahlung · Materiallieferung · Abnahme).
    </div>
    {!! $fussblock !!}
</section>

{{-- ═══ Seite 2 · Technische Ausführung ═══ --}}
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
    <section class="page">
        <h2 style="margin-top:0">Technische Ausführung</h2>
        <table class="kv">
            @foreach ($zeilen as $label => $wert)
                <tr><td class="k">{{ $label }}</td><td>{{ $wert }}</td></tr>
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
        <h2>Entwässerung</h2>
        <p style="margin:0">Die Entwässerung erfolgt über die integrierte Dachrinne mit innenliegendem
            Fallrohr im Pfosten (DN 75) inklusive Laubfänger. Der Anschluss an die bauseitige
            Grundleitung ist nicht Bestandteil dieses Angebots.</p>
        {!! $fussblock !!}
    </section>
@endif

{{-- ═══ Seite 3 · Annahme / Entscheidung ═══ --}}
<section class="page" id="entscheidung">
    <h2 style="margin-top:0">Vor der Montage zu beachten</h2>
    @foreach ([
        'Freie Zufahrt und Zugang zum Montagebereich (Materialtransport bis zur Terrasse möglich)',
        'Stromanschluss 230 V und Wasser sind auf der Baustelle verfügbar',
        'Parkmöglichkeit für das Montagefahrzeug in unmittelbarer Nähe',
        'Der Untergrund im Pfostenbereich ist tragfähig; Fundamente gemäß Absprache vorbereitet',
        'Der Montagebereich ist frei von Möbeln, Pflanzkübeln und sonstigen Gegenständen',
    ] as $punkt)
        <div class="check">☐ {{ $punkt }}</div>
    @endforeach

    <h2>Annahme / Auftragserteilung</h2>

    @if (session('annahme_info'))
        <div class="status-box ok"><b>Rückmeldung übermittelt</b>{{ session('annahme_info') }}</div>
    @endif
    @if (session('annahme_fehler'))
        <div class="status-box warn"><b>Hinweis</b>{{ session('annahme_fehler') }}</div>
    @endif

    @if ($angenommen)
        <div class="status-box ok">
            <b>Angebot angenommen</b>
            Vielen Dank! Das Angebot wurde
            @if ($angebot->angenommen_am) am {{ $angebot->angenommen_am->format('d.m.Y H:i') }} @endif
            verbindlich angenommen. Wir melden uns zur Terminabstimmung.
        </div>
    @elseif ($angebot->status === \App\Enums\AngebotStatus::Abgelehnt)
        <div class="status-box warn">
            <b>Angebot abgelehnt</b>
            Sie haben dieses Angebot abgelehnt. Bei Fragen erreichen Sie uns unter {{ config('lea.telefon') }}.
        </div>
    @elseif ($abgelaufen)
        <div class="status-box warn">
            <b>Angebot abgelaufen</b>
            Die Gültigkeit dieses Angebots ist abgelaufen. Bitte kontaktieren Sie uns unter
            {{ config('lea.telefon') }} für ein aktualisiertes Angebot.
        </div>
    @elseif ((float) $angebot->summe <= 0)
        <div class="status-box warn">
            <b>In Bearbeitung</b>
            Dieses Angebot ist noch in Bearbeitung und kann noch nicht online angenommen werden.
        </div>
    @else
        <form method="POST" action="{{ route('angebote.annahme.bestaetigen', $angebot->accept_token) }}">
            @csrf
            @foreach ([
                'angebot' => 'Ich nehme das Angebot '.$angebot->nr.' vom '.Format::datum($angebot->datum).' verbindlich an.',
                'technik' => 'Die technische Ausführung habe ich geprüft und bestätige die Maße.',
                'montage' => 'Die Hinweise zur Montagevorbereitung habe ich zur Kenntnis genommen.',
                'widerruf' => 'Die Widerrufsbelehrung (unten) habe ich gelesen.',
            ] as $wert => $text)
                <label class="check"><input type="checkbox" name="bestaetigung[]" value="{{ $wert }}"> <span>{{ $text }}</span></label>
            @endforeach

            <div style="margin-top:14px">
                <label for="kommentar" style="font-size:12px;color:var(--muted)">Kommentar / Änderungswünsche (optional — für
                    «Zur Überarbeitung» bitte kurz beschreiben, was angepasst werden soll):</label>
                <textarea id="kommentar" name="kommentar" maxlength="2000"
                          placeholder="z. B. Bitte Tiefe auf 3,5 m ändern, LED weglassen …">{{ old('kommentar') }}</textarea>
            </div>

            <div class="aktionen">
                <button class="gruen" type="submit" name="aktion" value="annehmen">✓ Angebot verbindlich annehmen</button>
                <button type="submit" name="aktion" value="ueberarbeitung">Zur Überarbeitung senden</button>
                <button class="rot" type="submit" name="aktion" value="ablehnen"
                        onclick="return confirm('Angebot wirklich ablehnen?')">Ablehnen</button>
            </div>
            <p style="font-size:10.5px;color:var(--muted);margin-top:10px">Mit «Angebot verbindlich annehmen»
                kommt gemäß §§ 145 ff. BGB ein verbindlicher Vertrag zustande. Die endgültige Ausführung
                erfolgt nach Aufmaß vor Ort.</p>
        </form>
    @endif

    <h2>Widerrufsbelehrung (Verbraucher)</h2>
    <p style="font-size:10.5px;color:var(--muted);margin:0">Wurde der Vertrag außerhalb von Geschäftsräumen
        oder im Fernabsatz geschlossen, steht Ihnen ein 14-tägiges Widerrufsrecht ab Vertragsschluss zu.
        Der Widerruf ist zu richten an: {{ config('lea.firma') }}, {{ config('lea.anschrift') }},
        {{ config('lea.email') }}.</p>
    {!! $fussblock !!}
</section>

</body>
</html>
