@extends('layouts.app')

@section('title', $projekt->nr)

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    {{-- Kopf: Titel, Kerndaten, Ablauf. Status per Auswahlliste statt
         Knopfreihe; hervorgehoben ist nur der Montage-Modus. --}}
    <div class="card prj-kopf">
        <div class="jb wrap" style="gap:10px">
            <span class="fx ac gap8 wrap">
                <a class="btn btns" href="{{ route('projekte') }}" title="Zurück zur Projektliste">
                    <svg class="i"><use href="#ic-aleft"/></svg>Projekte</a>
                <span class="anf-nr mono">{{ $projekt->nr }}</span>
                <span class="badge {{ $projekt->status->badgeClass() }}">{{ $projekt->status->label() }}</span>
                @if ($projekt->konfiguration === null && ! $vorschau)
                    <span class="badge b-yellow">Standardkonfiguration — noch nicht erfasst</span>
                @endif
            </span>
            <a class="btn btns btnp" href="{{ route('projekte.montage', $projekt) }}">
                <svg class="i"><use href="#ic-layers"/></svg>Montage-Modus</a>
        </div>
        <h2 class="serif prj-titel" style="font-size:23px;margin:12px 0">{{ $projekt->titel }}</h2>
        <div class="metarow prj-meta">
            <span><span class="meta-k">Kunde</span><span class="meta-v">{{ $projekt->kunde->anzeigename }} <span class="mono" style="color:var(--ink3);font-weight:500">{{ $projekt->kunde->kunden_nr }}</span></span></span>
            <span><span class="meta-k">Projektleiter</span><span class="meta-v">{{ $projekt->projektleiter?->name ?? '–' }}</span></span>
            <span><span class="meta-k">Montage</span><span class="meta-v mono">{{ $projekt->termin_von ? Format::datum($projekt->termin_von) : '–' }}</span></span>
            <span><span class="meta-k">Angebot</span><span class="meta-v mono">{{ $projekt->angebot?->nr ?? '—' }}</span></span>
            <span><span class="meta-k">Auftragswert</span><span class="meta-v mono">{{ $projekt->angebot?->summe !== null && $projekt->angebot ? Format::eur($projekt->angebot->summe) : 'in Konfiguration' }}</span></span>
        </div>
        <div class="stepper">
            @foreach ($projekt->stepper() as $schritt)
                <span class="step {{ $schritt['cls'] }}">
                    <span class="sdot">
                        @if ($schritt['cls'] === 'done')
                            <svg class="i" style="width:11px;height:11px"><use href="#ic-check"/></svg>
                        @endif
                    </span>
                    <span class="slbl">{{ $schritt['label'] }}</span>
                    <span class="sdate mono">{{ $schritt['datum'] }}</span>
                </span>
            @endforeach
        </div>

        <form method="POST" action="{{ route('projekte.status', $projekt) }}" class="fx ac gap8 prj-status">
            @csrf
            <label class="hint" for="prj-status">Status setzen:</label>
            <select class="inp" id="prj-status" name="status" onchange="this.form.submit()">
                @foreach (\App\Enums\ProjektStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($status === $projekt->status)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <noscript><button class="btn btns" type="submit">Setzen</button></noscript>
        </form>
    </div>

    @php
        $bereiche = [
            'uebersicht' => 'Übersicht', 'kunde' => 'Kunde & Termine',
            'material' => 'Material + Bestellungen', 'fotos' => 'Fotos',
            'dokumente' => 'Dokumente', 'zahlungen' => 'Zahlungen', 'aktivitaet' => 'Aktivität',
        ];
    @endphp
    {{-- Alle Bereiche auf einer Seite — die Tabs springen als Anker
         (Scrollspy in app.js hebt den aktiven Bereich hervor). --}}
    <div class="anf-tools prj-tabs" data-prj-tabs data-start="{{ $tab }}">
        <div class="seg prj-seg">
            @foreach ($bereiche as $key => $label)
                <a class="{{ $key === 'uebersicht' ? 'on' : '' }}" data-sek="sek-{{ $key }}"
                   href="#sek-{{ $key }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @foreach ($bereiche as $key => $label)
        <section class="prj-sek" id="sek-{{ $key }}">
            @if ($key !== 'uebersicht')
                <h2 class="sek-t">{{ $label }}</h2>
            @endif
            @include('projekte.tabs.'.$key)
        </section>
    @endforeach

    @include('partials.roof-lightbox')

</div>
@endsection
