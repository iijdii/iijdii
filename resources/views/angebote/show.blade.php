@extends('layouts.app')

@section('title', 'Angebot · '.$angebot->nr)

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="jb" style="flex-wrap:wrap;gap:10px">
        <a class="btn btns" href="{{ route('angebote') }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Zurück zu den Angeboten</a>
        <span class="ctas">
            <a class="btn btns" href="{{ route('angebote.pdf', $angebot) }}">
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

    <div class="cols-2" style="grid-template-columns:minmax(0,1.7fr) minmax(260px,1fr);align-items:start">
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Positionen aus der Konfiguration</span>
                <span class="pill">{{ count($positionen) }}</span>
            </div>
            <div class="card-b" style="padding:6px 17px">
                @if ($positionen === [])
                    <p class="hint" style="padding:10px 0">Keine Konfiguration hinterlegt — Positionen folgen aus dem Projekt-Konfigurator.</p>
                @else
                    <table class="tbl">
                        <thead><tr><th>Pos</th><th>Bezeichnung</th><th class="num">Menge</th></tr></thead>
                        <tbody>
                        @foreach ($positionen as $position)
                            <tr>
                                <td><span class="pos">{{ $position['pos'] }}</span></td>
                                <td class="b">{{ $position['name'] }}</td>
                                <td class="num mono">{{ $position['menge'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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
                <form method="POST" action="{{ route('angebote.summe', $angebot) }}"
                      class="fx ac gap8" style="margin-top:10px">
                    @csrf
                    <input class="inp mono" type="number" step="0.01" min="0" name="summe"
                           value="{{ old('summe', $angebot->summe) }}" placeholder="Gesamtsumme (brutto)" style="flex:1">
                    <button class="btn btns" type="submit">Speichern</button>
                </form>
                @error('summe')<p class="hint" style="color:var(--red)">{{ $message }}</p>@enderror
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
