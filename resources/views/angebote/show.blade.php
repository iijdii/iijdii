@extends('layouts.app')

@section('title', 'Angebot · '.$angebot->nr)

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="jb" style="flex-wrap:wrap;gap:10px">
        <a class="btn btns" href="{{ route('angebote') }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Zurück zu den Angeboten</a>
        <span class="ctas">
            <a class="btn btns" href="{{ route('angebote.pdf', $angebot) }}"
               data-pdf data-pdf-titel="Angebot {{ $angebot->nr }}">
                <svg class="i"><use href="#ic-doc"/></svg>PDF</a>
            @if ($angebot->projekt)
                <a class="btn btns" href="{{ route('projekte.show', $angebot->projekt) }}">
                    <svg class="i"><use href="#ic-projekte"/></svg>Zum Projekt</a>
            @endif
        </span>
    </div>

    <div class="card">
        <div class="fx ac gap10" style="flex-wrap:wrap">
            <span class="anf-nr mono">{{ $angebot->nr }}</span>
            <span class="badge {{ $angebot->status->badgeClass() }}">{{ $angebot->status->label() }}</span>
        </div>
        <h2 class="serif" style="font-size:25px;margin:8px 0 4px">{{ $angebot->titel ?? 'Angebot' }}</h2>
        <div class="metarow">
            <span><span class="meta-k">Kunde</span><span class="meta-v">
                <a href="{{ route('kunden.show', $angebot->kunde) }}" style="color:inherit">{{ $angebot->kunde->anzeigename }}</a></span></span>
            <span><span class="meta-k">Datum</span><span class="meta-v mono">{{ Format::datumKurz($angebot->datum) }}</span></span>
            <span><span class="meta-k">Projekt</span><span class="meta-v mono">
                @if ($angebot->projekt)
                    <a href="{{ route('projekte.show', $angebot->projekt) }}" style="color:var(--blued)">{{ $angebot->projekt->nr }}</a>
                @else – @endif</span></span>
            <span><span class="meta-k">Anfrage</span><span class="meta-v mono">
                @if ($angebot->anfrage)
                    <a href="{{ route('anfragen.show', $angebot->anfrage) }}" style="color:var(--blued)">{{ $angebot->anfrage->nummer }}</a>
                @else – @endif</span></span>
        </div>
    </div>

    @php $eingefroren = $angebot->status === \App\Enums\AngebotStatus::Angenommen; @endphp
    <div class="cols-2" style="grid-template-columns:minmax(0,1.7fr) minmax(260px,1fr);align-items:start">
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Positionen &amp; Preise</span>
                <span class="pill">{{ count($rechnung['positionen']) }}</span>
            </div>
            <div class="card-b" style="padding:6px 17px 14px">
                @if (count($rechnung['positionen']) === 1)
                    <p class="hint" style="padding:8px 0 0">Keine Konfiguration hinterlegt —
                        Positionen folgen aus dem Projekt-Konfigurator.</p>
                @endif
                <form method="POST" action="{{ route('angebote.preise', $angebot) }}">
                    @csrf
                    <table class="tbl">
                        <thead><tr><th>Pos</th><th>Bezeichnung</th><th class="num">Menge</th>
                            <th class="num">Einzelpreis €</th><th class="num">Rabatt %</th><th class="num">Gesamt</th></tr></thead>
                        <tbody>
                        @foreach ($rechnung['positionen'] as $position)
                            <tr>
                                <td><span class="pos">{{ $position['pos'] }}</span></td>
                                <td>
                                    <span class="b">{{ $position['titel'] }}</span>
                                    @foreach ($position['details'] as $detail)
                                        <div class="hint">{{ $detail }}</div>
                                    @endforeach
                                    @if ($position['key'] === 'dach' && $position['listenpreis'] !== null)
                                        <div class="hint">Listenpreis laut Preisliste:
                                            {{ Format::eur($position['listenpreis']) }} · zzgl. Montagekosten</div>
                                    @endif
                                </td>
                                <td class="num mono">{{ $position['menge'] }}</td>
                                <td class="num" style="width:110px">
                                    <input class="inp mono num" type="number" step="0.01" min="0"
                                           name="preise[{{ $position['key'] }}]"
                                           value="{{ old('preise.'.$position['key'], ($angebot->preise[$position['key']] ?? null)) }}"
                                           placeholder="{{ $position['listenpreis'] !== null ? number_format($position['listenpreis'], 2, '.', '') : '–' }}"
                                           @disabled($eingefroren)>
                                </td>
                                <td class="num" style="width:76px">
                                    <input class="inp mono num" type="number" step="0.01" min="0" max="100"
                                           name="rabatte[{{ $position['key'] }}]"
                                           value="{{ old('rabatte.'.$position['key'], ($angebot->rabatte[$position['key']] ?? null)) }}"
                                           placeholder="0"
                                           @disabled($eingefroren)>
                                </td>
                                <td class="num mono">{{ $position['gesamt'] !== null ? Format::eur($position['gesamt']) : '–' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="fx ac gap8" style="margin-top:10px;flex-wrap:wrap;justify-content:flex-end">
                        <label class="hint" for="rabatt">Gesamtrabatt %</label>
                        <input class="inp mono num" id="rabatt" type="number" step="0.01" min="0" max="100"
                               name="rabatt_prozent" value="{{ old('rabatt_prozent', (float) $angebot->rabatt_prozent ?: '') }}"
                               style="width:90px" @disabled($eingefroren)>
                        <button class="btn btns btnp" type="submit" @disabled($eingefroren)>Preise speichern</button>
                    </div>
                </form>
                <table class="tbl" style="margin-top:8px">
                    <tbody>
                    <tr><td class="num" style="border:0">Zwischensumme</td>
                        <td class="num mono" style="width:130px;border:0">{{ Format::eur($rechnung['zwischensumme']) }}</td></tr>
                    @if ($rechnung['rabattBetrag'] > 0)
                        <tr><td class="num" style="border:0">Rabatt {{ rtrim(rtrim(number_format($rechnung['rabattProzent'], 2, ',', '.'), '0'), ',') }} %</td>
                            <td class="num mono" style="border:0">−{{ Format::eur($rechnung['rabattBetrag']) }}</td></tr>
                    @endif
                    <tr><td class="num b" style="border:0">Gesamtbetrag (brutto)</td>
                        <td class="num mono b" style="border:0">{{ Format::eur($rechnung['gesamt']) }}</td></tr>
                    <tr><td class="num hint" style="border:0">darin enthaltene MwSt. 19 %</td>
                        <td class="num mono hint" style="border:0">{{ Format::eur($rechnung['mwst']) }}</td></tr>
                    </tbody>
                </table>
                @if ($eingefroren)
                    <p class="hint">Angenommen am {{ $angebot->angenommen_am?->format('d.m.Y H:i') ?? '–' }} —
                        das Angebot ist eingefroren.</p>
                @endif
            </div>
        </div>

        <div class="colstack">
            <div class="kbox">
                <div class="kt">Angebotssumme</div>
                <div class="lgstat"><b>{{ $angebot->summe !== null ? Format::eur($angebot->summe) : '—' }}</b>
                    <span>brutto</span></div>
                @if ($angebot->summe === null)
                    <p class="hint">Noch keine Summe erfasst — «in Konfiguration».</p>
                @endif
                @unless ($eingefroren)
                    <form method="POST" action="{{ route('angebote.summe', $angebot) }}"
                          class="fx ac gap8" style="margin-top:10px">
                        @csrf
                        <input class="inp mono" type="number" step="0.01" min="0" name="summe"
                               value="{{ old('summe', $angebot->summe) }}" placeholder="Gesamtsumme (brutto)" style="flex:1">
                        <button class="btn btns" type="submit">Speichern</button>
                    </form>
                    @error('summe')<p class="hint" style="color:var(--red)">{{ $message }}</p>@enderror
                @endunless
            </div>
            <div class="card">
                <div class="mc-h"><svg class="i"><use href="#ic-doc"/></svg>Online-Annahme</div>
                <p class="hint">Dieser Link steht auf Seite 4 des Angebots-PDF — der Kunde kann das
                    Angebot damit ohne Login verbindlich annehmen
                    (gültig bis {{ $angebot->gueltig_bis?->format('d.m.Y') ?? '–' }}).</p>
                <input class="inp mono" readonly value="{{ $annahmeUrl }}" onclick="this.select()" style="width:100%;margin-top:6px">
                @if ($angebot->angenommen_am)
                    <p class="hint" style="margin-top:6px">Online angenommen am
                        {{ $angebot->angenommen_am->format('d.m.Y H:i') }} · IP {{ $angebot->angenommen_ip ?? '–' }}</p>
                @endif
            </div>
            <div class="card">
                <div class="mc-h"><svg class="i"><use href="#ic-angebote"/></svg>Status</div>
                <div class="kv"><div class="k">Aktuell</div>
                    <div class="v"><span class="badge {{ $angebot->status->badgeClass() }}">{{ $angebot->status->label() }}</span></div></div>
                @if ($naechsteStatus === [])
                    <p class="hint">Keine weiteren Übergänge — Angebot ist angenommen.</p>
                @endif
                <div class="colstack" style="gap:8px;margin-top:8px">
                    @foreach ($naechsteStatus as $wert)
                        @php $ziel = \App\Enums\AngebotStatus::from($wert); @endphp
                        <form method="POST" action="{{ route('angebote.status', $angebot) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $wert }}">
                            <button class="btn btns {{ $wert === 'angenommen' ? 'btnp' : '' }}" type="submit" style="width:100%">
                                {{ match ($wert) {
                                    'versendet' => 'Versenden',
                                    'angenommen' => 'Annehmen',
                                    'abgelehnt' => 'Ablehnen',
                                    'entwurf' => 'Erneut bearbeiten',
                                } }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
