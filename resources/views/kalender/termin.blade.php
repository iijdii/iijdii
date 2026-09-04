@extends('layouts.app')

@section('title', 'Neuer Termin')

@section('content')
<form class="card" method="POST" action="{{ route('kalender.termin.speichern') }}" style="max-width:560px">
    @csrf
    <div class="mc-h"><svg class="i"><use href="#ic-kalender"/></svg>Neuer Montage-Termin</div>
    <p class="note">Setzt die Montage-Spanne des Projekts — sie erscheint im Kalender,
        auf dem Dashboard und im Montage-Modus.</p>

    @if ($errors->any())
        <div class="kwarn" style="margin:10px 0">{{ $errors->first() }}</div>
    @endif

    <div class="fld" style="margin-top:10px"><label>Projekt *</label>
        <select class="inp" name="projekt_id" required>
            <option value="">— Projekt wählen —</option>
            @foreach ($projekte as $projekt)
                <option value="{{ $projekt->id }}" @selected((int) old('projekt_id') === $projekt->id)>
                    {{ $projekt->nr }} · {{ $projekt->kunde->anzeigename }}</option>
            @endforeach
        </select></div>
    <div class="cols-2">
        <div class="fld"><label>Von *</label>
            <input class="inp mono" type="date" name="termin_von" value="{{ old('termin_von', $datum) }}" required></div>
        <div class="fld"><label>Bis</label>
            <input class="inp mono" type="date" name="termin_bis" value="{{ old('termin_bis') }}"></div>
    </div>

    <div class="jb" style="margin-top:12px">
        <a class="btn btns" href="{{ route('kalender') }}">Abbrechen</a>
        <button class="btn btnp" type="submit"><svg class="i"><use href="#ic-check"/></svg>Termin eintragen</button>
    </div>
</form>
@endsection
