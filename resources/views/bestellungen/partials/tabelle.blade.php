@php use App\Support\Format; @endphp

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Bestellungen</span>
        <span class="pill">{{ $karten->count() }} Bestellungen</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead>
            <tr><th>Nr.</th><th>Projekt</th><th>Lieferant</th><th>Kategorie</th><th>Liefertermin</th><th class="num">Status</th></tr>
            </thead>
            <tbody>
            @foreach ($karten as $karte)
                @php $b = $karte['bestellung']; @endphp
                <tr class="lrow" onclick="window.location='{{ route('bestellungen.show', $b) }}'">
                    <td class="b mono">{{ $b->nr }}</td>
                    <td class="mono">{{ $b->projekt?->nr ?? '–' }}</td>
                    <td>{{ $b->lieferant?->name ?? '— wählen —' }}</td>
                    <td><span class="pill {{ $b->kategoriePillClass() }}">{{ $b->kategorieLabel() }}</span></td>
                    <td class="mono">{{ Format::datumKurz($b->liefertermin) }}</td>
                    <td class="num"><span class="badge {{ $b->status->badgeClass() }}">{{ $b->status->label() }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
