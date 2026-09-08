@php use App\Support\Format; @endphp

<div class="anf-grid">
    @foreach ($anfragen as $anfrage)
        @php
            $ac = match ($anfrage->status->uiStufe()) {
                1 => 'ac-gray', 2 => 'ac-yellow', 3 => 'ac-blue', 4 => 'ac-green', default => 'ac-red',
            };
            $d = $anfrage->details ?? [];
            $specs = array_filter([
                'Form' => $d['form'] ?? null,
                'Maße' => ($anfrage->breite_cm && $anfrage->tiefe_cm)
                    ? ($anfrage->breite_cm * 10).'×'.($anfrage->tiefe_cm * 10) : null,
                'Neigung' => $anfrage->dachneigung_grad ? $anfrage->dachneigung_grad.'°' : null,
                'Pfosten' => $d['pfAnzahl'] ?? $anfrage->anzahl_stuetzen,
                'Farbe' => $d['farbe'] ?? null,
            ]);
        @endphp
        <a class="anf {{ $ac }}" href="{{ route('anfragen.show', $anfrage) }}">
            <div class="anf-top">
                <span class="anf-nr mono">{{ $anfrage->nummer }}</span>
                <span class="badge {{ $anfrage->status->badgeClass() }}">{{ $anfrage->status->label() }}</span>
            </div>
            <div class="anf-b">
                <span class="anf-title">{{ $anfrage->produkt_notiz ?? 'Anfrage' }}</span>
                <div class="anf-sub">{{ $anfrage->kunde->anzeigename }} · {{ $anfrage->objekt_stadt ?? $anfrage->kunde->stadt ?? '–' }}</div>
                <div class="anf-specs">
                    @foreach ($specs as $k => $v)
                        <span class="spec">{{ $k }} <b>{{ $v }}</b></span>
                    @endforeach
                </div>
            </div>
            <div class="anf-foot">
                <span class="anf-meta">
                    <span>Quelle <b>{{ $anfrage->anfrage_quelle ?? '–' }}</b></span>
                    <span>Eingang <b class="mono">{{ Format::datumKurz($anfrage->created_at) }}</b></span>
                </span>
                @if ($anfrage->projekt)
                    <button class="anf-cta" type="button"
                            onclick="event.preventDefault();event.stopPropagation();window.location='{{ route('projekte.show', $anfrage->projekt) }}'">
                        <svg class="i"><use href="#ic-projekte"/></svg>Projekt öffnen</button>
                @else
                    {{-- Weg zum Angebot: Detail → Projekt erstellen → Als Angebot übergeben --}}
                    <button class="anf-cta" type="button"
                            onclick="event.preventDefault();event.stopPropagation();window.location='{{ route('anfragen.show', $anfrage) }}'">
                        <svg class="i"><use href="#ic-angebote"/></svg>Angebot</button>
                @endif
            </div>
        </a>
    @endforeach
</div>
