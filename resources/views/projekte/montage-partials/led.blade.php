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
            <span class="pl-lbl" data-al="{{ $text['al'] }}" style="{{ $text['st'] }}{{ ($text['led'] ?? false) ? ';color:#2E8C5A' : '' }}">{{ $text['t'] }}</span>
        @endforeach
    </div>

    @if ($ledKpi['gesetzt'] === 0)
        <div class="mm-note blue"><svg class="i"><use href="#ic-help"/></svg>Noch keine Lampen gesetzt — Positionen unten festlegen, dann erscheinen hier die Endmaße.</div>
    @endif

    {{-- Die Abstände stehen direkt auf der Zeichnung (grüne Labels am
         Sparren) — keine separate Auflistung mehr. --}}
    <div class="mm-kpis" style="grid-template-columns:repeat(3,1fr)">
        <div class="mm-kpi"><div class="kl">Sparrenlänge</div><div class="kn">{{ $ledKpi['sparLen'] }}<span class="ku">mm</span></div></div>
        <div class="mm-kpi"><div class="kl">Sparren mit LED</div><div class="kn">{{ count($ledZeichnung['abstaende']) }}</div></div>
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

@include('projekte.partials.led-modal', ['ledZurueck' => 'montage'])
