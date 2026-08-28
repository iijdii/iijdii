@php use App\Support\Format; @endphp

@if ($low->isNotEmpty())
    <div class="card lg-low">
        <div class="card-b fx">
            <svg class="i" style="color:var(--red)"><use href="#ic-bell"/></svg>
            <div style="flex:1">
                <b>{{ $low->count() }} Artikel unter Mindestbestand</b>
                <div class="hint">
                    {{ $low->take(5)->map(fn ($a) => $a->name.' ('.$a->verfuegbar().' / min '.$a->min_bestand.')')->join('  ·  ') }}
                </div>
            </div>
            <button class="btn btns" type="button" data-toast="Bestellvorschlag für {{ $low->count() }} Artikel erzeugt">
                <svg class="i"><use href="#ic-bestellungen"/></svg>Bestellvorschlag</button>
        </div>
    </div>
@endif

<div class="chips">
    @foreach ($chips as $chip)
        <a class="chip {{ $chip['aktiv'] ? 'on' : '' }}"
           href="{{ route('lager', ['tab' => 'bestand', 'kategorie' => $chip['key']]) }}">
            {{ $chip['label'] }}<span class="ct">{{ $chip['anzahl'] }}</span>
        </a>
    @endforeach
</div>

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Lagerbestand</span>
        <span class="pill">{{ $gefiltert->count() }} Artikel</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead>
            <tr>
                <th style="width:58px"></th><th>Artikel</th><th>Kategorie</th><th>Lagerort</th>
                <th>Bestand</th><th>Reserviert</th><th>Verfügbar</th><th>Min.</th><th>EK</th><th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($gefiltert as $a)
                @php
                    $status = $a->bestandsstatus();
                    [$badge, $bc] = match ($status) {
                        'ok' => ['Auf Lager', 'b-green'],
                        'niedrig' => ['Niedrig', 'b-yellow'],
                        'leer' => ['Nachbestellen', 'b-red'],
                    };
                    $bar = match ($status) { 'ok' => '', 'niedrig' => 'part', 'leer' => 'low' };
                    $zugang = $letzterZugang->get($a->id);
                @endphp
                <tr class="lrow" data-modal-url="{{ route('lager.artikel', $a) }}">
                    <td>
                        @if ($a->bild)
                            <img class="artimg" src="{{ asset('images/comp/'.$a->bild.'.png') }}" alt="">
                        @else
                            <span class="artimg fx" style="justify-content:center;color:var(--ink3)">
                                <svg class="i" style="width:15px;height:15px"><use href="#ic-lager"/></svg></span>
                        @endif
                    </td>
                    <td>
                        <span class="b">{{ $a->name }}</span>
                        <div class="artsub">
                            <span class="mono">{{ $a->art_nr }}</span> · {{ $a->lieferant->name }}
                            @if ($zugang)
                                · <span class="lgok">Zugang {{ Format::datumKurz($zugang['datum']) }} {{ $zugang['nr'] }}</span>
                            @endif
                        </div>
                    </td>
                    <td><span class="pill {{ $a->kategorie->value === 'glas' ? 'kat-glas' : ($a->kategorie->value === 'profile' ? 'kat-alu' : 'kat-mix') }}">{{ $a->kategorie->label() }}</span></td>
                    <td class="mono">{{ $a->lagerort }}</td>
                    <td>
                        <span class="artbar">
                            <b class="mono">{{ $a->bestand }}</b>
                            <span class="lgbar {{ $bar }}"><i style="width:{{ $a->fuellstandProzent() }}%"></i></span>
                        </span>
                    </td>
                    <td class="mono">{{ $a->reserviert() > 0 ? $a->reserviert() : '–' }}</td>
                    <td><b class="mono">{{ $a->verfuegbar() }}</b></td>
                    <td class="mono" style="color:var(--ink3)">{{ $a->min_bestand }}</td>
                    <td class="mono">{{ Format::eur($a->ek_preis) }}</td>
                    <td><span class="badge {{ $bc }}">{{ $badge }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
