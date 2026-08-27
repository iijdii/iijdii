@extends('layouts.app')

@section('title', $titel)

@section('content')
    <div class="card">
        <h2>{{ $titel }}</h2>
        <p class="hint">Modul „{{ $titel }}" — Umsetzung folgt in einem späteren Milestone.
            Layout, Navigation, Datenmodell und Seed-Daten stehen bereits.</p>
    </div>
@endsection
