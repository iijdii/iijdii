@extends('layouts.app')

@section('title', 'Lager')

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="kpirow">
        <div class="kpi2 kp-dark">
            <span class="kl">Artikel im Lager</span>
            <span class="kn">{{ $artikel->count() }}</span>
            <span class="ks">Lagerorte H1–H3 · V</span>
        </div>
        <div class="kpi2 kp-blue">
            <span class="kl">Lagerwert (EK)</span>
            <span class="kn">{{ Format::eur0($lagerwert) }}</span>
            <span class="ks">inkl. reservierter Ware</span>
        </div>
        <div class="kpi2 kp-gold">
            <span class="kl">Unter Mindestbestand</span>
            <span class="kn">{{ $low->count() }}</span>
            <span class="ks">{{ $low->filter(fn ($a) => $a->bestandsstatus() === 'leer')->count() }} davon nicht verfügbar</span>
        </div>
        <div class="kpi2 kp-green">
            <span class="kl">Wareneingang offen</span>
            <span class="kn">{{ $kpiOffen['anzahl'] }}</span>
            <span class="ks">{{ $kpiOffen['artikel'] }} Artikel erwartet</span>
        </div>
    </div>

    <div class="anf-tools">
        <div class="seg">
            <a class="{{ $tab === 'bestand' ? 'on' : '' }}" href="{{ route('lager', ['tab' => 'bestand']) }}">
                <svg class="i"><use href="#ic-lager"/></svg>Bestand</a>
            <a class="{{ $tab === 'wareneingang' ? 'on' : '' }}" href="{{ route('lager', ['tab' => 'wareneingang']) }}">
                <svg class="i"><use href="#ic-truck"/></svg>Wareneingang</a>
            <a class="{{ $tab === 'reservierungen' ? 'on' : '' }}" href="{{ route('lager', ['tab' => 'reservierungen']) }}">
                <svg class="i"><use href="#ic-tag"/></svg>Reservierungen</a>
            <a class="{{ $tab === 'bewegungen' ? 'on' : '' }}" href="{{ route('lager', ['tab' => 'bewegungen']) }}">
                <svg class="i"><use href="#ic-clock"/></svg>Bewegungen</a>
        </div>
        <div class="ctas">
            <span class="pill">Lagerwert {{ Format::eur0($lagerwert) }}</span>
            <button class="btn" type="button" data-toast="Inventurliste als XLSX exportiert">
                <svg class="i"><use href="#ic-download"/></svg>Inventurliste</button>
        </div>
    </div>

    @include('lager.partials.'.$tab)

</div>
@endsection
