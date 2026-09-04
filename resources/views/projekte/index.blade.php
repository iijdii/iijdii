@extends('layouts.app')

@section('title', 'Projekte')

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="anf-tools">
        <div class="chips">
            @foreach ($chips as $chip)
                <a class="chip {{ $chip['aktiv'] ? 'on' : '' }}"
                   href="{{ route('projekte', ['status' => $chip['key']]) }}">
                    {{ $chip['label'] }}<span class="ct">{{ $chip['anzahl'] }}</span>
                </a>
            @endforeach
        </div>
        <a class="btn btns btnp" href="{{ route('anfragen') }}">
            <svg class="i"><use href="#ic-plus"/></svg>Aus Anfrage erstellen</a>
    </div>

    <div class="anf-grid">
        @foreach ($projekte as $projekt)
            @php $k = $projekt->konfiguration ?? []; @endphp
            <a class="anf ac-blue" href="{{ route('projekte.show', $projekt) }}">
                <div class="anf-top">
                    <span class="anf-nr mono">{{ $projekt->nr }}</span>
                    <span class="badge {{ $projekt->status->badgeClass() }}">{{ $projekt->status->label() }}</span>
                </div>
                <div class="anf-b">
                    <span class="anf-title">{{ $projekt->titel }}</span>
                    <div class="anf-sub">{{ $projekt->kunde->anzeigename }} · {{ $projekt->kunde->kunden_nr }}</div>
                    <div class="anf-specs">
                        @if (($k['width'] ?? null) && ($k['depth'] ?? null))
                            <span class="spec">{{ number_format($k['width'], 0, ',', '.') }} × {{ number_format($k['depth'], 0, ',', '.') }} mm</span>
                        @endif
                        @if ($k['product'] ?? null)
                            <span class="spec">{{ $k['product'] }}</span>
                        @endif
                        @if ($k['color'] ?? null)
                            <span class="spec">{{ $k['color'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="anf-foot">
                    <span class="anf-meta">
                        <span>Auftragswert <b class="mono">{{ $projekt->angebot?->summe !== null && $projekt->angebot ? Format::eur($projekt->angebot->summe) : 'in Konfiguration' }}</b></span>
                        <span>Angebot <b class="mono">{{ $projekt->angebot?->nr ?? '—' }}</b></span>
                    </span>
                    <svg class="i"><use href="#ic-cright"/></svg>
                </div>
            </a>
        @endforeach
    </div>

</div>
@endsection
