@php use App\Support\Format; @endphp

@if (! $zahlung)
    <div class="card"><p class="hint">Noch kein Angebot mit Auftragswert verknüpft — der Zahlungsplan entsteht mit dem Angebot.</p></div>
@else
    <div class="cols-2">
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Zahlungsplan</span>
                <span class="badge b-yellow">{{ $zahlung['bezahltAnzahl'] }} von {{ count($zahlung['raten']) }} bezahlt</span>
            </div>
            <div class="card-b">
                <table class="tbl">
                    <thead><tr><th>Rate</th><th class="num">Anteil</th><th class="num">Betrag</th><th class="num">Status</th></tr></thead>
                    <tbody>
                    @foreach ($zahlung['raten'] as $rate)
                        <tr>
                            <td class="b">{{ $rate['label'] }}</td>
                            <td class="num mono">{{ $rate['anteil'] }}</td>
                            <td class="num mono">{{ Format::eur($rate['betrag']) }}</td>
                            <td class="num"><span class="badge {{ $rate['badge'] }}">{{ $rate['status'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-euro"/></svg>Zusammenfassung</div>
            <div class="sumline"><span>Nettobetrag</span><b class="mono">{{ Format::eur($zahlung['netto']) }}</b></div>
            <div class="sumline"><span>MwSt. 19 %</span><b class="mono">{{ Format::eur($zahlung['mwst']) }}</b></div>
            <div class="sumline tot"><span>Gesamtbetrag</span><b class="mono">{{ Format::eur($zahlung['brutto']) }}</b></div>
            <div class="msep"></div>
            <div class="sumline"><span>Bereits bezahlt</span><b class="mono" style="color:var(--green)">{{ Format::eur($zahlung['bezahlt']) }}</b></div>
            <div class="sumline"><span>Offener Betrag</span><b class="mono">{{ Format::eur($zahlung['offen']) }}</b></div>
        </div>
    </div>
@endif
