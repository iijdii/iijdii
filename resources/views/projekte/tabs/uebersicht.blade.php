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

<div class="prj-cols">
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

        {{-- Links nur das Dach — die Extra-Positionen stehen kompakt rechts. --}}
        @forelse ($projekt->positionen->filter(fn ($p) => $p->produkt->istDach()) as $position)
            <div class="card p0">
                <div class="card-h">
                    <span class="card-t" style="min-width:0">Technische Daten — Position {{ $position->pos }} · {{ $position->produkt->label() }}</span>
                    <span class="card-akt">
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
                        <div class="spec-cols">
                        <div>
                        <div class="specsec">Maße</div>
                        <div class="spec-row"><span class="spec-k">Breite × Tiefe</span><span class="spec-v mono">{{ $mm($k['width']) }} × {{ $mm($k['depth']) }}</span></div>
                        <div class="spec-row"><span class="spec-k">Höhe Wand / Rinne</span><span class="spec-v mono">{{ $mm($kalk['wallHEff']) }} / {{ $mm($kalk['gutterHEff']) }}</span></div>
                        <div class="spec-row"><span class="spec-k">Form · Montage</span><span class="spec-v">{{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }} · {{ $k['mounting'] }} · {{ $kalk['slopeEff'] }}° ({{ $kalk['gefaelleProzent'] }} %)</span></div>
                        <div class="specsec">Konstruktion</div>
                        <div class="spec-row"><span class="spec-k">Farbe</span><span class="spec-v">{{ $k['color'] }}</span></div>
                        <div class="spec-row"><span class="spec-k">Dach</span><span class="spec-v">{{ $k['covering'] }} {{ $k['thickness'] }} · {{ $k['glasTrans'] }}</span></div>
                        <div class="specsec">Kalkulation</div>
                        <div class="spec-row"><span class="spec-k">Pfosten · Sparren · Felder</span><span class="spec-v mono">{{ $kalk['pn'] ?: '–' }} · {{ $kalk['rafters'] ?: '–' }} · {{ $kalk['fields'] ?: '–' }}</span></div>
                        <div class="spec-row"><span class="spec-k">Sparrenabstand</span><span class="spec-v mono">{{ $kalk['sparText'] }}</span></div>
                        <div class="spec-row"><span class="spec-k">Wandblende</span><span class="spec-v mono">{{ $kalk['blendeText'] }}</span></div>
                        </div>
                        {{-- Verglasung wie in der Bestellung (volle Breite) --}}
                        @if ($kalk['fields'] > 0 && $kalk['glasB'] > 0)
                            <div class="spec-voll" style="order:3">
                            <div class="specsec">Verglasung</div>
                            @php
                                $glasName = $k['covering'].' '.$k['thickness'].' · '.$k['glasTrans'];
                                $glasFelder = [[
                                    'menge' => $kalk['polyRestPlatte'] !== null && $kalk['fields'] > 1 ? $kalk['fields'] - 1 : $kalk['fields'],
                                    'breite' => $kalk['glasB'],
                                ]];
                                if ($kalk['polyRestPlatte'] !== null && $kalk['fields'] > 1) {
                                    $glasFelder[] = ['menge' => 1, 'breite' => $kalk['polyRestPlatte']];
                                }
                            @endphp
                            <div class="fx gap8 wrap" style="align-items:flex-start;margin-top:6px">
                                @foreach ($glasFelder as $feld)
                                    @php
                                        $skizzenPosition = (object) [
                                            'breite_mm' => $feld['breite'], 'hoehe_mm' => $kalk['glasT'],
                                            'details' => [], 'menge' => $feld['menge'],
                                        ];
                                    @endphp
                                    <div style="width:200px;flex:0 0 auto">
                                        @include('bestellungen.partials.glas-skizze', ['glas' => [
                                            'skizze' => \App\Support\GlasSkizze::position($feld['breite'], $kalk['glasT'], $kalk['glasT'], false),
                                            'position' => $skizzenPosition,
                                        ]])
                                        <div class="hint" style="text-align:center;margin-top:2px">
                                            {{ $feld['menge'] }} Stück · {{ number_format($feld['breite'], 0, ',', '.') }} × {{ number_format($kalk['glasT'], 0, ',', '.') }} mm
                                            @if ($loop->index === 1) · Zuschnitt @endif</div>
                                    </div>
                                @endforeach
                                <div class="hint" style="flex:1;min-width:140px">{{ $glasName }}</div>
                            </div>
                            </div>
                        @endif
                        <div style="order:2">
                        <div class="specsec">Ausstattung</div>
                        <div class="spec-row"><span class="spec-k">LED</span><span class="spec-v">{{ $kalk['ledTot'] > 0 ? $kalk['ledTot'].' Spots · '.$k['led']['color'] : 'Keine Beleuchtung' }}</span></div>
                        <div class="spec-row"><span class="spec-k">Entwässerung</span><span class="spec-v">Pfosten {{ $k['drain']['post'] }} · {{ $k['drain']['dir'] }}</span></div>
                        @if (($k['mounting'] ?? '') !== 'freistehend')
                            @php
                                $wandInfo = ($k['wand']['belag'] ?? 'Putz')
                                    .((($k['wand']['isolierung'] ?? 'nein') === 'ja') ? ' · Isolierung '.(($k['wand']['daemmstaerke'] ?? '') ?: '?').' mm' : '');
                            @endphp
                            <div class="spec-row"><span class="spec-k">Wandanschluss</span><span class="spec-v">{{ $wandInfo }}
                                · {{ $k['duebel']['typ'] }} {{ $k['duebel']['size'] }}</span></div>
                        @endif
                        <div class="spec-row"><span class="spec-k">Pfosten-Befestigung</span><span class="spec-v">
                            @if (($k['postMontageJe'] ?? '') == 1 && ($k['postMontageListe'] ?? []) !== [])
                                {{ implode(' · ', array_map(fn ($m, $i) => ($i + 1).': '.$m, $k['postMontageListe'], array_keys($k['postMontageListe']))) }}
                            @else
                                {{ $k['postMontage'] ?? 'Beton' }}{{ ($k['postMontage'] ?? '') === 'Pfostenhalter' ? ' (Konsole)' : '' }}
                            @endif
                        </span></div>
                        @if ($kalk['trapez'])
                            <div class="spec-row"><span class="spec-k">Trapez</span><span class="spec-v">Wand {{ $mm($kalk['trapez']['wand']) }} · Rinne {{ $mm($kalk['trapez']['rinne']) }} · Offsets {{ $kalk['trapez']['offsetLinks'] }}/{{ $kalk['trapez']['offsetRechts'] }} mm · Winkel {{ $kalk['trapez']['winkelLinks'] }}°/{{ $kalk['trapez']['winkelRechts'] }}°</span></div>
                        @endif
                        <div class="spec-row"><span class="spec-k">Pfosten-Positionen</span><span class="spec-v mono">{{ $kalk['postPositionen'] !== [] ? implode(' · ', array_map(fn ($x) => number_format($x, 0, ',', '.'), $kalk['postPositionen'])).' mm' : '–' }}</span></div>
                        @if (count($kalk['profilSegmente']) > 1)
                            <div class="spec-row"><span class="spec-k">Profilsegmente</span><span class="spec-v mono">{{ implode(' + ', array_map(fn ($s) => number_format($s, 0, ',', '.'), $kalk['profilSegmente'])) }} mm</span></div>
                        @endif
                        <div class="spec-row"><span class="spec-k">Unterzug</span><span class="spec-v">
                            @if ($kalk['unterzug']['gewaehlt'] ?? false)
                                {{ $kalk['unterzug']['anzahl'] }}× {{ $kalk['unterzug']['groesse'] }} · Position {{ $mm($kalk['unterzug']['position']) }}@if ($kalk['unterzug']['ueberstand'] > 0) · Überstand {{ $mm($kalk['unterzug']['ueberstand']) }}@endif
                            @else
                                keiner
                            @endif
                        </span></div>
                        </div>
                        {{-- Warnungen der Kalkulation (früher eigene kbox rechts) --}}
                        <div class="spec-voll" style="order:4">
                        @if ($kalk['glasZuBreit'])
                            <div class="kwarn">Eindeckungsbreite &gt; {{ number_format($kalk['maxPlatte'], 0, ',', '.') }} mm — Fertigungsgrenze überschritten (Feldanzahl erhöhen).</div>
                        @endif
                        @if ($kalk['unterzug']['erforderlich'] && ! ($kalk['unterzug']['gewaehlt'] ?? false))
                            <div class="kwarn">Unterzug empfohlen: {{ implode(', ', $kalk['unterzug']['gruende']) }}.</div>
                        @endif
                        @if ($kalk['spannZuGross'])
                            <div class="kwarn">Pfosten-Spannweite über 4.000 mm — Position prüfen.</div>
                        @endif
                        @if ($kalk['stossPfosten'] !== [])
                            <div class="kwarn">Profilstoß bei {{ implode(' / ', array_map(fn ($x) => number_format($x, 0, ',', '.'), $kalk['stossPfosten'])) }} mm — Pfosten unter dem Stoß empfohlen (Stoß − 55).</div>
                        @endif
                        </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><p class="hint">Noch keine Dach-Position — über «Position hinzufügen» starten.</p></div>
        @endforelse

        {{-- LED-Plan unter dem Konfigurator (Wunsch des Betreibers) --}}
        @isset ($ledZeichnung)
            @include('projekte.partials.led-plan')
        @endisset
    </div>

    <div class="colstack prj-side">
        @if ($vorschau)
            <div class="card"><span class="hint">Vorschau — noch nicht gespeichert</span></div>
        @endif

        @if ($dachPosition)
            <div class="kbox">
                <div class="kt">Listenpreis</div>
                <div class="spec-row"><span class="spec-k">Maße</span><span class="spec-v mono">{{ $mm($k['width']) }} × {{ $mm($k['depth']) }}</span></div>
                <div class="spec-row"><span class="spec-k">Dach</span><span class="spec-v">{{ $k['covering'] }} {{ $k['thickness'] }}</span></div>
                @php $listenpreis = \App\Support\Preisliste::ausKonfiguration($projekt->konfiguration); @endphp
                <div class="spec-row"><span class="spec-k">Preis laut Preisliste</span>
                    <span class="spec-v mono">{{ $listenpreis !== null ? Format::eur($listenpreis) : '– (außerhalb)' }}</span></div>
                @if ($listenpreis !== null)
                    <p class="hint" style="margin-top:6px">zzgl. Montage · Maße auf das Raster der Preisliste aufgerundet</p>
                @endif
            </div>
        @endif

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

        {{-- Extra-Positionen (Wände, Schiebe, Keile, Sonnenschutz) kompakt rechts --}}
        @foreach ($projekt->positionen->filter(fn ($p) => ! $p->produkt->istDach()) as $position)
            <div class="card p0">
                <div class="card-h" style="flex-wrap:wrap;row-gap:6px">
                    <span class="card-t" style="min-width:0">Pos. {{ $position->pos }} · {{ $position->produkt->label() }}</span>
                    <span class="card-akt">
                        <span class="badge {{ $position->phase === 2 ? 'b-yellow' : 'b-gray' }}"
                              title="{{ $position->phase === 2 ? 'Endmaße nach Dachmontage' : '' }}">Phase {{ $position->phase }}</span>
                        <button class="btn btns" type="button" data-modal-target="posmodal-{{ $position->id }}">
                            <svg class="i"><use href="#ic-edit"/></svg></button>
                        <form method="POST" action="{{ route('projekte.positionen.loeschen', [$projekt, $position]) }}"
                              onsubmit="return confirm('Position {{ $position->pos }} entfernen?')">
                            @csrf
                            <button class="btn btns" type="submit" title="Position entfernen">✕</button>
                        </form>
                    </span>
                </div>
                <div class="card-b">
                    <div class="anf-specs">
                        @foreach ($position->felder ?? [] as $schluessel => $wert)
                            @continue(is_array($wert))
                            <span class="spec">{{ $posLabels[$schluessel] ?? $schluessel }}
                                <b>{{ str_contains($schluessel, '_mm') && is_numeric($wert) ? number_format((int) $wert, 0, ',', '.').' mm' : $wert }}</b></span>
                        @endforeach
                    </div>
                    @php
                        $f = $position->felder ?? [];
                        $wandPanels = $position->produkt === \App\Enums\ProjektProdukt::Wand
                            && (int) ($f['breite_mm'] ?? 0) > 0 && (int) ($f['h_links_mm'] ?? 0) > 0
                            ? \App\Support\SeitenwandRechner::panels(
                                (int) $f['breite_mm'], (int) $f['h_links_mm'],
                                (int) ($f['h_rechts_mm'] ?? $f['h_links_mm']), (int) ($f['anzahl'] ?? 1),
                            )
                            : [];
                    @endphp
                    @if ($wandPanels !== [])
                        <div class="specsec" style="margin-top:10px">Glaszuschnitt (Fuge 30 mm)</div>
                        @foreach ($wandPanels as $panel)
                            <div class="spec-row"><span class="spec-k">Panel {{ $panel['nr'] }} · {{ $panel['form'] }}</span>
                                <span class="spec-v mono">{{ number_format($panel['breite'], 0, ',', '.') }} × {{ number_format($panel['hLinks'], 0, ',', '.') }}/{{ number_format($panel['hRechts'], 0, ',', '.') }} mm</span></div>
                        @endforeach
                    @endif
                </div>
            </div>
        @endforeach

        <div class="card">
            <button class="btn btns btn-block" type="button" data-modal-target="posmodal-neu">
                <svg class="i"><use href="#ic-plus"/></svg>Position hinzufügen
                <span class="hint" style="margin-left:6px">Extras · Sonnenschutz{{ $dachPosition ? '' : ' · Dach' }}</span></button>
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
