{{-- Eine Dachzeichnung (RoofZeichnung::ansicht). $z = {viewBox, nodes};
     $nodim = true blendet Maße/Beschriftungen/Titelblock per CSS aus. --}}
<div class="rd {{ ($nodim ?? false) ? 'nodim' : '' }}">
    <svg viewBox="{{ $z['viewBox'] }}" preserveAspectRatio="xMidYMid meet">
        @foreach ($z['nodes'] as $n)
            @switch($n['tag'])
                @case('line')
                    <line x1="{{ $n['x1'] }}" y1="{{ $n['y1'] }}" x2="{{ $n['x2'] }}" y2="{{ $n['y2'] }}"
                          @if ($n['cls']) class="{{ $n['cls'] }}" @endif {!! $n['extra'] !!}/>
                    @break
                @case('poly')
                    <polygon points="{{ $n['pts'] }}" @if ($n['cls']) class="{{ $n['cls'] }}" @endif {!! $n['extra'] !!}/>
                    @break
                @case('rect')
                    <rect x="{{ $n['x'] }}" y="{{ $n['y'] }}" width="{{ $n['w'] }}" height="{{ $n['h'] }}"
                          @if ($n['cls']) class="{{ $n['cls'] }}" @endif {!! $n['extra'] !!}/>
                    @break
                @case('path')
                    <path d="{{ $n['d'] }}" @if ($n['cls']) class="{{ $n['cls'] }}" @endif {!! $n['extra'] !!}/>
                    @break
                @case('circle')
                    <circle cx="{{ $n['cx'] }}" cy="{{ $n['cy'] }}" r="{{ $n['r'] }}"
                            @if ($n['cls']) class="{{ $n['cls'] }}" @endif/>
                    @break
                @case('text')
                    <text x="{{ $n['x'] }}" y="{{ $n['y'] }}" class="{{ $n['cls'] }}"
                          @if ($n['anchor']) text-anchor="{{ $n['anchor'] }}" @endif
                          @if ($n['rot']) transform="rotate({{ $n['rot'] }})" @endif
                          @if ($n['size']) style="font-size:{{ $n['size'] }}px" @endif>{{ $n['t'] }}</text>
                    @break
            @endswitch
        @endforeach
    </svg>
</div>
