{{-- Produkt-Positions-Formular: gruppiertes Produkt-Select + Feldblöcke.
     Dach-Felder sind bewusst im pcfg-Format benannt (width/depth/…), damit
     KonfigurationSync sie 1:1 nach projekte.konfiguration spiegeln kann.
     Wiederverwendet im Anfrage-Formular und im Projekt-Konfigurator.
     Ohne JS sind alle Blöcke sichtbar — der Server liest nur das gewählte
     Produkt. --}}
@php
    use App\Enums\ProjektProdukt;
    use App\Support\KonfiguratorRechner;
    $f = old($prefix.'.felder', $position?->felder ?? []);
    $v = fn (string $pfad, $standard = '') => data_get($f, $pfad, $standard);
    $produktWert = old($prefix.'.produkt', $position?->produkt?->value ?? ($standardProdukt ?? 'ueberdachung'));
@endphp

<div data-position-form data-kalk-scope>
    <div class="fgrid2">
        <div class="fld"><label>Produkt</label>
            <select class="inp" name="{{ $prefix }}[produkt]" data-pos-produkt>
                @foreach (ProjektProdukt::nachGruppen() as $gruppe => $produkte)
                    <optgroup label="{{ $gruppe }}">
                        @foreach ($produkte as $produkt)
                            <option value="{{ $produkt->value }}" data-dach="{{ $produkt->istDach() ? 1 : 0 }}"
                                @selected($produktWert === $produkt->value)>{{ $produkt->label() }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select></div>
    </div>

    {{-- ——— Dachprodukte: pcfg-Basis ——— --}}
    <div data-produkt-felder="dach" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Montageart</label>
            <select class="inp" name="{{ $prefix }}[felder][mounting]">
                <option value="an der Wand" @selected($v('mounting', 'an der Wand') === 'an der Wand')>an der Wand</option>
                <option value="freistehend" @selected($v('mounting') === 'freistehend')>freistehend</option>
            </select></div>
        <div class="fld"><label>Form</label>
            <select class="inp" name="{{ $prefix }}[felder][shape]">
                <option value="rechteck" @selected($v('shape', 'rechteck') === 'rechteck')>Rechteck</option>
                <option value="trapez" @selected($v('shape') === 'trapez')>Trapez</option>
            </select></div>
        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][width]" value="{{ $v('width') }}" data-kalk="width"></div>
        <div class="fld"><label>Tiefe / Ausladung (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][depth]" value="{{ $v('depth') }}" data-kalk="depth"></div>
        <div class="fld"><label>Höhe Wandprofil (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][wallH]" value="{{ $v('wallH') }}"></div>
        <div class="fld"><label>Höhe Rinne (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][gutterH]" value="{{ $v('gutterH') }}"></div>
        <div class="fld"><label>Dachneigung (°)</label><input class="inp" type="number" name="{{ $prefix }}[felder][slope]" value="{{ $v('slope') }}"></div>
        <div class="fld"><label>Pfosten (Override)</label><input class="inp" type="number" name="{{ $prefix }}[felder][postN]" value="{{ $v('postN') }}" placeholder="auto" data-kalk="postN"></div>
        <div class="fld"><label>Glasfelder (Override)</label><input class="inp" type="number" name="{{ $prefix }}[felder][fieldN]" value="{{ $v('fieldN') }}" placeholder="auto" data-kalk="fieldN"></div>
        <div class="fld"><label>Farbe</label>
            <select class="inp" name="{{ $prefix }}[felder][color]">
                @foreach (KonfiguratorRechner::FARBEN as $farbe)
                    <option @selected($v('color', 'Weiß · RAL 9016') === $farbe)>{{ $farbe }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Dachdeckung</label>
            <select class="inp" name="{{ $prefix }}[felder][covering]" data-kalk="covering">
                @foreach (KonfiguratorRechner::DECKUNGEN as $deckung)
                    <option @selected($v('covering', 'VSG-Glas') === $deckung)>{{ $deckung }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Stärke</label>
            <select class="inp" name="{{ $prefix }}[felder][thickness]">
                @foreach (KonfiguratorRechner::STAERKEN as $staerke)
                    <option @selected($v('thickness', '8 mm') === $staerke)>{{ $staerke }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Glasfarbe</label>
            <select class="inp" name="{{ $prefix }}[felder][glasTrans]">
                <option value="Klar" @selected($v('glasTrans', 'Klar') === 'Klar')>Klar</option>
                <option value="Milch" @selected($v('glasTrans') === 'Milch')>Milch</option>
            </select></div>
        <div class="fld"><label>Schneelastzone</label>
            <select class="inp" name="{{ $prefix }}[felder][snow]">
                @foreach (KonfiguratorRechner::SCHNEELAST as $zone)
                    <option @selected($v('snow', 'SLZ 2 · 0,85 kN/m²') === $zone)>{{ $zone }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Windzone</label>
            <select class="inp" name="{{ $prefix }}[felder][wind]">
                @foreach (KonfiguratorRechner::WINDZONE as $zone)
                    <option @selected($v('wind', 'WZ 2 · Binnenland') === $zone)>{{ $zone }}</option>
                @endforeach
            </select></div>

        {{-- Entwässerung, Wandanschluss & Beleuchtung — pcfg-verschachtelt,
             damit KonfigurationSync sie 1:1 spiegelt (Montage-Modus liest sie). --}}
        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Entwässerung, Wandanschluss &amp; Beleuchtung</div>
        <div class="fld"><label>Ablauf an Pfosten Nr.</label><input class="inp" type="number" name="{{ $prefix }}[felder][drain][post]" value="{{ $v('drain.post', 1) }}"></div>
        <div class="fld"><label>Ablauf Höhe (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][drain][height]" value="{{ $v('drain.height', 1150) }}"></div>
        <div class="fld"><label>Ablauf Blickrichtung</label>
            <select class="inp" name="{{ $prefix }}[felder][drain][dir]">
                @foreach (['nach vorn', 'nach hinten', 'nach links', 'nach rechts'] as $richtung)
                    <option @selected($v('drain.dir', 'nach vorn') === $richtung)>{{ $richtung }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Dübeltyp</label>
            <select class="inp" name="{{ $prefix }}[felder][duebel][typ]">
                @foreach (['Schlagdübel', 'Bolzenanker', 'Injektionsanker', 'Porenbetonanker'] as $typ)
                    <option @selected($v('duebel.typ', 'Schlagdübel') === $typ)>{{ $typ }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Dübelgröße</label><input class="inp" type="text" name="{{ $prefix }}[felder][duebel][size]" value="{{ $v('duebel.size', '10 × 80 mm') }}"></div>
        <div class="fld"><label>Dübelabstand (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][duebel][abstand]" value="{{ $v('duebel.abstand', 500) }}"></div>
        <div class="fld"><label>LED-Spots gesamt</label>
            <select class="inp" name="{{ $prefix }}[felder][led][total]" data-kalk="ledTotal">
                <option value="6" @selected((int) $v('led.total', 12) === 6)>6 Spots</option>
                <option value="12" @selected((int) $v('led.total', 12) === 12)>12 Spots</option>
            </select></div>
        <div class="fld"><label>Lichtfarbe</label>
            <select class="inp" name="{{ $prefix }}[felder][led][color]">
                @foreach (['Warmweiß 3000K', 'Neutralweiß 4000K', 'RGBW'] as $farbe)
                    <option @selected($v('led.color', 'Warmweiß 3000K') === $farbe)>{{ $farbe }}</option>
                @endforeach
            </select></div>

        {{-- Live-Kalkulation im Konfigurator-Fenster --}}
        <div class="kbox" style="grid-column:1/-1;margin-top:6px">
            <div class="fsec">Dach-Kalkulation</div>
            <div class="kgrid">
                <div class="kcell"><span>Pfosten</span><b class="mono" data-kalk-out="pn">–</b></div>
                <div class="kcell"><span>Sparren</span><b class="mono" data-kalk-out="rafters">–</b></div>
                <div class="kcell"><span>Felder</span><b class="mono" data-kalk-out="fields">–</b></div>
                <div class="kcell"><span>Sparrenabstand</span><b class="mono" data-kalk-out="spar">–</b></div>
                <div class="kcell"><span>Wandblende</span><b class="mono" data-kalk-out="blende">–</b></div>
                <div class="kcell"><span>Glasmaß</span><b class="mono" data-kalk-out="glas">–</b></div>
                <div class="kcell"><span>LED-Spots</span><b class="mono" data-kalk-out="ledTot">12</b></div>
            </div>
            <div class="kwarn" data-kalk-warn="glas" hidden>
                Eindeckungsbreite über Plattenmaß (Glas {{ KonfiguratorRechner::MAX_GLAS_BREITE }} /
                Stegplatte {{ KonfiguratorRechner::POLY_PLATTE }} mm) — Feldanzahl erhöhen.</div>
        </div>
    </div>

    {{-- ——— Extras ——— --}}
    <div data-produkt-felder="wand" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="{{ $prefix }}[felder][anzahl]" value="{{ $v('anzahl', 1) }}"></div>
        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][breite_mm]" value="{{ $v('breite_mm') }}"></div>
        <div class="fld"><label>Höhe links (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][h_links_mm]" value="{{ $v('h_links_mm') }}"></div>
        <div class="fld"><label>Höhe rechts (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][h_rechts_mm]" value="{{ $v('h_rechts_mm') }}"></div>
        <div class="fld"><label>Verglasung</label><input class="inp" type="text" name="{{ $prefix }}[felder][glas]" value="{{ $v('glas', 'VSG-Glas') }}"></div>
    </div>

    <div data-produkt-felder="schiebe" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Öffnung Breite (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][breite_mm]" value="{{ $v('breite_mm') }}"></div>
        <div class="fld"><label>Öffnung Höhe (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][hoehe_mm]" value="{{ $v('hoehe_mm') }}"></div>
        <div class="fld"><label>Anzahl Elemente</label><input class="inp" type="number" name="{{ $prefix }}[felder][anzahl]" value="{{ $v('anzahl', 3) }}"></div>
        <div class="fld"><label>Laufrichtung</label>
            <select class="inp" name="{{ $prefix }}[felder][richtung]">
                @foreach (['Nach links', 'Nach rechts', 'Mittig'] as $richtung)
                    <option @selected($v('richtung', 'Nach links') === $richtung)>{{ $richtung }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Verglasung</label><input class="inp" type="text" name="{{ $prefix }}[felder][glas]" value="{{ $v('glas', 'VSG 8 mm klar') }}"></div>
    </div>

    <div data-produkt-felder="keil" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="{{ $prefix }}[felder][anzahl]" value="{{ $v('anzahl', 1) }}"></div>
        <div class="fld"><label>Höhe vorn (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][h_vorn_mm]" value="{{ $v('h_vorn_mm') }}"></div>
        <div class="fld"><label>Seite</label>
            <select class="inp" name="{{ $prefix }}[felder][seite]">
                @foreach (['Links', 'Rechts', 'Beidseitig'] as $seite)
                    <option @selected($v('seite', 'Links') === $seite)>{{ $seite }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Material</label>
            <select class="inp" name="{{ $prefix }}[felder][material]">
                @foreach (['Glas', 'Aluminium', 'Polycarbonat'] as $material)
                    <option @selected($v('material', 'Glas') === $material)>{{ $material }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Transparenz</label>
            <select class="inp" name="{{ $prefix }}[felder][transparenz]">
                @foreach (['Klar', 'Opal', 'Matt'] as $transparenz)
                    <option @selected($v('transparenz', 'Klar') === $transparenz)>{{ $transparenz }}</option>
                @endforeach
            </select></div>
    </div>

    <div data-produkt-felder="gelaender" class="fgrid2" style="margin-top:10px">
        {{-- Feldsatz-Entwurf — vom Betreiber zu bestätigen. --}}
        <div class="fld"><label>Länge (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][laenge_mm]" value="{{ $v('laenge_mm') }}"></div>
        <div class="fld"><label>Höhe (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][hoehe_mm]" value="{{ $v('hoehe_mm', 1000) }}"></div>
        <div class="fld"><label>Anzahl Felder</label><input class="inp" type="number" name="{{ $prefix }}[felder][felder_n]" value="{{ $v('felder_n') }}"></div>
        <div class="fld"><label>Material</label>
            <select class="inp" name="{{ $prefix }}[felder][material]">
                @foreach (['Aluminium', 'Glas', 'Edelstahl'] as $material)
                    <option @selected($v('material', 'Aluminium') === $material)>{{ $material }}</option>
                @endforeach
            </select></div>
    </div>

    {{-- ——— Sonnenschutz ——— --}}
    <div data-produkt-felder="markise" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Modell</label><input class="inp" type="text" name="{{ $prefix }}[felder][modell]" value="{{ $v('modell', 'Varisol T200') }}"></div>
        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][breite_mm]" value="{{ $v('breite_mm') }}"></div>
        <div class="fld"><label>Ausfall (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][ausfall_mm]" value="{{ $v('ausfall_mm') }}"></div>
        <div class="fld"><label>Anzahl Felder</label><input class="inp" type="number" name="{{ $prefix }}[felder][felder_n]" value="{{ $v('felder_n', 1) }}"></div>
        <div class="fld"><label>Antrieb</label>
            <select class="inp" name="{{ $prefix }}[felder][antrieb]">
                @foreach (['Motor', 'Kurbel'] as $antrieb)
                    <option @selected($v('antrieb', 'Motor') === $antrieb)>{{ $antrieb }}</option>
                @endforeach
            </select></div>
    </div>

    <div data-produkt-felder="sonnensegel" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="{{ $prefix }}[felder][anzahl]" value="{{ $v('anzahl', 1) }}"></div>
        <div class="fld"><label>Größe</label><input class="inp" type="text" name="{{ $prefix }}[felder][groesse]" value="{{ $v('groesse', '4×4 m') }}"></div>
        <div class="fld"><label>Farbe</label><input class="inp" type="text" name="{{ $prefix }}[felder][farbe]" value="{{ $v('farbe', 'Sandbeige') }}"></div>
    </div>
</div>
