@extends('layouts.app')

@section('title', $projekt->nr)

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="card">
        <div class="jb wrap">
            <a class="btn btns" href="{{ route('projekte') }}">
                <svg class="i"><use href="#ic-aleft"/></svg>Zurück zur Projektliste</a>
            <span class="ctas">
                <a class="btn btns" href="{{ route('projekte.pdf', $projekt) }}">
                    <svg class="i"><use href="#ic-download"/></svg>PDF exportieren</a>
                <a class="btn btns" href="{{ route('projekte.show', [$projekt, 'tab' => 'konfig']) }}">
                    <svg class="i"><use href="#ic-edit"/></svg>Bearbeiten</a>
                <a class="btn btns btnp" href="{{ route('projekte.montage', $projekt) }}">
                    <svg class="i"><use href="#ic-layers"/></svg>Montage-Modus</a>
            </span>
        </div>
        <div class="fx" style="margin-top:14px">
            <span class="anf-nr mono">{{ $projekt->nr }}</span>
            <span class="badge {{ $projekt->status->badgeClass() }}">{{ $projekt->status->label() }}</span>
        </div>
        <h2 class="serif" style="margin:8px 0 14px;font-size:23px">{{ $projekt->titel }}</h2>
        <div class="metarow">
            <span><span class="meta-k">Projektleiter</span><span class="meta-v">{{ $projekt->projektleiter?->name ?? '–' }}</span></span>
            <span><span class="meta-k">Erstellt am</span><span class="meta-v mono">{{ Format::datum($projekt->created_at) }}</span></span>
            <span><span class="meta-k">Kundennummer</span><span class="meta-v mono">{{ $projekt->kunde->kunden_nr }}</span></span>
            <span><span class="meta-k">Angebotsnummer</span><span class="meta-v mono">{{ $projekt->angebot?->nr ?? '—' }}</span></span>
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
    </div>

    <div class="anf-tools">
        <div class="seg" style="flex-wrap:wrap">
            @foreach ([
                'uebersicht' => 'Übersicht', 'konfig' => 'Konfigurator', 'technik' => 'Technik & Statik',
                'material' => 'Material', 'dokumente' => 'Dokumente', 'zahlungen' => 'Zahlungen',
                'aktivitaet' => 'Aktivität',
            ] as $key => $label)
                <a class="{{ $tab === $key ? 'on' : '' }}" href="{{ route('projekte.show', [$projekt, 'tab' => $key]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @include('projekte.tabs.'.$tab)

    @include('partials.roof-lightbox')

</div>
@endsection
