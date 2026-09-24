<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil', ['palette' => 'blau'])
<style>
    /* Karten-Design nach Vorbild des Produktionsauftrags (Screenshot-Referenz):
       runde Karten mit hellem Kopf, KV-Zeilen grau/fett, dunkle Tabellenköpfe. */
    .kopfzeile { width: 100%; border-collapse: collapse; }
    .doktitel { font-size: 23px; font-weight: bold; color: #243447; letter-spacing: 1px; text-align: right; }
    .dokuntertitel { font-size: 9.5px; color: #64748b; margin-top: 2px; }
    .strich { border-bottom: 3px solid #243447; margin: 10px 0 14px; }
    .pille { display: inline-block; border-radius: 9px; padding: 2px 9px; font-size: 8.5px; font-weight: bold; margin-left: 4px; }
    .pille-gelb { background: #fdf6dd; color: #8a6d1a; border: 1px solid #ead9a0; }
    .pille-gruen { background: #e8f5e9; color: #2e7d32; border: 1px solid #bfe3c2; }
    .pille-grau { background: #eef2f6; color: #47586b; border: 1px solid #d7dfe8; }
    .karte { border: 1px solid #dbe2ea; border-radius: 8px; margin-bottom: 12px; page-break-inside: avoid; }
    .karte-teilbar { page-break-inside: auto; }
    .karte-kopf { background: #f1f5f9; border-bottom: 1px solid #dbe2ea; border-radius: 7px 7px 0 0;
                  padding: 7px 12px; font-weight: bold; font-size: 10px; letter-spacing: 1.2px;
                  text-transform: uppercase; color: #243447; }
    .karte-korp { padding: 10px 12px; }
    .kvz { width: 100%; border-collapse: collapse; }
    .kvz td { padding: 3px 0; vertical-align: top; font-size: 10.5px; }
    .kvz .k { color: #64748b; width: 40%; padding-right: 8px; }
    .kvz .w { font-weight: bold; color: #1f2937; }
    .paar { width: 100%; border-collapse: separate; border-spacing: 0; }
    .paar > tr > td, .paar td.h { vertical-align: top; }
    .pos-box { border: 1px solid #dbe2ea; border-radius: 8px; padding: 10px 12px; margin: 4px 0 10px; page-break-inside: avoid; }
    .pos-titel { font-weight: bold; font-size: 11.5px; color: #243447; margin-bottom: 4px; }
    .skiztab { width: 100%; border-collapse: collapse; }
    .skiztab th { background: #243447; color: #ffffff; font-size: 9.5px; letter-spacing: 1px;
                  text-transform: uppercase; text-align: left; padding: 6px 10px; }
    .skiztab td { border-bottom: 1px solid #e2e8f0; padding: 8px 10px; vertical-align: middle; font-size: 10.5px; }
    .skiztab tr { page-break-inside: avoid; }
    .spez { background: #eef4fb; border-left: 3px solid #2f6bb0; border-radius: 3px; padding: 7px 10px; margin-top: 7px; font-size: 10px; }
    .spez-t { color: #2f6bb0; font-weight: bold; margin-bottom: 3px; }
    .checkbox { display: inline-block; width: 9px; height: 9px; border: 1.4px solid #47586b; border-radius: 2px;
                margin-right: 6px; vertical-align: -1px; }
    .checkkasten { display: inline-block; border: 1px solid #dbe2ea; border-radius: 6px; padding: 8px 12px; margin: 2px 8px 2px 0; font-size: 10.5px; color: #1f2937; }
    .sigtab { width: 100%; border-collapse: collapse; margin-top: 30px; page-break-inside: avoid; }
    .sigtab td { width: 46%; border-top: 1.4px solid #243447; padding-top: 5px; font-size: 9.5px; color: #47586b; }
    .sigtab td.zw { width: 8%; border-top: none; }
</style>
</head>
@php
    use App\Support\Format;
    use App\Support\PdfSkizze;

    // Zusammenfassung: Stückzahl + Glasfläche (Trapez: mittlere Höhe),
    // Gewicht über Glasstärke (2,5 kg/m² je mm), wo die Stärke im
    // Glas-Text steht.
    $gesamtStueck = 0; $gesamtFlaeche = 0.0; $gesamtGewicht = 0.0;
    foreach ($glasPositionen as $g) {
        $d = $g['position']->details ?? [];
        $hL = (int) ($d['hL'] ?? $g['position']->hoehe_mm); $hR = (int) ($d['hR'] ?? $hL);
        $menge = (float) $g['position']->menge;
        $flaeche = ((int) $g['position']->breite_mm / 1000) * ((($hL + $hR) / 2) / 1000) * $menge;
        $gesamtStueck += (int) $menge; $gesamtFlaeche += $flaeche;
        if (preg_match('/(\d+)\s*mm/', (string) $g['glas'], $m)) { $gesamtGewicht += $flaeche * 2.5 * (int) $m[1]; }
    }
    foreach ($schiebePositionen as $s) {
        $flaeche = ((int) $s['position']->breite_mm / 1000) * ((int) $s['position']->hoehe_mm / 1000);
        $gesamtStueck += $s['anzahl']; $gesamtFlaeche += $flaeche;
        if (preg_match('/VSG\s*(\d+)|(\d+)\s*mm/', (string) $s['glas'], $m)) { $gesamtGewicht += $flaeche * 2.5 * (int) ($m[1] !== '' ? $m[1] : $m[2]); }
    }
    $logoPfad = public_path('images/logo-lea.png');
@endphp
<body>
    @include('partials.pdf-fuss')

    <table class="kopfzeile"><tr>
        <td style="width:50%">
            @if (is_file($logoPfad))
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPfad)) }}" style="width:27mm" alt="LEA Überdachung">
            @else
                <div class="logo">LEA<span class="akzent">.</span></div>
            @endif
            <div class="logo-untertitel">Terrassendach · Montage · Service</div>
        </td>
        <td style="width:50%;vertical-align:top">
            <div class="doktitel">BESTELLUNG</div>
            <div style="text-align:right;margin-top:5px">
                <span class="pille pille-gruen">{{ $bestellung->nr }}</span>
                <span class="pille pille-gelb">{{ $bestellung->status->label() }}</span>
                @if ($bestellung->liefertermin)
                    <span class="pille pille-grau">Liefertermin {{ Format::datum($bestellung->liefertermin) }}</span>
                @endif
            </div>
            <div class="dokuntertitel" style="text-align:right">{{ $bestellung->kategorieLabel() }} · Produktionsauftrag / Bestellung beim Lieferanten</div>
        </td>
    </tr></table>
    <div class="strich"></div>

    <table class="paar"><tr>
        <td style="width:48.5%">
            <div class="karte">
                <div class="karte-kopf">Auftragsdaten</div>
                <div class="karte-korp"><table class="kvz">
                    <tr><td class="k">Bestell-Nr.</td><td class="w">{{ $bestellung->nr }}</td></tr>
                    <tr><td class="k">Projekt / Kunde</td><td class="w">{{ $bestellung->kunde?->anzeigename ?? '–' }}</td></tr>
                    <tr><td class="k">CRM Projekt-ID</td><td class="w">{{ $bestellung->projekt?->nr ?? '–' }}</td></tr>
                    <tr><td class="k">Besteller</td><td class="w">{{ config('lea.firma') }}, {{ config('lea.anschrift') }}</td></tr>
                    <tr><td class="k">Datum</td><td class="w">{{ Format::datum($bestellung->created_at) }}</td></tr>
                    <tr><td class="k">Gewünschter Termin</td><td class="w">{{ $bestellung->liefertermin ? Format::datum($bestellung->liefertermin) : '–' }}</td></tr>
                </table></div>
            </div>
        </td>
        <td style="width:3%"></td>
        <td style="width:48.5%">
            <div class="karte">
                <div class="karte-kopf">Produktion / Lieferant</div>
                <div class="karte-korp"><table class="kvz">
                    <tr><td class="k">Lieferant</td><td class="w">{{ $bestellung->lieferant?->name ?? '–' }}</td></tr>
                    <tr><td class="k">Ansprechpartner</td><td class="w">{{ $bestellung->lieferant?->ansprechpartner ?? '–' }}</td></tr>
                    <tr><td class="k">E-Mail</td><td class="w">{{ $bestellung->lieferant?->email ?? '–' }}</td></tr>
                    <tr><td class="k">Telefon</td><td class="w">{{ $bestellung->lieferant?->telefon ?? '–' }}</td></tr>
                    <tr><td class="k">Adresse</td><td class="w">{{ trim(($bestellung->lieferant?->strasse ?? '').', '.($bestellung->lieferant?->plz ?? '').' '.($bestellung->lieferant?->stadt ?? ''), ', ') ?: '–' }}</td></tr>
                </table></div>
            </div>
        </td>
    </tr></table>

    @if ($glasPositionen->isNotEmpty())
        <div class="karte karte-teilbar">
            <div class="karte-kopf">Dachglas – Einzelskizzen je Position</div>
            <table class="skiztab">
                <thead><tr><th style="width:9mm">Pos.</th><th style="width:72mm">Skizze</th><th>Maße / Hinweise</th></tr></thead>
                <tbody>
                @foreach ($glasPositionen as $g)
                    @php
                        $d = $g['position']->details ?? [];
                        $hL = (int) ($d['hL'] ?? $g['position']->hoehe_mm); $hR = (int) ($d['hR'] ?? $hL);
                        $trapez = $g['form'] === 'Trapez';
                    @endphp
                    <tr>
                        <td class="w" style="font-weight:bold">{{ $g['nr'] }}</td>
                        <td style="text-align:center"><img src="{{ PdfSkizze::glas($g['skizze']) }}" style="width:68mm" alt="Skizze Position {{ $g['nr'] }}"></td>
                        <td>
                            <div style="font-weight:bold;color:#243447">{{ $g['position']->bezeichnung }}</div>
                            {{ $g['form'] }} · Typ: Dachglas<br>
                            Breite: <b>{{ number_format((int) $g['position']->breite_mm, 0, ',', '.') }} mm</b><br>
                            @if ($trapez)
                                Höhe links: <b>{{ number_format($hL, 0, ',', '.') }} mm</b> · rechts: <b>{{ number_format($hR, 0, ',', '.') }} mm</b><br>
                                Rohmaß: {{ number_format((int) $g['position']->breite_mm, 0, ',', '.') }} × {{ number_format(max($hL, $hR), 0, ',', '.') }} mm<br>
                            @else
                                Höhe: <b>{{ number_format($hL, 0, ',', '.') }} mm</b><br>
                            @endif
                            <span style="color:#64748b">Anzahl: {{ Format::menge($g['position']->menge) }} {{ $g['position']->einheit }} · Maße {{ $g['quelle'] === 'live' ? 'aus Projekt' : 'manuell erfasst' }}</span>
                            <div class="spez">
                                <div class="spez-t">Glas-Spezifikation</div>
                                Glasart: <b>{{ $g['glas'] }}</b><br>
                                Form: <b>{{ $g['form'] }}</b>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($schiebePositionen->isNotEmpty())
        <div class="karte">
            <div class="karte-kopf">Schiebesystem(e)</div>
            <div class="karte-korp">
                @foreach ($schiebePositionen as $s)
                    <div class="pos-box">
                        <div class="pos-titel">Pos. {{ $s['nr'] }} · {{ $s['position']->bezeichnung }}</div>
                        <div style="text-align:center"><img src="{{ PdfSkizze::schiebe($s['skizze']) }}" style="width:96mm" alt="Skizze {{ $s['position']->bezeichnung }}"></div>
                        <table class="kvz"><tr>
                            <td style="width:50%;padding-right:10px"><table class="kvz">
                                <tr><td class="k">Öffnung Breite</td><td class="w">{{ number_format((int) $s['position']->breite_mm, 0, ',', '.') }} mm</td></tr>
                                <tr><td class="k">Anzahl Elemente</td><td class="w">{{ $s['anzahl'] }}</td></tr>
                                <tr><td class="k">Glas</td><td class="w">{{ $s['glas'] }}</td></tr>
                            </table></td>
                            <td style="width:50%"><table class="kvz">
                                <tr><td class="k">Öffnung Höhe</td><td class="w">{{ number_format((int) $s['position']->hoehe_mm, 0, ',', '.') }} mm</td></tr>
                                <tr><td class="k">Öffnungsrichtung</td><td class="w">{{ $s['richtung'] }}</td></tr>
                                <tr><td class="k">Maße</td><td class="w">{{ $s['quelle'] === 'live' ? 'aus Projekt' : 'manuell erfasst' }}</td></tr>
                            </table></td>
                        </tr></table>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($materialPositionen->isNotEmpty())
        <div class="karte karte-teilbar">
            <div class="karte-kopf">Material-Positionen</div>
            <table class="skiztab">
                <thead><tr><th>Bezeichnung</th><th style="width:32mm">Art.-Nr.</th><th style="width:28mm;text-align:right">Menge</th></tr></thead>
                <tbody>
                @foreach ($materialPositionen as $position)
                    <tr>
                        <td>{{ $position->bezeichnung }}</td>
                        <td>{{ $position->artikel?->art_nr ?? '–' }}</td>
                        <td style="text-align:right">{{ Format::menge($position->menge) }} {{ $position->einheit }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <table class="paar"><tr>
        <td style="width:48.5%">
            <div class="karte">
                <div class="karte-kopf">Kontrolle vor Versand</div>
                <div class="karte-korp">
                    <span class="checkkasten"><span class="checkbox"></span>Maße geprüft</span>
                    <span class="checkkasten"><span class="checkbox"></span>Skizzen geprüft</span>
                    <span class="checkkasten"><span class="checkbox"></span>Glas-Spezifikation geprüft</span>
                </div>
            </div>
        </td>
        <td style="width:3%"></td>
        <td style="width:48.5%">
            <div class="karte">
                <div class="karte-kopf">Zusammenfassung</div>
                <div class="karte-korp"><table class="kvz">
                    <tr><td class="k">Gesamtanzahl</td><td class="w">{{ $gesamtStueck }} Stück</td></tr>
                    <tr><td class="k">Gesamtfläche</td><td class="w">{{ number_format($gesamtFlaeche, 1, ',', '.') }} m²</td></tr>
                    <tr><td class="k">Gewicht ca.</td><td class="w">{{ $gesamtGewicht > 0 ? number_format($gesamtGewicht, 0, ',', '.').' kg' : '–' }}</td></tr>
                    <tr><td class="k">Bearbeiter</td><td class="w">{{ $bestellung->ersteller?->name ?? '–' }}</td></tr>
                </table></div>
            </div>
        </td>
    </tr></table>

    @if ($bestellung->notizen)
        <div class="karte">
            <div class="karte-kopf">Hinweise</div>
            <div class="karte-korp">{{ $bestellung->notizen }}</div>
        </div>
    @endif

    <table class="sigtab"><tr>
        <td>Datum / Bearbeiter LEA</td>
        <td class="zw"></td>
        <td>Bestätigung Produktion / Lieferant</td>
    </tr></table>
</body>
</html>
