@extends('layouts.app')

@section('title', 'Bestellungen')

@section('content')
<div class="colstack">

    <div class="anf-tools">
        <div class="chips">
            @foreach ($chips as $chip)
                <a class="chip {{ $chip['aktiv'] ? 'on' : '' }}"
                   href="{{ route('bestellungen', ['status' => $chip['key'], 'ansicht' => $ansicht]) }}">
                    {{ $chip['label'] }}<span class="ct">{{ $chip['anzahl'] }}</span>
                </a>
            @endforeach
        </div>
        <span class="fx ac gap8">
        <a class="btn btns btnp" href="{{ route('bestellungen.create') }}">
            <svg class="i"><use href="#ic-plus"/></svg>Neue Bestellung</a>
        <div class="seg">
            <a class="{{ $ansicht === 'karten' ? 'on' : '' }}"
               href="{{ route('bestellungen', ['status' => $filter, 'ansicht' => 'karten']) }}">
                <svg class="i"><use href="#ic-dashboard"/></svg>Karten</a>
            <a class="{{ $ansicht === 'tabelle' ? 'on' : '' }}"
               href="{{ route('bestellungen', ['status' => $filter, 'ansicht' => 'tabelle']) }}">
                <svg class="i"><use href="#ic-anfragen"/></svg>Tabelle</a>
        </div>
        </span>
    </div>

    @if ($ansicht === 'karten')
        @include('bestellungen.partials.karten')
    @else
        @include('bestellungen.partials.tabelle')
    @endif

</div>
@endsection
