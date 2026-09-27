{{-- Status-Badge einer Bestellung + Meldungszeile (seit wann, vom Lieferanten). Erwartet: $b. --}}
@php $portal = auth()->user()->istLieferant(); $meldung = $b->meldungKurz(); @endphp
<span class="badge {{ $b->status->badgeClass() }}">@if ($b->vom_lieferanten && ! $portal && $meldung)✓ @endif{{ $b->status->anzeige($portal) }}</span>
@if ($meldung)
    <div class="hint" style="font-size:11px;margin-top:3px;white-space:nowrap">{{ $portal ? 'seit '.\Illuminate\Support\Str::after($meldung, 'seit ') : $meldung }}</div>
@endif
