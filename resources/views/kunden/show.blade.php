@extends('layouts.app')

@section('title', $kunde->anzeigename)

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="card">
        <div class="jb wrap">
            <a class="btn btns" href="{{ route('kunden') }}">
                <svg class="i"><use href="#ic-aleft"/></svg>Zurück zur Liste</a>
            <span class="ctas">
                <button class="btn btns" type="button" data-toast="Aktion ausgeführt">
                    <svg class="i"><use href="#ic-edit"/></svg>Bearbeiten</button>
                <a class="btn btns btnp" href="{{ route('anfragen.create', ['kunde' => $kunde->kunden_nr]) }}">
                    <svg class="i"><use href="#ic-anfragen"/></svg>Neue Anfrage</a>
            </span>
        </div>
        <div class="fx" style="margin-top:14px">
            <span class="anf-nr mono">{{ $kunde->kunden_nr }}</span>
            <span class="pill">{{ $kunde->typ === 'gewerbe' ? 'Gewerbe' : 'Privatkunde' }}</span>
            <span class="badge {{ match ($kunde->status) { 'Aktiv' => 'b-green', 'Lead' => 'b-yellow', default => 'b-gray' } }}">{{ $kunde->status }}</span>
        </div>
        <h2 class="serif" style="margin:8px 0 14px;font-size:23px">{{ $kunde->anzeigename }}</h2>
        <div class="metarow">
            <span><span class="meta-k">Typ</span><span class="meta-v">{{ $kunde->typ === 'gewerbe' ? 'Gewerbe' : 'Privatkunde' }}</span></span>
            <span><span class="meta-k">Quelle</span><span class="meta-v">{{ $kunde->quelle ?? '–' }}</span></span>
            <span><span class="meta-k">Region</span><span class="meta-v">{{ $kunde->region ?? '–' }}</span></span>
            <span><span class="meta-k">Erstellt</span><span class="meta-v mono">{{ Format::datum($kunde->created_at) }}</span></span>
        </div>
    </div>

    <div class="cols-2">
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-kunden"/></svg>Kontaktdaten</div>
            <div class="pinfo">
                <span class="pk"><span>Name</span><b>{{ $kunde->anzeigename }}</b></span>
                <span class="pk"><span>Ansprechp.</span><b>{{ $kunde->ansprechpartner ?? '–' }}</b></span>
                <span class="pk"><span>Telefon</span><b class="mono">{{ $kunde->telefon ?? '–' }}</b></span>
                <span class="pk"><span>E-Mail</span><b>{{ $kunde->email ?? '–' }}</b></span>
            </div>
            <div class="specsec">Adresse</div>
            <div class="pinfo">
                <span class="pk"><span>Straße</span><b>{{ trim(($kunde->strasse ?? '').' '.($kunde->hausnummer ?? '')) ?: '–' }}</b></span>
                <span class="pk"><span>PLZ / Ort</span><b>{{ trim(($kunde->plz ?? '').' '.($kunde->stadt ?? '')) ?: '–' }}</b></span>
                <span class="pk"><span>Region</span><b>{{ $kunde->region ?? '–' }}</b></span>
            </div>
        </div>
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-tag"/></svg>Status &amp; Quelle</div>
            <div class="pinfo">
                <span class="pk"><span>Status</span>
                    <span class="badge {{ match ($kunde->status) { 'Aktiv' => 'b-green', 'Lead' => 'b-yellow', default => 'b-gray' } }}">{{ $kunde->status }}</span></span>
                <span class="pk"><span>Quelle</span><b>{{ $kunde->quelle ?? '–' }}</b></span>
            </div>
            @if (($kunde->tags ?? []) !== [])
                <div class="specsec">Tags</div>
                <div class="tags">
                    @foreach ($kunde->tags as $tag)
                        <span class="tag">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
            @if ($kunde->notizen)
                <div class="specsec">Notizen</div>
                <p class="note">{{ $kunde->notizen }}</p>
            @endif
        </div>
    </div>

    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Anfragen</span>
            <span class="pill">{{ $kunde->anfragen->count() }}</span>
        </div>
        <div class="card-b" style="overflow-x:auto">
            @if ($kunde->anfragen->isEmpty())
                <p class="hint">Keine Anfragen vorhanden.</p>
            @else
                <table class="tbl">
                    <thead><tr><th>Anfrage</th><th>Produkt</th><th>Aufmaß</th><th class="num">Status</th></tr></thead>
                    <tbody>
                    @foreach ($kunde->anfragen as $anfrage)
                        <tr class="lrow" onclick="window.location='{{ route('anfragen.show', $anfrage) }}'">
                            <td class="b mono">{{ $anfrage->nummer }}</td>
                            <td>{{ $anfrage->produkt_notiz ?? '–' }}</td>
                            <td class="mono">{{ Format::datumKurz($anfrage->besuchstermin_datum) }}</td>
                            <td class="num"><span class="badge {{ $anfrage->status->badgeClass() }}">{{ $anfrage->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Angebote</span>
            <span class="pill">{{ $kunde->angebote->count() }}</span>
        </div>
        <div class="card-b" style="overflow-x:auto">
            @if ($kunde->angebote->isEmpty())
                <p class="hint">Keine Angebote vorhanden.</p>
            @else
                <table class="tbl">
                    <thead><tr><th>Angebot</th><th>Datum</th><th class="num">Summe brutto</th><th class="num">Status</th></tr></thead>
                    <tbody>
                    @foreach ($kunde->angebote as $angebot)
                        <tr>
                            <td class="b mono">{{ $angebot->nr }}</td>
                            <td class="mono">{{ Format::datumKurz($angebot->datum) }}</td>
                            <td class="num mono">{{ $angebot->summe !== null ? Format::eur($angebot->summe) : '–' }}</td>
                            <td class="num"><span class="badge {{ $angebot->status->badgeClass() }}">{{ $angebot->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Projekte / Aufträge</span>
            <span class="pill">{{ $kunde->projekte->count() }}</span>
        </div>
        <div class="card-b" style="overflow-x:auto">
            @if ($kunde->projekte->isEmpty())
                <p class="hint">Keine Projekte vorhanden.</p>
            @else
                <table class="tbl">
                    <thead><tr><th>Projekt</th><th>Objekt</th><th>Montagezeitraum</th><th class="num">Status</th></tr></thead>
                    <tbody>
                    @foreach ($kunde->projekte as $projekt)
                        <tr class="lrow" onclick="window.location='{{ route('projekte.show', $projekt) }}'">
                            <td class="b mono">{{ $projekt->nr }}</td>
                            <td>{{ $projekt->titel }}</td>
                            <td class="mono">{{ $projekt->termin_von ? Format::datumKurz($projekt->termin_von).'–'.Format::datumKurz($projekt->termin_bis) : '–' }}</td>
                            <td class="num"><span class="badge {{ $projekt->status->badgeClass() }}">{{ $projekt->status->label() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-clock"/></svg>Aktivitäts-Timeline</div>
        <div class="tl">
            @foreach ($timeline as $eintrag)
                <div class="tli">
                    <span class="tld">
                        @if ($eintrag['status'] === 'done')
                            <svg class="i" style="width:11px;height:11px"><use href="#ic-check"/></svg>
                        @endif
                    </span>
                    <span style="flex:1">
                        <span class="tlt">{{ $eintrag['titel'] }}</span>
                        <div class="hint">{{ $eintrag['sub'] }} · {{ $eintrag['datum'] }}</div>
                    </span>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
