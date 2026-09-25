{{-- Bestellblatt Varisol (T200 / F513) je Markisen-Position — Aufbau wie
     die Original-Bestellblätter des Betreibers, Kästchen ■ = gewählt.
     Erwartet: $bestellung, $position (details.art = markise), $neueSeite. --}}
@php
    use App\Support\Format;
    use App\Support\MarkisenFormular;
    use App\Support\MarkisenSkizze;
    $f = $position->details['formular'] ?? [];
    $modell = MarkisenFormular::modell($f);
    $w = fn (string $feld) => $f[$feld] ?? null;
    $zahl = fn ($wert) => $wert !== null && $wert !== '' ? number_format((int) $wert, 0, ',', '.') : '';
    $lieferant = $bestellung->lieferant;
    $kw = $bestellung->liefertermin ? 'KW '.$bestellung->liefertermin->isoWeek().' / '.$bestellung->liefertermin->isoWeekYear() : '';
    $skizze = $modell === 'F513'
        ? MarkisenSkizze::f513Kabelabgang((string) $w('kabelabgang'))
        : MarkisenSkizze::t200(($w('typ') ?? '') === 'Typ B (gekoppelt)', (int) $w('breite_mm') ?: null,
            (int) $w('b1_mm') ?: null, (int) $w('b2_mm') ?: null, (int) $w('ausfall_mm') ?: null);
@endphp
<div style="{{ $neueSeite ? 'page-break-before:always;' : '' }}">
    <table style="width:100%;border-collapse:collapse">
        <tr>
            <td style="width:62%;vertical-align:top;padding-right:12px">
                <table class="vb-kopf">
                    <tr><td class="k">Fachhändler</td><td class="w"><b>{{ config('lea.firma') }}</b></td></tr>
                    <tr><td class="k">Kundennr.</td><td class="w"><b>{{ $lieferant?->kundennummer ?? '–' }}</b></td></tr>
                    <tr><td class="k">Bestelldatum</td><td class="w">{{ Format::datum($bestellung->created_at) }}</td></tr>
                    <tr><td class="k">Kommission</td><td class="w">{{ $bestellung->kunde?->nachname ?: ($bestellung->kunde?->anzeigename ?? '–') }}
                        @if (! empty($position->details['projekt_pos'])) · Pos. {{ $position->details['projekt_pos'] }}@endif</td></tr>
                    <tr><td class="k">Terminwunsch (KW)</td><td class="w">{{ $kw }}</td></tr>
                    <tr><td class="k">Ansprechpartner</td><td class="w">{{ $bestellung->ersteller?->name ?? '' }}</td></tr>
                    <tr><td class="k">Unterschrift</td><td class="w" style="height:18px"></td></tr>
                </table>
            </td>
            <td style="width:38%;vertical-align:top;font-size:10px;line-height:1.5">
                <b style="font-size:11px">{{ $lieferant?->name ?? 'Lieferant' }}</b><br>
                {{ $lieferant?->strasse }}<br>
                {{ trim(($lieferant?->plz ?? '').' '.($lieferant?->stadt ?? '')) }}<br>
                @if ($lieferant?->telefon)Telefon {{ $lieferant->telefon }}<br>@endif
                <b>{{ $lieferant?->email }}</b>
            </td>
        </tr>
    </table>

    <table class="vb-titel"><tr>
        <td>{{ $modell === 'F513' ? 'Bestellung für ' : '' }}{{ MarkisenFormular::MODELLE[$modell] }}</td>
        <td style="text-align:right;width:30mm">{{ Format::menge($position->menge) }} Stück</td>
    </tr></table>

    <table style="width:100%;border-collapse:collapse;margin-bottom:6px"><tr>
        <td style="width:56%;vertical-align:top">
            <table class="vb">
                <tr><td class="k">{{ $modell === 'F513' ? 'Breite' : 'Bestellmaß B / Achsmaß' }}</td>
                    <td><span class="vb-feld">{{ $zahl($w('breite_mm')) }}</span> mm</td></tr>
                @if ($modell === 'T200')
                    <tr><td class="k">Ausführung</td><td>
                        @foreach (['Typ A', 'Typ B (gekoppelt)'] as $o)<span class="vb-opt"><span class="bx {{ $w('typ') === $o || ($o === 'Typ A' && ! $w('typ')) ? 'on' : '' }}"></span>{{ $o }}</span>@endforeach
                    </td></tr>
                    <tr><td class="k">Aufteilung Typ B</td><td>B1: <span class="vb-feld">{{ $zahl($w('b1_mm')) }}</span> mm &nbsp; B2: <span class="vb-feld">{{ $zahl($w('b2_mm')) }}</span> mm</td></tr>
                @endif
                <tr><td class="k">Ausfall</td><td><span class="vb-feld">{{ $zahl($w('ausfall_mm')) }}</span> mm</td></tr>
                @if ($modell === 'F513')
                    <tr><td class="k">Kabelabgang Nr.</td><td><span class="vb-feld">{{ $w('kabelabgang') }}</span>
                        {{ $w('kabelabgang') ? MarkisenFormular::KABELABGAENGE[$w('kabelabgang')] ?? '' : '' }}</td></tr>
                @endif
            </table>
        </td>
        <td style="width:44%;text-align:center;vertical-align:top">
            <img src="{{ MarkisenSkizze::dataUri($skizze) }}" style="width:66mm" alt="Skizze">
        </td>
    </tr></table>

    <table class="vb">
        @foreach (MarkisenFormular::felder() as $feld => [$label, $typ, $optionen, $modelle])
            @continue(! in_array($modell, $modelle, true) || in_array($feld, ['typ', 'b1_mm', 'b2_mm', 'kabelabgang', 'sonderfarbe', 'kurbellaenge_mm', 'vv_ausfall_mm', 'vv_dessin', 'sonstiges'], true))
            <tr>
                <td class="k">{{ $label }}</td>
                <td>
                    @if ($typ === 'wahl')
                        @foreach (MarkisenFormular::optionen($feld, $modell) as $o)
                            <span class="vb-opt"><span class="bx {{ (string) $w($feld) === (string) $o ? 'on' : '' }}"></span>&nbsp;{{ $o }}@if (isset(MarkisenFormular::FARB_HINWEISE[$o])) <span class="vb-klein">({{ MarkisenFormular::FARB_HINWEISE[$o] }})</span>@endif</span>
                        @endforeach
                        @if ($feld === 'gestellfarbe' && $w('sonderfarbe'))<br>Sonderfarbe: <b>{{ $w('sonderfarbe') }}</b>@endif
                        @if ($feld === 'antrieb' && $modell === 'T200' && $w('kurbellaenge_mm'))<br>Kurbellänge: <b>{{ $zahl($w('kurbellaenge_mm')) }} mm</b>@endif
                    @elseif ($typ === 'ja')
                        <span class="vb-opt"><span class="bx {{ (string) $w($feld) === '1' ? 'on' : '' }}"></span>ja</span>
                        @if ($feld === 'vario_volant')
                            VV-Ausfall: <span class="vb-feld">{{ $zahl($w('vv_ausfall_mm')) }}</span> mm &nbsp;
                            Dessin: <span class="vb-feld">{{ $w('vv_dessin') }}</span>
                        @endif
                    @elseif ($typ === 'zahl')
                        <span class="vb-feld">{{ $zahl($w($feld)) }}</span> mm
                    @else
                        <span class="vb-feld" style="min-width:90mm">{{ $w($feld) }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
        <tr><td class="k">Sonstiges</td><td><div class="vb-feld" style="display:block;min-height:22px">{{ $w('sonstiges') }}</div></td></tr>
    </table>
</div>
