{{-- Briefkopf aller PDF-Dokumente: Logo links, Firmendaten rechts,
     darunter Dokumenttitel (H1) und Status-Badges.
     Erwartet: $titel (string), optional $badges (string[]). --}}
<table class="kopf"><tr>
    <td style="width:55%">
        <div class="logo">LEA<span class="akzent">.</span></div>
        <div class="logo-untertitel">Terrassendach · Montage · Service</div>
    </td>
    <td class="firmen-daten">
        {{ config('lea.firma') }}<br>
        {{ config('lea.anschrift') }}<br>
        {{ config('lea.telefon') }} · {{ config('lea.email') }}<br>
        USt-IdNr. {{ config('lea.ustid') }}
    </td>
</tr></table>
<h1>{{ $titel }}</h1>
@foreach ($badges ?? [] as $badgeText)<span class="badge">{{ $badgeText }}</span>@endforeach
