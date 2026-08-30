@php
    use App\Support\Format;
    use App\Support\KonfiguratorRechner;
    $k = $kalk['pcfg'];
    $extras = $k['extras'] ?? [];
    $hat = fn ($x) => in_array($x, $extras, true);
@endphp

<div class="cols-2" style="grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);align-items:start">
    <form class="card p0" method="POST" action="{{ route('projekte.konfiguration', $projekt) }}" id="konfigForm">
        @csrf
        <div class="card-h">
            <span class="card-t">Konfigurator — {{ $k['product'] }} · {{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }}</span>
            <span class="pill">{{ count($kalk['positionen']) }} Positionen</span>
        </div>
        <div class="card-b cfg-form">

            <div class="cfg-block">
                <div class="fsec">Grundkonstruktion</div>
                <div class="fld"><label>Produktart</label>
                    <div class="radiorow">
                        @foreach (KonfiguratorRechner::PRODUKTE as $produkt)
                            <button class="rpill {{ $k['product'] === $produkt ? 'on' : '' }}" type="submit"
                                    name="product" value="{{ $produkt }}"><span class="rdot"></span>{{ $produkt }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="fld"><label>Montageart</label>
                    <div class="radiorow">
                        <button class="rpill {{ $k['mounting'] === 'an der Wand' ? 'on' : '' }}" type="submit" name="mounting" value="an der Wand"><span class="rdot"></span>Wandmontage</button>
                        <button class="rpill {{ $k['mounting'] === 'freistehend' ? 'on' : '' }}" type="submit" name="mounting" value="freistehend"><span class="rdot"></span>Freistehend</button>
                    </div>
                </div>
                <div class="fld"><label>Form</label>
                    <div class="radiorow">
                        <button class="rpill {{ $k['shape'] === 'rechteck' ? 'on' : '' }}" type="submit" name="shape" value="rechteck"><span class="rdot"></span>Rechteck</button>
                        <button class="rpill {{ $k['shape'] === 'trapez' ? 'on' : '' }}" type="submit" name="shape" value="trapez"><span class="rdot"></span>Trapez</button>
                    </div>
                </div>
                <div class="fgrid2">
                    <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="width" value="{{ $k['width'] }}" data-kalk="width"></div>
                    <div class="fld"><label>Tiefe / Ausladung (mm)</label><input class="inp" type="number" name="depth" value="{{ $k['depth'] }}" data-kalk="depth"></div>
                    <div class="fld"><label>Höhe Wandprofil (mm)</label><input class="inp" type="number" name="wallH" value="{{ $k['wallH'] }}"></div>
                    <div class="fld"><label>Höhe Rinne (mm)</label><input class="inp" type="number" name="gutterH" value="{{ $k['gutterH'] }}"></div>
                    <div class="fld"><label>Dachneigung (°)</label><input class="inp" type="number" name="slope" value="{{ $k['slope'] }}"></div>
                    <div class="fld"><label>Pfosten (Override)</label><input class="inp" type="number" name="postN" value="{{ $k['postN'] }}" placeholder="auto" data-kalk="postN"></div>
                </div>
                <div class="fgrid2">
                    <div class="fld"><label>Farbe</label>
                        <select class="inp" name="color">
                            @foreach (KonfiguratorRechner::FARBEN as $farbe)
                                <option @selected($k['color'] === $farbe)>{{ $farbe }}</option>
                            @endforeach
                        </select></div>
                    <div class="fld"><label>Dachdeckung</label>
                        <select class="inp" name="covering">
                            @foreach (KonfiguratorRechner::DECKUNGEN as $deckung)
                                <option @selected($k['covering'] === $deckung)>{{ $deckung }}</option>
                            @endforeach
                        </select></div>
                    <div class="fld"><label>Glasfarbe</label>
                        <select class="inp" name="glasTrans">
                            <option @selected($k['glasTrans'] === 'Klar')>Klar</option>
                            <option @selected($k['glasTrans'] === 'Milch')>Milch</option>
                        </select></div>
                    <div class="fld"><label>Materialstärke</label>
                        <select class="inp" name="thickness">
                            @foreach (KonfiguratorRechner::STAERKEN as $staerke)
                                <option @selected($k['thickness'] === $staerke)>{{ $staerke }}</option>
                            @endforeach
                        </select></div>
                    <div class="fld"><label>Schneelastzone</label>
                        <select class="inp" name="snow">
                            @foreach (KonfiguratorRechner::SCHNEELAST as $zone)
                                <option @selected($k['snow'] === $zone)>{{ $zone }}</option>
                            @endforeach
                        </select></div>
                    <div class="fld"><label>Windzone</label>
                        <select class="inp" name="wind">
                            @foreach (KonfiguratorRechner::WINDZONE as $zone)
                                <option @selected($k['wind'] === $zone)>{{ $zone }}</option>
                            @endforeach
                        </select></div>
                </div>
            </div>

            <div class="cfg-block">
                <div class="fsec">Entwässerung, Wandanschluss &amp; Beleuchtung</div>
                <div class="fgrid2">
                    <div class="fld"><label>Ablauf an Pfosten Nr.</label><input class="inp" type="number" name="drain[post]" value="{{ $k['drain']['post'] }}"></div>
                    <div class="fld"><label>Ablauf Höhe (mm)</label><input class="inp" type="number" name="drain[height]" value="{{ $k['drain']['height'] }}"></div>
                    <div class="fld"><label>Ablauf Blickrichtung</label>
                        <select class="inp" name="drain[dir]">
                            @foreach (['nach vorn', 'nach hinten', 'nach links', 'nach rechts'] as $richtung)
                                <option @selected($k['drain']['dir'] === $richtung)>{{ $richtung }}</option>
                            @endforeach
                        </select></div>
                    <div class="fld"><label>Dübeltyp</label>
                        <select class="inp" name="duebel[typ]">
                            @foreach (['Schlagdübel', 'Bolzenanker', 'Injektionsanker', 'Porenbetonanker'] as $typ)
                                <option @selected($k['duebel']['typ'] === $typ)>{{ $typ }}</option>
                            @endforeach
                        </select></div>
                    <div class="fld"><label>Dübelgröße</label><input class="inp" type="text" name="duebel[size]" value="{{ $k['duebel']['size'] }}"></div>
                    <div class="fld"><label>Dübelabstand (mm)</label><input class="inp" type="number" name="duebel[abstand]" value="{{ $k['duebel']['abstand'] }}"></div>
                    <div class="fld"><label>LED-Spots gesamt</label>
                        <select class="inp" name="led[total]" data-kalk="ledTotal">
                            <option value="6" @selected($kalk['ledTot'] === 6)>6 Spots</option>
                            <option value="12" @selected($kalk['ledTot'] === 12)>12 Spots</option>
                        </select></div>
                    <div class="fld"><label>Lichtfarbe</label>
                        <select class="inp" name="led[color]">
                            @foreach (['Warmweiß 3000K', 'Neutralweiß 4000K', 'RGBW'] as $farbe)
                                <option @selected($k['led']['color'] === $farbe)>{{ $farbe }}</option>
                            @endforeach
                        </select></div>
                </div>
                <div class="kwarn" style="background:var(--bluebg);color:var(--blued)">
                    Randsparren bleiben ohne LED. Eingerechnet sind <b data-kalk-out="ledTot">{{ $kalk['ledTot'] }}</b> Spots —
                    die Verteilung legt der Monteur vor Ort fest.
                    Wandblende = Sparrenabstand − 60 mm = <b data-kalk-out="blende">{{ $kalk['blendeText'] }}</b>.
                </div>
            </div>

            <div class="cfg-block">
                <div class="fsec">Elemente · Mehrfachauswahl</div>
                <div class="radiorow">
                    @foreach (KonfiguratorRechner::EXTRAS as $extra)
                        <label class="rpill {{ $hat($extra) ? 'on' : '' }}">
                            <input type="checkbox" name="extras[]" value="{{ $extra }}" @checked($hat($extra))
                                   onchange="this.form.submit()" hidden>
                            <span class="rdot"></span>{{ $extra }}
                        </label>
                    @endforeach
                </div>
            </div>

            @if ($hat('Keile'))
                <div class="cfg-block">
                    <div class="fsec">Keile / Seitenelemente</div>
                    <div class="fgrid2">
                        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="keil[count]" value="{{ $k['keil']['count'] }}"></div>
                        <div class="fld"><label>Höhe vorn (mm)</label><input class="inp" type="number" name="keil[hFront]" value="{{ $k['keil']['hFront'] }}"></div>
                        <div class="fld"><label>Seite</label>
                            <select class="inp" name="keil[side]">
                                @foreach (['Links', 'Rechts', 'Beidseitig'] as $seite)
                                    <option @selected($k['keil']['side'] === $seite)>{{ $seite }}</option>
                                @endforeach
                            </select></div>
                        <div class="fld"><label>Material</label>
                            <select class="inp" name="keil[material]">
                                @foreach (['Glas', 'Aluminium', 'Polycarbonat'] as $material)
                                    <option @selected($k['keil']['material'] === $material)>{{ $material }}</option>
                                @endforeach
                            </select></div>
                        <div class="fld"><label>Transparenz</label>
                            <select class="inp" name="keil[trans]">
                                @foreach (['Klar', 'Opal', 'Matt'] as $trans)
                                    <option @selected($k['keil']['trans'] === $trans)>{{ $trans }}</option>
                                @endforeach
                            </select></div>
                    </div>
                </div>
            @endif

            @if ($hat('Festelemente'))
                <div class="cfg-block">
                    <div class="fsec">Festelemente / Seitenwand</div>
                    <div class="fgrid2">
                        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="fest[count]" value="{{ $k['fest']['count'] }}"></div>
                        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="fest[width]" value="{{ $k['fest']['width'] }}"></div>
                        <div class="fld"><label>Höhe links (mm)</label><input class="inp" type="number" name="fest[height]" value="{{ $k['fest']['height'] }}"></div>
                        <div class="fld"><label>Höhe rechts (mm)</label><input class="inp" type="number" name="fest[h2]" value="{{ $k['fest']['h2'] }}"></div>
                        <div class="fld"><label>Verglasung</label>
                            <select class="inp" name="fest[glas]">
                                @foreach (['VSG-Glas', 'Polycarbonat klar', 'Polycarbonat opal'] as $glas)
                                    <option @selected($k['fest']['glas'] === $glas)>{{ $glas }}</option>
                                @endforeach
                            </select></div>
                    </div>
                </div>
            @endif

            @if ($hat('Schiebe-Elemente'))
                <div class="cfg-block">
                    <div class="fsec">Schiebe-Elemente</div>
                    <div class="fgrid2">
                        <div class="fld"><label>Öffnung Breite (mm)</label><input class="inp" type="number" name="schiebe[width]" value="{{ $k['schiebe']['width'] }}"></div>
                        <div class="fld"><label>Öffnung Höhe (mm)</label><input class="inp" type="number" name="schiebe[height]" value="{{ $k['schiebe']['height'] }}"></div>
                        <div class="fld"><label>Anzahl Elemente</label><input class="inp" type="number" name="schiebe[count]" value="{{ $k['schiebe']['count'] }}"></div>
                        <div class="fld"><label>Laufrichtung</label>
                            <select class="inp" name="schiebe[dir]">
                                <option value="left" @selected($k['schiebe']['dir'] === 'left')>Nach links</option>
                                <option value="right" @selected($k['schiebe']['dir'] === 'right')>Nach rechts</option>
                                <option value="center" @selected($k['schiebe']['dir'] === 'center')>Mittig (beidseitig)</option>
                            </select></div>
                        <div class="fld"><label>Verglasung</label>
                            <select class="inp" name="schiebe[glas]">
                                <option @selected($k['schiebe']['glas'] === 'Klar')>Klar</option>
                                <option @selected($k['schiebe']['glas'] === 'Milchglas')>Milchglas</option>
                            </select></div>
                    </div>
                </div>
            @endif

            @if ($hat('Markisen'))
                <div class="cfg-block">
                    <div class="fsec">Markisen (Varisol)</div>
                    <div class="fgrid2">
                        <div class="fld"><label>Modell</label>
                            <select class="inp" name="markise[modell]">
                                @foreach (['Varisol T200', 'Varisol F413', 'Varisol F513'] as $modell)
                                    <option @selected($k['markise']['modell'] === $modell)>{{ $modell }}</option>
                                @endforeach
                            </select></div>
                        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="markise[width]" value="{{ $k['markise']['width'] }}"></div>
                        <div class="fld"><label>Ausfall (mm)</label><input class="inp" type="number" name="markise[ausfall]" value="{{ $k['markise']['ausfall'] }}"></div>
                        <div class="fld"><label>Anzahl Felder</label><input class="inp" type="number" name="markise[felder]" value="{{ $k['markise']['felder'] }}"></div>
                    </div>
                </div>
            @endif

            @if ($hat('Sonnensegel'))
                <div class="cfg-block">
                    <div class="fsec">Sonnensegel</div>
                    <div class="fgrid2">
                        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="segel[count]" value="{{ $k['segel']['count'] }}"></div>
                        <div class="fld"><label>Größe</label><input class="inp" type="text" name="segel[size]" value="{{ $k['segel']['size'] }}"></div>
                        <div class="fld"><label>Farbe</label>
                            <select class="inp" name="segel[color]">
                                @foreach (['Sandbeige', 'Anthrazit', 'Weiß', 'Grau'] as $farbe)
                                    <option @selected($k['segel']['color'] === $farbe)>{{ $farbe }}</option>
                                @endforeach
                            </select></div>
                    </div>
                </div>
            @endif

        </div>
        <div class="card-b jb" style="border-top:1px solid var(--bd2)">
            <button class="btn btns" type="submit" name="aktion" value="berechnen">Berechnen</button>
            @if ($vorschau)
                <span class="hint">Vorschau — noch nicht gespeichert</span>
            @endif
        </div>
    </form>

    <div class="colstack">
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Positionsliste</span>
                <span class="pill">live</span>
            </div>
            <div class="card-b">
                @include('projekte.partials.positionsliste', ['positionen' => $kalk['positionen']])
            </div>
        </div>

        <div class="kbox">
            <div class="fsec">Dach-Kalkulation</div>
            <div class="kgrid">
                <div class="kcell"><span>Pfosten</span><b class="mono" data-kalk-out="pn">{{ $kalk['pn'] ?: '–' }}</b></div>
                <div class="kcell"><span>Sparren</span><b class="mono" data-kalk-out="rafters">{{ $kalk['rafters'] ?: '–' }}</b></div>
                <div class="kcell"><span>Felder</span><b class="mono" data-kalk-out="fields">{{ $kalk['fields'] ?: '–' }}</b></div>
                <div class="kcell"><span>Sparrenabstand</span><b class="mono" data-kalk-out="spar">{{ $kalk['sparText'] }}</b></div>
                <div class="kcell"><span>Wandblende</span><b class="mono" data-kalk-out="blende2">{{ $kalk['blendeText'] }}</b></div>
                <div class="kcell"><span>LED-Spots</span><b class="mono" data-kalk-out="ledTot2">{{ $kalk['ledTot'] }}</b></div>
            </div>
            @if ($kalk['warnung'])
                <div class="kwarn">Tiefe &gt; 4000 mm — statische Prüfung / Unterzug erforderlich.</div>
            @endif
        </div>

        <div class="card">
            <div class="colstack" style="gap:9px">
                <button class="btn btnp btn-block" type="submit" form="konfigForm" name="aktion" value="speichern">
                    <svg class="i"><use href="#ic-check"/></svg>Konfiguration speichern</button>
                <form method="POST" action="{{ route('projekte.angebot', $projekt) }}">
                    @csrf
                    <button class="btn btn-block" type="submit">
                        <svg class="i"><use href="#ic-angebote"/></svg>Als Angebot übergeben</button>
                </form>
            </div>
        </div>
    </div>
</div>
