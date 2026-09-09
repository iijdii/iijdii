{{-- Übersicht = Auftrag + komplette Technik: bemaßte Zeichnungen,
     Produktpass je Position («Ändern» öffnet das Konfigurator-Fenster),
     Kalkulation und Angebots-Status. Kunde/Termine → Tab «Kunde & Termine»,
     Material/Bestellungen → Tab «Material + Bestellungen». --}}
@php
    use App\Support\Format;
    $k = $kalk['pcfg'];
    $dachPosition = $projekt->positionen->firstWhere('gruppe', 'dach');
    $posLabels = [
        'anzahl' => 'Anzahl', 'breite_mm' => 'Breite', 'hoehe_mm' => 'Höhe', 'h_links_mm' => 'Höhe links',
        'h_rechts_mm' => 'Höhe rechts', 'h_vorn_mm' => 'Höhe vorn', 'laenge_mm' => 'Länge',
        'ausfall_mm' => 'Ausfall', 'felder_n' => 'Felder', 'richtung' => 'Laufrichtung', 'glas' => 'Verglasung',
        'seite' => 'Seite', 'material' => 'Material', 'transparenz' => 'Transparenz', 'modell' => 'Modell',
        'antrieb' => 'Antrieb', 'groesse' => 'Größe', 'farbe' => 'Farbe',
    ];
    $mm = fn ($wert) => is_numeric($wert) ? number_format((int) $wert, 0, ',', '.').' mm' : '–';
@endphp

<div class="cols-2" style="grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);align-items:start">
    <div class="colstack">
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Technische Zeichnungen — Konstruktion</span>
                <button class="btn btns" type="button" data-roof-open="4">
                    <svg class="i"><use href="#ic-zoom"/></svg>Bemaßung im Detail</button>
            </div>
            <div class="card-b">
                <div class="draw-grid lg">
                    @foreach (\App\Support\RoofZeichnung::ANSICHTEN as $i => $ansicht)
                        <button class="dtile" type="button" data-roof-open="{{ $i }}">
                            <span class="dtl">{{ \App\Support\RoofZeichnung::TITEL[$ansicht] }}</span>
                            <span class="dtz"><svg class="i" style="width:14px;height:14px"><use href="#ic-zoom"/></svg></span>
                            @include('partials.roof-zeichnung', ['z' => $roof[$ansicht], 'nodim' => false])
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @forelse ($projekt->positionen as $position)
            <div class="card p0">
                <div class="card-h">
                    <span class="card-t">Technische Daten — Position {{ $position->pos }} · {{ $position->produkt->label() }}</span>
                    <span class="fx ac gap8 wrap">
                        <span class="badge {{ $position->phase === 2 ? 'b-yellow' : 'b-gray' }}">
                            Phase {{ $position->phase }}{{ $position->phase === 2 ? ' · Endmaße nach Dachmontage' : '' }}</span>
                        <button class="btn btns" type="button" data-modal-target="posmodal-{{ $position->id }}">
                            <svg class="i"><use href="#ic-edit"/></svg>Ändern</button>
                        <form method="POST" action="{{ route('projekte.positionen.loeschen', [$projekt, $position]) }}"
                              onsubmit="return confirm('Position {{ $position->pos }} entfernen?')">
                            @csrf
                            <button class="btn btns" type="submit" title="Position entfernen">✕</button>
                        </form>
                    </span>
                </div>
                <div class="card-b">
                    @if ($position->produkt->istDach())
                        <div class="specsec">Maße</div>
                        <div class="spec-row"><span class="spec-k">Breite × Tiefe</span><span class="spec-v mono">{{ $mm($k['width']) }} × {{ $mm($k['depth']) }}</span></div>
                        <div class="spec-row"><span class="spec-k">Höhe Wand / Rinne</span><span class="spec-v mono">{{ $mm($k['wallH']) }} / {{ $mm($k['gutterH']) }}</span></div>
                        <div class="spec-row"><span class="spec-k">Form · Montage</span><span class="spec-v">{{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }} · {{ $k['mounting'] }} · {{ $k['slope'] }}°</span></div>
                        <div class="specsec">Konstruktion</div>
                        <div class="spec-row"><span class="spec-k">Farbe</span><span class="spec-v">{{ $k['color'] }}</span></div>
                        <div class="spec-row"><span class="spec-k">Dach</span><span class="spec-v">{{ $k['covering'] }} {{ $k['thickness'] }} · {{ $k['glasTrans'] }}</span></div>
                        <div class="spec-row"><span class="spec-k">Statik</span><span class="spec-v">{{ $k['snow'] }} · {{ $k['wind'] }}</span></div>
                        <div class="specsec">Ausstattung</div>
                        <div class="spec-row"><span class="spec-k">LED</span><span class="spec-v">{{ $kalk['ledTot'] }} Spots · {{ $k['led']['color'] }}</span></div>
                        <div class="spec-row"><span class="spec-k">Entwässerung</span><span class="spec-v">Pfosten {{ $k['drain']['post'] }} · {{ $k['drain']['dir'] }}</span></div>
                        <div class="spec-row"><span class="spec-k">Dübel</span><span class="spec-v">{{ $k['duebel']['typ'] }} {{ $k['duebel']['size'] }}</span></div>
                    @else
                        <div class="anf-specs">
                            @foreach ($position->felder ?? [] as $schluessel => $wert)
                                @continue(is_array($wert))
                                <span class="spec">{{ $posLabels[$schluessel] ?? $schluessel }}
                                    <b>{{ str_contains($schluessel, '_mm') && is_numeric($wert) ? number_format((int) $wert, 0, ',', '.').' mm' : $wert }}</b></span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><p class="hint">Noch keine Positionen — über «Position hinzufügen» starten.</p></div>
        @endforelse

        <div class="card">
            <button class="btn btns" type="button" data-modal-target="posmodal-neu">
                <svg class="i"><use href="#ic-plus"/></svg>Position hinzufügen
                <span class="hint" style="margin-left:6px">Extras · Sonnenschutz{{ $dachPosition ? '' : ' · Dach' }}</span></button>
        </div>
    </div>

    <div class="colstack">
        <div class="kbox">
            <div class="fsec">Dach-Kalkulation</div>
            <div class="kgrid">
                <div class="kcell"><span>Pfosten</span><b class="mono">{{ $kalk['pn'] ?: '–' }}</b></div>
                <div class="kcell"><span>Sparren</span><b class="mono">{{ $kalk['rafters'] ?: '–' }}</b></div>
                <div class="kcell"><span>Felder</span><b class="mono">{{ $kalk['fields'] ?: '–' }}</b></div>
                <div class="kcell"><span>Sparrenabstand</span><b class="mono">{{ $kalk['sparText'] }}</b></div>
                <div class="kcell"><span>Wandblende</span><b class="mono">{{ $kalk['blendeText'] }}</b></div>
                <div class="kcell"><span>Glasmaß</span><b class="mono">{{ $kalk['glasText'] }}</b></div>
                <div class="kcell"><span>LED-Spots</span><b class="mono">{{ $kalk['ledTot'] }}</b></div>
            </div>
            @if ($kalk['glasZuBreit'])
                <div class="kwarn">Glasbreite &gt; {{ \App\Support\KonfiguratorRechner::MAX_GLAS_BREITE }} mm — Fertigungsgrenze überschritten (Feldanzahl erhöhen).</div>
            @endif
            @if ($kalk['warnung'])
                <div class="kwarn">Tiefe &gt; 4000 mm — statische Prüfung / Unterzug erforderlich.</div>
            @endif
            @if ($vorschau)
                <span class="hint">Vorschau — noch nicht gespeichert</span>
            @endif
        </div>

        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-angebote"/></svg>Angebot</div>
            @if ($projekt->angebot)
                <div class="spec-row"><span class="spec-k">Nummer</span><span class="spec-v mono">{{ $projekt->angebot->nr }}</span></div>
                <div class="spec-row"><span class="spec-k">Summe</span><span class="spec-v mono">{{ $projekt->angebot->summe !== null ? Format::eur($projekt->angebot->summe) : 'in Konfiguration' }}</span></div>
                <div class="spec-row"><span class="spec-k">Status</span><span class="spec-v"><span class="badge {{ $projekt->angebot->status->badgeClass() }}">{{ $projekt->angebot->status->label() }}</span></span></div>
                <a class="btn btns btn-block" style="margin-top:10px" href="{{ route('angebote.show', $projekt->angebot) }}">
                    <svg class="i"><use href="#ic-angebote"/></svg>Angebot öffnen</a>
            @else
                <p class="hint">Noch kein Angebot zu diesem Projekt.</p>
                <form method="POST" action="{{ route('projekte.angebot', $projekt) }}" style="margin-top:10px">
                    @csrf
                    <button class="btn btns btn-block" type="submit">
                        <svg class="i"><use href="#ic-angebote"/></svg>Als Angebot übergeben</button>
                </form>
            @endif
        </div>
    </div>
</div>

{{-- Konfigurator-Fenster: eine Instanz je Position + «Position hinzufügen» --}}
@foreach ($projekt->positionen as $position)
    <div class="modal" id="posmodal-{{ $position->id }}" hidden>
        <div class="modalc lg">
            <form method="POST" action="{{ route('projekte.positionen.update', [$projekt, $position]) }}"
                  style="display:flex;flex-direction:column;min-height:0">
                @csrf
                @method('PUT')
                <div class="modalh">Konfigurator — Position {{ $position->pos }} · {{ $position->produkt->label() }}
                    <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
                <div class="modalb">
                    @include('projekte.partials.produkt-form', ['prefix' => 'position', 'position' => $position])
                </div>
                <div class="modalf">
                    <button class="btn btns" type="button" data-modal-close>Abbrechen</button>
                    <button class="btn btns btnp" type="submit"><svg class="i"><use href="#ic-check"/></svg>Speichern</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

<div class="modal" id="posmodal-neu" hidden>
    <div class="modalc lg">
        <form method="POST" action="{{ route('projekte.positionen.store', $projekt) }}"
              style="display:flex;flex-direction:column;min-height:0">
            @csrf
            <div class="modalh">Konfigurator — neue Position
                <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
            <div class="modalb">
                @include('projekte.partials.produkt-form', [
                    'prefix' => 'position', 'position' => null,
                    'standardProdukt' => $dachPosition ? 'wand' : 'ueberdachung',
                ])
            </div>
            <div class="modalf">
                <button class="btn btns" type="button" data-modal-close>Abbrechen</button>
                <button class="btn btns btnp" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Position hinzufügen</button>
            </div>
        </form>
    </div>
</div>
