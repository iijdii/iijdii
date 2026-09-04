@extends('layouts.app')

@section('title', 'Kunden')

@section('content')
<div class="card p0">
    <div class="card-h">
        <span class="card-t">Kunden</span>
        <span class="fx ac gap8"><span class="pill">{{ $kunden->count() }} Einträge</span>
            <a class="btn btns btnp" href="{{ route('kunden.create') }}">
                <svg class="i"><use href="#ic-plus"/></svg>Neuer Kunde</a></span>
    </div>
    <div class="card-b" style="overflow-x:auto">
        <table class="tbl">
            <thead>
            <tr><th>Nr.</th><th>Name</th><th>Typ</th><th>Ort</th><th>Telefon</th><th class="num">Status</th></tr>
            </thead>
            <tbody>
            @foreach ($kunden as $kunde)
                @php
                    $bc = match ($kunde->status) {
                        'Aktiv' => 'b-green', 'Lead' => 'b-yellow', default => 'b-gray',
                    };
                @endphp
                <tr class="lrow" onclick="window.location='{{ route('kunden.show', $kunde) }}'">
                    <td class="b mono">{{ $kunde->kunden_nr }}</td>
                    <td class="b">{{ $kunde->anzeigename }}</td>
                    <td>{{ $kunde->typ === 'gewerbe' ? 'Gewerbe' : 'Privatkunde' }}</td>
                    <td>{{ $kunde->stadt ?? '–' }}</td>
                    <td class="mono">{{ $kunde->telefon ?? '–' }}</td>
                    <td class="num"><span class="badge {{ $bc }}">{{ $kunde->status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
