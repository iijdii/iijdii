{{-- Felder «Benutzer anlegen/bearbeiten». Erwartet: $u (?User), $fehler (MessageBag des Fensters), $lieferanten. --}}
@php
    $alt = fn (string $feld, $standard = null) => $fehler->any() ? old($feld, $standard) : $standard;
    $rolle = $alt('role', $u?->role?->value ?? \App\Enums\Rolle::Verkaeufer->value);
@endphp
@if ($fehler->any())
    <div class="kwarn">{{ $fehler->first() }}</div>
@endif
<div class="fgrid2">
    <div class="fld"><label>Name</label>
        <input class="inp" name="name" value="{{ $alt('name', $u?->name) }}" required maxlength="120"></div>
    <div class="fld"><label>E-Mail (Login)</label>
        <input class="inp" type="email" name="email" value="{{ $alt('email', $u?->email) }}" required autocomplete="off"></div>
    <div class="fld"><label>Rolle</label>
        <select class="inp" name="role" required>
            @foreach (\App\Enums\Rolle::cases() as $r)
                <option value="{{ $r->value }}" @selected($rolle === $r->value)>{{ $r->label() }}</option>
            @endforeach
        </select></div>
    <div class="fld"><label>Lieferant (nur Rolle «Lieferant»)</label>
        <select class="inp" name="lieferant_id">
            <option value="">–</option>
            @foreach ($lieferanten as $id => $name)
                <option value="{{ $id }}" @selected((string) $alt('lieferant_id', $u?->lieferant_id) === (string) $id)>{{ $name }}</option>
            @endforeach
        </select></div>
    <div class="fld"><label>{{ $u ? 'Neues Passwort' : 'Passwort' }}</label>
        <input class="inp" type="password" name="password" minlength="8" autocomplete="new-password"
               @if (! $u) required @endif placeholder="{{ $u ? 'leer lassen = unverändert' : 'mind. 8 Zeichen' }}"></div>
    <div class="fld"><label>Passwort wiederholen</label>
        <input class="inp" type="password" name="password_confirmation" minlength="8" autocomplete="new-password"
               @if (! $u) required @endif></div>
</div>
