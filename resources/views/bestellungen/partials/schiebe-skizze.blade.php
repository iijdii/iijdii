@php $s = $schiebe['skizze']; @endphp
<div class="skwrap">
    <svg viewBox="0 0 360 260">
        <rect class="glp" x="{{ $s['rx'] }}" y="{{ $s['ry'] }}" width="{{ $s['rw'] }}" height="{{ $s['rh'] }}"/>
        @foreach ($s['divs'] as $l)
            <line class="divln" x1="{{ $l['x1'] }}" y1="{{ $l['y1'] }}" x2="{{ $l['x2'] }}" y2="{{ $l['y2'] }}"/>
        @endforeach
        @foreach ($s['slines'] as $l)
            <line class="slln" x1="{{ $l['x1'] }}" y1="{{ $l['y1'] }}" x2="{{ $l['x2'] }}" y2="{{ $l['y2'] }}"/>
        @endforeach
        @foreach ($s['sheads'] as $punkte)
            <polyline class="slln" points="{{ $punkte }}"/>
        @endforeach
        @foreach ($s['dl'] as $l)
            <line class="dln" x1="{{ $l['x1'] }}" y1="{{ $l['y1'] }}" x2="{{ $l['x2'] }}" y2="{{ $l['y2'] }}"/>
        @endforeach
        @foreach ($s['ar'] as $punkte)
            <polyline class="dln" points="{{ $punkte }}"/>
        @endforeach
        <text x="180" y="252" text-anchor="middle" style="font:600 9px 'DM Mono',monospace;fill:#828a94;letter-spacing:.14em">LAUFSCHIENE</text>
    </svg>
    @foreach ($s['nums'] as $num)
        <span class="skl skl-num" style="{{ $num['st'] }}">{{ $num['i'] }}</span>
    @endforeach
    <span class="skl skl-dim" style="{{ $s['sW'] }}">{{ $schiebe['position']->breite_mm }}</span>
    <span class="skl skl-dim" style="{{ $s['sHL'] }}">{{ $schiebe['position']->hoehe_mm }}</span>
</div>
