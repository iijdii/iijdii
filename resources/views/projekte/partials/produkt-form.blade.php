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
        <div class="fld" data-produkt-felder="dach"><label>Farbe Konstruktion</label>
            <select class="inp" name="{{ $prefix }}[felder][color]">
                @foreach (KonfiguratorRechner::FARBEN as $farbe)
                    <option @selected($v('color', 'Weiß · RAL 9016') === $farbe)>{{ $farbe }}</option>
                @endforeach
            </select></div>
    </div>

    {{-- ——— Dachprodukte: pcfg-Basis, in Sinnabschnitte gegliedert.
         [data-dach-nur] blendet Teilbereiche nach den Steuer-Selects
         (Form, Montageart, Isolierung, Unterzug, Einzel-Befestigung). --}}
    <div data-produkt-felder="dach" class="fgrid2" style="margin-top:10px">

        <div class="fsec" style="grid-column:1/-1;margin:4px 0 0">Maße &amp; Form</div>
        <div class="fld"><label>Montageart</label>
            <select class="inp" name="{{ $prefix }}[felder][mounting]" data-dach-mounting>
                <option value="an der Wand" @selected($v('mounting', 'an der Wand') === 'an der Wand')>an der Wand</option>
                <option value="freistehend" @selected($v('mounting') === 'freistehend')>freistehend</option>
            </select></div>
        <div class="fld"><label>Form</label>
            <select class="inp" name="{{ $prefix }}[felder][shape]" data-dach-shape>
                <option value="rechteck" @selected($v('shape', 'rechteck') === 'rechteck')>Rechteck</option>
                <option value="trapez" @selected($v('shape') === 'trapez')>Trapez</option>
            </select></div>
        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][width]" value="{{ $v('width') }}" data-kalk="width"></div>
        <div class="fld"><label>Tiefe / Ausladung (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][depth]" value="{{ $v('depth') }}" data-kalk="depth"></div>
        <div class="fld" data-dach-nur="trapez"><label>Trapez: Länge Wandprofil (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][trapez][wand]" value="{{ $v('trapez.wand') }}" placeholder="= Breite"></div>
        <div class="fld" data-dach-nur="trapez"><label>Trapez: Länge Rinne (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][trapez][rinne]" value="{{ $v('trapez.rinne') }}" placeholder="= Breite"></div>
        <div class="fld" data-dach-nur="trapez"><label>Trapez-Offset links (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][trapez][offsetL]" value="{{ $v('trapez.offsetL') }}" placeholder="auto"></div>
        <div class="fld" data-dach-nur="trapez"><label>Trapez-Offset rechts (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][trapez][offsetR]" value="{{ $v('trapez.offsetR') }}" placeholder="auto"></div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Höhen &amp; Neigung</div>
        <div class="fld"><label>Höhe Wandprofil (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][wallH]" value="{{ $v('wallH') }}" data-dach-hoehe="wand"></div>
        <div class="fld"><label>Höhe Rinne (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][gutterH]" value="{{ $v('gutterH') }}" data-dach-hoehe="rinne"></div>
        <div class="fld"><label>Dachneigung (°)</label>
            <input class="inp" type="number" step="0.1" name="{{ $prefix }}[felder][slope]" value="{{ $v('slope') }}" data-dach-slope>
            <span class="hint">errechnet sich automatisch aus beiden Höhen und der Tiefe</span></div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0" data-dach-nur="wandmontage">Wandanschluss</div>
        <div class="fld" data-dach-nur="wandmontage"><label>Wandbelag</label>
            <select class="inp" name="{{ $prefix }}[felder][wand][belag]" data-dach-belag>
                @foreach (['Putz', 'Klinker', 'Holz'] as $belag)
                    <option @selected($v('wand.belag', 'Putz') === $belag)>{{ $belag }}</option>
                @endforeach
            </select></div>
        <div class="fld" data-dach-nur="wandmontage"><label>Isolierung</label>
            <select class="inp" name="{{ $prefix }}[felder][wand][isolierung]" data-dach-isolierung>
                <option value="nein" @selected($v('wand.isolierung', 'nein') === 'nein')>keine</option>
                <option value="ja" @selected($v('wand.isolierung') === 'ja')>vorhanden</option>
            </select></div>
        <div class="fld" data-dach-nur="isolierung"><label>Isolierstärke (mm)</label>
            <input class="inp" type="number" name="{{ $prefix }}[felder][wand][daemmstaerke]" value="{{ $v('wand.daemmstaerke') }}" data-dach-daemmung placeholder="z. B. 160"></div>
        <div class="fld" data-dach-nur="wandmontage"><label>Befestigung / Dübeltyp</label>
            <select class="inp" name="{{ $prefix }}[felder][duebel][typ]" data-dach-duebeltyp>
                @foreach (['Schlagdübel', 'Bolzenanker', 'Injektionsanker', 'Porenbetonanker', 'Stockschrauben'] as $typ)
                    <option @selected($v('duebel.typ', 'Schlagdübel') === $typ)>{{ $typ }}</option>
                @endforeach
            </select>
            <span class="hint">Vorschlag folgt Belag &amp; Isolierung — überschreibbar</span></div>
        <div class="fld" data-dach-nur="wandmontage"><label>Dübellänge / -größe</label>
            <input class="inp" type="text" name="{{ $prefix }}[felder][duebel][size]" value="{{ $v('duebel.size', '10 × 80 mm') }}" data-dach-duebelsize></div>
        <div class="fld" data-dach-nur="wandmontage"><label>Dübelabstand (mm)</label>
            <input class="inp" type="number" name="{{ $prefix }}[felder][duebel][abstand]" value="{{ $v('duebel.abstand', 500) }}"></div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Pfosten</div>
        <div class="fld"><label>Anzahl Pfosten</label>
            <input class="inp" type="number" name="{{ $prefix }}[felder][postN]" value="{{ $v('postN') }}" placeholder="auto" data-kalk="postN">
            <span class="hint">berechnet: <b class="mono" data-kalk-out="pn">–</b> Stück — bei Bedarf ändern</span></div>
        <div class="fld"><label>Positionen (automatisch symmetrisch)</label>
            <span class="hint" style="display:block;padding:9px 0"><span class="mono" data-kalk-out="posten">–</span> mm</span></div>
        <label class="fx ac gap8" style="grid-column:1/-1;cursor:pointer">
            <input type="checkbox" name="{{ $prefix }}[felder][postAdvanced]" value="1"
                   data-dach-postadv @checked($v('postAdvanced') == 1 || $v('postManual') !== '' || $v('postLeftOffset') !== '' || $v('postRightOffset') !== '' || $v('postMiddle') !== '')>
            Abstände &amp; Positionen anpassen</label>
        <div class="fld" data-dach-nur="postadv"><label>Positionen manuell (CSV, mm)</label>
            <input class="inp mono" type="text" name="{{ $prefix }}[felder][postManual]" value="{{ $v('postManual') }}" placeholder="z. B. 0, 3500, 7000" data-kalk="postManual"></div>
        <div class="fld" data-dach-nur="postadv"><label>Randabstand links (mm, max. 500)</label><input class="inp" type="number" max="500" name="{{ $prefix }}[felder][postLeftOffset]" value="{{ $v('postLeftOffset') }}" placeholder="0"></div>
        <div class="fld" data-dach-nur="postadv"><label>Randabstand rechts (mm, max. 500)</label><input class="inp" type="number" max="500" name="{{ $prefix }}[felder][postRightOffset]" value="{{ $v('postRightOffset') }}" placeholder="0"></div>
        <div class="fld" data-dach-nur="postadv"><label>Mittelpfosten-Position (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][postMiddle]" value="{{ $v('postMiddle') }}" placeholder="Mitte"></div>
        <div class="fld"><label>Pfosten-Befestigung</label>
            <select class="inp" name="{{ $prefix }}[felder][postMontage]">
                @foreach (['Beton', 'U-Profil', 'Pfostenhalter'] as $montage)
                    <option @selected($v('postMontage', 'Beton') === $montage)>{{ $montage === 'Pfostenhalter' ? 'Pfostenhalter (Konsole)' : $montage }}</option>
                @endforeach
            </select></div>
        <label class="fx ac gap8" style="grid-column:1/-1;cursor:pointer">
            <input type="checkbox" name="{{ $prefix }}[felder][postMontageJe]" value="1"
                   data-dach-montageje @checked($v('postMontageJe') == 1)>
            Befestigung je Pfosten einzeln festlegen</label>
        <div class="fld" style="grid-column:1/-1" data-dach-nur="montageje" data-dach-montageliste
             data-dach-montageliste-werte='@json((array) $v("postMontageListe", []))'
             data-dach-montageliste-name="{{ $prefix }}[felder][postMontageListe]">
            <span class="hint">Reihenfolge von links nach rechts.</span></div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Unterzug</div>
        <div class="fld"><label>Unterzug</label>
            <select class="inp" name="{{ $prefix }}[felder][unterzug][on]" data-dach-unterzug>
                <option value="nein" @selected($v('unterzug.on', 'nein') === 'nein')>keiner</option>
                <option value="ja" @selected($v('unterzug.on') === 'ja')>vorhanden</option>
            </select></div>
        <div class="fld" data-dach-nur="unterzug"><label>Anzahl</label>
            <select class="inp" name="{{ $prefix }}[felder][unterzug][anzahl]">
                @foreach ([1, 2, 3] as $anzahl)
                    <option value="{{ $anzahl }}" @selected((int) $v('unterzug.anzahl', 1) === $anzahl)>{{ $anzahl }}</option>
                @endforeach
            </select></div>
        <div class="fld" data-dach-nur="unterzug"><label>Größe</label>
            <select class="inp" name="{{ $prefix }}[felder][unterzug][groesse]">
                @foreach (['110×190', '110×110', 'manuell'] as $groesse)
                    <option @selected($v('unterzug.groesse', '110×190') === $groesse)>{{ $groesse }}</option>
                @endforeach
            </select></div>
        <div class="fld" data-dach-nur="unterzug"><label>Überstand (mm)</label>
            <input class="inp" type="number" name="{{ $prefix }}[felder][unterzug][ueberstand]" value="{{ $v('unterzug.ueberstand') }}" placeholder="0"></div>
        <div class="fld" data-dach-nur="unterzug"><label>Pfostenlinie / Terrassentiefe (mm)</label>
            <input class="inp" type="number" name="{{ $prefix }}[felder][terraceDepth]" value="{{ $v('terraceDepth') }}" placeholder="= Dachtiefe"></div>
        <div class="fld" data-dach-nur="unterzug"><label>Dachüberstand Rinne (mm)</label>
            <input class="inp" type="number" name="{{ $prefix }}[felder][gutterOverhang]" value="{{ $v('gutterOverhang') }}" placeholder="0"></div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Profile (Rinne / Wandprofil)</div>
        <div class="fld" style="grid-column:1/-1"><label>Segmente automatisch (max. {{ number_format(KonfiguratorRechner::MAX_PROFIL, 0, ',', '.') }} mm je Profil)</label>
            <span class="hint" style="display:block;padding:6px 0"><span class="mono" data-kalk-out="segmente">–</span></span></div>
        <label class="fx ac gap8" style="grid-column:1/-1;cursor:pointer">
            <input type="checkbox" name="{{ $prefix }}[felder][profilManuell]" value="1"
                   data-dach-profilmanuell @checked($v('profilManuell') == 1)>
            Segmente manuell festlegen (Anzahl &amp; Längen)</label>
        <div class="fld" style="grid-column:1/-1" data-dach-nur="profil"><label>Segmentlängen (CSV, mm — Anzahl folgt den Einträgen)</label>
            <input class="inp mono" type="text" name="{{ $prefix }}[felder][profilSegmenteListe]" value="{{ $v('profilSegmenteListe') }}"
                   placeholder="z. B. 7500, 1130" data-kalk="profilListe">
            <span class="hint">Der Stoß liegt am Ende jedes Segments — Pfosten unter dem Stoß empfohlen (Stoß − 55).</span></div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Dachdeckung</div>
        <div class="fld"><label>Deckung</label>
            <select class="inp" name="{{ $prefix }}[felder][covering]" data-kalk="covering">
                @foreach (KonfiguratorRechner::DECKUNGEN as $deckung)
                    <option @selected($v('covering', 'Glas') === $deckung || str_contains($v('covering', ''), $deckung === 'Glas' ? 'Glas' : 'Polycarbonat'))>{{ $deckung }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Ausführung</label>
            <select class="inp" name="{{ $prefix }}[felder][glasTrans]">
                <option value="Klar" @selected($v('glasTrans', 'Klar') === 'Klar')>Klar</option>
                <option value="Milch" @selected($v('glasTrans') === 'Milch')>Milchig / Opal</option>
            </select></div>
        <div class="fld"><label>Stärke</label>
            <select class="inp" name="{{ $prefix }}[felder][thickness]">
                @foreach (KonfiguratorRechner::STAERKEN as $staerke)
                    <option @selected($v('thickness', '8 mm') === $staerke)>{{ $staerke }}</option>
                @endforeach
            </select></div>
        <div class="fld"><label>Glasfelder (Override)</label>
            <input class="inp" type="number" name="{{ $prefix }}[felder][fieldN]" value="{{ $v('fieldN') }}" placeholder="auto" data-kalk="fieldN">
            <span class="hint">Feldmaß: <span class="mono" data-kalk-out="glas">–</span></span></div>
        <div class="kwarn" style="grid-column:1/-1" data-kalk-warn="glas" hidden>
            Eindeckungsbreite über Plattenmaß (Glas {{ KonfiguratorRechner::MAX_GLAS_BREITE }} /
            Stegplatte {{ KonfiguratorRechner::POLY_PLATTE }} mm) — Feldanzahl erhöhen.</div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Entwässerung</div>
        <div class="fld"><label>Ablauf-Pfosten (Seite)</label>
            <select class="inp" name="{{ $prefix }}[felder][drain][post]">
                <option value="links" @selected($v('drain.post', 'links') === 'links' || is_numeric($v('drain.post')))>links</option>
                <option value="rechts" @selected($v('drain.post') === 'rechts')>rechts</option>
            </select></div>
        <div class="fld"><label>Ablauf Höhe (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][drain][height]" value="{{ $v('drain.height', 1150) }}"></div>
        <div class="fld"><label>Ablauf Blickrichtung</label>
            <select class="inp" name="{{ $prefix }}[felder][drain][dir]">
                @foreach (['nach vorn', 'nach hinten', 'nach links', 'nach rechts'] as $richtung)
                    <option @selected($v('drain.dir', 'nach vorn') === $richtung)>{{ $richtung }}</option>
                @endforeach
            </select></div>

        <div class="fsec" style="grid-column:1/-1;margin:6px 0 0">Beleuchtung</div>
        <div class="fld"><label>LED-Spots gesamt</label>
            <select class="inp" name="{{ $prefix }}[felder][led][total]" data-kalk="ledTotal">
                <option value="0" @selected((int) $v('led.total', 12) === 0)>Keine Beleuchtung</option>
                <option value="6" @selected((int) $v('led.total', 12) === 6)>6 Spots</option>
                <option value="12" @selected((int) $v('led.total', 12) === 12)>12 Spots</option>
            </select></div>
        <div class="fld"><label>Lichtfarbe</label>
            <select class="inp" name="{{ $prefix }}[felder][led][color]">
                @foreach (['Warmweiß 3000K', 'Neutralweiß 4000K', 'RGBW'] as $farbe)
                    <option @selected($v('led.color', 'Warmweiß 3000K') === $farbe)>{{ $farbe }}</option>
                @endforeach
            </select></div>
    </div>

    {{-- ——— Extras ——— --}}
    <div data-produkt-felder="wand" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Elemente nebeneinander</label><input class="inp" type="number" name="{{ $prefix }}[felder][anzahl]" value="{{ $v('anzahl', 1) }}"></div>
        <div class="fld"><label>Reihen übereinander</label><input class="inp" type="number" name="{{ $prefix }}[felder][reihen]" value="{{ $v('reihen', 1) }}"></div>
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
        <div class="fld"><label>Einbauort</label>
            <input class="inp" type="text" name="{{ $prefix }}[felder][einbauort]" value="{{ $v('einbauort') }}"
                   list="einbauorte" placeholder="z. B. Vorne links">
            <datalist id="einbauorte">
                @foreach (['Links', 'Rechts', 'Vorne', 'Vorne links', 'Vorne Mitte', 'Vorne rechts'] as $ort)
                    <option value="{{ $ort }}"></option>
                @endforeach
            </datalist></div>
        <div class="fld"><label>Verglasung</label><input class="inp" type="text" name="{{ $prefix }}[felder][glas]" value="{{ $v('glas', 'VSG 8 mm klar') }}"></div>
    </div>

    <div data-produkt-felder="keil" class="fgrid2" style="margin-top:10px">
        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="{{ $prefix }}[felder][anzahl]" value="{{ $v('anzahl', 1) }}"></div>
        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][breite_mm]" value="{{ $v('breite_mm') }}" placeholder="leer = Dachtiefe"></div>
        <div class="fld"><label>Höhe hinten (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][h_hinten_mm]" value="{{ $v('h_hinten_mm') }}" placeholder="leer = aus Gefälle"></div>
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
        <div class="fld"><label>Anzahl</label><input class="inp" type="number" name="{{ $prefix }}[felder][anzahl]" value="{{ $v('anzahl') }}" placeholder="leer = Anzahl Dachfelder"></div>
        <div class="fld"><label>Breite (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][breite_mm]" value="{{ $v('breite_mm') }}" placeholder="leer = wie Wandblende"></div>
        <div class="fld"><label>Länge (mm)</label><input class="inp" type="number" name="{{ $prefix }}[felder][laenge_mm]" value="{{ $v('laenge_mm') }}" placeholder="leer = Dachtiefe"></div>
        <div class="fld"><label>Farbe</label><input class="inp" type="text" name="{{ $prefix }}[felder][farbe]" value="{{ $v('farbe', 'Sandbeige') }}"></div>
    </div>
</div>
