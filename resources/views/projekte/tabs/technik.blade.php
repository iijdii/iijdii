@php $k = $kalk['pcfg']; @endphp

<div class="card p0">
    <div class="card-h">
        <span class="card-t">Technische Zeichnungen — Konstruktion</span>
        <button class="btn btns" type="button" data-toast="Zeichnungen folgen im nächsten Milestone">Bemaßung im Detail</button>
    </div>
    <div class="card-b">
        <div class="draw-grid lg">
            @foreach (['Montageübersicht', 'Draufsicht', 'Vorderansicht', 'Seitenansicht', 'Detailschnitt A–A'] as $zeichnung)
                <button class="dtile" type="button" data-toast="Zeichnungen folgen im nächsten Milestone">
                    <span class="dtl">{{ $zeichnung }}</span>
                </button>
            @endforeach
        </div>
    </div>
</div>

<div class="cols-3">
    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-layers"/></svg>Statik</div>
        <div class="spec-row"><span class="spec-k">Schneelast</span><span class="spec-v">{{ $k['snow'] }}</span></div>
        <div class="spec-row"><span class="spec-k">Windlast</span><span class="spec-v">{{ $k['wind'] }}</span></div>
        <div class="spec-row"><span class="spec-k">Sparrenabstand</span><span class="spec-v mono">{{ $kalk['sparText'] }}</span></div>
        <div class="spec-row"><span class="spec-k">Nachweis</span><span class="badge b-green">Geprüft</span></div>
    </div>
    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-material"/></svg>Profile</div>
        <div class="spec-row"><span class="spec-k">Pfosten</span><span class="spec-v mono">110×110×3 mm</span></div>
        <div class="spec-row"><span class="spec-k">Unterzug</span><span class="spec-v mono">120×60 mm</span></div>
        <div class="spec-row"><span class="spec-k">Sparren</span><span class="spec-v mono">80×60 mm</span></div>
        <div class="spec-row"><span class="spec-k">Legierung</span><span class="spec-v">EN AW-6060 T66</span></div>
    </div>
    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-doc"/></svg>Verglasung</div>
        <div class="spec-row"><span class="spec-k">Typ</span><span class="spec-v">{{ $k['covering'] }} {{ $k['thickness'] }}</span></div>
        <div class="spec-row"><span class="spec-k">Felder</span><span class="spec-v mono">{{ $kalk['fields'] }} Stück</span></div>
        <div class="spec-row"><span class="spec-k">Feldbreite</span><span class="spec-v mono">{{ $kalk['sparText'] }}</span></div>
        <div class="spec-row"><span class="spec-k">Dichtung</span><span class="spec-v">EPDM schwarz</span></div>
    </div>
</div>
