@php use App\Support\Format; @endphp

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Lagerbewegungen</span>
        <span class="pill">{{ $bewegungen->count() }} Buchungen</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead>
            <tr><th>Datum</th><th>Typ</th><th>Art.-Nr.</th><th>Artikel</th><th>Menge</th><th>Einheit</th><th>Referenz</th><th>Benutzer</th></tr>
            </thead>
            <tbody>
            @foreach ($bewegungen as $bewegung)
                @php
                    $bc = match ($bewegung->typ->value) {
                        'Eingang' => 'b-green',
                        'Ausgang' => 'b-gray',
                        'Reservierung' => 'b-blue',
                        'Korrektur' => 'b-yellow',
                    };
                @endphp
                <tr>
                    <td class="mono">{{ Format::datum($bewegung->datum) }}</td>
                    <td><span class="badge {{ $bc }}">{{ $bewegung->typ->value }}</span></td>
                    <td class="mono">{{ $bewegung->artikel->art_nr }}</td>
                    <td>{{ $bewegung->artikel->name }}</td>
                    <td><b class="mono">{{ $bewegung->typ->value === 'Reservierung' ? $bewegung->menge : Format::mengeSigniert($bewegung->menge) }}</b></td>
                    <td>{{ $bewegung->artikel->einheit->value }}</td>
                    <td class="mono">{{ $bewegung->referenz }}</td>
                    <td>{{ $bewegung->benutzer_name ?? $bewegung->benutzer?->name ?? '–' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
