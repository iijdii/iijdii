{{-- Material + Bestellungen: links die Vorbestell-Liste aus der
     Konfiguration, rechts Aufmaß-Freigabe, reserviertes Lagermaterial
     (kompakt) und die Bestellungen des Objekts — die volle
     Bestell-Tabelle öffnet im Fenster. --}}
@php
    use App\Support\Format;
    $geliefert = collect($materialListe)->where('status', 'Geliefert')->count();
@endphp

<div class="cols-2" style="grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);align-items:start">
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
    </div>

    <div class="colstack">
        @include('projekte.partials.aufmass-bestaetigung')

        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Reserviertes Material (Lager)</span>
                <span class="pill">{{ count($materialListe) }}</span>
            </div>
            <div class="card-b">
                @if ($materialListe === [])
                    <p class="hint">Keine Reservierungen für dieses Projekt.</p>
                @else
                    @foreach ($materialListe as $zeile)
                        <div class="spec-row">
                            <span class="spec-k" style="min-width:0">
                                <span class="b" style="color:var(--ink)">{{ $zeile['artikel']->name }}</span><br>
                                <span class="mono">{{ Format::menge($zeile['menge']) }} {{ $zeile['artikel']->einheit->value }}
                                    · {{ $zeile['artikel']->lagerort ?: '–' }} · {{ $zeile['beleg'] }}</span>
                            </span>
                            <span class="fx ac gap8" style="flex:none">
                                <span class="badge {{ $zeile['badge'] }}">{{ $zeile['status'] }}</span>
                                <form method="POST" action="{{ route('projekte.reservierungen.loeschen', [$projekt, $zeile['reservierung_id']]) }}">
                                    @csrf
                                    <button class="btn btns" type="submit" style="color:var(--red)" aria-label="Reservierung aufheben">✕</button>
                                </form>
                            </span>
                        </div>
                    @endforeach
                    <p class="hint" style="margin-top:8px"><b>{{ $geliefert }} von {{ count($materialListe) }} Positionen geliefert und eingelagert</b>
                        — Status und Beleg live aus dem Lager.</p>
                @endif
            </div>
            <div class="card-b" style="border-top:1px solid var(--bd2)">
                <form method="POST" action="{{ route('projekte.reservierungen.store', $projekt) }}" class="colstack" style="gap:8px">
                    @csrf
                    <select class="inp" name="artikel_id" required>
                        <option value="">— Artikel wählen —</option>
                        @foreach ($alleArtikel as $artikel)
                            <option value="{{ $artikel->id }}">{{ $artikel->name }} · {{ $artikel->art_nr }}</option>
                        @endforeach
                    </select>
                    <div class="fx ac gap8">
                        <input class="inp mono" type="number" step="0.5" min="0.5" name="menge" placeholder="Menge" style="width:100px" required>
                        <button class="btn btns" type="submit" style="flex:1"><svg class="i"><use href="#ic-plus"/></svg>Material reservieren</button>
                    </div>
                    <a class="hint" href="{{ route('lager', ['tab' => 'wareneingang']) }}" style="text-decoration:underline">Lager öffnen →</a>
                </form>
            </div>
        </div>

        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Bestellungen zu diesem Objekt</span>
                <span class="pill">{{ $bestellungen->count() }}</span>
            </div>
            <div class="card-b">
                @if ($bestellungen->isEmpty())
                    <p class="hint">Noch keine Bestellungen — nach der Aufmaß-Bestätigung per
                        «Bestellung aus Positionen» erzeugen.</p>
                @else
                    @foreach ($bestellungen as $bestellung)
                        <a class="spec-row" style="text-decoration:none;color:inherit"
                           href="{{ route('bestellungen.show', $bestellung) }}">
                            <span class="spec-k" style="min-width:0">
                                <span class="b mono" style="color:var(--ink)">{{ $bestellung->nr }}</span><br>
                                <span>{{ $bestellung->lieferant?->name ?? '— Lieferant wählen —' }}</span>
                            </span>
                            <span class="badge {{ $bestellung->status->badgeClass() }}" style="flex:none">{{ $bestellung->status->label() }}</span>
                        </a>
                    @endforeach
                    <button class="btn btns btn-block" type="button" style="margin-top:10px"
                            data-modal-target="bestellungen-modal">
                        <svg class="i"><use href="#ic-expand"/></svg>Übersicht im Fenster öffnen</button>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Volle Bestell-Tabelle im Fenster --}}
<div class="modal" id="bestellungen-modal" hidden>
    <div class="modalc lg">
        <div class="modalh">Bestellungen zu diesem Objekt · {{ $projekt->nr }}
            <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
        <div class="modalb" style="overflow-x:auto">
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
        </div>
    </div>
</div>
