@php use App\Support\Format; @endphp

<div class="anf-grid">
    @foreach ($projekte as $projekt)
        @php $k = $projekt->konfiguration ?? []; @endphp
        {{-- div statt <a>: Karteninhalt erbt sonst Link-Unterstreichungen. --}}
        <div class="anf ac-blue" onclick="window.location='{{ route('projekte.show', $projekt) }}'">
            <div class="anf-top">
                <span class="anf-nr mono">{{ $projekt->nr }}</span>
                <span class="badge {{ $projekt->status->badgeClass() }}">{{ $projekt->status->label() }}</span>
            </div>
            <div class="anf-b">
                <span class="anf-title">{{ $projekt->titel }}</span>
                <div class="anf-sub">{{ $projekt->kunde->anzeigename }} · {{ $projekt->kunde->kunden_nr }}</div>
                <div class="anf-specs">
                    @if (($k['width'] ?? null) && ($k['depth'] ?? null))
                        <span class="spec">{{ number_format($k['width'], 0, ',', '.') }} × {{ number_format($k['depth'], 0, ',', '.') }} mm</span>
                    @endif
                    @if ($k['product'] ?? null)
                        <span class="spec">{{ $k['product'] }}</span>
                    @endif
                    @if ($k['color'] ?? null)
                        <span class="spec">{{ $k['color'] }}</span>
                    @endif
                </div>
            </div>
            <div class="anf-foot">
                <span class="anf-meta">
                    <span>Auftragswert <b class="mono">{{ $projekt->angebot?->summe !== null && $projekt->angebot ? Format::eur($projekt->angebot->summe) : 'in Konfiguration' }}</b></span>
                    <span>Angebot <b class="mono">{{ $projekt->angebot?->nr ?? '—' }}</b></span>
                </span>
                <svg class="i"><use href="#ic-cright"/></svg>
            </div>
        </div>
    @endforeach
</div>
