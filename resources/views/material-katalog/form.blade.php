@extends('layouts.app')

@section('title', $artikel ? 'Artikel bearbeiten' : 'Neuer Artikel')

@php $wert = fn (string $feld, $default = '') => old($feld, $artikel?->{$feld} ?? $default); @endphp

@section('content')
<div class="colstack" style="max-width:860px">

    <form class="colstack" method="POST"
          action="{{ $artikel ? route('material-katalog.update', $artikel) : route('material-katalog.store') }}">
        @csrf
        @if ($artikel) @method('PUT') @endif

        <div class="jb">
            <a class="btn btns" href="{{ route('material-katalog') }}">
                <svg class="i"><use href="#ic-aleft"/></svg>Abbrechen</a>
            <button class="btn btnp" type="submit">
                <svg class="i"><use href="#ic-check"/></svg>Speichern</button>
        </div>

        @if ($errors->any())
            <div class="kwarn">{{ $errors->first() }}</div>
        @endif

        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-material"/></svg>Artikel</div>
            <div class="cols-2">
                <div class="fld"><label>Art.-Nr. *</label>
                    <input class="inp mono" name="art_nr" value="{{ $wert('art_nr') }}" required></div>
                <div class="fld"><label>Name *</label>
                    <input class="inp" name="name" value="{{ $wert('name') }}" required></div>
                <div class="fld"><label>Kategorie</label>
                    <select class="inp" name="kategorie">
                        @foreach ($kategorien as $kategorie)
                            <option value="{{ $kategorie->value }}"
                                    @selected(old('kategorie', $artikel?->kategorie->value) === $kategorie->value)>{{ $kategorie->label() }}</option>
                        @endforeach
                    </select></div>
                <div class="fld"><label>Einheit</label>
                    <select class="inp" name="einheit">
                        @foreach ($einheiten as $einheit)
                            <option value="{{ $einheit->value }}"
                                    @selected(old('einheit', $artikel?->einheit->value) === $einheit->value)>{{ $einheit->value }}</option>
                        @endforeach
                    </select></div>
                <div class="fld"><label>Lieferant</label>
                    <select class="inp" name="lieferant_id">
                        @foreach ($lieferanten as $lieferant)
                            <option value="{{ $lieferant->id }}"
                                    @selected((int) old('lieferant_id', $artikel?->lieferant_id) === $lieferant->id)>{{ $lieferant->name }}</option>
                        @endforeach
                    </select></div>
                <div class="fld"><label>Lagerort</label>
                    <input class="inp mono" name="lagerort" value="{{ $wert('lagerort') }}" placeholder="A-01-03"></div>
                <div class="fld"><label>EK-Preis (€)</label>
                    <input class="inp mono" type="number" step="0.01" min="0" name="ek_preis" value="{{ $wert('ek_preis', 0) }}"></div>
                <div class="fld"><label>Mindestbestand</label>
                    <input class="inp mono" type="number" min="0" name="min_bestand" value="{{ $wert('min_bestand', 0) }}"></div>
                @if ($artikel)
                    <div class="fld"><label>Bestand</label>
                        <input class="inp mono" value="{{ $artikel->bestand }}" disabled>
                        <span class="hint">Bestand nur per Korrekturbuchung ändern (Lager → Artikel).</span></div>
                @else
                    <div class="fld"><label>Anfangsbestand</label>
                        <input class="inp mono" type="number" min="0" name="bestand" value="{{ old('bestand', 0) }}"></div>
                @endif
            </div>
        </div>
    </form>

    @if ($artikel)
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Aliase (automatische Wareneingangs-Zuordnung)</span>
                <span class="pill">{{ $artikel->aliase->count() }}</span>
            </div>
            <div class="card-b">
                <p class="hint" style="margin-bottom:10px">Freitext-Bezeichnungen aus Bestellungen werden über
                    diese Aliase dem Artikel zugeordnet (Groß-/Kleinschreibung und Leerzeichen egal, × = x).</p>
                @foreach ($artikel->aliase as $alias)
                    <div class="fx ac gap8" style="padding:6px 0;border-top:1px solid var(--bd2)">
                        <span style="flex:1">{{ $alias->alias }}
                            <span class="mono" style="font-size:11px;color:var(--ink3)">→ {{ $alias->alias_normalized }}</span></span>
                        <form method="POST" action="{{ route('material-katalog.aliase.loeschen', [$artikel, $alias]) }}">
                            @csrf
                            <button class="btn btns" type="submit" style="color:var(--red)">Entfernen</button>
                        </form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('material-katalog.aliase.store', $artikel) }}"
                      class="fx ac gap8" style="margin-top:10px">
                    @csrf
                    <input class="inp" name="alias" placeholder="z. B. VSG 8mm klar" style="flex:1" required>
                    <button class="btn btns" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Alias hinzufügen</button>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection
