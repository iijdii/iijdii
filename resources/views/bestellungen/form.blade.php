@extends('layouts.app')

@section('title', $bestellung ? 'Bestellung bearbeiten' : 'Neue Bestellung')

@php $wert = fn (string $feld, $default = '') => old($feld, $bestellung?->{$feld} ?? $default); @endphp

@section('content')
<form class="colstack" method="POST" style="max-width:860px"
      action="{{ $bestellung ? route('bestellungen.update', $bestellung) : route('bestellungen.store') }}">
    @csrf
    @if ($bestellung) @method('PUT') @endif

    <div class="jb">
        <a class="btn btns" href="{{ $bestellung ? route('bestellungen.show', $bestellung) : route('bestellungen') }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Abbrechen</a>
        <button class="btn btnp" type="submit">
            <svg class="i"><use href="#ic-check"/></svg>{{ $bestellung ? 'Speichern' : 'Bestellung anlegen (Entwurf)' }}</button>
    </div>

    @if ($errors->any())
        <div class="kwarn">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-bestellungen"/></svg>Bestellkopf</div>
        <div class="cols-2">
            <div class="fld"><label>Titel *</label>
                <input class="inp" name="titel" value="{{ $wert('titel') }}" required
                       placeholder="z. B. Dachverglasung — Terrasse"></div>
            <div class="fld"><label>Lieferant *</label>
                <select class="inp" name="lieferant_id" required>
                    @foreach ($lieferanten as $lieferant)
                        <option value="{{ $lieferant->id }}"
                                @selected((int) old('lieferant_id', $bestellung?->lieferant_id) === $lieferant->id)>{{ $lieferant->name }}</option>
                    @endforeach
                </select></div>
            <div class="fld"><label>Kategorie</label>
                <select class="inp" name="kategorie">
                    @foreach (['glas' => 'Glas', 'aluminium' => 'Aluminium / Zubehör', 'gemischt' => 'Gemischt'] as $schluessel => $label)
                        <option value="{{ $schluessel }}" @selected($wert('kategorie', 'gemischt') === $schluessel)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <div class="fld"><label>Projekt (optional)</label>
                <select class="inp" name="projekt_id">
                    <option value="">— kein Projekt —</option>
                    @foreach ($projekte as $projekt)
                        <option value="{{ $projekt->id }}"
                                @selected((int) old('projekt_id', $bestellung?->projekt_id) === $projekt->id)>{{ $projekt->nr }} · {{ $projekt->kunde->anzeigename }}</option>
                    @endforeach
                </select></div>
            <div class="fld"><label>Liefertermin</label>
                <input class="inp mono" type="date" name="liefertermin"
                       value="{{ old('liefertermin', $bestellung?->liefertermin?->toDateString()) }}"></div>
        </div>
        <div class="fld" style="margin-top:8px"><label>Hinweise</label>
            <textarea class="inp" name="notizen" rows="3"
                      placeholder="Lieferhinweise, Beschichtung, Verpackung …">{{ $wert('notizen') }}</textarea></div>
        @unless ($bestellung)
            <p class="hint" style="margin-top:8px">Positionen (Glas / Schiebe / Material) werden nach dem Anlegen
                auf der Detailseite erfasst — solange die Bestellung im Entwurf/Geprüft ist.</p>
        @endunless
    </div>
</form>
@endsection
