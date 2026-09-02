@extends('layouts.montage')

@section('title', 'Lade-Modus · '.$tour->nr)

@php use App\Support\Format; @endphp

@section('content')
<div class="mm">
    <header class="mm-top">
        <a class="mm-back" href="{{ route('logistik.tour', $tour) }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Zurück</a>
        <span class="mm-tt"><b>LADE-MODUS</b><span>{{ $tour->nr }} · {{ $tour->fahrzeug }} · {{ $tour->kennzeichen }}</span></span>
        <nav class="ld-seg" style="margin-left:auto">
            <a class="{{ $tab === 'laden' ? 'on' : '' }}" href="{{ route('logistik.lade', $tour) }}">
                <svg class="i"><use href="#ic-truck"/></svg>Laden</a>
            <a class="{{ $tab === 'tour' ? 'on' : '' }}" href="{{ route('logistik.lade', [$tour, 'tab' => 'tour']) }}">
                <svg class="i"><use href="#ic-kalender"/></svg>Tour</a>
        </nav>
        <span class="mm-tt" style="text-align:right"><b>{{ $done }}/{{ $tot }} geladen</b>
            <span>{{ Format::datumKurz($tour->datum) }} · {{ $tour->fahrer }}</span></span>
    </header>

    <div class="mm-body" style="display:block;max-width:1060px">
        <div class="mm-kpis" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
            <div class="mm-kpi"><span class="kl">Aufträge</span><b class="mono">{{ $auftraege->count() }}</b></div>
            <div class="mm-kpi"><span class="kl">Positionen</span><b class="mono">{{ $tot }}</b></div>
            <div class="mm-kpi"><span class="kl">Geladen</span><b class="mono">{{ $done }}</b></div>
            <div class="mm-kpi"><span class="kl">Offen</span><b class="mono">{{ $offen }}</b></div>
        </div>

        @if ($tab === 'laden')
            <div class="colstack" style="gap:16px">
                @foreach ($auftraege as $auftrag)
                    @php $bestellung = $auftrag['bestellung']; @endphp
                    <section class="mm-card">
                        <div class="ld-oh">
                            <div>
                                <span class="ld-onr">{{ $bestellung->nr }}</span>
                                <div class="ld-oti">{{ $bestellung->titel }}</div>
                                <div class="ld-osub">{{ $bestellung->kunde?->anzeigename ?? '–' }} · {{ $bestellung->projekt?->nr ?? '–' }}</div>
                            </div>
                            <div class="fx ac gap10">
                                <span class="ld-cnt">{{ $auftrag['done'] }}/{{ $auftrag['tot'] }}</span>
                                <form method="POST" action="{{ route('logistik.alle', $bestellung) }}">
                                    @csrf<input type="hidden" name="markieren" value="1">
                                    <button class="btn btns" type="submit">Alle</button>
                                </form>
                            </div>
                        </div>
                        <div class="ld-list">
                            @foreach ($auftrag['positionen'] as $zeile)
                                @php $position = $zeile['position']; @endphp
                                <div>
                                    <form method="POST" action="{{ route('logistik.position.toggle', [$bestellung, $position]) }}" style="display:flex">
                                        @csrf
                                        <button class="ld-row {{ $position->kommissioniert_am ? 'on' : '' }}" type="submit">
                                            <span class="ld-box"><svg class="i"><use href="#ic-check"/></svg></span>
                                            <span class="ld-tx">
                                                <span class="ld-nm">{{ $position->bezeichnung }}</span>
                                                <span class="ld-dt">{{ $zeile['gruppe'] }} · {{ $zeile['detail'] }}</span>
                                            </span>
                                            <span class="ld-qt">{{ $zeile['menge'] }}</span>
                                        </button>
                                    </form>
                                    @if ($position->kommissionier_notiz)
                                        <div class="ld-note"><svg class="i"><use href="#ic-pen"/></svg>{{ $position->kommissionier_notiz }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <form method="POST" action="{{ route('logistik.abfahrt', $tour) }}">
                    @csrf
                    <button class="btn btnp ld-big" type="submit">
                        <svg class="i"><use href="#ic-truck"/></svg>Beladung fertig · Abfahrt bestätigen</button>
                </form>
            </div>
        @else
            <div class="colstack" style="gap:16px">
                <section class="mm-card">
                    <div class="mm-h">Lieferstopps · {{ $stopps->count() }} Adressen</div>
                    <div class="colstack" style="gap:10px">
                        @foreach ($stopps as $stopp)
                            <div class="ld-stop {{ $stopp['fertig'] ? 'done' : '' }}">
                                <span class="ld-num">{{ $stopp['n'] }}</span>
                                <span style="flex:1;min-width:0">
                                    <span class="ld-addr">{{ $stopp['kunde'] }} · {{ $stopp['strasse'] }}</span>
                                    <span class="ld-meta" style="display:block">{{ $stopp['ort'] }} ·
                                        {{ $stopp['auftrag']['bestellung']->nr }} · {{ $stopp['auftrag']['done'] }}/{{ $stopp['auftrag']['tot'] }} Positionen</span>
                                </span>
                                @if ($stopp['tel'])
                                    <a class="ld-call" href="{{ $stopp['tel'] }}">
                                        <svg class="i"><use href="#ic-phone"/></svg>Anrufen</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
                <section class="mm-card">
                    <div class="mm-h">Fahrzeug &amp; Fahrer</div>
                    <div class="mm-row"><span class="rk">Fahrzeug</span><span class="rv">{{ $tour->fahrzeug }}</span></div>
                    <div class="mm-row"><span class="rk">Kennzeichen</span><span class="rv mono">{{ $tour->kennzeichen }}</span></div>
                    <div class="mm-row"><span class="rk">Ladedatum</span><span class="rv mono">{{ Format::datumKurz($tour->datum) }}</span></div>
                    <div class="mm-row"><span class="rk">Fahrer</span><span class="rv">{{ $tour->fahrer }}</span></div>
                </section>
            </div>
        @endif
    </div>
</div>
@endsection
