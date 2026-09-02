{{-- Zeichnungs-Lightbox (Prototyp Z. 968): Stage mit allen 5 Ansichten
     (server-gerendert, JS schaltet Sichtbarkeit), Prev/Next modulo 5,
     „Maße"-Schalter, Thumbnail-Leiste (immer ohne Maße).
     Erwartet: $projekt, $kalk, $roof = RoofZeichnung::alle(). --}}
@php
    use App\Support\RoofZeichnung;
    $k = $kalk['pcfg'];
@endphp
<div class="modal" id="roofLightbox" hidden>
    <div class="lbxc">
        <div class="lbxh">
            <div>
                <div class="card-t" data-roof-title>{{ RoofZeichnung::TITEL['iso'] }}</div>
                <div style="font-size:12px;font-weight:600;color:var(--ink3);margin-top:3px">
                    {{ $projekt->nr }} · {{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }}
                    {{ number_format((int) $k['width'], 0, ',', '.') }} × {{ number_format((int) $k['depth'], 0, ',', '.') }} mm</div>
            </div>
            <div class="fx ac gap8">
                <button class="btn btns on" type="button" data-roof-dims>
                    <svg class="i" style="width:15px;height:15px"><use href="#ic-layers"/></svg>Maße</button>
                <button class="lnav" type="button" data-roof-close aria-label="Schließen">
                    <svg class="i"><use href="#ic-x"/></svg></button>
            </div>
        </div>
        <div class="lbxstage">
            <button class="lnav lbx-l" type="button" data-roof-prev aria-label="Vorherige Ansicht">
                <svg class="i"><use href="#ic-aleft"/></svg></button>
            <div class="rdwrap">
                @foreach (RoofZeichnung::ANSICHTEN as $i => $ansicht)
                    <div data-roof-view data-roof-title="{{ RoofZeichnung::TITEL[$ansicht] }}" @if ($i > 0) hidden @endif>
                        @include('partials.roof-zeichnung', ['z' => $roof[$ansicht], 'nodim' => false])
                    </div>
                @endforeach
            </div>
            <button class="lnav lbx-r" type="button" data-roof-next aria-label="Nächste Ansicht">
                <svg class="i" style="transform:rotate(180deg)"><use href="#ic-aleft"/></svg></button>
        </div>
        <div class="lbxthumbs">
            @foreach (RoofZeichnung::ANSICHTEN as $i => $ansicht)
                <button class="lbxt {{ $i === 0 ? 'on' : '' }}" type="button" data-roof-thumb>
                    @include('partials.roof-zeichnung', ['z' => $roof[$ansicht], 'nodim' => true])
                    <span>{{ RoofZeichnung::TITEL[$ansicht] }}</span>
                </button>
            @endforeach
        </div>
    </div>
</div>
