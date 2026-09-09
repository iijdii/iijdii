{{-- Positions-Editor des Projekts: Dach-Position wird im großen
     Konfigurator gepflegt, Element-Positionen (Extras/Sonnenschutz) hier. --}}
@php
    $posLabels = [
        'anzahl' => 'Anzahl', 'breite_mm' => 'Breite', 'hoehe_mm' => 'Höhe', 'h_links_mm' => 'Höhe links',
        'h_rechts_mm' => 'Höhe rechts', 'h_vorn_mm' => 'Höhe vorn', 'laenge_mm' => 'Länge',
        'ausfall_mm' => 'Ausfall', 'felder_n' => 'Felder', 'richtung' => 'Laufrichtung', 'glas' => 'Verglasung',
        'seite' => 'Seite', 'material' => 'Material', 'transparenz' => 'Transparenz', 'modell' => 'Modell',
        'antrieb' => 'Antrieb', 'groesse' => 'Größe', 'farbe' => 'Farbe',
    ];
@endphp

<div class="card p0" style="margin-top:16px">
    <div class="card-h">
        <span class="card-t">Positionen</span>
        <span class="pill">{{ $projekt->positionen->count() }}</span>
    </div>
    <div class="card-b">
        @foreach ($projekt->positionen as $position)
            <div style="padding:10px 0;border-bottom:1px solid var(--bd2)">
                <div class="jb wrap">
                    <b>Position {{ $position->pos }} — {{ $position->produkt->label() }}</b>
                    <span class="fx ac gap8">
                        <span class="badge {{ $position->phase === 2 ? 'b-yellow' : 'b-gray' }}">
                            Phase {{ $position->phase }}{{ $position->phase === 2 ? ' · Endmaße nach Dachmontage' : '' }}</span>
                        <form method="POST" action="{{ route('projekte.positionen.loeschen', [$projekt, $position]) }}"
                              onsubmit="return confirm('Position {{ $position->pos }} entfernen?')">
                            @csrf
                            <button class="btn btns" type="submit" title="Position entfernen">✕</button>
                        </form>
                    </span>
                </div>
                @if ($position->produkt->istDach())
                    <p class="hint" style="margin-top:6px">Felder werden im Konfigurator oben gepflegt.</p>
                @else
                    <div class="anf-specs" style="margin:8px 0 0">
                        @foreach ($position->felder ?? [] as $schluessel => $wert)
                            <span class="spec">{{ $posLabels[$schluessel] ?? $schluessel }}
                                <b>{{ str_contains($schluessel, '_mm') && is_numeric($wert) ? number_format((int) $wert, 0, ',', '.').' mm' : $wert }}</b></span>
                        @endforeach
                    </div>
                    <details style="margin-top:8px">
                        <summary class="hint" style="cursor:pointer">Bearbeiten</summary>
                        <form method="POST" action="{{ route('projekte.positionen.update', [$projekt, $position]) }}" style="margin-top:10px">
                            @csrf
                            @method('PUT')
                            @include('projekte.partials.produkt-form', ['prefix' => 'position', 'position' => $position])
                            <button class="btn btns btnp" type="submit" style="margin-top:10px">
                                <svg class="i"><use href="#ic-check"/></svg>Position speichern</button>
                        </form>
                    </details>
                @endif
            </div>
        @endforeach

        <details style="margin-top:12px">
            <summary class="hint" style="cursor:pointer"><b>+ Position hinzufügen</b> (Extras · Sonnenschutz{{ $projekt->positionen->firstWhere('gruppe', 'dach') ? '' : ' · Dach' }})</summary>
            <form method="POST" action="{{ route('projekte.positionen.store', $projekt) }}" style="margin-top:10px">
                @csrf
                @include('projekte.partials.produkt-form', [
                    'prefix' => 'position', 'position' => null,
                    'standardProdukt' => $projekt->positionen->firstWhere('gruppe', 'dach') ? 'wand' : 'ueberdachung',
                ])
                <button class="btn btns btnp" type="submit" style="margin-top:10px">
                    <svg class="i"><use href="#ic-plus"/></svg>Position hinzufügen</button>
            </form>
        </details>
    </div>
</div>
