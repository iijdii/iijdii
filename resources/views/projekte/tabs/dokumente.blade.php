@php use App\Support\Format; @endphp

<div class="cols-2">
    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Dokumente</span>
            <span class="pill">{{ $projekt->dokumente->count() }} Dateien</span>
        </div>
        <div class="card-b">
            @if ($projekt->dokumente->isEmpty())
                <p class="hint">Keine Dokumente vorhanden.</p>
            @endif
            @foreach ($projekt->dokumente as $dokument)
                @php $ext = mb_strtolower(pathinfo($dokument->dateiname, PATHINFO_EXTENSION)); @endphp
                <div class="doc">
                    <span class="fi {{ $ext === 'pdf' ? 'pdf' : ($ext === 'dwg' ? 'dwg' : 'xlsx') }}">{{ mb_strtoupper($ext) }}</span>
                    <span style="flex:1">
                        <span class="dn">{{ $dokument->dateiname }}</span>
                        @if ($dokument->badge)
                            <span class="badge b-green">{{ $dokument->badge }}</span>
                        @endif
                        <div class="dm">{{ number_format($dokument->groesse / 1024, 0, ',', '.') }} KB · {{ Format::datumKurz($dokument->datum) }}</div>
                    </span>
                    <button class="dlb btn btns" type="button" data-toast="PDF wird erstellt …">
                        <svg class="i"><use href="#ic-download"/></svg></button>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-pen"/></svg>Übergabeprotokoll</div>
        <p class="note">Das Übergabeprotokoll wird bei Abschluss der Montage vom Kunden digital unterschrieben.</p>
        <div class="colstack" style="gap:10px;margin-top:12px">
            <button class="btn btnp" type="button" data-toast="Abnahmeprotokoll folgt im Montage-Milestone">
                <svg class="i"><use href="#ic-pen"/></svg>Protokoll erstellen &amp; unterschreiben</button>
            <span class="fx"><span class="badge b-gray">Status</span>
                <span class="hint">Offen — Montage ausstehend</span></span>
        </div>
    </div>
</div>
