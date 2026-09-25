{{-- Varisol-Bestellblatt als Eingabeformular (Konfigurator + Montage-Modus).
     Erwartet: $name (fn(string $feld): string → Input-Name), $werte (array),
     $mitBasis (Modell/Stück/Breite/Ausfall mit anzeigen).
     Modellspezifische Zeilen tragen data-nur-modell; app.js blendet sie
     beim Modellwechsel um. --}}
@php
    use App\Support\MarkisenFormular;
    use App\Support\MarkisenSkizze;
    $modell = MarkisenFormular::modell($werte);
    $sichtbar = fn (array $modelle) => in_array($modell, $modelle, true);
    $wert = fn (string $feld, $standard = '') => $werte[$feld] ?? $standard;
@endphp
<div class="mf" data-markise-formular>
    @if ($mitBasis)
        <div class="fgrid2">
            <div class="fld"><label>Modell (Varisol)</label>
                <select class="inp" name="{{ $name('modell') }}" data-markise-modell>
                    @foreach (MarkisenFormular::MODELLE as $schluessel => $label)
                        <option value="{{ $schluessel }}" @selected($modell === $schluessel)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <div class="fld"><label>Stück</label>
                <input class="inp" type="number" min="1" name="{{ $name('anzahl') }}" value="{{ $wert('anzahl', 1) }}"></div>
            <div class="fld"><label>Breite / Bestellmaß B (mm)</label>
                <input class="inp" type="number" name="{{ $name('breite_mm') }}" value="{{ $wert('breite_mm') }}"></div>
            <div class="fld"><label>Ausfall (mm)</label>
                <input class="inp" type="number" name="{{ $name('ausfall_mm') }}" value="{{ $wert('ausfall_mm') }}"></div>
        </div>
    @else
        <input type="hidden" name="{{ $name('modell') }}" value="{{ $modell }}">
        <div class="hint" style="margin-bottom:6px">{{ MarkisenFormular::MODELLE[$modell] }}</div>
    @endif

    <div class="mf-skizzen">
        <div data-nur-modell="T200" @unless ($sichtbar(['T200'])) hidden @endunless>
            <div class="mf-skizze">{!! MarkisenSkizze::t200(false, (int) $wert('breite_mm') ?: null, null, null, (int) $wert('ausfall_mm') ?: null) !!}</div>
            <div class="mf-skizze">{!! MarkisenSkizze::t200(true, (int) $wert('breite_mm') ?: null, (int) $wert('b1_mm') ?: null, (int) $wert('b2_mm') ?: null) !!}</div>
        </div>
        <div data-nur-modell="F513" @unless ($sichtbar(['F513'])) hidden @endunless>
            <div class="mf-skizze">{!! MarkisenSkizze::f513Kabelabgang((string) $wert('kabelabgang')) !!}</div>
        </div>
    </div>

    @foreach (MarkisenFormular::felder() as $feld => [$label, $typ, $optionen, $modelle])
        <div class="mf-row" data-nur-modell="{{ implode(' ', $modelle) }}" @unless ($sichtbar($modelle)) hidden @endunless>
            <div class="mf-label">{{ $label }}</div>
            <div>
                @if ($typ === 'wahl')
                    @php
                        $alle = array_is_list($optionen) ? $optionen : array_values(array_unique(array_merge(...array_values($optionen))));
                    @endphp
                    <div class="mf-opts">
                        @foreach ($alle as $option)
                            @php
                                $nur = array_is_list($optionen) ? $modelle
                                    : array_keys(array_filter($optionen, fn ($liste) => in_array($option, $liste, true)));
                            @endphp
                            <label class="mf-opt" data-nur-modell="{{ implode(' ', $nur) }}" @unless ($sichtbar($nur)) hidden @endunless>
                                <input type="radio" name="{{ $name($feld) }}" value="{{ $option }}" @checked((string) $wert($feld) === (string) $option)>
                                {{ $feld === 'kabelabgang' ? $option.' · '.MarkisenFormular::KABELABGAENGE[$option] : $option }}
                                @if (isset(MarkisenFormular::FARB_HINWEISE[$option]))<span class="hint">{{ MarkisenFormular::FARB_HINWEISE[$option] }}</span>@endif
                            </label>
                        @endforeach
                    </div>
                @elseif ($typ === 'ja')
                    <input type="hidden" name="{{ $name($feld) }}" value="0">
                    <label class="mf-opt"><input type="checkbox" name="{{ $name($feld) }}" value="1" @checked((string) $wert($feld) === '1')> ja</label>
                @elseif ($typ === 'zahl')
                    <input class="inp mono" type="number" min="0" name="{{ $name($feld) }}" value="{{ $wert($feld) }}" placeholder="mm" style="max-width:160px">
                @else
                    <input class="inp" type="text" name="{{ $name($feld) }}" value="{{ $wert($feld) }}" maxlength="160">
                @endif
            </div>
        </div>
    @endforeach
</div>
