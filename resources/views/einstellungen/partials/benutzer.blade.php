{{-- Benutzer & Passwörter (nur Admin). Erwartet: $benutzer, $lieferanten. --}}
<div class="card p0" id="benutzer">
    <div class="card-h">
        <span class="card-t"><svg class="i"><use href="#ic-user"/></svg> Benutzer &amp; Passwörter</span>
        <span class="pill">{{ $benutzer->count() }}</span>
        <button class="btn btnp" type="button" data-modal-target="benutzer-neu" style="margin-left:auto">
            <svg class="i"><use href="#ic-plus"/></svg>Neuer Benutzer</button>
    </div>
    <div class="card-b" style="padding:6px 17px;overflow-x:auto">
        <table class="tbl">
            <thead><tr><th>Name</th><th>E-Mail (Login)</th><th>Rolle</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($benutzer as $u)
                @php $ich = $u->is(auth()->user()); @endphp
                <tr>
                    <td class="b">{{ $u->name }}@if ($ich) <span class="hint">(Sie)</span>@endif</td>
                    <td class="mono" style="font-size:12px">{{ $u->email }}</td>
                    <td>{{ $u->role->label() }}@if ($u->lieferant) <div class="hint">{{ $u->lieferant->name }}</div>@endif</td>
                    <td><span class="badge {{ $u->istGesperrt() ? 'b-red' : 'b-green' }}">{{ $u->istGesperrt() ? 'gesperrt' : 'aktiv' }}</span></td>
                    <td class="num">
                        <span class="fx ac gap8" style="justify-content:flex-end">
                            <button class="btn btns" type="button" data-modal-target="benutzer-{{ $u->id }}">Bearbeiten / Passwort</button>
                            @unless ($ich)
                                <form method="POST" action="{{ route('benutzer.sperren', $u) }}">
                                    @csrf
                                    <button class="btn btns" type="submit">{{ $u->istGesperrt() ? 'Entsperren' : 'Sperren' }}</button>
                                </form>
                                <form method="POST" action="{{ route('benutzer.loeschen', $u) }}"
                                      onsubmit="return confirm('Benutzer {{ addslashes($u->name) }} endgültig löschen?')">
                                    @csrf
                                    <button class="btn btns" type="submit" style="color:var(--red)">Löschen</button>
                                </form>
                            @endunless
                        </span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="hint" style="margin:8px 0">Gesperrte Benutzer können sich nicht mehr anmelden, ihre Einträge bleiben erhalten.
            Löschen geht nur bei Benutzern ohne Einträge.</p>
    </div>
</div>

@php $fehlerNeu = $errors->getBag('benutzer_neu'); @endphp
<div class="modal" id="benutzer-neu" @unless ($fehlerNeu->any()) hidden @endunless>
    <div class="modalc">
        <div class="modalh">Neuer Benutzer
            <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
        <div class="modalb">
            <form method="POST" action="{{ route('benutzer.store') }}" class="colstack" style="gap:10px">
                @csrf
                @include('einstellungen.partials.benutzer-felder', ['u' => null, 'fehler' => $fehlerNeu])
                <div class="jb">
                    <span class="hint">Login mit E-Mail und Passwort.</span>
                    <button class="btn btnp" type="submit">Anlegen</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach ($benutzer as $u)
    @php $fehlerU = $errors->getBag('benutzer_'.$u->id); @endphp
    <div class="modal" id="benutzer-{{ $u->id }}" @unless ($fehlerU->any()) hidden @endunless>
        <div class="modalc">
            <div class="modalh">{{ $u->name }} bearbeiten
                <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
            <div class="modalb">
                <form method="POST" action="{{ route('benutzer.update', $u) }}" class="colstack" style="gap:10px">
                    @csrf
                    @include('einstellungen.partials.benutzer-felder', ['u' => $u, 'fehler' => $fehlerU])
                    <div class="jb">
                        <span class="hint">Passwort leer lassen, um es nicht zu ändern.</span>
                        <button class="btn btnp" type="submit">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
