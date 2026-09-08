@php $ledKpi = $ledZeichnung['kpis']; @endphp

<section class="mm-card c7" id="s7">
    <div class="mm-h"><span class="n">7</span>Beleuchtung · LED
        <span class="mm-sub" style="margin-left:auto">Finale Anordnung · {{ $ledKpi['gesetzt'] }} von {{ $ledKpi['total'] }} gesetzt (max. {{ $ledKpi['total'] }})</span></div>

    <div style="position:relative">
        <svg viewBox="{{ $ledZeichnung['viewBox'] }}" style="width:100%;height:auto;display:block;aspect-ratio:720/470" preserveAspectRatio="xMidYMid meet">
            <rect x="152" y="74" width="512" height="248" fill="rgba(74,111,165,.05)" stroke="#33507d" stroke-width="1.6"/>
            <rect x="152" y="74" width="512" height="13" fill="rgba(51,80,125,.16)"/>
            @foreach ($ledZeichnung['rafterLines'] as $sparren)
                <line x1="{{ $sparren['x'] }}" y1="{{ $sparren['y1'] }}" x2="{{ $sparren['x'] }}" y2="{{ $sparren['y2'] }}"
                      stroke="{{ $sparren['c'] }}" stroke-width="{{ $sparren['w'] }}"/>
            @endforeach
            @foreach ($ledZeichnung['ketten'] as $kette)
                <line x1="{{ $kette['x1'] }}" y1="{{ $kette['y1'] }}" x2="{{ $kette['x2'] }}" y2="{{ $kette['y2'] }}" stroke="#9aa7bb" stroke-width="1"/>
            @endforeach
            @foreach ($ledZeichnung['lampen'] as $lampe)
                <circle cx="{{ $lampe['cx'] }}" cy="{{ $lampe['cy'] }}" r="7.5" fill="#2E8C5A" stroke="#2E8C5A"/>
            @endforeach
        </svg>
        @foreach ($ledZeichnung['texte'] as $text)
            <span class="pl-lbl" data-al="{{ $text['al'] }}" style="{{ $text['st'] }}">{{ $text['t'] }}</span>
        @endforeach
    </div>

    @if ($ledKpi['gesetzt'] === 0)
        <div class="mm-note blue"><svg class="i"><use href="#ic-help"/></svg>Noch keine Lampen gesetzt — Positionen unten festlegen, dann erscheinen hier die Endmaße.</div>
    @endif

    <div class="mm-kpis" style="grid-template-columns:repeat(4,1fr)">
        <div class="mm-kpi"><div class="kl">Sparrenlänge</div><div class="kn">{{ $ledKpi['sparLen'] }}<span class="ku">mm</span></div></div>
        <div class="mm-kpi"><div class="kl">Randabstand a</div><div class="kn">{{ $ledKpi['edge'] }}<span class="ku">mm</span></div></div>
        <div class="mm-kpi"><div class="kl">Achsabstand e</div><div class="kn">{{ $ledKpi['pitch'] }}<span class="ku">mm</span></div></div>
        <div class="mm-kpi"><div class="kl">Gesetzt</div><div class="kn">{{ $ledKpi['gesetzt'] }} / {{ $ledKpi['total'] }}</div></div>
    </div>

    <button class="btn btnp" style="width:100%;margin:10px 0" type="button" data-led-modal-open>
        <svg class="i"><use href="#ic-expand"/></svg>LED-Positionen festlegen &amp; abhaken</button>

    <div class="mm-prog"><i style="width:{{ $ledKpi['total'] ? round($ledKpi['gesetzt'] / $ledKpi['total'] * 100) : 0 }}%"></i></div>
    <div class="mm-sub" style="margin:4px 0 10px">{{ $ledKpi['gesetzt'] }} / {{ $ledKpi['total'] }} Spots</div>

    <div class="mm-row"><span class="rk">Lichtfarbe</span><span class="rv">{{ $pcfg['led']['color'] }}</span></div>
    <div class="mm-row"><span class="rk">Spannung</span><span class="rv">24 V · Trafo vorn links</span></div>
    <div class="mm-row"><span class="rk">Kabelführung</span><span class="rv">verdeckt im Sparren</span></div>
    <div class="mm-note"><svg class="i"><use href="#ic-help"/></svg>Randsparren bleiben ohne LED — die Verteilung legt der Monteur vor Ort fest.</div>
</section>

{{-- LED-Pickermodal: alle Kandidaten als POST-Buttons --}}
<div class="modal" id="ledModal" hidden>
    <div class="modalc xl">
        <div class="lbxh">
            <div>
                <b>LED-Positionen festlegen</b>
                <div class="hint">Draufsicht · {{ $ledKpi['total'] }} Spots · Sparrenlänge {{ $ledKpi['sparLen'] }} mm (Tiefe − 110 mm)</div>
            </div>
            <button class="lnav" type="button" data-led-modal-close aria-label="Schließen"><svg class="i"><use href="#ic-x"/></svg></button>
        </div>
        <div class="mbody">
            <div class="jb" style="margin-bottom:10px">
                <span class="fx"><span class="badge b-green">{{ $ledKpi['gesetzt'] }} von {{ $ledKpi['total'] }} gesetzt</span>
                    <span class="hint">Je Sparren stehen 3 Positionen zur Wahl — antippen setzt eine Lampe. Weniger als {{ $ledKpi['total'] }} ist erlaubt, mehr nicht.</span></span>
                <form method="POST" action="{{ route('projekte.montage.led', $projekt) }}">
                    @csrf
                    <input type="hidden" name="aktion" value="reset">
                    <button class="btn btns" type="submit">Alle zurücksetzen</button>
                </form>
            </div>
            <div style="position:relative">
                <svg viewBox="0 0 720 470" style="width:100%;height:auto;display:block;aspect-ratio:720/470" preserveAspectRatio="xMidYMid meet">
                    <rect x="152" y="74" width="512" height="248" fill="rgba(74,111,165,.05)" stroke="#33507d" stroke-width="1.6"/>
                    <rect x="152" y="74" width="512" height="13" fill="rgba(51,80,125,.16)"/>
                    @foreach ($ledZeichnung['rafterLines'] as $sparren)
                        <line x1="{{ $sparren['x'] }}" y1="{{ $sparren['y1'] }}" x2="{{ $sparren['x'] }}" y2="{{ $sparren['y2'] }}" stroke="#33507d" stroke-width="1.8"/>
                    @endforeach
                    @foreach ($ledZeichnung['ticks'] as $tick)
                        <line x1="{{ $tick['x1'] }}" y1="{{ $tick['y'] }}" x2="{{ $tick['x2'] }}" y2="{{ $tick['y'] }}" stroke="{{ $tick['c'] }}" stroke-width="1"/>
                    @endforeach
                </svg>
                @foreach ($ledKandidaten as $kandidat)
                    @php $gesetztPos = in_array($kandidat['key'], $ledGesetzt, true); @endphp
                    <form method="POST" action="{{ route('projekte.montage.led', $projekt) }}"
                          style="position:absolute;{{ $kandidat['style'] }};transform:translate(-50%,-50%);margin:0">
                        @csrf
                        <input type="hidden" name="pos" value="{{ $kandidat['key'] }}">
                        <button class="lampb {{ $gesetztPos ? 'on' : ($ledKpi['voll'] ? 'lock' : '') }}"
                                type="submit" aria-label="{{ $kandidat['key'] }}"></button>
                    </form>
                @endforeach
            </div>
            <div class="mm-note blue" style="margin-top:10px"><svg class="i"><use href="#ic-help"/></svg>
                Randabstand a ist immer die Hälfte des Achsabstands e — dadurch sitzt die Reihe symmetrisch auf dem Sparren. Spot antippen = montiert.</div>
        </div>
        <div class="mfoot">
            <span></span>
            <button class="btn btnp" type="button" data-led-modal-close>Positionen übernehmen</button>
        </div>
    </div>
</div>
