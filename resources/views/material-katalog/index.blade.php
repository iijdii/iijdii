@extends('layouts.app')

@section('title', 'Material-Katalog')

@php use App\Enums\ArtikelKategorie; use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="kpirow">
        <div class="kpi2 kp-dark">
            <span class="kl">Artikel im Katalog</span>
            <span class="kn">{{ $gesamt }}</span>
            <span class="ks">{{ count(ArtikelKategorie::cases()) }} Kategorien</span>
        </div>
        <div class="kpi2 kp-blue">
            <span class="kl">Lieferanten</span>
            <span class="kn">{{ $kpi['lieferanten']->count() }}</span>
            <span class="ks">{{ $kpi['lieferanten']->join(' · ') }}</span>
        </div>
        <div class="kpi2 kp-gold">
            <span class="kl">Ø EK-Preis</span>
            <span class="kn">{{ Format::eur0($kpi['oEk']) }}</span>
            <span class="ks">ohne Glas-Sonderformate</span>
        </div>
        <div class="kpi2 kp-green">
            <span class="kl">Mit Bild</span>
            <span class="kn">{{ $kpi['mitBild'] }}</span>
            <span class="ks">Bauteil-Zeichnungen hinterlegt</span>
        </div>
    </div>

    <div class="anf-tools">
        <form class="search" method="GET" action="{{ route('material-katalog') }}">
            <input type="hidden" name="kategorie" value="{{ $kategorie }}">
            <input type="hidden" name="ansicht" value="{{ $ansicht }}">
            <svg class="i si"><use href="#ic-search"/></svg>
            <input class="searchin" type="search" name="q" value="{{ $suche }}"
                   placeholder="Artikel, Art.-Nr. oder Lieferant suchen">
        </form>
        <div class="ctas">
            <span class="pill">{{ $gefiltert->count() }} von {{ $gesamt }}</span>
            <div class="seg">
                <a class="{{ $ansicht === 'karten' ? 'on' : '' }}"
                   href="{{ route('material-katalog', ['q' => $suche, 'kategorie' => $kategorie, 'ansicht' => 'karten']) }}">
                    <svg class="i"><use href="#ic-material"/></svg>Karten</a>
                <a class="{{ $ansicht === 'liste' ? 'on' : '' }}"
                   href="{{ route('material-katalog', ['q' => $suche, 'kategorie' => $kategorie, 'ansicht' => 'liste']) }}">
                    <svg class="i"><use href="#ic-doc"/></svg>Liste</a>
            </div>
            <button class="btn btns" type="button" data-toast="Katalog als XLSX exportiert · {{ $gefiltert->count() }} Artikel">
                <svg class="i"><use href="#ic-download"/></svg>Export</button>
            <button class="btn btns btnp" type="button" data-toast="Neuer Artikel – Formular geöffnet">
                <svg class="i"><use href="#ic-plus"/></svg>Artikel anlegen</button>
        </div>
    </div>

    <div class="chips">
        @foreach ($chips as $chip)
            <a class="chip {{ $chip['aktiv'] ? 'on' : '' }}"
               href="{{ route('material-katalog', ['q' => $suche, 'kategorie' => $chip['key'], 'ansicht' => $ansicht]) }}">
                {{ $chip['label'] }}<span class="ct">{{ $chip['anzahl'] }}</span>
            </a>
        @endforeach
    </div>

    @if ($gefiltert->isEmpty())
        <div class="empty">
            <svg class="i ei"><use href="#ic-search"/></svg>
            <h3>Keine Treffer</h3>
            <p>Für „{{ $suche }}" wurde kein Artikel gefunden. Suchbegriff anpassen oder Kategorie zurücksetzen.</p>
        </div>
    @elseif ($ansicht === 'karten')
        @include('material-katalog.partials.karten')
    @else
        @include('material-katalog.partials.liste')
    @endif

</div>
@endsection
