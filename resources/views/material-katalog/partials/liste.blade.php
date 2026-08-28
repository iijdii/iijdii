@php use App\Support\Format; @endphp

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Material-Katalog</span>
        <span class="pill">{{ $gefiltert->count() }} Artikel</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead>
            <tr>
                <th style="width:58px"></th><th>Artikel</th><th>Art.-Nr.</th><th>Kategorie</th>
                <th>Lieferant</th><th>Einheit</th><th>Lagerort</th><th>EK-Preis</th><th>Bestand</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($gefiltert as $a)
                @php
                    [$kurz, $bc] = match ($a->bestandsstatus()) {
                        'ok' => ['verfügbar', 'b-green'],
                        'niedrig' => ['knapp', 'b-yellow'],
                        'leer' => ['leer', 'b-red'],
                    };
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
                    <td><span class="b">{{ $a->name }}</span></td>
                    <td class="mono">{{ $a->art_nr }}</td>
                    <td><span class="pill {{ $a->kategorie->value === 'glas' ? 'kat-glas' : ($a->kategorie->value === 'profile' ? 'kat-alu' : 'kat-mix') }}">{{ $a->kategorie->label() }}</span></td>
                    <td>{{ $a->lieferant->name }}</td>
                    <td>{{ $a->einheit->value }}</td>
                    <td class="mono">{{ $a->lagerort }}</td>
                    <td class="mono">{{ Format::eur($a->ek_preis) }}</td>
                    <td><span class="badge {{ $bc }}">{{ $a->bestand }} · {{ $kurz }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
