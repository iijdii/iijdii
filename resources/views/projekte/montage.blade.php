@extends('layouts.montage')

@section('title', 'Montage · '.$projekt->nr)

@php
    use App\Support\Format;
    $F = fn ($n) => number_format(round($n), 0, ',', '.');
    $farbteile = explode('·', $pcfg['color'] ?? '');
    $farbe = trim($farbteile[0] ?? '') ?: '–';
    $farbeRal = trim($farbteile[1] ?? '');
    $ledKpi = $ledZeichnung['kpis'];
@endphp

@section('content')
<div class="mm">
    <header class="mm-top">
        <a class="mm-back" href="{{ route('projekte.show', $projekt) }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Zurück zum Büro</a>
        <span class="mm-tt"><b>MONTAGE-MODUS</b><span>{{ $projekt->nr }} · {{ $projekt->titel }}</span></span>
        <span class="mm-date">
            <b>Montage {{ Format::datum($projekt->termin_von) }}</b>
            <span>{{ $projekt->termin_bis && $projekt->termin_von ? 'Dauer '.($projekt->termin_von->diffInDays($projekt->termin_bis) + 1).' Tage' : '' }} · Team Nord</span>
        </span>
    </header>

    <div class="mm-body">

        {{-- 1 · Objekt & Termin --}}
        <section class="mm-card c7" id="s1">
            <div class="mm-h"><span class="n">1</span>Objekt &amp; Termin</div>
            <div class="mm-row"><span class="rk">Projekt</span><span class="rv">{{ $projekt->nr }} · {{ $projekt->titel }}</span></div>
            <div class="mm-row"><span class="rk">Ansprechpartner</span><span class="rv">{{ $projekt->kunde->ansprechpartner ?? $projekt->kunde->anzeigename }} · {{ $projekt->kunde->telefon ?? '–' }}</span></div>
            <div class="mm-row"><span class="rk">Montage</span><span class="rv mono">{{ Format::datum($projekt->termin_von) }}</span></div>
            <div class="mm-row"><span class="rk">Team</span><span class="rv">Team Nord · 3 Monteure</span></div>
            @if ($projekt->kunde->notizen)
                <div class="mm-note blue"><svg class="i"><use href="#ic-anfragen"/></svg>{{ $projekt->kunde->notizen }}</div>
            @endif
        </section>

        {{-- Adresse & Anfahrt --}}
        <section class="mm-card c5" id="s-anfahrt">
            <div class="mm-h"><svg class="i"><use href="#ic-pin"/></svg>Adresse &amp; Anfahrt</div>
            <div style="position:relative;z-index:0;isolation:isolate;height:216px;border:1px solid var(--bd);border-radius:12px;overflow:hidden">
                <anfahrt-map lat="52.5322" lon="13.3846" zoom="16" label="{{ $adresse }}"></anfahrt-map>
                <a style="position:absolute;top:10px;right:10px;z-index:900;background:#fff;border:1px solid var(--bd);border-radius:999px;padding:5px 11px;font-size:11.5px;font-weight:600;text-decoration:none;color:var(--ink)"
                   href="https://www.google.com/maps/search/?api=1&query={{ $mapsQuery }}" target="_blank" rel="noopener">Google Maps ↗</a>
            </div>
            <a class="mm-row" style="text-decoration:none" href="https://www.google.com/maps/dir/?api=1&destination={{ $mapsQuery }}" target="_blank" rel="noopener">
                <span class="rk">Adresse</span><span class="rv" style="color:var(--blued)">{{ $adresse }} ↗</span></a>
            <div class="mm-row"><span class="rk">Zufahrt</span><span class="rv">{{ $projekt->kunde->notizen ? 'siehe Notiz' : 'Hofeinfahrt · Stellfläche im Hof' }}</span></div>
            <a class="btn btnp" style="width:100%;height:46px;margin-top:10px"
               href="https://www.google.com/maps/dir/?api=1&destination={{ $mapsQuery }}&travelmode=driving" target="_blank" rel="noopener">
                <svg class="i"><use href="#ic-truck"/></svg>Route in Google Maps starten</a>
        </section>

        {{-- 2 · Kernmaße --}}
        <section class="mm-card c12" id="s2">
            <div class="mm-h"><span class="n">2</span>Kernmaße</div>
            <div class="mm-kpis">
                <div class="mm-kpi"><span>Breite</span><b class="mono">{{ $F($pcfg['width']) }}<span class="ku">mm</span></b></div>
                <div class="mm-kpi"><span>Tiefe</span><b class="mono">{{ $F($pcfg['depth']) }}<span class="ku">mm</span></b></div>
                <div class="mm-kpi"><span>Höhe hinten</span><b class="mono">{{ $F($pcfg['wallH']) }}<span class="ku">mm</span></b></div>
                <div class="mm-kpi"><span>Höhe vorn</span><b class="mono">{{ $F($pcfg['gutterH']) }}<span class="ku">mm</span></b></div>
                <div class="mm-kpi"><span>Farbe</span><b>{{ $farbe }}</b><span class="ku">{{ $farbeRal }}</span></div>
            </div>
        </section>

        {{-- 3 · Technische Zeichnungen --}}
        <section class="mm-card c12" id="s3">
            <div class="mm-h"><span class="n">3</span>Technische Zeichnungen<span class="mm-sub" style="margin-left:auto">Tippen zum Vergrößern</span></div>
            <div class="mm-drawgrid">
                @foreach (['Montageübersicht', 'Draufsicht', 'Vorderansicht', 'Seitenansicht', 'Detailschnitt A–A'] as $zeichnungTitel)
                    <button class="mm-draw" type="button" data-toast="Zeichnungen folgen im nächsten Milestone">
                        <span class="dtl">{{ $zeichnungTitel }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        {{-- 4 · Pfosten & Verankerung --}}
        <section class="mm-card c6" id="s4">
            <div class="mm-h"><span class="n">4</span>Pfosten &amp; Verankerung</div>
            <div class="mm-row"><span class="rk">Anzahl</span><span class="rv mono">{{ $kalk['pn'] }} Stück</span></div>
            <div class="mm-row"><span class="rk">Größe</span><span class="rv mono">90 × 90 × 3 mm</span></div>
            <div class="mm-row"><span class="rk">Pfostenabstand</span><span class="rv mono">{{ $kalk['pn'] > 1 ? $F($pcfg['width'] / ($kalk['pn'] - 1)).' mm (Achse)' : '–' }}</span></div>
            <div class="mm-row"><span class="rk">Anordnung</span><span class="rv">{{ $kalk['pn'] }} vorn (Rinne)</span></div>
            <div class="mm-row"><span class="rk">Wasserablauf an</span><span class="rv">Pfosten {{ $pcfg['drain']['post'] ?: 1 }} von {{ $kalk['pn'] }}</span></div>
            <div class="mm-row"><span class="rk">Ablauf Höhe</span><span class="rv mono">{{ $F($pcfg['drain']['height']) }} mm</span></div>
            <div class="mm-row"><span class="rk">Ablauf Blickrichtung</span><span class="rv">{{ $pcfg['drain']['dir'] }}</span></div>
            <div class="mm-row"><span class="rk">Anker</span><span class="rv">U-Profil-Bodenhalter · M12 · 60 Nm</span></div>
            <div class="mm-note"><svg class="i"><use href="#ic-help"/></svg>Pfosten lot- &amp; fluchtrecht ausrichten, dann Anker anziehen. Ablaufpfosten vor dem Setzen ausrichten.</div>
        </section>

        {{-- 5 · Profile & Konstruktion --}}
        <section class="mm-card c6" id="s5">
            <div class="mm-h"><span class="n">5</span>Profile &amp; Konstruktion</div>
            <div class="mm-row"><span class="rk">Unterzug</span><span class="rv mono">2 × 120 × 60 mm</span></div>
            <div class="mm-row"><span class="rk">Dachsparren</span><span class="rv mono">{{ $kalk['rafters'] }} × 80 × 60 mm</span></div>
            <div class="mm-row"><span class="rk">Sparrenabstand<br><small class="hint">Achse Mitte–Mitte</small></span><span class="rv mono">{{ $kalk['sparText'] }}</span></div>
            <div class="mm-row"><span class="rk">Gefälle</span><span class="rv mono">{{ (int) $pcfg['slope'] }}° ≈ {{ round(tan(deg2rad((int) $pcfg['slope'])) * 1000) }} mm/m</span></div>
            <div class="mm-row"><span class="rk">Wandblende<br><small class="hint">Sparrenabstand {{ $kalk['sparText'] }} − 60 mm</small></span><span class="rv mono">{{ $kalk['blendeText'] }}</span></div>
            <div class="mm-row"><span class="rk">Dübel<br><small class="hint">Abstand {{ $F($pcfg['duebel']['abstand'] ?: 500) }} mm</small></span>
                <span class="rv fx">{{ $pcfg['duebel']['typ'] }}
                    <input class="inp" style="width:120px" name="duebel_size" form="aufmass-form"
                           placeholder="Größe" value="{{ $duebelSize }}"></span></div>
            <div class="mm-note"><svg class="i"><use href="#ic-help"/></svg>Gefälle Richtung Traufe/Rinne (vorn) einhalten. Dübelgröße vor Ort nach Untergrund anpassen.</div>
        </section>

        {{-- 6 · Verglasung --}}
        <section class="mm-card c5" id="s6">
            <div class="mm-h"><span class="n">6</span>Verglasung<span class="mm-sub" style="margin-left:auto">Feld · Ansicht außen</span></div>
            @include('projekte.montage-partials.extra-zeichnung', ['z' => $glasZeichnung, 'klein' => true])
            <div class="mm-row"><span class="rk">Felder</span><span class="rv mono">{{ $kalk['fields'] }} × {{ $F(max(0, $kalk['spar'] - 20)) }} × {{ $F(max(0, $pcfg['depth'] - 50)) }} mm</span></div>
            <div class="mm-row"><span class="rk">Ausführung</span><span class="rv">{{ $pcfg['covering'] }} · {{ $pcfg['thickness'] }}</span></div>
            <div class="mm-row"><span class="rk">Farbe</span><span class="rv">{{ $pcfg['glasTrans'] === 'Milch' ? 'Milch' : 'Klar' }}</span></div>
            <div class="mm-note" style="background:var(--redbg);color:var(--red)"><svg class="i"><use href="#ic-bell"/></svg>Glas nur zu zweit mit Saugheber einsetzen · Kantenschutz.</div>
        </section>

        {{-- 7 · Beleuchtung · LED --}}
        @include('projekte.montage-partials.led')

        {{-- 8 · Endmaße der Extras --}}
        @include('projekte.montage-partials.endmasse')

        {{-- 9 · Notiz / Problem --}}
        <section class="mm-card c6" id="s9">
            <div class="mm-h"><span class="n">9</span>Notiz / Problem</div>
            <form method="POST" action="{{ route('projekte.montage.notizen', $projekt) }}">
                @csrf
                <div class="radiorow" style="margin-bottom:9px">
                    @foreach (['hinweis' => 'Hinweis', 'problem' => 'Problem', 'aenderung' => 'Änderung'] as $wert => $label)
                        <label class="rpill {{ $loop->first ? 'on' : '' }}" data-radiopill>
                            <input type="radio" name="typ" value="{{ $wert }}" @checked($loop->first) hidden>
                            <span class="rdot"></span>{{ $label }}
                        </label>
                    @endforeach
                </div>
                <textarea class="inp" name="text" rows="3" placeholder="Was ist aufgefallen? Abweichung, Schaden, Kundenwunsch …"></textarea>
                <button class="btn" style="width:100%;margin-top:9px" type="submit">
                    <svg class="i"><use href="#ic-plus"/></svg>Notiz hinzufügen</button>
            </form>
            @foreach ($projekt->montageNotizen->sortByDesc('id') as $notiz)
                <div class="mm-row">
                    <span class="badge {{ match ($notiz->typ) { 'problem' => 'b-red', 'aenderung' => 'b-yellow', default => 'b-blue' } }}">{{ ucfirst($notiz->typ === 'aenderung' ? 'Änderung' : $notiz->typ) }}</span>
                    <span class="rk" style="flex:1">{{ $notiz->text }}</span>
                    <span class="rv mono">{{ $notiz->created_at->format('H:i') }} Uhr</span>
                    <form method="POST" action="{{ route('projekte.montage.notizen.loeschen', [$projekt, $notiz]) }}">
                        @csrf
                        <button class="lnav" type="submit" aria-label="Löschen"><svg class="i"><use href="#ic-x"/></svg></button>
                    </form>
                </div>
            @endforeach
        </section>

        {{-- 10 · Zusatzmaterial --}}
        <section class="mm-card c6" id="s10">
            <div class="mm-h"><span class="n">10</span>Zusatzmaterial<span class="mm-sub" style="margin-left:auto">{{ $projekt->montageZusatzmaterial->count() }} Positionen</span></div>
            <form method="POST" action="{{ route('projekte.montage.material', $projekt) }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 92px;gap:8px">
                    <input class="inp" name="bezeichnung" placeholder="Material / Artikel …">
                    <input class="inp" type="number" name="menge" placeholder="Menge" value="1">
                </div>
                <button class="btn" style="width:100%;margin-top:9px" type="submit">
                    <svg class="i"><use href="#ic-plus"/></svg>Zusatzmaterial erfassen</button>
            </form>
            @foreach ($projekt->montageZusatzmaterial->sortByDesc('id') as $zeile)
                <div class="mm-row">
                    <span class="rk" style="flex:1">{{ $zeile->bezeichnung }}</span>
                    <span class="rv mono">{{ $zeile->menge }} ×</span>
                    <form method="POST" action="{{ route('projekte.montage.material.loeschen', [$projekt, $zeile]) }}">
                        @csrf
                        <button class="lnav" type="submit" aria-label="Löschen"><svg class="i"><use href="#ic-x"/></svg></button>
                    </form>
                </div>
            @endforeach
            <div class="mm-note"><svg class="i"><use href="#ic-lager"/></svg>Erfasstes Zusatzmaterial wird dem Projekt zur Nachberechnung zugebucht.</div>
        </section>

        {{-- 11 · Montage-Checkliste --}}
        @include('projekte.montage-partials.checkliste')

    </div>
</div>
@endsection
