<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Angebot {{ $angebot->nr }} · {{ config('lea.firma') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:opsz,wght@9..40,400..700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
</head>
@php use App\Support\Format; @endphp
<body class="login-body">
<main class="login-card" style="max-width:640px">
    <div class="brand login-brand">
        <span class="logo" aria-hidden="true"></span>
        <span class="brandtx"><b>{{ config('lea.firma') }}</b><span>Terrassendach · Montage · Service</span></span>
    </div>

    <h1 class="login-title">Angebot {{ $angebot->nr }}</h1>
    <p class="hint">für {{ $angebot->kunde->anzeigename }} · vom {{ Format::datumKurz($angebot->datum) }}
        @if ($angebot->gueltig_bis) · gültig bis {{ $angebot->gueltig_bis->format('d.m.Y') }} @endif</p>

    <table class="tbl" style="margin-top:12px">
        <thead><tr><th>Pos</th><th>Bezeichnung</th><th class="num">Menge</th><th class="num">Gesamt</th></tr></thead>
        <tbody>
        @foreach ($rechnung['positionen'] as $position)
            @if ($position['gesamt'] !== null || $position['key'] !== 'montage')
                <tr>
                    <td>{{ $position['pos'] }}</td>
                    <td class="b">{{ $position['titel'] }}</td>
                    <td class="num mono">{{ $position['menge'] }}</td>
                    <td class="num mono">{{ $position['gesamt'] !== null ? Format::eur($position['gesamt']) : '–' }}</td>
                </tr>
            @endif
        @endforeach
        @if ($rechnung['rabattBetrag'] > 0)
            <tr><td colspan="3" class="num">Rabatt</td>
                <td class="num mono">−{{ Format::eur($rechnung['rabattBetrag']) }}</td></tr>
        @endif
        <tr><td colspan="3" class="num b">Gesamtbetrag (brutto, inkl. 19 % MwSt.)</td>
            <td class="num mono b">{{ Format::eur($rechnung['gesamt']) }}</td></tr>
        </tbody>
    </table>

    @if ($angebot->status === \App\Enums\AngebotStatus::Angenommen)
        <div class="kbox" style="margin-top:14px">
            <div class="kt">Angebot angenommen</div>
            <p class="hint">Vielen Dank! Das Angebot wurde
                @if ($angebot->angenommen_am) am {{ $angebot->angenommen_am->format('d.m.Y H:i') }} @endif
                verbindlich angenommen. Wir melden uns zur Terminabstimmung.</p>
        </div>
    @elseif ($abgelaufen)
        <div class="kbox" style="margin-top:14px">
            <div class="kt">Angebot abgelaufen</div>
            <p class="hint">Die Gültigkeit dieses Angebots ist abgelaufen.
                Bitte kontaktieren Sie uns unter {{ config('lea.telefon') }} für ein aktualisiertes Angebot.</p>
        </div>
    @elseif ((float) $angebot->summe <= 0)
        <p class="hint" style="margin-top:14px">Dieses Angebot ist noch in Bearbeitung und kann
            noch nicht online angenommen werden.</p>
    @else
        <form method="POST" action="{{ route('angebote.annahme.bestaetigen', $angebot->accept_token) }}"
              style="margin-top:14px">
            @csrf
            <p class="hint">Mit dem Klick auf «Angebot verbindlich annehmen» nehmen Sie das Angebot
                {{ $angebot->nr }} gemäß §§&nbsp;145&nbsp;ff. BGB an; es kommt ein verbindlicher
                Vertrag zustande. Die endgültige Ausführung erfolgt nach Aufmaß vor Ort.</p>
            <button class="btn btns btnp" type="submit" style="width:100%;margin-top:10px">
                Angebot verbindlich annehmen</button>
        </form>
    @endif

    <p class="hint" style="margin-top:16px">{{ config('lea.firma') }} · {{ config('lea.anschrift') }} ·
        {{ config('lea.telefon') }} · {{ config('lea.email') }}</p>
</main>
</body>
</html>
