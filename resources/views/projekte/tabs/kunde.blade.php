{{-- Kunde & Termine: Stammdaten des Projekts an einem Ort — Kundenkarte,
     Herkunft (Anfrage), Montage-Termine und Verantwortung. --}}
<div class="cols-2">
    <div class="colstack">
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-kunden"/></svg>Kunde</div>
            <div class="pinfo">
                <span class="pk"><span>Name</span><b>{{ $projekt->kunde->anzeigename }}</b></span>
                <span class="pk"><span>Typ</span><b>{{ $projekt->kunde->typ === 'gewerbe' ? 'Gewerbe' : 'Privatkunde' }} · {{ $projekt->kunde->kunden_nr }}</b></span>
                <span class="pk"><span>Objekt-Adresse</span><b>{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) ?: '–' }}<br>{{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</b></span>
                @if ($projekt->kunde->telefon)
                    <span class="pk"><span>Telefon</span><b class="mono">{{ $projekt->kunde->telefon }}</b></span>
                @endif
                @if ($projekt->kunde->email)
                    <span class="pk"><span>E-Mail</span><b>{{ $projekt->kunde->email }}</b></span>
                @endif
            </div>
            <a class="btn btns" style="margin-top:12px" href="{{ route('kunden.show', $projekt->kunde) }}">
                <svg class="i"><use href="#ic-kunden"/></svg>Kundenakte öffnen</a>
        </div>

        @if ($projekt->anfrage)
            <div class="card">
                <div class="mc-h"><svg class="i"><use href="#ic-anfragen"/></svg>Herkunft</div>
                <p class="hint">Dieses Projekt entstand aus der Anfrage
                    <a href="{{ route('anfragen.show', $projekt->anfrage) }}" class="mono">{{ $projekt->anfrage->nummer }}</a>.</p>
            </div>
        @endif

        @if ($projekt->kunde->notizen)
            <div class="card">
                <div class="mc-h"><svg class="i"><use href="#ic-anfragen"/></svg>Kunden-Notizen</div>
                <p class="note">{{ $projekt->kunde->notizen }}</p>
            </div>
        @endif
    </div>

    <div class="colstack">
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-kalender"/></svg>Termine &amp; Verantwortung</div>
            <form method="POST" action="{{ route('projekte.stammdaten', $projekt) }}" class="colstack" style="gap:8px">
                @csrf
                <div class="fld"><label>Montage von</label>
                    <input class="inp mono" type="date" name="termin_von"
                           value="{{ old('termin_von', $projekt->termin_von?->toDateString()) }}"></div>
                <div class="fld"><label>Montage bis</label>
                    <input class="inp mono" type="date" name="termin_bis"
                           value="{{ old('termin_bis', $projekt->termin_bis?->toDateString()) }}"></div>
                <div class="fld"><label>Projektleitung</label>
                    <select class="inp" name="projektleiter_id">
                        <option value="">—</option>
                        @foreach (\App\Models\User::query()->orderBy('name')->get() as $benutzer)
                            <option value="{{ $benutzer->id }}"
                                    @selected((int) old('projektleiter_id', $projekt->projektleiter_id) === $benutzer->id)>{{ $benutzer->name }}</option>
                        @endforeach
                    </select></div>
                @error('termin_bis')<p class="hint" style="color:var(--red)">{{ $message }}</p>@enderror
                <button class="btn btns" type="submit" style="align-self:flex-end">Speichern</button>
            </form>
        </div>
    </div>
</div>
