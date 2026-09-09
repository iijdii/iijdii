{{-- Material + Bestellungen: Vorbestell-Liste aus der Konfiguration,
     reserviertes Lagermaterial (manuell ergänzbar), Aufmaß-Freigabe und
     alle Bestellungen dieses Objekts. --}}
@php
    use App\Support\Format;
    $geliefert = collect($materialListe)->where('status', 'Geliefert')->count();
@endphp

<div class="cols-2" style="grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);align-items:start">
    <div class="colstack">
        @php $stueckliste = \App\Support\Stueckliste::dach($kalk); @endphp
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Materialliste aus Konfiguration</span>
                <span class="pill">{{ count($stueckliste) }} Positionen · Vorbestellung</span>
            </div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl">
                    <thead><tr><th>Pos</th><th>Bezeichnung</th><th>Maß</th><th class="num">Menge</th></tr></thead>
                    <tbody>
                    @foreach ($stueckliste as $i => $zeile)
                        <tr>
                            <td class="mono">{{ $i + 1 }}</td>
                            <td class="b">{{ $zeile['name'] }}</td>
                            <td class="mono">
                                @if (isset($zeile['breite_mm']))
                                    {{ number_format($zeile['breite_mm'], 0, ',', '.') }} × {{ number_format($zeile['hoehe_mm'], 0, ',', '.') }} mm
                                @elseif (isset($zeile['laenge_mm']))
                                    L {{ number_format($zeile['laenge_mm'], 0, ',', '.') }} mm
                                @else
                                    –
                                @endif
                            </td>
                            <td class="num mono">{{ $zeile['menge'] }} {{ $zeile['einheit'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="hint" style="margin-top:10px">Hauptpositionen nach KD-Stückliste, automatisch aus dem
                    Konfigurator. Nach der Aufmaß-Bestätigung wird daraus per «Bestellung aus Positionen» der
                    Bestell-Entwurf; Kleinteile (Schrauben, Silikon, Dichtungen) ergänzen Sie dort.</p>
            </div>
        </div>

        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Reserviertes Material (Lager)</span>
                <span class="pill">{{ count($materialListe) }} Positionen</span>
            </div>
            <div class="card-b" style="overflow-x:auto">
                @if ($materialListe === [])
                    <p class="hint">Keine Reservierungen für dieses Projekt.</p>
                @else
                    <table class="tbl">
                        <thead>
                        <tr><th>Pos</th><th>Bezeichnung</th><th>Artikel-Nr.</th><th>Menge</th><th>Lagerort</th><th>Beleg</th><th class="num">Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        @foreach ($materialListe as $zeile)
                            <tr>
                                <td class="mono">{{ $zeile['pos'] }}</td>
                                <td class="b">{{ $zeile['artikel']->name }}</td>
                                <td class="mono">{{ $zeile['artikel']->art_nr }}</td>
                                <td class="mono">{{ Format::menge($zeile['menge']) }} {{ $zeile['artikel']->einheit->value }}</td>
                                <td class="mono">{{ $zeile['artikel']->lagerort }}</td>
                                <td class="mono">{{ $zeile['beleg'] }}</td>
                                <td class="num"><span class="badge {{ $zeile['badge'] }}">{{ $zeile['status'] }}</span></td>
                                <td class="num">
                                    <form method="POST" action="{{ route('projekte.reservierungen.loeschen', [$projekt, $zeile['reservierung_id']]) }}">
                                        @csrf
                                        <button class="btn btns" type="submit" style="color:var(--red)" aria-label="Reservierung aufheben">✕</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-b" style="border-top:1px solid var(--bd2)">
                <form method="POST" action="{{ route('projekte.reservierungen.store', $projekt) }}" class="fx ac gap8" style="flex-wrap:wrap">
                    @csrf
                    <select class="inp" name="artikel_id" style="flex:2;min-width:220px" required>
                        <option value="">— Artikel wählen —</option>
                        @foreach ($alleArtikel as $artikel)
                            <option value="{{ $artikel->id }}">{{ $artikel->name }} · {{ $artikel->art_nr }}</option>
                        @endforeach
                    </select>
                    <input class="inp mono" type="number" step="0.5" min="0.5" name="menge" placeholder="Menge" style="width:100px" required>
                    <button class="btn btns" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Material reservieren</button>
                </form>
            </div>
            @if ($materialListe !== [])
                <div class="card-b jb wrap" style="border-top:1px solid var(--bd2)">
                    <span class="hint"><b>{{ $geliefert }} von {{ count($materialListe) }} Positionen geliefert und eingelagert</b>
                        — Status, Lagerort und Beleg kommen live aus dem Lager</span>
                    <a class="btn btns" href="{{ route('lager', ['tab' => 'wareneingang']) }}">
                        <svg class="i"><use href="#ic-lager"/></svg>Lager öffnen</a>
                </div>
            @endif
        </div>

        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Bestellungen zu diesem Objekt</span>
                <span class="pill">{{ $bestellungen->count() }} Bestellungen</span>
            </div>
            <div class="card-b" style="overflow-x:auto">
                @if ($bestellungen->isEmpty())
                    <p class="hint">Noch keine Bestellungen — nach der Aufmaß-Bestätigung per
                        «Bestellung aus Positionen» erzeugen.</p>
                @else
                    <table class="tbl">
                        <thead>
                        <tr><th>Nr.</th><th>Titel</th><th>Lieferant</th><th>Positionen</th><th>Liefertermin</th><th class="num">Status</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($bestellungen as $bestellung)
                            <tr class="lrow" onclick="window.location='{{ route('bestellungen.show', $bestellung) }}'">
                                <td class="b mono">{{ $bestellung->nr }}</td>
                                <td class="b">{{ $bestellung->titel }}</td>
                                <td>{{ $bestellung->lieferant?->name ?? '— wählen —' }}</td>
                                <td class="mono">{{ $bestellung->positionen->count() }}</td>
                                <td class="mono">{{ Format::datumKurz($bestellung->liefertermin) }}</td>
                                <td class="num"><span class="badge {{ $bestellung->status->badgeClass() }}">{{ $bestellung->status->label() }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <div class="colstack">
        @include('projekte.partials.aufmass-bestaetigung')
    </div>
</div>
