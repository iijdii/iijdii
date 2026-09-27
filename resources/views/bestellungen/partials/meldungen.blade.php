{{-- Zeitpunkte der Lieferanten-Meldungen (Portal) — intern und im Portal. --}}
@if ($bestellung->in_arbeit_am || $bestellung->bereit_am)
    <div class="fx wrap" style="gap:8px;margin-top:10px">
        @if ($bestellung->vom_lieferanten && ! auth()->user()->istLieferant())
            <span class="hint">Vom Lieferanten gemeldet:</span>
        @endif
        @if ($bestellung->in_arbeit_am)
            <span class="badge b-blue">In Arbeit / bestellt seit {{ $bestellung->in_arbeit_am->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</span>
        @endif
        @if ($bestellung->bereit_am)
            <span class="badge b-yellow">Abholbereit seit {{ $bestellung->bereit_am->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</span>
        @endif
    </div>
@endif
