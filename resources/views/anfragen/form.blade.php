@extends('layouts.app')

@section('title', $anfrage ? 'Anfrage bearbeiten' : 'Anfrage erfassen')

@php
    use App\Enums\AnfrageStatus;
    $wert = fn (string $feld, $default = null) => old($feld, $anfrage?->{$feld} ?? $default);
@endphp

@section('content')
<form class="colstack" method="POST"
      action="{{ $anfrage ? route('anfragen.update', $anfrage) : route('anfragen.store') }}"
      style="max-width:860px">
    @csrf
    @if ($anfrage)
        @method('PUT')
    @endif

    <div class="card">
        <div class="jb wrap">
            <a class="btn btns" href="{{ $anfrage ? route('anfragen.show', $anfrage) : route('anfragen') }}">
                <svg class="i"><use href="#ic-aleft"/></svg>Abbrechen</a>
            <button class="btn btns btnp" type="submit">
                <svg class="i"><use href="#ic-check"/></svg>{{ $anfrage ? 'Änderungen speichern' : 'Anfrage speichern' }}</button>
        </div>
        <h2 class="serif" style="margin:14px 0 4px;font-size:23px">{{ $anfrage ? 'Anfrage bearbeiten' : 'Anfrage erfassen' }}</h2>
        <p class="hint">{{ $anfrage ? $anfrage->nummer.' · Konstruktion aktualisieren' : 'Kunde & Termin, Konstruktion konfigurieren' }}</p>
        @if ($errors->any())
            <div class="kwarn" style="margin-top:10px">{{ $errors->first() }}</div>
        @endif
    </div>

    <div class="card cfg-form">
        <div class="cfg-block">
            <div class="fsec">Kunde &amp; Termin</div>
            <div class="fgrid2">
                <div class="fld"><label>Kunde auswählen</label>
                    {{-- Kein Round-Trip zurück ins Formular — dokumentierte Vereinfachung --}}
                    <a href="{{ route('kunden.create') }}" style="font-size:11.5px;color:var(--blued);float:right">+ Neuen Kunden anlegen</a>
                    <select class="inp" name="kunde_id" required>
                        <option value="">– Bitte Kunden wählen –</option>
                        @foreach ($kunden as $kunde)
                            <option value="{{ $kunde->id }}"
                                @selected((int) $wert('kunde_id') === $kunde->id || $vorausgewaehlt === $kunde->kunden_nr)>
                                {{ $kunde->kunden_nr }} · {{ $kunde->anzeigename }}</option>
                        @endforeach
                    </select></div>
                <div class="fld"><label>Status</label>
                    <select class="inp" name="status">
                        @foreach (AnfrageStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($wert('status')?->value ?? $wert('status') ?? 'neu') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select></div>
                <div class="fld"><label>Quelle</label><input class="inp" type="text" name="anfrage_quelle" value="{{ $wert('anfrage_quelle') }}"></div>
                <div class="fld"><label>Aufmaß-Datum</label><input class="inp" type="date" name="besuchstermin_datum" value="{{ $wert('besuchstermin_datum')?->format('Y-m-d') ?? $wert('besuchstermin_datum') }}"></div>
                <div class="fld"><label>Uhrzeit</label><input class="inp" type="time" name="besuchstermin_uhrzeit" value="{{ $wert('besuchstermin_uhrzeit') }}"></div>
            </div>
        </div>

        @if ($anfrage)
            <div class="cfg-block">
                <div class="fsec">Konfiguration</div>
                <p class="hint">Die Produkt-Positionen werden im
                    @if ($anfrage->projekt)
                        <a href="{{ route('projekte.show', [$anfrage->projekt, 'tab' => 'konfig']) }}">Projekt-Konfigurator ({{ $anfrage->projekt->nr }})</a>
                    @else
                        Projekt-Konfigurator
                    @endif
                    gepflegt.</p>
            </div>
        @else
            <div class="cfg-block">
                <div class="fsec">Position 1 — Konfigurator</div>
                @include('projekte.partials.produkt-form', ['prefix' => 'position', 'position' => null])
                <p class="hint" style="margin-top:8px">Beim Speichern werden automatisch Projekt und
                    Angebot angelegt; weitere Positionen fügen Sie danach im Projekt-Konfigurator hinzu.</p>
            </div>
        @endif

        <div class="cfg-block">
            <div class="fsec">Notiz</div>
            <div class="fld"><textarea class="inp" name="kommentar_intern" rows="3">{{ $wert('kommentar_intern') }}</textarea></div>
        </div>
    </div>

    @unless ($anfrage)
        <div class="kbox">
            <div class="fsec">Dach-Kalkulation</div>
            <div class="kgrid">
                <div class="kcell"><span>Pfosten</span><b class="mono" data-kalk-out="pn">–</b></div>
                <div class="kcell"><span>Sparren</span><b class="mono" data-kalk-out="rafters">–</b></div>
                <div class="kcell"><span>Glasfelder</span><b class="mono" data-kalk-out="fields">–</b></div>
                <div class="kcell"><span>Glasmaß</span><b class="mono" data-kalk-out="glas">–</b></div>
            </div>
        </div>
    @endunless
</form>
@endsection
