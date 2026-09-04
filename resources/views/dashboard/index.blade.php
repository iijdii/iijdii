@extends('layouts.app')

@section('title', 'Dashboard')

@php use App\Support\Format; @endphp

@section('content')
    {{-- KPI-Zeile (Prototyp Z. 900/1626) --}}
    <div class="kpirow">
        @foreach ($kpis as $kpi)
            <div class="kpi2 {{ $kpi['c'] }}">
                <div class="kl">{{ $kpi['k'] }}</div>
                <div class="kn">{{ $kpi['v'] }}</div>
                <div class="ks">{{ $kpi['s'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="dash-2">
        <div class="dstack">
            {{-- Vertriebs-Trichter --}}
            <div class="card">
                <div class="card-h"><div class="card-t">Vertriebs-Trichter</div><span class="pill">{{ $funnelMonat }}</span></div>
                <div class="card-b"><div class="funnel">
                    @foreach ($funnel as $stufe)
                        <div class="fnl">
                            <span class="fnl-l">{{ $stufe['label'] }}</span>
                            <div class="fnl-track"><div class="fnl-fill" style="width:{{ $stufe['breite'] }}%">{{ $stufe['count'] }}</div></div>
                            <span class="fnl-conv">{{ $stufe['conv'] }}</span>
                        </div>
                    @endforeach
                </div></div>
            </div>

            {{-- Auftragseingang --}}
            <div class="card">
                <div class="card-h"><div class="card-t">Auftragseingang</div><span class="pill">6 Monate · k€</span></div>
                <div class="card-b">
                    <div class="bars">
                        @foreach ($bars as $bar)
                            <div class="bar {{ $bar['on'] ? 'on' : '' }}"><div class="bt" style="height:{{ $bar['hoehe'] }}%"><span class="bv">{{ $bar['v'] }}</span></div></div>
                        @endforeach
                    </div>
                    <div class="barx">
                        @foreach ($bars as $bar)<span>{{ $bar['m'] }}</span>@endforeach
                    </div>
                </div>
            </div>

            {{-- Letzte Angebote --}}
            <div class="card p0">
                <div class="card-h"><div class="card-t">Letzte Angebote</div>
                    <a class="btn btns btng" href="{{ route('angebote') }}">Alle anzeigen</a></div>
                <div class="card-b" style="padding:6px 17px"><table class="tbl">
                    <thead><tr><th>Nr.</th><th>Kunde</th><th class="num">Betrag</th><th class="num">Status</th></tr></thead>
                    <tbody>
                    @foreach ($letzteAngebote as $angebot)
                        <tr class="lrow" onclick="window.location='{{ route('angebote.show', $angebot) }}'">
                            <td class="b mono">{{ $angebot->nr }}</td>
                            <td>{{ $angebot->kunde->anzeigename }}</td>
                            <td class="num mono">{{ Format::eur($angebot->summe) }}</td>
                            <td class="num"><span class="badge {{ $angebot->status->badgeClass() }}">{{ $angebot->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="dstack">
            {{-- Heutige Termine --}}
            <div class="card">
                <div class="card-h"><div class="card-t">Heutige Termine</div><span class="pill">{{ $heute }}</span></div>
                <div class="card-b">
                    @if ($termine->isEmpty())
                        <p class="hint">Keine Termine heute.</p>
                    @endif
                    @foreach ($termine as $termin)
                        <a class="trow" href="{{ $termin['url'] }}" style="text-decoration:none;color:inherit">
                            <span class="ttime">{{ $termin['zeit'] }}</span>
                            <div style="flex:1">
                                <div style="font-size:13px;font-weight:600">{{ $termin['kunde'] }}</div>
                                <div style="font-size:11.5px;color:var(--ink3)">{{ $termin['sub'] }}</div>
                            </div>
                            <span class="badge {{ $termin['bc'] }}">{{ $termin['typ'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Lager-Warnungen --}}
            <div class="card">
                <div class="card-h"><div class="card-t">Lager-Warnungen</div>
                    <span class="badge b-red">{{ $niedrig->count() }} kritisch</span></div>
                <div class="card-b">
                    @if ($niedrig->isEmpty())
                        <p class="hint">Alle Bestände im grünen Bereich.</p>
                    @endif
                    @foreach ($niedrig->take(3) as $artikel)
                        <a class="warn" href="{{ route('lager') }}" style="text-decoration:none;color:inherit">
                            <div style="font-size:13px;font-weight:600">{{ $artikel->name }}</div>
                            <div class="fx ac gap10">
                                <span class="mono" style="font-size:12.5px;color:var(--ink2)">{{ Format::menge($artikel->bestand) }} / {{ Format::menge($artikel->min_bestand) }}</span>
                                <span class="badge {{ $artikel->bestandsstatus() === 'leer' ? 'b-red' : 'b-yellow' }}">{{ $artikel->bestandsstatus() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Letzte Aktivität --}}
            <div class="card">
                <div class="card-h"><div class="card-t">Letzte Aktivität</div></div>
                <div class="card-b"><div class="tl">
                    @if ($aktivitaeten->isEmpty())
                        <p class="hint">Noch keine Aktivitäten.</p>
                    @endif
                    @foreach ($aktivitaeten as $aktivitaet)
                        <div class="tli">
                            <div class="tld {{ $aktivitaet->status }}">
                                @if ($aktivitaet->status === 'done')
                                    <svg class="i"><use href="#ic-check"/></svg>
                                @endif
                            </div>
                            <div class="tlx">
                                <div>
                                    <div class="tlt">{{ $aktivitaet->titel }}</div>
                                    <div class="tls">{{ $aktivitaet->wer }} · {{ $aktivitaet->projekt->nr }}</div>
                                </div>
                                <div class="tldate">{{ $aktivitaet->datum }}</div>
                            </div>
                        </div>
                    @endforeach
                </div></div>
            </div>
        </div>
    </div>
@endsection
