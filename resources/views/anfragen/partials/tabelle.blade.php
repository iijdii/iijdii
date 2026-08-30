@php use App\Support\Format; @endphp

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Anfragen</span>
        <span class="pill">{{ $anfragen->count() }} Anfragen</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead><tr><th>Nr.</th><th>Kunde</th><th>Produkt</th><th>Aufmaß</th><th class="num">Status</th></tr></thead>
            <tbody>
            @foreach ($anfragen as $anfrage)
                <tr class="lrow" onclick="window.location='{{ route('anfragen.show', $anfrage) }}'">
                    <td class="b mono">{{ $anfrage->nummer }}</td>
                    <td class="b">{{ $anfrage->kunde->anzeigename }}</td>
                    <td>{{ $anfrage->produkt_notiz ?? '–' }}</td>
                    <td class="mono">{{ Format::datumKurz($anfrage->besuchstermin_datum) }}</td>
                    <td class="num"><span class="badge {{ $anfrage->status->badgeClass() }}">{{ $anfrage->status->label() }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
