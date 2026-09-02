@extends('layouts.app')

@section('title', 'Einstellungen')

@section('content')
<div class="cols-2" style="align-items:start">

    <form class="card" method="POST" action="{{ route('einstellungen.speichern') }}">
        @csrf
        <div class="mc-h"><svg class="i"><use href="#ic-settings"/></svg>Aufmaß-Toleranzen</div>
        <p class="note">Ampel der Soll/Ist-Abweichungen im Montage-Modus: |Δ| bis zur Grün-Schwelle grün,
            bis zur Gelb-Schwelle gelb, darüber rot. Geschäftsregel — bewusst konfigurierbar statt hartkodiert.</p>

        @if ($errors->any())
            <div class="kwarn" style="margin-top:10px">{{ $errors->first() }}</div>
        @endif

        <div class="cols-2" style="margin-top:12px">
            <div class="fld">
                <label for="toleranz_gruen_mm">Grün bis (mm)</label>
                <input class="inp mono" type="number" min="0" max="100" id="toleranz_gruen_mm"
                       name="toleranz_gruen_mm" value="{{ old('toleranz_gruen_mm', $gruen) }}">
            </div>
            <div class="fld">
                <label for="toleranz_gelb_mm">Gelb bis (mm)</label>
                <input class="inp mono" type="number" min="0" max="100" id="toleranz_gelb_mm"
                       name="toleranz_gelb_mm" value="{{ old('toleranz_gelb_mm', $gelb) }}">
            </div>
        </div>
        <p class="hint" style="margin-top:8px">Darüber: rot — «Δ &gt; {{ $gelb }} mm muss im Büro freigegeben werden».</p>

        <div class="jb" style="margin-top:14px">
            <span class="hint">Gilt sofort für Montage-Modus und Endmaße.</span>
            <button class="btn btnp" type="submit">Speichern</button>
        </div>
    </form>

    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-doc"/></svg>Firmenstammdaten</div>
        <div class="kv"><div class="k">Firma</div><div class="v">{{ config('lea.firma') }}</div></div>
        <div class="kv"><div class="k">Anschrift</div><div class="v sub">{{ config('lea.anschrift') }}</div></div>
        <div class="kv"><div class="k">Bauleitung</div><div class="v">{{ config('lea.bauleitung') }}</div></div>
        <div class="kv"><div class="k">Montageteam</div><div class="v">{{ config('lea.montageteam') }}</div></div>
        <p class="hint" style="margin-top:8px">Pflege über <span class="mono">config/lea.php</span> —
            erscheint u. a. im Abnahmeprotokoll und im Titelblock der Zeichnungen.</p>
    </div>

</div>
@endsection
