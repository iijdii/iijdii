@extends('layouts.app')

@section('title', 'Lieferanten')

@php use App\Support\Format; @endphp

@section('content')
<div class="colstack">

    <div class="kpirow">
        <div class="kpi2 kp-blue"><div class="kl">Lieferanten</div><div class="kn">{{ $lieferanten->count() }}</div><div class="ks">Stammdaten gepflegt</div></div>
        <div class="kpi2 kp-gold"><div class="kl">Artikel im Katalog</div><div class="kn">{{ $artikelGesamt }}</div><div class="ks">über alle Lieferanten</div></div>
        <div class="kpi2 kp-green"><div class="kl">Offene Bestellungen</div><div class="kn">{{ $offenGesamt }}</div><div class="ks">geprüft · bestellt · bereit</div></div>
        <div class="kpi2 kp-dark"><div class="kl">Lagerwert</div><div class="kn" style="font-size:24px">{{ Format::eur0($lagerwertGesamt) }}</div><div class="ks">EK-Basis, gesamter Bestand</div></div>
    </div>

    <div class="card p0">
        <div class="card-h">
            <span class="card-t">Lieferanten</span>
            <span class="pill">{{ $lieferanten->count() }}</span>
        </div>
        <div class="card-b" style="padding:6px 17px;overflow-x:auto">
            <table class="tbl">
                <thead><tr><th>Name</th><th>Ansprechpartner</th><th>Kontakt</th><th>Ort</th>
                    <th class="num">Artikel</th><th class="num">Offene Bestellungen</th><th class="num">Lagerwert</th></tr></thead>
                <tbody>
                @foreach ($lieferanten as $zeile)
                    @php $lieferant = $zeile['lieferant']; @endphp
                    <tr class="lrow" onclick="window.location='{{ route('bestellungen', ['lieferant' => $lieferant->id]) }}'">
                        <td class="b">{{ $lieferant->name }}</td>
                        <td>{{ $lieferant->ansprechpartner ?? '–' }}</td>
                        <td>
                            <span class="mono" style="font-size:12px">{{ $lieferant->telefon ?? '–' }}</span>
                            <div style="font-size:11px;color:var(--ink3)">{{ $lieferant->email ?? '–' }}</div>
                        </td>
                        <td>{{ trim(($lieferant->plz ?? '').' '.($lieferant->stadt ?? '')) ?: '–' }}</td>
                        <td class="num mono">{{ $lieferant->artikel_count }}</td>
                        <td class="num mono">{{ $zeile['offeneBestellungen'] }}</td>
                        <td class="num mono">{{ Format::eur0($zeile['lagerwert']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="hint">Klick auf eine Zeile öffnet die Bestellungen. Detailansicht &amp; Pflege der Stammdaten folgen als Ausbaustufe.</p>

</div>
@endsection
