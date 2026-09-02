@extends('layouts.app')

@section('title', 'Logistik')

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="anf-tools">
        <div class="chips">
            @foreach ($chips as $chip)
                <a class="chip {{ $chip['aktiv'] ? 'on' : '' }}" href="{{ route('logistik', ['filter' => $chip['key']]) }}">
                    {{ $chip['label'] }}<span class="ct">{{ $chip['anzahl'] }}</span>
                </a>
            @endforeach
        </div>
        <span class="pill"><svg class="i" style="width:14px;height:14px"><use href="#ic-truck"/></svg>Kommissionierung</span>
    </div>

    @if ($touren->isNotEmpty())
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Touren · Fahrzeugbeladung</span>
                <span class="pill">Mehrere Aufträge je Fahrzeug</span>
            </div>
            <div class="card-b colstack" style="gap:9px">
                @foreach ($touren as $tour)
                    @php
                        $tourPositionen = $tour->bestellungen->flatMap->positionen;
                        $tourDone = $tourPositionen->whereNotNull('kommissioniert_am')->count();
                        $tourTot = $tourPositionen->count();
                        $tourPct = $tourTot > 0 ? (int) round($tourDone / $tourTot * 100) : 0;
                    @endphp
                    <a class="lgtour" href="{{ Route::has('logistik.tour') ? route('logistik.tour', $tour) : route('logistik') }}">
                        <span class="lgtour-ic"><svg class="i"><use href="#ic-truck"/></svg></span>
                        <span style="flex:1;min-width:0">
                            <span style="display:block;font-size:13.5px;font-weight:700">{{ $tour->fahrzeug }} · {{ $tour->kennzeichen }}</span>
                            <span style="display:block;font-size:11.5px;font-weight:500;color:var(--ink3)">
                                {{ $tour->nr }} · {{ Format::datumKurz($tour->datum) }} · {{ $tour->fahrer }}
                                · {{ $tour->bestellungen->count() }} Aufträge ({{ $tour->bestellungen->pluck('nr')->implode(' · ') ?: '—' }})</span>
                        </span>
                        <span style="width:120px;flex:none">
                            <span class="lgstat"><b>{{ $tourDone }}/{{ $tourTot }}</b><span>{{ $tourPct }}%</span></span>
                            <span class="lgbar {{ $tourDone === $tourTot && $tourTot > 0 ? '' : 'part' }}"><i style="width:{{ $tourPct }}%"></i></span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('logistik.touren.erstellen') }}">
        @csrf

        <div class="lgbulk">
            <svg class="i" style="width:17px;height:17px;color:var(--blued)"><use href="#ic-check"/></svg>
            <span style="font-size:13px;font-weight:600;color:var(--blued);flex:1">Aufträge für eine Tour ausgewählt</span>
            <a class="btn btns btng" href="{{ route('logistik') }}">Auswahl löschen</a>
            <button class="btn btns btnp" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Tour erstellen</button>
        </div>

        <div class="card p0">
            <div class="card-b" style="padding:6px 17px;overflow-x:auto">
                @if ($auftraege->isEmpty())
                    <p class="hint" style="padding:12px 0">Keine Aufträge in der Kommissionierung.</p>
                @else
                    <table class="tbl">
                        <thead><tr>
                            <th style="width:28px"></th><th>Bestellung</th><th>Auftrag / Kunde</th><th>Inhalt</th>
                            <th class="num">Glas</th><th class="num">Schiebe</th><th class="num">Material</th>
                            <th class="num">Stk</th><th style="min-width:132px">Fortschritt</th>
                            <th>Liefertermin</th><th class="num">Status</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($auftraege as $auftrag)
                            @php $bestellung = $auftrag['bestellung']; @endphp
                            <tr class="lrow" onclick="window.location='{{ route('logistik.bestellung', $bestellung) }}'">
                                <td onclick="event.stopPropagation()">
                                    <label class="lgpick">
                                        <input type="checkbox" name="bestellungen[]" value="{{ $bestellung->id }}" hidden>
                                        <svg class="i"><use href="#ic-check"/></svg>
                                    </label>
                                </td>
                                <td><span class="b mono">{{ $bestellung->nr }}</span>
                                    <div style="font-size:11px;color:var(--ink3)">{{ $bestellung->lieferant->name }}</div></td>
                                <td>{{ $bestellung->kunde?->anzeigename ?? '–' }}
                                    <div class="mono" style="font-size:11px;color:var(--ink3)">{{ $bestellung->projekt?->nr ?? '–' }}</div></td>
                                <td class="b">{{ $bestellung->titel }}
                                    <div style="font-size:11px;font-weight:500;color:var(--ink3)">{{ $auftrag['mix'] }}</div></td>
                                <td class="num mono">{{ $auftrag['nGlas'] ?: '–' }}</td>
                                <td class="num mono">{{ $auftrag['nSchiebe'] ?: '–' }}</td>
                                <td class="num mono">{{ $auftrag['nMat'] ?: '–' }}</td>
                                <td class="num mono">{{ $auftrag['stk'] }}</td>
                                <td>
                                    <span class="lgstat"><b>{{ $auftrag['done'] }}/{{ $auftrag['tot'] }}</b><span>{{ $auftrag['pct'] }}%</span></span>
                                    <span class="lgbar {{ $auftrag['status'] === 'fertig' ? '' : 'part' }}"><i style="width:{{ $auftrag['pct'] }}%"></i></span>
                                </td>
                                <td class="mono">{{ Format::datumKurz($bestellung->liefertermin) }}</td>
                                <td class="num"><span class="badge {{ $auftrag['statusBadge'] }}">{{ $auftrag['statusLabel'] }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </form>

</div>
@endsection
