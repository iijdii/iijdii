@extends('layouts.app')

@section('title', $anfrage->nummer)

@php
    use App\Support\Format;
    use App\Support\GlasSkizze;
    $d = $anfrage->details ?? [];
    $keile = $d['keils'] ?? [];
    $sidewalls = $d['sidewalls'] ?? [];
    $sliding = $d['sliding'] ?? [];
    $trapez = str_contains(mb_strtolower($d['form'] ?? ($anfrage->form ?? '')), 'trapez');
    $mm = fn ($v) => $v ? number_format((int) preg_replace('/[^\d]/', '', (string) $v), 0, ',', '.').' mm' : '–';
@endphp

@section('content')
<div class="colstack">

    <div class="card">
        <div class="jb wrap">
            <a class="btn btns" href="{{ route('anfragen') }}">
                <svg class="i"><use href="#ic-aleft"/></svg>Zurück zur Liste</a>
            <span class="ctas">
                <a class="btn btns" href="{{ route('anfragen.edit', $anfrage) }}">
                    <svg class="i"><use href="#ic-edit"/></svg>Bearbeiten</a>
                <form method="POST" action="{{ route('anfragen.projekt', $anfrage) }}">
                    @csrf
                    <button class="btn btns btnp" type="submit">
                        <svg class="i"><use href="#ic-projekte"/></svg>Projekt erstellen</button>
                </form>
            </span>
        </div>
        <div class="fx" style="margin-top:14px">
            <span class="anf-nr mono">{{ $anfrage->nummer }}</span>
            <span class="badge {{ $anfrage->status->badgeClass() }}">{{ $anfrage->status->label() }}</span>
            @if ($anfrage->prioritaet && $anfrage->prioritaet !== \App\Enums\Prioritaet::Normal)
                <span class="badge {{ $anfrage->prioritaet->badgeClass() }}">Priorität {{ $anfrage->prioritaet->label() }}</span>
            @endif
        </div>
        <h2 class="serif" style="margin:8px 0 14px;font-size:23px">{{ $anfrage->produkt_notiz ?? 'Anfrage' }}</h2>
        <div class="metarow">
            <span><span class="meta-k">Kunde</span><span class="meta-v">{{ $anfrage->kunde->anzeigename }}</span></span>
            <span><span class="meta-k">Ort</span><span class="meta-v">{{ $anfrage->objekt_stadt ?? '–' }}</span></span>
            <span><span class="meta-k">Quelle</span><span class="meta-v">{{ $anfrage->anfrage_quelle ?? '–' }}</span></span>
            <span><span class="meta-k">Eingang</span><span class="meta-v mono">{{ Format::datumKurz($anfrage->created_at) }}</span></span>
            <span><span class="meta-k">Aufmaß</span><span class="meta-v mono">{{ Format::datumKurz($anfrage->besuchstermin_datum) }}</span></span>
        </div>

        <div class="stp">
            <span class="stp-l">Status</span>
            <span class="stp-bs">
                @foreach ($stufen as $nr => $status)
                    @php
                        $cls = match ($nr) { 1 => 's-gray', 2 => 's-yellow', 3 => 's-blue', 4 => 's-green' };
                        $aktuell = $anfrage->status->uiStufe() === $nr;
                    @endphp
                    <form method="POST" action="{{ route('anfragen.status', $anfrage) }}">
                        @csrf
                        <input type="hidden" name="stufe" value="{{ $nr }}">
                        <button class="stp-b {{ $cls }} {{ $aktuell ? 'cur' : '' }}" type="submit" @disabled($aktuell)>
                            @if ($aktuell)<svg class="i"><use href="#ic-check"/></svg>@endif
                            {{ $status->label() }}
                        </button>
                    </form>
                @endforeach
            </span>
        </div>
    </div>

    <div class="cols-2">
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-kunden"/></svg>Kunde</div>
            <div class="pinfo">
                <span class="pk"><span>Name</span><b>{{ $anfrage->kunde->anzeigename }}</b></span>
                <span class="pk"><span>Ansprechp.</span><b>{{ $d['contact'] ?? $anfrage->kunde->ansprechpartner ?? '–' }}</b></span>
                <span class="pk"><span>Straße</span><b>{{ $anfrage->objekt_strasse ? trim($anfrage->objekt_strasse.' '.$anfrage->objekt_hausnummer) : '–' }}</b></span>
                <span class="pk"><span>PLZ / Ort</span><b>{{ trim(($anfrage->objekt_plz ?? '').' '.($anfrage->objekt_stadt ?? '')) ?: '–' }}</b></span>
                <span class="pk"><span>Telefon</span><b class="mono">{{ $anfrage->kunden_telefon ?? '–' }}</b></span>
                <span class="pk"><span>E-Mail</span><b>{{ $anfrage->kunden_email ?? '–' }}</b></span>
            </div>
        </div>
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-kalender"/></svg>Termin / Aufmaß</div>
            <div class="pinfo">
                <span class="pk"><span>Datum</span><b class="mono">{{ Format::datum($anfrage->besuchstermin_datum) }}</b></span>
                <span class="pk"><span>Zeit</span><b class="mono">{{ $d['terminTime'] ?? ($anfrage->besuchstermin_uhrzeit ?? '–') }}</b></span>
                <span class="pk"><span>Art</span><b>{{ $d['terminType'] ?? 'Aufmaß' }}</b></span>
            </div>
            @if (($d['produkte'] ?? $anfrage->interessierte_produkte ?? []) !== [])
                <div class="specsec">Produkte</div>
                <div class="tags">
                    @foreach ($d['produkte'] ?? $anfrage->interessierte_produkte as $produkt)
                        <span class="tag">{{ $produkt }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Position 1 — Überdachung</span>
            <span class="pill">{{ $d['produktart'] ?? $anfrage->produkt_notiz ?? '–' }}</span>
        </div>
        <div class="card-b cols-2">
            <div>
                <div class="specsec">Basis</div>
                <div class="spec-row"><span class="spec-k">Produktart</span><span class="spec-v">{{ $d['produktart'] ?? '–' }}</span></div>
                <div class="spec-row"><span class="spec-k">Montageart</span><span class="spec-v">{{ $d['montage'] ?? ($anfrage->befestigung_art ?? '–') }}</span></div>
                <div class="spec-row"><span class="spec-k">Farbe</span><span class="spec-v">{{ $d['farbe'] ?? ($anfrage->profil_farbe_name ?? '–') }}</span></div>
                <div class="spec-row"><span class="spec-k">Fassade</span><span class="spec-v">{{ $d['fassade'] ?? '–' }}</span></div>
                <div class="specsec">Form &amp; Geometrie</div>
                <div class="spec-row"><span class="spec-k">Form</span><span class="spec-v">{{ $d['form'] ?? ($anfrage->form ?? '–') }}</span></div>
                <div class="spec-row"><span class="spec-k">Breite</span><span class="spec-v mono">{{ $d['breite'] ?? ($anfrage->breite_cm ? $mm($anfrage->breite_cm * 10) : '–') }}</span></div>
                <div class="spec-row"><span class="spec-k">Tiefe</span><span class="spec-v mono">{{ $d['tiefe'] ?? ($anfrage->tiefe_cm ? $mm($anfrage->tiefe_cm * 10) : '–') }}</span></div>
                @if ($trapez)
                    <div class="spec-row"><span class="spec-k">Länge Wandprofil</span><span class="spec-v mono">{{ $d['wpLen'] ?? '–' }}</span></div>
                    <div class="spec-row"><span class="spec-k">Länge Rinne</span><span class="spec-v mono">{{ $d['rinneLen'] ?? '–' }}</span></div>
                @endif
            </div>
            <div>
                <div class="specsec">Höhen &amp; Dachneigung</div>
                <div class="spec-row"><span class="spec-k">Höhe Wandprofil</span><span class="spec-v mono">{{ $d['wandH'] ?? '–' }}</span></div>
                <div class="spec-row"><span class="spec-k">Höhe Rinne</span><span class="spec-v mono">{{ $d['rinneH'] ?? '–' }}</span></div>
                <div class="spec-row"><span class="spec-k">Dachneigung</span><span class="spec-v mono">{{ $d['neigung'] ?? ($anfrage->dachneigung_grad ? $anfrage->dachneigung_grad.'°' : '–') }}</span></div>
                <div class="spec-row"><span class="spec-k">Gefälle</span><span class="spec-v mono">{{ $d['gefaelle'] ?? '–' }}</span></div>
                <div class="specsec">Dachdeckung &amp; Beleuchtung</div>
                <div class="spec-row"><span class="spec-k">Material</span><span class="spec-v">{{ $d['dachMat'] ?? ($anfrage->dach_material ?? '–') }}</span></div>
                <div class="spec-row"><span class="spec-k">Stärke</span><span class="spec-v">{{ $d['dachStk'] ?? '–' }}</span></div>
                <div class="spec-row"><span class="spec-k">Beleuchtung</span><span class="spec-v">{{ $d['ledCount'] ?? ($anfrage->led_beleuchtung ? 'LED' : '–') }}</span></div>
            </div>
        </div>
    </div>

    <div class="card p0">
        <div class="card-h"><span class="card-t">Pfosten &amp; Profile</span></div>
        <div class="card-b">
            <div class="spec-row"><span class="spec-k">Anzahl Pfosten</span><span class="spec-v mono">{{ $d['pfAnzahl'] ?? ($anfrage->anzahl_stuetzen ?? '–') }}</span></div>
            <div class="spec-row"><span class="spec-k">Befestigung</span><span class="spec-v">{{ $d['montage'] ?? ($anfrage->befestigung_art ?? '–') }}</span></div>
            <div class="spec-row"><span class="spec-k">Untergrund</span><span class="spec-v">{{ $anfrage->untergrund_typ ?? '–' }}</span></div>
        </div>
    </div>

    @if ($keile !== [])
        <div class="card p0">
            <div class="card-h"><span class="card-t">Keil(e)</span><span class="pill">{{ count($keile) }}</span></div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                    <tr><th>Seite</th><th>Material</th><th>Transparenz</th><th class="num">Höhe hinten</th><th class="num">Höhe vorne</th><th class="num">Breite unten</th><th class="num">Breite oben</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($keile as $keil)
                        <tr>
                            <td class="b">{{ $keil[0] }}</td>
                            <td>{{ $keil[1] }}</td>
                            <td>{{ $keil[2] }}</td>
                            <td class="num mono">{{ $mm($keil[3] ?? null) }}</td>
                            <td class="num mono">{{ $mm($keil[4] ?? null) }}</td>
                            <td class="num mono">{{ $mm($keil[5] ?? null) }}</td>
                            <td class="num mono">{{ $mm($keil[6] ?? null) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="skgrid">
                    @foreach ($keile as $keil)
                        @if (($keil[3] ?? 0) > 0 && ($keil[5] ?? 0) > 0)
                            @include('anfragen.partials.keil-skizze', [
                                'seite' => $keil[0],
                                'sk' => GlasSkizze::keil((int) $keil[3], (int) $keil[4], (int) $keil[5]),
                                'hB' => $keil[3], 'hF' => $keil[4], 'bU' => $keil[5],
                            ])
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($sidewalls !== [])
        <div class="card p0">
            <div class="card-h"><span class="card-t">Seitenwände</span><span class="pill">{{ count($sidewalls) }}</span></div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl">
                    <thead><tr><th>Position</th><th>Anzahl</th><th>Material</th><th>Transparenz</th></tr></thead>
                    <tbody>
                    @foreach ($sidewalls as $wand)
                        <tr>
                            <td class="b">{{ $wand[0] }}</td>
                            <td class="mono">{{ $wand[1] }}</td>
                            <td>{{ $wand[2] }}</td>
                            <td>{{ $wand[3] ?? '–' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Draufsicht (Prototyp: statische Schema-Karte im Anfrage-Detail) --}}
    <div class="card p0">
        <div class="card-h"><span class="card-t">Draufsicht</span><span class="pill mono">Schema</span></div>
        <div class="card-b" style="padding:13px">
            <div class="dtile" style="height:230px;width:100%;cursor:default">
                <span class="dtl">Draufsicht</span>
                @include('partials.roof-zeichnung', ['z' => $draufsicht, 'nodim' => false])
            </div>
        </div>
    </div>

    @if ($anfrage->kommentar_intern)
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-pen"/></svg>Interner Kommentar</div>
            <p class="note">{{ $anfrage->kommentar_intern }}</p>
        </div>
    @endif

    @if ($sliding !== [])
        <div class="card p0">
            <div class="card-h"><span class="card-t">Schiebesystem(e)</span><span class="pill">{{ count($sliding) }}</span></div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl">
                    <thead><tr><th>Elemente</th><th>Glas-Typ</th><th class="num">Breite</th><th class="num">Höhe</th><th>Öffnungsrichtung</th><th>Griff</th></tr></thead>
                    <tbody>
                    @foreach ($sliding as $anlage)
                        <tr>
                            <td class="b mono">{{ $anlage[0] }}</td>
                            <td>{{ $anlage[1] }}</td>
                            <td class="num mono">{{ $mm($anlage[2] ?? null) }}</td>
                            <td class="num mono">{{ $mm($anlage[3] ?? null) }}</td>
                            <td>{{ $anlage[4] ?? '–' }}</td>
                            <td>{{ $anlage[5] ?? '–' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
