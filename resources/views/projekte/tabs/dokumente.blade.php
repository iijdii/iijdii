@php use App\Support\Format; @endphp

<div class="cols-2">
    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Dokumente</span>
            <span class="pill">{{ $projekt->dokumente->count() }} Dateien</span>
        </div>
        <div class="card-b">
            <form method="POST" action="{{ route('projekte.dokumente.upload', $projekt) }}"
                  enctype="multipart/form-data" class="fx ac gap8" style="margin-bottom:12px;flex-wrap:wrap">
                @csrf
                <input class="inp" type="file" name="datei" style="flex:1;min-width:200px">
                <button class="btn btns" type="submit"><svg class="i"><use href="#ic-download"/></svg>Hochladen</button>
            </form>
            @error('datei')<p class="hint" style="color:var(--red);margin-bottom:10px">{{ $message }}</p>@enderror
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
                    @if ($dokument->pfad)
                        <a class="dlb btn btns" href="{{ route('dokumente.download', $dokument) }}">
                            <svg class="i"><use href="#ic-download"/></svg></a>
                    @else
                        <button class="dlb btn btns" type="button" data-toast="Demo-Dokument ohne Datei">
                            <svg class="i"><use href="#ic-download"/></svg></button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-pen"/></svg>Übergabeprotokoll</div>
        <p class="note">Das Übergabeprotokoll wird bei Abschluss der Montage vom Kunden digital unterschrieben.</p>
        <div class="colstack" style="gap:10px;margin-top:12px">
            <a class="btn btnp" href="{{ route('projekte.abnahme', $projekt) }}">
                <svg class="i"><use href="#ic-pen"/></svg>Protokoll erstellen &amp; unterschreiben</a>
            @php $protokoll = $projekt->abnahmeprotokolle()->latest('id')->first(); @endphp
            @if ($protokoll)
                <span class="fx"><span class="badge {{ match ($protokoll->art->value) {
                        'ohne' => 'b-green', 'vorbehalt' => 'b-yellow', default => 'b-red',
                    } }}">{{ $protokoll->art->label() }}</span>
                    <span class="hint">{{ $protokoll->nr }} · unterschrieben am {{ \App\Support\Format::datum($protokoll->datum) }}</span></span>
            @else
                <span class="fx"><span class="badge b-gray">Status</span>
                    <span class="hint">Offen — Montage ausstehend</span></span>
            @endif
        </div>
    </div>
</div>
