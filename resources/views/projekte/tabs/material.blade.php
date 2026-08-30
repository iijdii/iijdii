@php
    use App\Support\Format;
    $geliefert = collect($materialListe)->where('status', 'Geliefert')->count();
@endphp

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Materialliste</span>
        <span class="pill">{{ count($materialListe) }} Positionen</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        @if ($materialListe === [])
            <p class="hint">Keine Reservierungen für dieses Projekt.</p>
        @else
            <table class="tbl">
                <thead>
                <tr><th>Pos</th><th>Bezeichnung</th><th>Artikel-Nr.</th><th>Menge</th><th>Einheit</th><th>Lagerort</th><th>Beleg</th><th class="num">Status</th></tr>
                </thead>
                <tbody>
                @foreach ($materialListe as $zeile)
                    <tr>
                        <td class="mono">{{ $zeile['pos'] }}</td>
                        <td class="b">{{ $zeile['artikel']->name }}</td>
                        <td class="mono">{{ $zeile['artikel']->art_nr }}</td>
                        <td class="mono">{{ Format::menge($zeile['menge']) }}</td>
                        <td>{{ $zeile['artikel']->einheit->value }}</td>
                        <td class="mono">{{ $zeile['artikel']->lagerort }}</td>
                        <td class="mono">{{ $zeile['beleg'] }}</td>
                        <td class="num"><span class="badge {{ $zeile['badge'] }}">{{ $zeile['status'] }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @if ($materialListe !== [])
        <div class="card-b jb" style="border-top:1px solid var(--bd2)">
            <span class="hint"><b>{{ $geliefert }} von {{ count($materialListe) }} Positionen geliefert und eingelagert</b>
                — Status, Lagerort und Beleg kommen live aus dem Lager</span>
            <a class="btn btns" href="{{ route('lager', ['tab' => 'wareneingang']) }}">
                <svg class="i"><use href="#ic-lager"/></svg>Lager öffnen</a>
        </div>
    @endif
</div>
