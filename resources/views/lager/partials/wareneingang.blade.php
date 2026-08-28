@php use App\Support\Format; @endphp

<div class="card">
    <div class="fx">
        <svg class="i" style="color:var(--accd)"><use href="#ic-truck"/></svg>
        <div>
            <b>Automatische Zubuchung</b>
            <p class="hint" style="margin:2px 0 0">Sobald eine Bestellung auf „Geliefert" gesetzt oder hier der
                Wareneingang gebucht wird, landen alle Positionen im Lagerbestand — und sind in Bestellung,
                Lieferschein und Projekt-Materialliste als <b>geliefert</b> gekennzeichnet.</p>
        </div>
    </div>
</div>

@foreach ($karten as $karte)
    @php $b = $karte['bestellung']; @endphp
    <div class="card p0 {{ $karte['gebucht'] ? 'lg-ok' : ($karte['entwurf'] ? '' : 'lg-open') }}">
        <div class="card-h">
            <span class="fx">
                <span class="anf-nr mono">{{ $b->nr }}</span>
                <span class="badge {{ $b->status->badgeClass() }}">{{ $b->status->label() }}</span>
                <span class="pill">{{ $b->lieferant->name }}</span>
            </span>
            <span class="hint" style="text-align:right">
                <span style="font-size:11px;letter-spacing:.06em;text-transform:uppercase">Liefertermin</span><br>
                <span class="mono">{{ Format::datumKurz($b->liefertermin) }}</span>
            </span>
        </div>
        <div class="card-b">
            <p class="hint" style="margin:0 0 10px">{{ $b->titel }} · {{ $b->projekt?->nr ?? '–' }} · {{ $b->kunde?->anzeigename ?? '–' }}</p>
            <div style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                    <tr><th>Art.-Nr.</th><th>Bezeichnung</th><th>Menge</th><th>Einheit</th><th>Lagerort</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($karte['zeilen'] as $zeile)
                        <tr>
                            <td class="mono">{{ $zeile['artikel']?->art_nr ?? '—' }}</td>
                            <td>{{ $zeile['bezeichnung'] }}</td>
                            <td class="mono">{{ Format::menge($zeile['menge']) }}</td>
                            <td>{{ $zeile['einheit'] }}</td>
                            <td class="mono">{{ $zeile['artikel']?->lagerort ?? '–' }}</td>
                            <td><span class="badge {{ $karte['gebucht'] ? 'b-green' : 'b-gray' }}">{{ $karte['gebucht'] ? 'Eingebucht' : 'Erwartet' }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-b jb" style="border-top:1px solid var(--bd2)">
            @if ($karte['gebucht'])
                @php $we = $karte['wareneingang']; @endphp
                <span class="fx" style="color:var(--green)">
                    <svg class="i"><use href="#ic-check"/></svg>
                    <b>Eingebucht am {{ Format::datum($we->datum) }} · {{ $we->benutzer?->name ?? 'S. Krüger' }} · Lager ·
                        {{ Format::menge($we->positionen->sum('menge')) }} Stück auf {{ $we->positionen->count() }} Artikeln</b>
                </span>
                <span class="ctas">
                    <button class="btn btns" type="button" data-toast="Lieferschein {{ $we->lieferschein_nr }} geöffnet">
                        <svg class="i"><use href="#ic-doc"/></svg>Lieferschein {{ $we->lieferschein_nr }}</button>
                    <a class="btn btns" href="{{ route('bestellungen') }}">Zur Bestellung</a>
                </span>
            @elseif ($karte['entwurf'])
                <span class="hint">Entwurf — beim Lieferanten noch nicht bestellt</span>
                <a class="btn btns" href="{{ route('bestellungen') }}">Zur Bestellung</a>
            @else
                <span class="hint"><b>{{ count($karte['zeilen']) }} Artikel · {{ Format::menge($karte['stueck']) }} Stück erwartet</b></span>
                <span class="ctas">
                    <a class="btn btns" href="{{ route('bestellungen') }}">Zur Bestellung</a>
                    <form method="POST" action="{{ route('lager.wareneingang.buchen', $b) }}">
                        @csrf
                        <button class="btn btns btnp" type="submit">
                            <svg class="i"><use href="#ic-check"/></svg>Wareneingang buchen</button>
                    </form>
                </span>
            @endif
        </div>
    </div>
@endforeach
