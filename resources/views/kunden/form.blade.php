@extends('layouts.app')

@section('title', $kunde ? 'Kunde bearbeiten' : 'Neuer Kunde')

@php
    // Alte Eingabe > vorhandener Wert (Muster anfragen/form).
    $wert = fn (string $feld, $default = '') => old($feld, $kunde?->{$feld} ?? $default);
@endphp

@section('content')
<form class="colstack" method="POST"
      action="{{ $kunde ? route('kunden.update', $kunde) : route('kunden.store') }}" style="max-width:860px">
    @csrf
    @if ($kunde) @method('PUT') @endif

    <div class="jb">
        <a class="btn btns" href="{{ $kunde ? route('kunden.show', $kunde) : route('kunden') }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Abbrechen</a>
        <button class="btn btnp" type="submit">
            <svg class="i"><use href="#ic-check"/></svg>Speichern</button>
    </div>

    @if ($errors->any())
        <div class="kwarn">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-kunden"/></svg>Stammdaten</div>
        <div class="cols-2">
            <div class="fld"><label>Anzeigename *</label>
                <input class="inp" name="anzeigename" value="{{ $wert('anzeigename') }}" required></div>
            <div class="fld"><label>Typ</label>
                <select class="inp" name="typ">
                    <option value="privat" @selected($wert('typ', 'privat') === 'privat')>Privatkunde</option>
                    <option value="gewerbe" @selected($wert('typ') === 'gewerbe')>Gewerbe</option>
                </select></div>
            <div class="fld"><label>Vorname</label>
                <input class="inp" name="vorname" value="{{ $wert('vorname') }}"></div>
            <div class="fld"><label>Nachname</label>
                <input class="inp" name="nachname" value="{{ $wert('nachname') }}"></div>
            <div class="fld"><label>Firma</label>
                <input class="inp" name="firma" value="{{ $wert('firma') }}"></div>
            <div class="fld"><label>Ansprechpartner</label>
                <input class="inp" name="ansprechpartner" value="{{ $wert('ansprechpartner') }}"></div>
            <div class="fld"><label>Status</label>
                <select class="inp" name="status">
                    @foreach (['Lead', 'Aktiv', 'Inaktiv'] as $status)
                        <option value="{{ $status }}" @selected($wert('status', 'Lead') === $status)>{{ $status }}</option>
                    @endforeach
                </select></div>
            <div class="fld"><label>Quelle</label>
                <input class="inp" name="quelle" value="{{ $wert('quelle') }}" placeholder="Website, Empfehlung …"></div>
        </div>
    </div>

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-pin"/></svg>Kontakt &amp; Adresse</div>
        <div class="cols-2">
            <div class="fld"><label>Telefon</label>
                <input class="inp mono" name="telefon" value="{{ $wert('telefon') }}"></div>
            <div class="fld"><label>E-Mail</label>
                <input class="inp" type="email" name="email" value="{{ $wert('email') }}"></div>
            <div class="fld"><label>Straße</label>
                <input class="inp" name="strasse" value="{{ $wert('strasse') }}"></div>
            <div class="fld"><label>Hausnummer</label>
                <input class="inp" name="hausnummer" value="{{ $wert('hausnummer') }}"></div>
            <div class="fld"><label>PLZ</label>
                <input class="inp mono" name="plz" value="{{ $wert('plz') }}"></div>
            <div class="fld"><label>Stadt</label>
                <input class="inp" name="stadt" value="{{ $wert('stadt') }}"></div>
            <div class="fld"><label>Region</label>
                <input class="inp" name="region" value="{{ $wert('region') }}"></div>
        </div>
    </div>

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-tag"/></svg>Tags &amp; Notizen</div>
        <div class="fld"><label>Tags (durch Komma getrennt)</label>
            <input class="inp" name="tags"
                   value="{{ old('tags', $kunde ? implode(', ', $kunde->tags ?? []) : '') }}"
                   placeholder="Stammkunde, Terrasse, …"></div>
        <div class="fld"><label>Notizen</label>
            <textarea class="inp" name="notizen" rows="3">{{ $wert('notizen') }}</textarea></div>
    </div>
</form>
@endsection
