@extends('layouts.app')

@section('title', 'Projekte')

@section('content')
<div class="colstack">

    <div class="anf-tools">
        <div class="chips">
            @foreach ($chips as $chip)
                <a class="chip {{ $chip['aktiv'] ? 'on' : '' }}"
                   href="{{ route('projekte', ['status' => $chip['key'], 'ansicht' => $ansicht]) }}">
                    {{ $chip['label'] }}<span class="ct">{{ $chip['anzahl'] }}</span>
                </a>
            @endforeach
        </div>
        <div class="ctas">
            <div class="seg">
                <a class="{{ $ansicht === 'karten' ? 'on' : '' }}"
                   href="{{ route('projekte', ['status' => $filter, 'ansicht' => 'karten']) }}">
                    <svg class="i"><use href="#ic-dashboard"/></svg>Karten</a>
                <a class="{{ $ansicht === 'tabelle' ? 'on' : '' }}"
                   href="{{ route('projekte', ['status' => $filter, 'ansicht' => 'tabelle']) }}">
                    <svg class="i"><use href="#ic-anfragen"/></svg>Tabelle</a>
            </div>
            <a class="btn btns btnp" href="{{ route('anfragen') }}">
                <svg class="i"><use href="#ic-plus"/></svg>Aus Anfrage erstellen</a>
        </div>
    </div>

    @if ($ansicht === 'karten')
        @include('projekte.partials.karten')
    @else
        @include('projekte.partials.tabelle')
    @endif

</div>
@endsection
