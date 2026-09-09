@php use App\Support\Format; @endphp

<div class="anf-grid">
    @foreach ($karten as $karte)
        @php $b = $karte['bestellung']; @endphp
        {{-- div statt <a>: der PDF-Link im Fuß darf nicht in einem Anker stecken
             (verschachtelte <a> bricht der Browser auf — die Karte zerfällt). --}}
        <div class="anf {{ $b->status->accentClass() }}"
             onclick="window.location='{{ route('bestellungen.show', $b) }}'">
            <div class="anf-top">
                <span class="anf-nr mono">{{ $b->nr }}</span>
                <span class="badge {{ $b->status->badgeClass() }}">{{ $b->status->label() }}</span>
            </div>
            <div class="anf-b">
                <div class="fx">
                    <span class="anf-title">{{ $b->lieferant?->name ?? '— Lieferant wählen —' }}</span>
                    <span class="pill {{ $b->kategoriePillClass() }}">{{ $b->kategorieLabel() }}</span>
                </div>
                <div class="anf-sub">{{ $b->projekt?->nr ?? '–' }}@unless (auth()->user()->istLieferant()) · {{ $b->kunde?->anzeigename ?? '–' }}@endunless</div>

                @if ($karte['tiles']->isNotEmpty())
                    <div class="gtiles">
                        @foreach ($karte['tiles'] as $tile)
                            <span class="gtile">
                                <svg viewBox="0 0 120 118">
                                    @if ($tile['trapez'])
                                        <rect class="rohm" x="{{ $tile['skizze']['rx'] }}" y="{{ $tile['skizze']['ry'] }}"
                                              width="{{ $tile['skizze']['rw'] }}" height="{{ $tile['skizze']['rh'] }}"/>
                                    @endif
                                    <polygon class="glp" points="{{ $tile['skizze']['pts'] }}"/>
                                </svg>
                                @if ($tile['menge'] > 1)
                                    <span class="gq">×{{ $tile['menge'] }}</span>
                                @endif
                                <span class="gcapg mono">{{ $tile['skizze']['cap'] }}</span>
                            </span>
                        @endforeach
                        @if ($karte['mehr'] > 0)
                            <span class="gmore">+{{ $karte['mehr'] }}</span>
                        @endif
                    </div>
                @endif

                @if ($karte['materialChips']->isNotEmpty())
                    <div class="anf-specs">
                        @foreach ($karte['materialChips'] as $chip)
                            <span class="spec">{{ $chip }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="anf-foot">
                <span class="anf-meta">
                    <span><b>{{ $karte['posCount'] }}</b> Positionen</span>
                    <span>Liefertermin <b class="mono">{{ Format::datumKurz($b->liefertermin) }}</b></span>
                </span>
                <a class="btn btns" href="{{ route('bestellungen.pdf', $b) }}" onclick="event.stopPropagation()">PDF</a>
            </div>
        </div>
    @endforeach
</div>
