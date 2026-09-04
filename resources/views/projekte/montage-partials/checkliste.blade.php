@php
    $erledigtGesamt = $aufgabenTage->sum('erledigt');
    $aufgabenGesamt = $aufgabenTage->sum(fn ($tag) => $tag['aufgaben']->count());
@endphp

<section class="mm-card c12" id="s11">
    <div class="mm-h"><span class="n">11</span>Montage-Checkliste · Aufgaben je Termin
        <span class="mm-sub" style="margin-left:auto">{{ $erledigtGesamt }} von {{ $aufgabenGesamt }} erledigt</span></div>

    @foreach ($aufgabenTage as $tag)
        <div style="border:1px solid var(--bd);background:var(--bg2);border-radius:12px;padding:14px;margin-bottom:12px">
            <div class="fx" style="margin-bottom:10px">
                <span class="pill mono">{{ $tag['datum']?->format('d.m.Y') ?? '–' }}</span>
                <b>{{ $tag['label'] }}</b>
                <span class="mm-sub">Termin geplant von {{ $tag['von'] }}</span>
                <span class="badge {{ $tag['erledigt'] === $tag['aufgaben']->count() ? 'b-green' : 'b-gray' }}" style="margin-left:auto">{{ $tag['erledigt'] }} / {{ $tag['aufgaben']->count() }}</span>
            </div>
            <div class="mm-checks">
                @foreach ($tag['aufgaben'] as $aufgabe)
                    <div class="fx" style="gap:6px;align-items:stretch">
                        <form method="POST" action="{{ route('projekte.montage.aufgaben.erledigt', [$projekt, $aufgabe]) }}" style="display:flex;flex:1;min-width:0">
                            @csrf
                            <button class="mm-check {{ $aufgabe->erledigt_am ? 'on' : '' }}" type="submit" style="flex:1">
                                <span class="mm-box">
                                    @if ($aufgabe->erledigt_am)<svg class="i" style="width:13px;height:13px"><use href="#ic-check"/></svg>@endif
                                </span>
                                <span class="mm-ct"><b>{{ $aufgabe->titel }}</b><span>{{ $aufgabe->beschreibung }}</span></span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('projekte.montage.aufgaben.loeschen', [$projekt, $aufgabe]) }}" style="display:flex">
                            @csrf
                            <button class="btn btns" type="submit" style="color:var(--red)" aria-label="Aufgabe entfernen">✕</button>
                        </form>
                    </div>
                @endforeach
            </div>
            <form class="fx" method="POST" action="{{ route('projekte.montage.aufgaben', $projekt) }}" style="margin-top:10px">
                @csrf
                <input type="hidden" name="datum" value="{{ $tag['datum']?->toDateString() }}">
                <input class="inp" style="flex:1" name="titel" placeholder="Aufgabe für diesen Termin ergänzen (Büro) …">
                <button class="btn btns" type="submit">Hinzufügen</button>
            </form>
        </div>
    @endforeach

    {{-- Neuen Termin (Tag) anlegen — erste Aufgabe eines neuen Datums (Büro) --}}
    <div style="border:1px dashed var(--bd);border-radius:12px;padding:14px;margin-bottom:12px">
        <form class="fx" method="POST" action="{{ route('projekte.montage.aufgaben', $projekt) }}" style="gap:8px;flex-wrap:wrap">
            @csrf
            <b style="align-self:center">Neuen Termin anlegen</b>
            <input class="inp mono" type="date" name="datum" style="width:170px" required>
            <input class="inp" style="flex:1;min-width:200px" name="titel" placeholder="Erste Aufgabe des Termins …" required>
            <button class="btn btns" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Termin anlegen</button>
        </form>
    </div>

    <div class="jb">
        <a class="btn btnp" href="{{ route('projekte.abnahme', $projekt) }}">
            <svg class="i"><use href="#ic-doc"/></svg>Protokoll erstellen</a>
        <span class="mm-sub">Das Protokoll enthält genau die erledigten Positionen.</span>
    </div>
</section>
