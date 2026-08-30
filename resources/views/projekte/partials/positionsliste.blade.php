@php use App\Support\Format; @endphp
<table class="tbl">
    <thead><tr><th>Pos</th><th>Bezeichnung</th><th class="num">Menge</th></tr></thead>
    <tbody>
    @foreach ($positionen as $position)
        <tr>
            <td><span class="pos">{{ $position['pos'] }}</span></td>
            <td class="b">{{ $position['name'] }}</td>
            <td class="num mono">{{ Format::menge($position['menge']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
