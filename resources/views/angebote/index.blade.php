@extends('layouts.app')

@section('title', 'Angebote')

@php use App\Support\Format; @endphp

@section('content')
<div class="card p0">
    <div class="card-h">
        <span class="card-t">Angebote</span>
        <span class="pill">{{ $angebote->count() }} aktiv</span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead>
            <tr><th>Nr.</th><th>Kunde</th><th>Projekt</th><th class="num">Betrag</th><th>Datum</th><th class="num">Status</th></tr>
            </thead>
            <tbody>
            @foreach ($angebote as $angebot)
                <tr class="lrow" onclick="window.location='{{ route('angebote.show', $angebot) }}'">
                    <td class="b mono">{{ $angebot->nr }}</td>
                    <td class="b">{{ $angebot->kunde->anzeigename }}</td>
                    <td class="mono">
                        @if ($angebot->projekt)
                            <a href="{{ route('projekte.show', $angebot->projekt) }}" onclick="event.stopPropagation()">{{ $angebot->projekt->nr }}</a>
                        @else
                            –
                        @endif
                    </td>
                    <td class="num mono">{{ $angebot->summe !== null ? Format::eur($angebot->summe) : 'in Konfiguration' }}</td>
                    <td class="mono">{{ Format::datumKurz($angebot->datum) }}</td>
                    <td class="num"><span class="badge {{ $angebot->status->badgeClass() }}">{{ $angebot->status->label() }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
