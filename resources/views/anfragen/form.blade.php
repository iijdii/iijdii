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
                <div class="fld"><label>Produkt</label><input class="inp" type="text" name="produkt_notiz" value="{{ $wert('produkt_notiz') }}" placeholder="z. B. Terrassenüberdachung + LED"></div>
            </div>
        </div>

        <div class="cfg-block">
            <div class="fsec">Position 1 — Basis</div>
            <div class="fgrid2">
                <div class="fld"><label>Montageart</label>
                    <select class="inp" name="befestigung_art">
                        <option value="">–</option>
                        <option value="wandmontage" @selected($wert('befestigung_art') === 'wandmontage')>Wandmontage</option>
                        <option value="freistehend" @selected($wert('befestigung_art') === 'freistehend')>Freistehend</option>
                        <option value="kombination" @selected($wert('befestigung_art') === 'kombination')>Kombination</option>
                    </select></div>
                <div class="fld"><label>Farbe</label><input class="inp" type="text" name="profil_farbe_name" value="{{ $wert('profil_farbe_name') }}" placeholder="Anthrazit · RAL 7016"></div>
            </div>
        </div>

        <div class="cfg-block">
            <div class="fsec">Form der Konstruktion</div>
            <div class="fgrid2">
                <div class="fld"><label>Form</label>
                    <select class="inp" name="form">
                        <option value="">–</option>
                        @foreach (['rechteckig' => 'Rechteck', 'trapezfoermig' => 'Trapez', 'l-form' => 'L-Form', 'individuell' => 'Individuell'] as $key => $label)
                            <option value="{{ $key }}" @selected($wert('form') === $key)>{{ $label }}</option>
                        @endforeach
                    </select></div>
            </div>
        </div>

        <div class="cfg-block">
            <div class="fsec">Grundmaße &amp; Höhen</div>
            <div class="fgrid2">
                <div class="fld"><label>Breite (cm)</label><input class="inp" type="number" name="breite_cm" value="{{ $wert('breite_cm') }}" data-kalk="widthCm"></div>
                <div class="fld"><label>Tiefe / Ausladung (cm)</label><input class="inp" type="number" name="tiefe_cm" value="{{ $wert('tiefe_cm') }}"></div>
                <div class="fld"><label>Höhe (cm)</label><input class="inp" type="number" name="hoehe_cm" value="{{ $wert('hoehe_cm') }}"></div>
                <div class="fld"><label>Dachneigung (°)</label><input class="inp" type="number" name="dachneigung_grad" value="{{ $wert('dachneigung_grad') }}"></div>
            </div>
        </div>

        <div class="cfg-block">
            <div class="fsec">Dachdeckung</div>
            <div class="fgrid2">
                <div class="fld"><label>Material</label><input class="inp" type="text" name="dach_material" value="{{ $wert('dach_material') }}" placeholder="VSG-Glas"></div>
                <div class="fld"><label>Verglasung</label><input class="inp" type="text" name="verglasung_typ" value="{{ $wert('verglasung_typ') }}" placeholder="vsg_8mm"></div>
            </div>
        </div>

        <div class="cfg-block">
            <div class="fsec">Pfosten</div>
            <div class="fgrid2">
                <div class="fld"><label>Empfohlene Anzahl</label><input class="inp" type="text" readonly value="–" data-kalk-cm-out="rec"></div>
                <div class="fld"><label>Anzahl überschreiben</label><input class="inp" type="number" name="anzahl_stuetzen" value="{{ $wert('anzahl_stuetzen') }}" placeholder="auto"></div>
            </div>
        </div>

        <div class="cfg-block">
            <div class="fsec">Notiz</div>
            <div class="fld"><textarea class="inp" name="kommentar_intern" rows="3">{{ $wert('kommentar_intern') }}</textarea></div>
        </div>
    </div>

    <div class="kbox">
        <div class="fsec">Dach-Kalkulation</div>
        <div class="kgrid">
            <div class="kcell"><span>Pfosten (Empf.)</span><b class="mono" data-kalk-cm-out="rec2">–</b></div>
            <div class="kcell"><span>Sparren</span><b class="mono" data-kalk-cm-out="rafters">–</b></div>
            <div class="kcell"><span>Felder</span><b class="mono" data-kalk-cm-out="fields">–</b></div>
            <div class="kcell"><span>Sparrenabstand</span><b class="mono" data-kalk-cm-out="spar">–</b></div>
        </div>
    </div>
</form>

<script>
// Live-Kalkulation der Anfrage-Form (cm-Eingabe → mm-Formeln des Rechenkerns).
(function () {
    const input = document.querySelector('[data-kalk="widthCm"]');
    if (!input) return;
    const update = () => {
        const w = (parseInt(input.value, 10) || 0) * 10;
        const rec = w > 0 ? Math.ceil(w / 4000) + 1 : 0;
        const rafters = w > 0 ? Math.round(w / 1080) + 1 : 0;
        const fields = Math.max(0, rafters - 1);
        const spar = fields > 0 ? Math.round(w / fields) : 0;
        const out = {
            rec: rec || '–', rec2: rec || '–', rafters: rafters || '–', fields: fields || '–',
            spar: spar ? spar.toLocaleString('de-DE') + ' mm' : '–',
        };
        document.querySelectorAll('[data-kalk-cm-out]').forEach((el) => {
            el.textContent = out[el.dataset.kalkCmOut] ?? '–';
        });
    };
    input.addEventListener('input', update);
    update();
})();
</script>
@endsection
