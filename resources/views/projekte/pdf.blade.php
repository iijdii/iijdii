<!DOCTYPE html>
<html lang="de">
<head>@include('partials.pdf-stil')</head>
@php
    use App\Support\Format;
    $k = $kalk['pcfg'];
    $mm = fn ($wert) => number_format((int) $wert, 0, ',', '.');
    $drainSeite = ($k['drain']['post'] ?? '') === 'rechts' ? 'Pfosten rechts' : 'Pfosten links';
    $unterzug = $kalk['unterzug'] ?? [];
    $unterzugText = ($unterzug['gewaehlt'] ?? false)
        ? ($unterzug['anzahl'] ?? 1).' Stück · '.($unterzug['groesse'] ?? '110×190 mm')
        : 'ohne';
    $zahlen = fn (array $werte) => implode(' · ', array_map(fn ($x) => $mm($x), $werte));
    $posLabels = [
        'anzahl' => 'Anzahl', 'reihen' => 'Reihen', 'breite_mm' => 'Breite', 'hoehe_mm' => 'Höhe', 'h_links_mm' => 'Höhe links',
        'h_rechts_mm' => 'Höhe rechts', 'h_hinten_mm' => 'Höhe hinten', 'h_vorn_mm' => 'Höhe vorn', 'laenge_mm' => 'Länge',
        'ausfall_mm' => 'Ausfall', 'felder_n' => 'Felder', 'richtung' => 'Öffnungsrichtung', 'einbauort' => 'Einbauort', 'glas' => 'Verglasung',
        'seite' => 'Seite', 'material' => 'Material', 'transparenz' => 'Transparenz', 'modell' => 'Modell',
        'antrieb' => 'Antrieb', 'groesse' => 'Größe', 'farbe' => 'Farbe',
    ];
    $extras = $projekt->positionen->filter(fn ($p) => ! $p->produkt->istDach())->sortBy('pos');
@endphp
<style>
    .zeichnung { page-break-inside: avoid; border: 1px solid #D8DCE2; padding: 6px; margin-bottom: 10px; text-align: center; }
    .zeichnung-titel { text-align: left; font-size: 10px; font-weight: bold; color: #2B2E34; margin-bottom: 4px; }
    .extra { page-break-inside: avoid; border: 1px solid #D8DCE2; padding: 8px 10px; margin-bottom: 10px; }
    .extra-titel { font-weight: bold; font-size: 11px; color: #2B2E34; margin-bottom: 4px; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; }
    .warn { background: #fbf3e2; color: #7a5f21; padding: 5px 8px; margin: 6px 0 0; font-size: 9.5px; }
</style>
<body>
    @include('partials.pdf-fuss')
    @include('partials.pdf-kopf', [
        'titel' => 'Projektmappe '.$projekt->nr,
        'badges' => array_filter([$projekt->titel, 'Status: '.$projekt->status->label()]),
    ])

    <h2>Kunde &amp; Objekt</h2>
    <table class="box-paar"><tr>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Kunde</div>
                <table class="kv">
                    <tr><td class="k">Name</td><td>{{ $projekt->kunde->anzeigename }} ({{ $projekt->kunde->kunden_nr }})</td></tr>
                    <tr><td class="k">Telefon</td><td>{{ $projekt->kunde->telefon ?? '–' }}</td></tr>
                    <tr><td class="k">E-Mail</td><td>{{ $projekt->kunde->email ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
        <td class="spalte"></td>
        <td class="haelfte">
            <div class="box">
                <div class="box-titel">Objekt</div>
                <table class="kv">
                    <tr><td class="k">Montageort</td><td>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</td></tr>
                    <tr><td class="k">Angebot</td><td>{{ $projekt->angebot?->nr ?? '–' }}</td></tr>
                    <tr><td class="k">Montage</td><td>{{ Format::datumKurz($projekt->termin_von) }}–{{ Format::datumKurz($projekt->termin_bis) }}</td></tr>
                    <tr><td class="k">Projektleiter</td><td>{{ $projekt->projektleiter?->name ?? '–' }}</td></tr>
                </table>
            </div>
        </td>
    </tr></table>

    <h2>Technische Daten</h2>
    <table class="kv">
        <tr><td class="k">Maße</td><td>{{ $mm($k['width']) }} × {{ $mm($k['depth']) }} mm</td>
            <td class="k">Form</td><td>{{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }}</td></tr>
        <tr><td class="k">Höhen</td><td>hinten {{ $mm($kalk['wallHEff']) }} / vorn {{ $mm($kalk['gutterHEff']) }} mm</td>
            <td class="k">Neigung</td><td>{{ number_format((float) $kalk['slopeEff'], 1, ',', '.') }}° ({{ number_format((float) $kalk['gefaelleProzent'], 1, ',', '.') }} %)</td></tr>
        <tr><td class="k">Dach</td><td>{{ $k['covering'] }} {{ $k['thickness'] }}</td>
            <td class="k">Farbe</td><td>{{ $k['color'] }}</td></tr>
        <tr><td class="k">Konstruktion</td><td>{{ $kalk['pn'] }} Pfosten · {{ $kalk['rafters'] }} Sparren · {{ $kalk['fields'] }} Felder à {{ $kalk['sparText'] }}</td>
            <td class="k">Unterzug</td><td>{{ $unterzugText }}</td></tr>
        <tr><td class="k">Verglasung</td><td>{{ $kalk['glasText'] }}</td>
            <td class="k">Entwässerung</td><td>{{ $drainSeite }} · {{ $k['drain']['dir'] ?? '' }}</td></tr>
        <tr><td class="k">Sparrenabstand</td><td>{{ $kalk['sparText'] }}</td>
            <td class="k">Wandblende</td><td>{{ $kalk['blendeText'] }}</td></tr>
        <tr><td class="k">Pfosten-Positionen</td><td>{{ $kalk['postPositionen'] !== [] ? $zahlen($kalk['postPositionen']).' mm' : '–' }}</td>
            <td class="k">Profilsegmente</td><td>{{ count($kalk['profilSegmente']) > 1 ? implode(' + ', array_map($mm, $kalk['profilSegmente'])).' mm' : 'ein Stück' }}</td></tr>
        <tr><td class="k">LED</td><td>{{ $kalk['ledTot'] > 0 ? $kalk['ledTot'].' Spots · '.$k['led']['color'] : 'keine' }}</td>
            <td class="k">Wandanschluss</td><td>{{ ($k['mounting'] ?? '') === 'freistehend' ? 'freistehend' : ($k['wand']['belag'] ?? 'Putz').' · '.$k['duebel']['typ'].' '.$k['duebel']['size'] }}</td></tr>
        @if ($kalk['trapez'])
            <tr><td class="k">Trapez</td><td colspan="3">Wand {{ $mm($kalk['trapez']['wand']) }} mm · Rinne {{ $mm($kalk['trapez']['rinne']) }} mm · Offsets {{ $kalk['trapez']['offsetLinks'] }}/{{ $kalk['trapez']['offsetRechts'] }} mm · Winkel {{ $kalk['trapez']['winkelLinks'] }}°/{{ $kalk['trapez']['winkelRechts'] }}°</td></tr>
        @endif
    </table>
    @if ($kalk['stossPfosten'] !== [])
        <div class="warn">Profilstoß bei {{ $zahlen($kalk['stossPfosten']) }} mm — Pfosten unter dem Stoß empfohlen (Stoß − 55).</div>
    @endif
    @if ($kalk['unterzug']['erforderlich'] && ! ($kalk['unterzug']['gewaehlt'] ?? false))
        <div class="warn">Unterzug empfohlen: {{ implode(', ', $kalk['unterzug']['gruende']) }}.</div>
    @endif

    <h2>Materialliste mit Zuschnittmaßen</h2>
    <table class="positions">
        <thead><tr><th style="width:30px">Pos</th><th>Bezeichnung</th><th style="width:120px">Maß</th><th class="num" style="width:70px">Menge</th></tr></thead>
        <tbody>
        @foreach ($stueckliste as $i => $zeile)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $zeile['name'] }}</td>
                <td class="mono">
                    @if (isset($zeile['breite_mm']))
                        {{ $mm($zeile['breite_mm']) }} × {{ $mm($zeile['hoehe_mm']) }} mm
                    @elseif (isset($zeile['laenge_mm']))
                        L {{ $mm($zeile['laenge_mm']) }} mm
                    @else
                        –
                    @endif
                </td>
                <td class="num">{{ $zeile['menge'] }} {{ $zeile['einheit'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if ($kalk['fields'] > 0 && $kalk['glasB'] > 0)
        <h2>Verglasung</h2>
        <table><tr>
            <td style="width:72mm;text-align:center">
                <img src="{{ \App\Support\PdfSkizze::glas(\App\Support\GlasSkizze::position($kalk['glasB'], $kalk['glasT'], $kalk['glasT'], false)) }}" style="width:66mm" alt="Glasfeld">
            </td>
            <td>
                <b>{{ $k['covering'] }} {{ $k['thickness'] }} · {{ $k['glasTrans'] }}</b><br>
                {{ $kalk['fields'] }} Felder · Zuschnitt {{ $mm($kalk['glasB']) }} × {{ $mm($kalk['glasT']) }} mm
                @if ($kalk['polyRestPlatte'] !== null)
                    <br>Restfeld: {{ $mm($kalk['polyRestPlatte']) }} × {{ $mm($kalk['glasT']) }} mm
                @endif
                <br><span class="legal">{{ $kalk['glasText'] }}</span>
            </td>
        </tr></table>
    @endif

    @if ($extras->isNotEmpty())
        @foreach ($extras as $position)
            @php
                $f = ($position->endmasse ?? []) + ($position->felder ?? []);
                $wandPanels = $position->produkt === \App\Enums\ProjektProdukt::Wand
                    && (int) ($f['breite_mm'] ?? 0) > 0 && (int) ($f['h_links_mm'] ?? 0) > 0
                    ? \App\Support\SeitenwandRechner::raster((int) $f['breite_mm'], (int) $f['h_links_mm'],
                        (int) ($f['h_rechts_mm'] ?? $f['h_links_mm']), max(1, (int) ($f['anzahl'] ?? 1)), max(1, (int) ($f['reihen'] ?? 1)))
                    : [];
                $schiebeSkizze = $position->produkt === \App\Enums\ProjektProdukt::Schiebe
                    && (int) ($f['breite_mm'] ?? 0) > 0 && (int) ($f['hoehe_mm'] ?? 0) > 0
                    ? \App\Support\PdfSkizze::schiebe(\App\Support\GlasSkizze::schiebe((int) $f['breite_mm'], (int) $f['hoehe_mm'],
                        max(1, (int) ($f['anzahl'] ?? 1)), \App\Support\GlasSkizze::richtungSchluessel($f['richtung'] ?? null)))
                    : null;
            @endphp
            {{-- Überschrift klebt an der ersten Position (kein Waisen-Titel am Seitenende) --}}
            <div style="page-break-inside:avoid">
            @if ($loop->first)
                <h2>Extras &amp; Sonnenschutz</h2>
            @endif
            <div class="extra">
                <div class="extra-titel">Pos. {{ $position->pos }} · {{ $position->produkt->label() }}
                    <span class="legal">· Phase {{ $position->phase }}{{ $position->endmasse ? ' · Endmaße erfasst' : '' }}</span></div>
                <table><tr>
                    @if ($schiebeSkizze)
                        <td style="width:72mm;text-align:center"><img src="{{ $schiebeSkizze }}" style="width:68mm" alt="Skizze"></td>
                    @endif
                    <td>
                        <table class="kv">
                            @foreach ($f as $schluessel => $wert)
                                @continue(is_array($wert) || $wert === null || $wert === '' || ! isset($posLabels[$schluessel]))
                                <tr><td class="k">{{ $posLabels[$schluessel] }}</td>
                                    <td>{{ str_ends_with($schluessel, '_mm') && is_numeric($wert) ? $mm($wert).' mm' : $wert }}</td></tr>
                            @endforeach
                        </table>
                    </td>
                </tr></table>
                @if ($wandPanels !== [])
                    <table class="positions" style="margin-top:6px">
                        <thead><tr><th>Feld</th><th>Form</th><th class="num">Breite</th><th class="num">Höhe links</th><th class="num">Höhe rechts</th></tr></thead>
                        <tbody>
                        @foreach ($wandPanels as $panel)
                            <tr><td>{{ $panel['spalte'] }}.{{ $panel['reihe'] }}</td><td>{{ $panel['form'] }}</td>
                                <td class="num">{{ $mm($panel['breite']) }} mm</td><td class="num">{{ $mm($panel['hLinks']) }} mm</td><td class="num">{{ $mm($panel['hRechts']) }} mm</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="legal">Glaszuschnitt mit 30 mm Fuge</div>
                @endif
            </div>
            </div>
        @endforeach
    @endif

    <h2>Materialliste (Reservierungen)</h2>
    @if ($materialListe === [])
        <p class="legal">Keine Reservierungen erfasst.</p>
    @else
        <table class="positions">
            <thead><tr><th style="width:34px">Pos</th><th>Artikel</th><th>Art.-Nr.</th><th class="num">Menge</th><th>Status</th><th>Beleg</th></tr></thead>
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

    <h2 style="page-break-before:always">Technische Zeichnungen</h2>
    @foreach (\App\Support\RoofZeichnung::ANSICHTEN as $ansicht)
        <div class="zeichnung">
            <div class="zeichnung-titel">{{ \App\Support\RoofZeichnung::TITEL[$ansicht] }}</div>
            <img src="{{ \App\Support\PdfDachZeichnung::dataUri($roof[$ansicht]) }}" style="width:100%;max-height:105mm" alt="{{ \App\Support\RoofZeichnung::TITEL[$ansicht] }}">
        </div>
    @endforeach
</body>
</html>
