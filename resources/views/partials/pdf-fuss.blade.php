{{-- Fixe 3-Spalten-Fußzeile + Seitenzahl — wiederholt sich auf jeder Seite. --}}
<div class="fuss">
    <table><tr>
        <td style="width:38%">
            <b>{{ config('lea.firma') }}</b><br>
            {{ config('lea.anschrift') }}<br>
            {{ config('lea.web') }}
        </td>
        <td style="width:31%">
            <b>Bankverbindung</b><br>
            {{ config('lea.bank') }}<br>
            IBAN {{ config('lea.iban') }}
        </td>
        <td style="width:31%">
            <b>Kontakt</b><br>
            {{ config('lea.telefon') }}<br>
            {{ config('lea.email') }}
        </td>
    </tr></table>
</div>
<div class="seite"></div>
