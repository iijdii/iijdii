@php use App\Support\Format; @endphp

<div class="lbxh">
    <div>
        <b>{{ $artikel->name }}</b>
        <div class="hint"><span class="mono">{{ $artikel->art_nr }}</span> · {{ $artikel->kategorie->label() }} · {{ $artikel->lieferant->name }}</div>
    </div>
    <button class="lnav" type="button" data-modal-close aria-label="Schließen">
        <svg class="i"><use href="#ic-x"/></svg>
    </button>
</div>

<div class="mbody">
    @php
        [$badge, $bc] = match ($artikel->bestandsstatus()) {
            'ok' => ['Auf Lager', 'b-green'],
            'niedrig' => ['Niedrig', 'b-yellow'],
            'leer' => ['Nachbestellen', 'b-red'],
        };
    @endphp
    <div class="kpirow" style="grid-template-columns:repeat(4,1fr)">
        <div class="lagcell"><span class="lk">Bestand</span><span class="lv mono">{{ $artikel->bestand }}</span></div>
        <div class="lagcell"><span class="lk">Reserviert</span><span class="lv mono">{{ $artikel->reserviert() }}</span></div>
        <div class="lagcell"><span class="lk">Verfügbar</span><span class="lv mono">{{ $artikel->verfuegbar() }}</span></div>
        <div class="lagcell"><span class="lk">Mindestbestand</span><span class="lv mono">{{ $artikel->min_bestand }}</span></div>
    </div>

    <div class="metarow">
        <span><span class="meta-k">Lagerort</span><span class="meta-v mono">{{ $artikel->lagerort }}</span></span>
        <span><span class="meta-k">Einheit</span><span class="meta-v">{{ $artikel->einheit->value }}</span></span>
        <span><span class="meta-k">EK-Preis</span><span class="meta-v mono">{{ Format::eur($artikel->ek_preis) }}</span></span>
        <span><span class="meta-k">Lagerwert</span><span class="meta-v mono">{{ Format::eur0($artikel->lagerwert()) }}</span></span>
        <span><span class="meta-k">Status</span><span class="badge {{ $bc }}">{{ $badge }}</span></span>
    </div>

    @if ($zugang)
        <div class="card lg-ok" style="margin-top:12px">
            <b>Letzter Zugang:</b> Zugang {{ Format::datumKurz($zugang['datum']) }} {{ $zugang['nr'] }} aus {{ $zugang['ls'] }}
        </div>
    @endif

    @if ($reservierungen->isNotEmpty())
        <div class="mc-h"><svg class="i"><use href="#ic-tag"/></svg>Reservierungen</div>
        <table class="tbl">
            <thead><tr><th>Projekt</th><th>Kunde</th><th>Menge</th></tr></thead>
            <tbody>
            @foreach ($reservierungen as $reservierung)
                <tr>
                    <td class="mono">{{ $reservierung->projekt->nr }}</td>
                    <td>{{ $reservierung->projekt->kunde->anzeigename }}</td>
                    <td><b class="mono">{{ Format::menge($reservierung->menge) }}</b></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($bewegungen->isNotEmpty())
        <div class="mc-h"><svg class="i"><use href="#ic-clock"/></svg>Bewegungen</div>
        <table class="tbl">
            <thead><tr><th>Datum</th><th>Typ</th><th>Menge</th><th>Referenz</th><th>Benutzer</th></tr></thead>
            <tbody>
            @foreach ($bewegungen as $bewegung)
                <tr>
                    <td class="mono">{{ Format::datum($bewegung->datum) }}</td>
                    <td>{{ $bewegung->typ->value }}</td>
                    <td class="mono">{{ $bewegung->typ->value === 'Reservierung' ? $bewegung->menge : Format::mengeSigniert($bewegung->menge) }}</td>
                    <td class="mono">{{ $bewegung->referenz }}</td>
                    <td>{{ $bewegung->benutzer_name ?? $bewegung->benutzer?->name ?? '–' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mfoot">
    <span class="ctas">
        <a class="btn btns" href="{{ route('material-katalog.edit', $artikel) }}">
            <svg class="i"><use href="#ic-edit"/></svg>Bearbeiten</a>
        <button class="btn btns" type="button" data-toast="Korrekturbuchung – Formular geöffnet">
            <svg class="i"><use href="#ic-pen"/></svg>Korrektur buchen</button>
    </span>
    <span class="ctas">
        <button class="btn btns" type="button" data-modal-close>Schließen</button>
        <button class="btn btns btnp" type="button"
                data-toast="Nachbestellung {{ $artikel->art_nr }} vorgemerkt · {{ max($artikel->min_bestand * 2 - $artikel->bestand, $artikel->min_bestand) }} {{ $artikel->einheit->value }}">
            <svg class="i"><use href="#ic-bestellungen"/></svg>Nachbestellen</button>
    </span>
</div>
