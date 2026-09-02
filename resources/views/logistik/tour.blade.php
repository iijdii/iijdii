@extends('layouts.app')

@section('title', 'Tour · '.$tour->nr)

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="jb" style="flex-wrap:wrap;gap:10px">
        <a class="btn btns" href="{{ route('logistik') }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Zurück zur Logistik</a>
        <span class="ctas">
            <form method="POST" action="{{ route('logistik.tour.alle', $tour) }}" style="display:inline">
                @csrf<input type="hidden" name="markieren" value="0">
                <button class="btn btns" type="submit">Zurücksetzen</button>
            </form>
            <form method="POST" action="{{ route('logistik.tour.alle', $tour) }}" style="display:inline">
                @csrf<input type="hidden" name="markieren" value="1">
                <button class="btn btns" type="submit"><svg class="i"><use href="#ic-check"/></svg>Alle markieren</button>
            </form>
        </span>
    </div>

    <div class="card">
        <div class="fx ac gap10" style="flex-wrap:wrap">
            <span class="lgbadge">{{ $tour->nr }}</span>
            <span class="badge b-blue">{{ $auftraege->count() }} Aufträge</span>
        </div>
        <h2 class="serif" style="font-size:25px;margin:8px 0 4px">{{ $tour->fahrzeug }} · {{ $tour->kennzeichen }}</h2>
        <div style="font-size:13px;font-weight:500;color:var(--ink3)">
            Ladedatum {{ Format::datumKurz($tour->datum) }} · {{ $tour->fahrer }}</div>
    </div>

    <div class="cols-2" style="grid-template-columns:minmax(0,1.7fr) minmax(240px,1fr);align-items:start">
        <div class="colstack">
            @foreach ($auftraege as $auftrag)
                @php $bestellung = $auftrag['bestellung']; @endphp
                <div class="card p0">
                    <div class="card-h">
                        <span class="card-t">{{ $bestellung->nr }} — {{ $bestellung->titel }}
                            <span style="display:block;font-size:11px;font-weight:500;color:var(--ink3);text-transform:none;letter-spacing:0">
                                {{ $bestellung->kunde?->anzeigename ?? '–' }} · {{ $bestellung->projekt?->nr ?? '–' }}</span></span>
                        <span class="fx ac gap8">
                            <span class="pill">{{ $auftrag['done'] }}/{{ $auftrag['tot'] }}</span>
                            <form method="POST" action="{{ route('logistik.alle', $bestellung) }}">
                                @csrf<input type="hidden" name="markieren" value="1">
                                <button class="btn btns" type="submit">Alle</button>
                            </form>
                        </span>
                    </div>
                    <div class="card-b"><div class="lglist">
                        @foreach ($auftrag['positionen'] as $zeile)
                            @php $position = $zeile['position']; @endphp
                            <form method="POST" action="{{ route('logistik.position.toggle', [$bestellung, $position]) }}" style="display:flex">
                                @csrf
                                <button class="lgrow {{ $position->kommissioniert_am ? 'on' : '' }}" type="submit">
                                    <span class="lgbox"><svg class="i"><use href="#ic-check"/></svg></span>
                                    <span class="lgtxt">
                                        <span class="lgname">{{ $position->bezeichnung }}</span>
                                        <span class="lgdet">{{ $zeile['gruppe'] }} · {{ $zeile['detail'] }}</span>
                                    </span>
                                    <span class="lgqty">{{ $zeile['menge'] }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="colstack">
            <div class="kbox">
                <div class="kt">Ladung gesamt</div>
                <div class="lgstat"><b>{{ $done }}/{{ $tot }}</b><span>Positionen geladen</span></div>
                <div class="lgbar {{ $done === $tot && $tot > 0 ? '' : 'part' }}"><i style="width:{{ $pct }}%"></i></div>
                <div class="kgrid" style="margin-top:12px">
                    <div class="kcell"><div class="kk">Geladen</div><div class="kv">{{ $done }}</div></div>
                    <div class="kcell"><div class="kk">Offen</div><div class="kv">{{ $offen }}</div></div>
                </div>
            </div>
            <div class="card p0">
                <div class="card-h"><span class="card-t">Aufträge in dieser Tour</span></div>
                <div class="card-b" style="padding:6px 17px"><table class="tbl"><tbody>
                    @foreach ($auftraege as $auftrag)
                        <tr>
                            <td><span class="b mono">{{ $auftrag['bestellung']->nr }}</span>
                                <div style="font-size:11px;color:var(--ink3)">{{ $auftrag['bestellung']->kunde?->anzeigename ?? '–' }}</div></td>
                            <td class="num" style="width:110px">
                                <span class="mono b">{{ $auftrag['done'] }}/{{ $auftrag['tot'] }}</span>
                                <span class="lgbar {{ $auftrag['status'] === 'fertig' ? '' : 'part' }}" style="display:block;margin-top:4px"><i style="width:{{ $auftrag['pct'] }}%"></i></span>
                            </td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            </div>
            <a class="btn btnp" href="{{ route('logistik.lade', $tour) }}" style="width:100%">
                <svg class="i"><use href="#ic-tablet"/></svg>Lade-Modus öffnen</a>
            <form method="POST" action="{{ route('logistik.tour.abschliessen', $tour) }}">
                @csrf
                <button class="btn" type="submit" style="width:100%">
                    <svg class="i"><use href="#ic-truck"/></svg>Ladung abschließen</button>
            </form>
        </div>
    </div>

</div>
@endsection
