@php use App\Support\Format; @endphp

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Projekte</span>
        <span class="pill">{{ $projekte->count() }} Projekte</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead>
            <tr><th>Nr.</th><th>Projekt</th><th>Kunde</th><th>Montage</th><th>Auftragswert</th><th>Angebot</th><th class="num">Status</th></tr>
            </thead>
            <tbody>
            @foreach ($projekte as $projekt)
                <tr class="lrow" onclick="window.location='{{ route('projekte.show', $projekt) }}'">
                    <td class="b mono">{{ $projekt->nr }}</td>
                    <td class="b">{{ $projekt->titel }}</td>
                    <td>{{ $projekt->kunde->anzeigename }}<div class="hint mono">{{ $projekt->kunde->kunden_nr }}</div></td>
                    <td class="mono">{{ $projekt->termin_von ? Format::datumKurz($projekt->termin_von) : '–' }}</td>
                    <td class="mono">{{ $projekt->angebot?->summe !== null && $projekt->angebot ? Format::eur($projekt->angebot->summe) : 'in Konfiguration' }}</td>
                    <td class="mono">{{ $projekt->angebot?->nr ?? '–' }}</td>
                    <td class="num"><span class="badge {{ $projekt->status->badgeClass() }}">{{ $projekt->status->label() }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
