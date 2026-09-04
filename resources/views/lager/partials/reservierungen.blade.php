@php use App\Support\Format; @endphp

@foreach ($gruppen as $gruppe)
    @php $projekt = $gruppe['projekt']; @endphp
    <div class="card p0">
        <div class="card-h">
            <span class="card-t">{{ $projekt->nr }} · {{ $projekt->kunde->anzeigename }}</span>
            <span class="pill">{{ $gruppe['reservierungen']->count() }} Positionen · {{ $gruppe['stueck'] }} Stück reserviert</span>
        </div>
        <div class="card-b" style="overflow-x:auto">
            <table class="tbl">
                <thead>
                <tr><th>Art.-Nr.</th><th>Bezeichnung</th><th>Reserviert</th><th>Einheit</th><th>Lagerort</th><th>Deckung</th><th>Zugang</th></tr>
                </thead>
                <tbody>
                @foreach ($gruppe['reservierungen'] as $reservierung)
                    @php
                        $a = $reservierung->artikel;
                        $menge = (int) $reservierung->menge;
                        $gedeckt = $a->bestand >= $menge;
                        $zugang = $zugaenge->get($a->id);
                    @endphp
                    <tr>
                        <td class="mono">{{ $a->art_nr }}</td>
                        <td>{{ $a->name }}</td>
                        <td><b class="mono">{{ $menge }}</b></td>
                        <td>{{ $a->einheit->value }}</td>
                        <td class="mono">{{ $a->lagerort }}</td>
                        <td><span class="badge {{ $gedeckt ? 'b-green' : 'b-red' }}">{{ $gedeckt ? 'Im Lager' : 'Fehlt '.($menge - $a->bestand) }}</span></td>
                        <td><span class="badge {{ $zugang ? 'b-green' : 'b-gray' }}">{{ $zugang ? 'Geliefert '.Format::datumKurz($zugang['datum']) : 'offen' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-b" style="border-top:1px solid var(--bd2);text-align:right">
            <a class="btn btns" href="{{ $gruppe['kommissionierung'] ? route('logistik.bestellung', $gruppe['kommissionierung']) : route('logistik') }}">
                <svg class="i"><use href="#ic-logistik"/></svg>Kommissionierung öffnen</a>
        </div>
    </div>
@endforeach
