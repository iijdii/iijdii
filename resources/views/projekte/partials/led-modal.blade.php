{{-- LED-Pickermodal — geteilt zwischen Projekt-Übersicht (Verkäufer)
     und Montage-Modus (Monteur). Erwartet $ledZeichnung, $ledKandidaten,
     $ledGesetzt, $projekt; $ledZurueck ('montage'|'projekt') steuert den
     Redirect im No-JS-Fallback. data-led-total = clientseitige Obergrenze
     (Lampen setzen ohne Neuladen). --}}
@php $ledKpi = $ledZeichnung['kpis']; $ledZurueck = $ledZurueck ?? 'montage'; @endphp
<div class="modal" id="ledModal" data-led-total="{{ $ledKpi['total'] }}" hidden>
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
                <span class="fx"><span class="badge b-green"><span data-led-count>{{ $ledKpi['gesetzt'] }}</span>&nbsp;von {{ $ledKpi['total'] }} gesetzt</span>
                    <span class="hint">Antippen legt fest, wie viele Lampen auf dem Sparren sitzen — die Abstände
                        rechnet das System (1 → Mitte, 2 → Drittel, 3 → Viertel). Weniger als {{ $ledKpi['total'] }} ist erlaubt, mehr nicht.</span></span>
                <form method="POST" action="{{ route('projekte.montage.led', $projekt) }}">
                    @csrf
                    <input type="hidden" name="aktion" value="reset">
                    <input type="hidden" name="zurueck" value="{{ $ledZurueck }}">
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
                        <input type="hidden" name="zurueck" value="{{ $ledZurueck }}">
                        <button class="lampb {{ $gesetztPos ? 'on' : ($ledKpi['voll'] ? 'lock' : '') }}"
                                type="submit" aria-label="{{ $kandidat['key'] }}"></button>
                    </form>
                @endforeach
            </div>
            <div class="mm-note blue" style="margin-top:10px"><svg class="i"><use href="#ic-help"/></svg>
                Angetippt wird nur die ANZAHL je Sparren — die Abstände rechnet das System aus der
                Sparrenlänge: 1 Lampe sitzt in der Mitte (÷2), 2 Lampen auf den Dritteln (÷3),
                3 auf den Vierteln (÷4). Die grünen Markierungen zeigen die endgültigen Positionen.</div>
        </div>
        <div class="mfoot">
            <span></span>
            <button class="btn btnp" type="button" data-led-modal-close>Positionen übernehmen</button>
        </div>
    </div>
</div>
