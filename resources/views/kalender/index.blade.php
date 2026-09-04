@extends('layouts.app')

@section('title', 'Kalender')

@section('content')
    <div class="cal-tools">
        <div class="cal-wk"><b>{{ $kw }}</b><span>{{ $zeitraum }}</span></div>
        <div class="fx ac gap8">
            <div class="cal-nav">
                <a class="btn btns" href="{{ route('kalender', ['woche' => $vorher]) }}" aria-label="Vorherige Woche">
                    <svg class="i" style="width:15px;height:15px"><use href="#ic-aleft"/></svg></a>
                <a class="btn btns" href="{{ route('kalender') }}">Heute</a>
                <a class="btn btns" href="{{ route('kalender', ['woche' => $nachher]) }}" aria-label="Nächste Woche">
                    <svg class="i" style="width:15px;height:15px;transform:scaleX(-1)"><use href="#ic-aleft"/></svg></a>
                <a class="btn btns btnp" href="{{ route('kalender.termin') }}">
                    <svg class="i" style="width:15px;height:15px"><use href="#ic-plus"/></svg>Termin</a>
            </div>
        </div>
    </div>

    <div class="cal-grid">
        @foreach ($tage as $tag)
            <div class="cal-day {{ $tag['heute'] ? 'today' : '' }}">
                <div class="cal-dh"><span class="dn">{{ $tag['name'] }}</span><span class="dd">{{ $tag['label'] }}</span></div>
                <div class="cal-db">
                    @if (empty($tag['events']))
                        <div class="cal-empty">Keine Termine</div>
                    @endif
                    @foreach ($tag['events'] as $event)
                        <a class="cal-ev {{ $event['cls'] }}" href="{{ $event['url'] }}" style="display:block;text-decoration:none;color:inherit">
                            <div class="cal-et">{{ $event['zeit'] }}</div>
                            <div class="cal-et" style="color:var(--blued)">{{ $event['nr'] }}</div>
                            <div class="cal-ett">{{ $event['titel'] }}</div>
                            <div class="cal-em">{{ $event['meta'] }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="cal-legend">
        @foreach ($legende as $eintrag)
            <span class="cal-lg"><span class="d {{ $eintrag['bc'] }}"></span>{{ $eintrag['label'] }}</span>
        @endforeach
    </div>

    <div class="card p0" style="margin-top:16px">
        <div class="card-h"><div class="card-t">Nächste Termine</div><span class="pill">{{ $kommende->count() }}</span></div>
        <div class="card-b" style="padding:6px 17px">
            @if ($kommende->isEmpty())
                <p class="hint" style="padding:10px 0">Keine Termine in dieser Woche.</p>
            @else
                <table class="tbl">
                    <thead><tr><th>Datum</th><th>Typ</th><th>Projekt / Titel</th><th>Ort &amp; Kunde</th></tr></thead>
                    <tbody>
                    @foreach ($kommende as $event)
                        <tr class="lrow" onclick="window.location='{{ $event['url'] }}'">
                            <td class="b mono">{{ $event['tag'] }} {{ $event['datum'] }}</td>
                            <td><span class="badge {{ $event['bc'] }}">{{ $event['typ'] }}</span></td>
                            <td><span class="mono" style="color:var(--blued)">{{ $event['nr'] }}</span> · {{ $event['titel'] }}</td>
                            <td>{{ $event['meta'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
