{{-- Briefkopf aller PDF-Dokumente: Logo links, Firmendaten rechts,
     darunter Dokumenttitel (H1) und Status-Badges.
     Erwartet: $titel (string), optional $badges (string[]). --}}
@php $logoPfad = public_path('images/logo-lea.png'); @endphp
<table class="kopf"><tr>
    <td style="width:55%">
        @if (is_file($logoPfad))
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPfad)) }}" style="width:27mm" alt="LEA Überdachung">
        @else
            <div class="logo">LEA<span class="akzent">.</span></div>
        @endif
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
