@php $fmt = fn ($v) => number_format((int) $v, 0, ',', '.'); @endphp
<div class="skcell">
    <div class="skcap">Keil {{ $seite }}</div>
    <div class="kwrap" style="position:relative;width:200px;height:150px">
        <svg viewBox="0 0 200 150" style="width:200px;height:150px;display:block;background:#fbfcfe;border:1px solid var(--bd);border-radius:8px">
            <polygon class="glp" points="{{ $sk['pts'] }}"/>
        </svg>
        <span class="skl skl-dim" style="{{ $sk['hbL'] }}">{{ $fmt($hB) }}</span>
        <span class="skl skl-dim" style="{{ $sk['hfL'] }}">{{ $fmt($hF) }}</span>
        <span class="skl skl-dim" style="{{ $sk['buL'] }}">{{ $fmt($bU) }}</span>
    </div>
</div>
