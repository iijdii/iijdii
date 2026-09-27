{{-- Felder «Lieferant anlegen/bearbeiten». Erwartet: $l (?Lieferant), $fehler (MessageBag des Fensters). --}}
@php $alt = fn (string $feld) => $fehler->any() ? old($feld, $l?->{$feld}) : $l?->{$feld}; @endphp
@if ($fehler->any())
    <div class="kwarn">{{ $fehler->first() }}</div>
@endif
<div class="fgrid2">
    <div class="fld"><label>Name</label>
        <input class="inp" name="name" value="{{ $alt('name') }}" required maxlength="190"></div>
    <div class="fld"><label>Unsere Kundennummer</label>
        <input class="inp mono" name="kundennummer" value="{{ $alt('kundennummer') }}" maxlength="40" placeholder="z. B. D.60986"></div>
    <div class="fld" style="grid-column:1/-1"><label>Sortiment</label>
        <input class="inp" name="sortiment" value="{{ $alt('sortiment') }}" maxlength="190" placeholder="z. B. Glas & Schiebe-Systeme"></div>
    <div class="fld"><label>Ansprechpartner</label>
        <input class="inp" name="ansprechpartner" value="{{ $alt('ansprechpartner') }}" maxlength="190"></div>
    <div class="fld"><label>E-Mail (Bestellungen)</label>
        <input class="inp" type="email" name="email" value="{{ $alt('email') }}" maxlength="190"></div>
    <div class="fld"><label>Telefon</label>
        <input class="inp" name="telefon" value="{{ $alt('telefon') }}" maxlength="60"></div>
    <div class="fld"><label>Straße</label>
        <input class="inp" name="strasse" value="{{ $alt('strasse') }}" maxlength="190"></div>
    <div class="fld"><label>PLZ</label>
        <input class="inp mono" name="plz" value="{{ $alt('plz') }}" maxlength="10"></div>
    <div class="fld"><label>Ort</label>
        <input class="inp" name="stadt" value="{{ $alt('stadt') }}" maxlength="190"></div>
</div>
