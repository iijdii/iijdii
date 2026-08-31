{{-- MontageZeichnung-Ausgabe: SVG + prozentpositionierte Labels --}}
<div class="dw {{ ($klein ?? false) ? 'dw-sm' : '' }}" style="position:relative">
    <svg viewBox="{{ $z['viewBox'] }}" style="width:100%;height:auto;display:block;{{ $z['aspect'] }}" preserveAspectRatio="xMidYMid meet">
        @foreach ($z['polys'] as $poly)
            <polygon points="{{ $poly['pts'] }}" fill="{{ $poly['fill'] }}" stroke="{{ $poly['stroke'] }}" stroke-width="{{ $poly['w'] }}"/>
        @endforeach
        @foreach ($z['lines'] as $linie)
            <line x1="{{ $linie['x1'] }}" y1="{{ $linie['y1'] }}" x2="{{ $linie['x2'] }}" y2="{{ $linie['y2'] }}"
                  stroke="{{ $linie['c'] }}" stroke-width="{{ $linie['w'] }}"
                  @if ($linie['d'] !== '') stroke-dasharray="{{ $linie['d'] }}" @endif stroke-linecap="round"/>
        @endforeach
    </svg>
    @foreach ($z['labels'] as $label)
        <span class="dw-l {{ $label['cls'] }}" style="{{ $label['st'] }}">{{ $label['t'] }}</span>
    @endforeach
</div>
<div class="dw-foot">{{ $z['note'] }}</div>
