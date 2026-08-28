@php $s = $glas['skizze']; @endphp
<div class="skwrap">
    <svg viewBox="0 0 360 260">
        @if ($s['showRoh'])
            <rect class="rohm" x="{{ $s['drx'] }}" y="{{ $s['dry'] }}" width="{{ $s['drw'] }}" height="{{ $s['drh'] }}"/>
        @endif
        <polygon class="glp" points="{{ $s['dpts'] }}"/>
        <line class="sheen" x1="{{ $s['shx1'] }}" y1="{{ $s['shy1'] }}" x2="{{ $s['shx2'] }}" y2="{{ $s['shy2'] }}"/>
        <line class="sheen2" x1="{{ $s['sh2x1'] }}" y1="{{ $s['sh2y1'] }}" x2="{{ $s['sh2x2'] }}" y2="{{ $s['sh2y2'] }}"/>
        @foreach ($s['dl'] as $l)
            <line class="dln" x1="{{ $l['x1'] }}" y1="{{ $l['y1'] }}" x2="{{ $l['x2'] }}" y2="{{ $l['y2'] }}"/>
        @endforeach
        @foreach ($s['ar'] as $punkte)
            <polyline class="dln" points="{{ $punkte }}"/>
        @endforeach
    </svg>
    @if ($s['showRoh'])
        <span class="skl skl-roh" style="{{ $s['sRoh'] }}">{{ $s['dRt'] }}</span>
    @endif
    <span class="skl skl-dim" style="{{ $s['sW'] }}">{{ $glas['position']->breite_mm }}</span>
    <span class="skl skl-dim" style="{{ $s['sHL'] }}">{{ ($glas['position']->details['hL'] ?? $glas['position']->hoehe_mm) }}</span>
    @if ($s['showRoh'])
        <span class="skl skl-dim" style="{{ $s['sHR'] }}">{{ $glas['position']->details['hR'] ?? '' }}</span>
    @endif
    @if ((int) $glas['position']->menge > 1)
        <span class="skl skl-qty">× {{ (int) $glas['position']->menge }}</span>
    @endif
</div>
