@extends('layouts.app')

@section('title', 'Lieferanten')

@php
    use App\Enums\Rolle;
    use App\Support\Format;
    $darfPflegen = auth()->user()->hasRole(Rolle::Admin, Rolle::Verkaeufer, Rolle::Projektleiter, Rolle::Lager);
@endphp

@section('content')
<div class="colstack">

    <div class="kpirow">
        <div class="kpi2 kp-blue"><div class="kl">Lieferanten</div><div class="kn">{{ $lieferanten->count() }}</div><div class="ks">Stammdaten gepflegt</div></div>
        <div class="kpi2 kp-gold"><div class="kl">Artikel im Katalog</div><div class="kn">{{ $artikelGesamt }}</div><div class="ks">über alle Lieferanten</div></div>
        <div class="kpi2 kp-green"><div class="kl">Offene Bestellungen</div><div class="kn">{{ $offenGesamt }}</div><div class="ks">geprüft · bestellt · bereit</div></div>
        <div class="kpi2 kp-dark"><div class="kl">Lagerwert</div><div class="kn" style="font-size:24px">{{ Format::eur0($lagerwertGesamt) }}</div><div class="ks">EK-Basis, gesamter Bestand</div></div>
    </div>

    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Lieferanten</span>
            <span class="pill">{{ $lieferanten->count() }}</span>
            @if ($darfPflegen)
                <button class="btn btnp" type="button" data-modal-target="lieferant-neu" style="margin-left:auto">
                    <svg class="i"><use href="#ic-plus"/></svg>Neuer Lieferant</button>
            @endif
        </div>
        <div class="card-b" style="padding:6px 17px;overflow-x:auto">
            <table class="tbl">
                <thead><tr><th>Name</th><th>Sortiment</th><th>Ansprechpartner</th><th>Kontakt</th><th>Ort</th>
                    <th class="num">Artikel</th><th class="num">Offene Bestellungen</th><th class="num">Lagerwert</th>@if ($darfPflegen)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($lieferanten as $zeile)
                    @php $lieferant = $zeile['lieferant']; @endphp
                    <tr class="lrow" onclick="if (! event.target.closest('button')) window.location='{{ route('bestellungen', ['lieferant' => $lieferant->id]) }}'">
                        <td class="b">{{ $lieferant->name }}@if ($lieferant->kundennummer)<div class="hint">Kundennr. {{ $lieferant->kundennummer }}</div>@endif</td>
                        <td style="font-size:12px;color:var(--ink3)">{{ $lieferant->sortiment ?? '–' }}</td>
                        <td>{{ $lieferant->ansprechpartner ?? '–' }}</td>
                        <td>
                            <span class="mono" style="font-size:12px">{{ $lieferant->telefon ?? '–' }}</span>
                            <div style="font-size:11px;color:var(--ink3)">{{ $lieferant->email ?? '–' }}</div>
                        </td>
                        <td>{{ trim(($lieferant->plz ?? '').' '.($lieferant->stadt ?? '')) ?: '–' }}</td>
                        <td class="num mono">{{ $lieferant->artikel_count }}</td>
                        <td class="num mono">{{ $zeile['offeneBestellungen'] }}</td>
                        <td class="num mono">{{ Format::eur0($zeile['lagerwert']) }}</td>
                        @if ($darfPflegen)
                            <td class="num">
                                <button class="btn btns" type="button" data-modal-target="lieferant-{{ $lieferant->id }}">Bearbeiten</button>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="hint">Klick auf eine Zeile öffnet die Bestellungen. Löschen geht nur bei Lieferanten ohne Bestellungen, Artikel und Portal-Zugänge.</p>

</div>

@if ($darfPflegen)
    @php $fehlerNeu = $errors->getBag('lieferant_neu'); @endphp
    <div class="modal" id="lieferant-neu" @unless ($fehlerNeu->any()) hidden @endunless>
        <div class="modalc">
            <div class="modalh">Neuer Lieferant
                <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
            <div class="modalb">
                <form method="POST" action="{{ route('lieferanten.store') }}" class="colstack" style="gap:10px">
                    @csrf
                    @include('lieferanten.partials.felder', ['l' => null, 'fehler' => $fehlerNeu])
                    <div class="jb"><span class="hint">Pflicht ist nur der Name.</span>
                        <button class="btn btnp" type="submit">Anlegen</button></div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($lieferanten as $zeile)
        @php $l = $zeile['lieferant']; $fehlerL = $errors->getBag('lieferant_'.$l->id); @endphp
        <div class="modal" id="lieferant-{{ $l->id }}" @unless ($fehlerL->any()) hidden @endunless>
            <div class="modalc">
                <div class="modalh">{{ $l->name }} bearbeiten
                    <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
                <div class="modalb">
                    <form method="POST" action="{{ route('lieferanten.update', $l) }}" class="colstack" style="gap:10px">
                        @csrf
                        @include('lieferanten.partials.felder', ['l' => $l, 'fehler' => $fehlerL])
                        <div class="jb"><span class="hint">Gilt sofort für neue Bestellungen und PDFs.</span>
                            <button class="btn btnp" type="submit">Speichern</button></div>
                    </form>
                    <form method="POST" action="{{ route('lieferanten.loeschen', $l) }}" style="margin-top:12px;border-top:1px solid var(--bd);padding-top:10px"
                          onsubmit="return confirm('Lieferant {{ addslashes($l->name) }} löschen?')">
                        @csrf
                        <div class="jb"><span class="hint">Nur ohne Bestellungen, Artikel und Portal-Zugänge.</span>
                            <button class="btn btns" type="submit" style="color:var(--red)">Lieferant löschen</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
