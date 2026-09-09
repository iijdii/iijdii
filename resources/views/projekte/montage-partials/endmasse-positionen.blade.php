{{-- Endmaße je Phase-2-Position (Einheitssystem M11): Soll aus dem
     Konfigurator, Ist vom Monteur — Grundlage der Nachbestellung. --}}
@php
    $emLabels = [
        'anzahl' => 'Anzahl', 'breite_mm' => 'Breite', 'hoehe_mm' => 'Höhe', 'h_links_mm' => 'Höhe links',
        'h_rechts_mm' => 'Höhe rechts', 'h_vorn_mm' => 'Höhe vorn', 'laenge_mm' => 'Länge',
        'ausfall_mm' => 'Ausfall', 'felder_n' => 'Felder',
    ];
@endphp
<section class="mm-card c12" id="s8">
    <div class="mm-h"><span class="n">8</span>Endmaße der Extras<span class="mm-sub" style="margin-left:auto">je Position · nach der Dachmontage</span></div>

    <form method="POST" action="{{ route('projekte.montage.endmasse', $projekt) }}">
        @csrf
        @foreach ($phase2 as $position)
            <div class="mm-note" style="margin-bottom:6px;justify-content:space-between">
                <span><b>Position {{ $position->pos }} — {{ $position->produkt->label() }}</b></span>
                @if ($position->endmasse_am)
                    <span class="badge b-green">erfasst · {{ $position->endmasseVon?->name ?? '–' }} · {{ $position->endmasse_am->format('d.m.Y H:i') }}</span>
                @else
                    <span class="badge b-yellow">offen</span>
                @endif
            </div>
            <div class="ex-fields" style="margin-bottom:14px">
                @foreach (($position->felder ?? []) as $schluessel => $wert)
                    @continue(! is_numeric($wert) || ! isset($emLabels[$schluessel]))
                    <div class="ex-f">
                        <span class="ex-tag">{{ strtoupper(substr($schluessel, 0, 2)) }}</span>
                        <span class="ex-lab"><b>{{ $emLabels[$schluessel] }}</b><span>Soll {{ number_format((int) $wert, 0, ',', '.') }}{{ str_contains($schluessel, '_mm') ? ' mm' : '' }}</span></span>
                        <input class="ex-in" type="number" inputmode="numeric" placeholder="Ist"
                               name="endmasse[{{ $position->id }}][{{ $schluessel }}]"
                               value="{{ $position->endmasse[$schluessel] ?? '' }}">
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="ex-foot">
            <span class="mm-sub" style="flex:1">Die Endmaße fließen in die Nachbestellung (Phase 2) —
                der Verkäufer erzeugt sie im Projekt unter «Material + Bestellungen».</span>
            <button class="btn btnp" type="submit">
                <svg class="i"><use href="#ic-check"/></svg>Endmaße speichern</button>
        </div>
    </form>
</section>
