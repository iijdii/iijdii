@extends('layouts.app')

@section('title', 'Kommissionierung · '.$bestellung->nr)

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="jb" style="flex-wrap:wrap;gap:10px">
        <a class="btn btns" href="{{ route('logistik') }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Zurück zur Rüstliste</a>
        <span class="ctas">
            <form method="POST" action="{{ route('logistik.alle', $bestellung) }}" style="display:inline">
                @csrf<input type="hidden" name="markieren" value="0">
                <button class="btn btns" type="submit">Zurücksetzen</button>
            </form>
            <form method="POST" action="{{ route('logistik.alle', $bestellung) }}" style="display:inline">
                @csrf<input type="hidden" name="markieren" value="1">
                <button class="btn btns" type="submit"><svg class="i"><use href="#ic-check"/></svg>Alle markieren</button>
            </form>
        </span>
    </div>

    <div class="card">
        <div class="fx ac gap10" style="flex-wrap:wrap">
            <span class="lgbadge">{{ $bestellung->nr }}</span>
            <span class="badge {{ $bestellung->status->badgeClass() }}">{{ $bestellung->status->label() }}</span>
        </div>
        <h2 class="serif" style="font-size:25px;margin:8px 0 4px">{{ $bestellung->titel }}</h2>
        <div style="font-size:13px;font-weight:500;color:var(--ink3)">
            {{ $bestellung->kunde?->anzeigename ?? '–' }} · {{ $bestellung->projekt?->nr ?? '–' }}
            · Liefertermin {{ Format::datumKurz($bestellung->liefertermin) }}</div>
    </div>

    <div class="cols-2" style="grid-template-columns:minmax(0,1.7fr) minmax(240px,1fr);align-items:start">
        <div class="colstack">
            @foreach ($gruppen as $label => $zeilen)
                <div class="card p0">
                    <div class="card-h">
                        <span class="card-t">{{ $label }}</span>
                        <span class="pill">{{ $zeilen->count() }}</span>
                    </div>
                    <div class="card-b"><div class="lglist">
                        @foreach ($zeilen as $zeile)
                            @php $position = $zeile['position']; @endphp
                            <div class="lgitem">
                                <div class="lgmain">
                                    <form method="POST" action="{{ route('logistik.position.toggle', [$bestellung, $position]) }}" style="flex:1;min-width:0;display:flex">
                                        @csrf
                                        <button class="lgrow {{ $position->kommissioniert_am ? 'on' : '' }}" type="submit">
                                            <span class="lgbox"><svg class="i"><use href="#ic-check"/></svg></span>
                                            <span class="lgtxt">
                                                <span class="lgname">{{ $position->bezeichnung }}</span>
                                                <span class="lgdet">{{ $zeile['detail'] }}</span>
                                            </span>
                                            <span class="lgqty">{{ $zeile['menge'] }}</span>
                                        </button>
                                    </form>
                                    <a class="lgnbtn {{ $position->kommissionier_notiz ? 'on' : '' }}"
                                       href="{{ route('logistik.bestellung', [$bestellung, 'notiz' => $position->id]) }}" title="Kommentar">
                                        <svg class="i"><use href="#ic-pen"/></svg></a>
                                </div>
                                @if ($notizEdit === $position->id)
                                    <div class="lgedit">
                                        <form method="POST" action="{{ route('logistik.notiz', [$bestellung, $position]) }}"
                                              style="display:flex;gap:7px;flex:1;align-items:center;flex-wrap:wrap">
                                            @csrf
                                            <input class="inp" name="notiz" value="{{ $position->kommissionier_notiz }}"
                                                   placeholder="Kommentar zur Position …" style="flex:1;min-width:150px;height:34px">
                                            <button class="btn btns btnp" type="submit">Speichern</button>
                                            <a class="btn btns" href="{{ route('logistik.bestellung', $bestellung) }}">Abbrechen</a>
                                        </form>
                                        @if ($position->kommissionier_notiz)
                                            <form method="POST" action="{{ route('logistik.notiz', [$bestellung, $position]) }}">
                                                @csrf<input type="hidden" name="notiz" value="">
                                                <button class="btn btns" type="submit" style="color:var(--red)">Löschen</button>
                                            </form>
                                        @endif
                                    </div>
                                @elseif ($position->kommissionier_notiz)
                                    <div class="lgnote"><svg class="i"><use href="#ic-pen"/></svg>{{ $position->kommissionier_notiz }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="colstack">
            <div class="kbox">
                <div class="kt">Fortschritt</div>
                <div class="lgstat"><b>{{ $done }}/{{ $tot }}</b><span>Positionen gepackt</span></div>
                <div class="lgbar {{ $done === $tot && $tot > 0 ? '' : 'part' }}"><i style="width:{{ $pct }}%"></i></div>
                <div class="kgrid" style="margin-top:12px">
                    <div class="kcell"><div class="kk">Gepackt</div><div class="kv">{{ $done }}</div></div>
                    <div class="kcell"><div class="kk">Offen</div><div class="kv">{{ $offen }}</div></div>
                </div>
            </div>
            <div class="card">
                <div class="mc-h"><svg class="i"><use href="#ic-truck"/></svg>Tour-Info</div>
                <div class="kv"><div class="k">Lieferant</div><div class="v">{{ $bestellung->lieferant->name }}</div></div>
                <div class="kv"><div class="k">Liefertermin</div><div class="v mono">{{ Format::datumKurz($bestellung->liefertermin) }}</div></div>
                <div class="kv"><div class="k">Hinweise</div><div class="v sub">{{ $bestellung->notizen ?? '–' }}</div></div>
            </div>
            <form method="POST" action="{{ route('logistik.abschliessen', $bestellung) }}">
                @csrf
                <button class="btn btnp" type="submit" style="width:100%">
                    <svg class="i"><use href="#ic-truck"/></svg>Kommissionierung abschließen</button>
            </form>
        </div>
    </div>

</div>
@endsection
