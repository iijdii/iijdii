{{-- Aufmaß-Bestätigung (M10): harte Voraussetzung, bevor Bestellungen
     aus den Projektpositionen erzeugt werden dürfen. --}}
<div class="card p0" style="margin-top:16px">
    <div class="card-h">
        <span class="card-t">Aufmaß &amp; Bestellung</span>
        @if ($projekt->aufmassBestaetigt())
            <span class="badge b-green">Aufmaß bestätigt</span>
        @else
            <span class="badge b-yellow">Aufmaß offen</span>
        @endif
    </div>
    <div class="card-b">
        @if ($projekt->aufmassBestaetigt())
            <div class="anf-specs">
                <span class="spec">Bestätigt von <b>{{ $projekt->aufmassBestaetiger?->name ?? '–' }}</b></span>
                <span class="spec">am <b class="mono">{{ $projekt->aufmass_bestaetigt_am->format('d.m.Y H:i') }}</b></span>
                <span class="spec">Vor-Ort-Termin <b>{{ $projekt->vor_ort_gewesen ? 'Ja — Verkäufer war am Objekt' : 'Nein' }}</b></span>
            </div>
            <div class="jb wrap" style="margin-top:12px">
                <form method="POST" action="{{ route('projekte.aufmass', $projekt) }}"
                      onsubmit="return confirm('Aufmaß-Bestätigung zurücksetzen? Neue Bestellungen sind dann gesperrt.')">
                    @csrf
                    <input type="hidden" name="aktion" value="zuruecksetzen">
                    <button class="btn btns" type="submit">Zurücksetzen</button>
                </form>
            </div>
        @else
            <p class="hint">Bestellungen aus diesem Projekt sind gesperrt, bis der Verkäufer die Maße
                am Objekt bestätigt hat.</p>
            <form method="POST" action="{{ route('projekte.aufmass', $projekt) }}" class="fx ac gap8 wrap" style="margin-top:10px">
                @csrf
                <input type="hidden" name="aktion" value="bestaetigen">
                <label class="fx ac gap8" style="cursor:pointer">
                    <input type="checkbox" name="vor_ort_gewesen" value="1" checked>
                    Verkäufer war vor Ort am Objekt
                </label>
                <button class="btn btns btnp" type="submit">
                    <svg class="i"><use href="#ic-check"/></svg>Aufmaß bestätigen</button>
            </form>
        @endif
    </div>
</div>
