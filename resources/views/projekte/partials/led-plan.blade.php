{{-- LED-Plan auf der Projekt-Übersicht: dieselbe Draufsicht wie im
     Montage-Modus, mit berechneten Abständen am Sparren; der Verkäufer
     kann die Lampen bereits im Büro setzen, der Monteur ändert sie
     später im Montage-Modus (gleiche Daten, gleiches Fenster). --}}
@php $ledKpi = $ledZeichnung['kpis']; @endphp
<div class="card p0">
    <div class="card-h">
        <span class="card-t">LED-Plan</span>
        <span class="pill">{{ $ledKpi['gesetzt'] }} / {{ $ledKpi['total'] }} Spots</span>
    </div>
    <div class="card-b">
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

        @if ($ledZeichnung['abstaende'] === [])
            <p class="hint" style="margin-top:10px">Noch keine Lampen gesetzt — Positionen können schon
                jetzt vom Verkäufer festgelegt werden, der Monteur passt sie vor Ort an.</p>
        @endif

        <button class="btn btns btnp" style="margin-top:10px" type="button" data-led-modal-open>
            <svg class="i"><use href="#ic-expand"/></svg>LED-Positionen festlegen</button>
    </div>
</div>

@include('projekte.partials.led-modal', ['ledZurueck' => 'projekt'])
