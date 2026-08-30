@php
    use App\Support\Format;
    $k = $kalk['pcfg'];
@endphp

<div class="cols-2" style="grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);align-items:start">
    <div class="colstack">
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Technische Zeichnungen</span>
                <button class="btn btns btng" type="button" data-toast="Zeichnungen folgen im nächsten Milestone">Vollbild</button>
            </div>
            <div class="card-b">
                <div class="draw-grid">
                    @foreach (['Montageübersicht', 'Draufsicht', 'Vorderansicht', 'Seitenansicht', 'Detailschnitt A–A'] as $zeichnung)
                        <button class="dtile" type="button" data-toast="Zeichnungen folgen im nächsten Milestone">
                            <span class="dtl">{{ $zeichnung }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Technische Daten</span>
                <span class="pill">{{ $k['shape'] === 'trapez' ? 'Trapez' : 'Rechteck' }}</span>
            </div>
            <div class="card-b">
                <div class="specsec">Maße</div>
                <div class="spec-row"><span class="spec-k">Breite</span><span class="spec-v mono">{{ number_format($k['width'], 0, ',', '.') }} mm</span></div>
                <div class="spec-row"><span class="spec-k">Tiefe</span><span class="spec-v mono">{{ number_format($k['depth'], 0, ',', '.') }} mm</span></div>
                <div class="spec-row"><span class="spec-k">Höhe vorn</span><span class="spec-v mono">{{ number_format($k['gutterH'], 0, ',', '.') }} mm</span></div>
                <div class="spec-row"><span class="spec-k">Höhe hinten</span><span class="spec-v mono">{{ number_format($k['wallH'], 0, ',', '.') }} mm</span></div>
                <div class="specsec">Konstruktion</div>
                <div class="spec-row"><span class="spec-k">Farbe</span><span class="spec-v">{{ $k['color'] }}</span></div>
                <div class="spec-row"><span class="spec-k">Dach</span><span class="spec-v">{{ $k['covering'] }} {{ $k['thickness'] }}</span></div>
                <div class="spec-row"><span class="spec-k">Pfosten</span><span class="spec-v mono">{{ $kalk['pn'] }} × 110 × 110 mm</span></div>
                <div class="spec-row"><span class="spec-k">Dachneigung</span><span class="spec-v mono">{{ $k['slope'] }}°</span></div>
                <div class="spec-row"><span class="spec-k">Schneelast</span><span class="spec-v">{{ $k['snow'] }}</span></div>
                <div class="specsec">Ausstattung</div>
                <div class="spec-row"><span class="spec-k">Elemente</span><span class="spec-v">{{ ($k['extras'] ?? []) !== [] ? implode(' · ', $k['extras']) : '–' }}</span></div>
                <div class="spec-row"><span class="spec-k">LED-Beleuchtung</span><span class="spec-v">{{ $kalk['ledTot'] }} Spots · {{ $k['led']['color'] }}</span></div>
                <div class="spec-row"><span class="spec-k">Entwässerung</span><span class="spec-v">Pfosten {{ $k['drain']['post'] }} · {{ $k['drain']['dir'] }}</span></div>
            </div>
        </div>
    </div>

    <div class="colstack">
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-kunden"/></svg>Kunde</div>
            <div class="pinfo">
                <span class="pk"><span>Name</span><b>{{ $projekt->kunde->anzeigename }}</b></span>
                <span class="pk"><span>Typ</span><b>{{ $projekt->kunde->typ === 'gewerbe' ? 'Gewerbe' : 'Privatkunde' }} · {{ $projekt->kunde->kunden_nr }}</b></span>
                <span class="pk"><span>Adresse</span><b>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) ?: '–' }}<br>{{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</b></span>
            </div>
        </div>
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-user"/></svg>Verantwortlich</div>
            <div class="fx">
                <span class="uava">MS</span>
                <span><b>Max Schneider</b><div class="hint">Vertriebsleiter</div></span>
            </div>
        </div>
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-anfragen"/></svg>Projekt-Notizen</div>
            <p class="note">{{ $projekt->kunde->notizen ?? '–' }}</p>
        </div>
    </div>
</div>

<div class="cols-3">
    <div class="card p0">
        <div class="card-h"><span class="card-t">Materialliste</span><span class="pill">{{ count($materialVorschau) }} Positionen</span></div>
        <div class="card-b">
            <table class="tbl">
                <thead><tr><th>Pos</th><th>Bezeichnung</th><th class="num">Menge</th></tr></thead>
                <tbody>
                @foreach ($materialVorschau as $zeile)
                    <tr>
                        <td class="mono">{{ $zeile['pos'] }}</td>
                        <td>{{ $zeile['artikel']->name }}</td>
                        <td class="num mono">{{ Format::menge($zeile['menge']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <a class="cardlink" href="{{ route('projekte.show', [$projekt, 'tab' => 'material']) }}">Alle Positionen anzeigen</a>
        </div>
    </div>
    <div class="card p0">
        <div class="card-h"><span class="card-t">Dokumente</span><span class="pill">{{ $projekt->dokumente->count() }} Dateien</span></div>
        <div class="card-b">
            @foreach ($projekt->dokumente->take(4) as $dokument)
                <div class="doc">
                    <span class="fi pdf">{{ mb_strtoupper(pathinfo($dokument->dateiname, PATHINFO_EXTENSION)) }}</span>
                    <span class="dn">{{ $dokument->dateiname }}</span>
                </div>
            @endforeach
            <a class="cardlink" href="{{ route('projekte.show', [$projekt, 'tab' => 'dokumente']) }}">Alle Dokumente anzeigen</a>
        </div>
    </div>
    <div class="card p0">
        <div class="card-h"><span class="card-t">Aktivitäten</span></div>
        <div class="card-b">
            <div class="tl">
                @foreach ($projekt->aktivitaeten->take(4) as $aktivitaet)
                    <div class="tli">
                        <span class="tld">
                            @if ($aktivitaet->status === 'done')<svg class="i" style="width:11px;height:11px"><use href="#ic-check"/></svg>@endif
                        </span>
                        <span style="flex:1"><span class="tlt">{{ $aktivitaet->titel }}</span>
                            <div class="hint">{{ $aktivitaet->wer }} · {{ $aktivitaet->datum }}</div></span>
                    </div>
                @endforeach
            </div>
            <a class="cardlink" href="{{ route('projekte.show', [$projekt, 'tab' => 'aktivitaet']) }}">Alle Aktivitäten anzeigen</a>
        </div>
    </div>
</div>
