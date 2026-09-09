{{-- Baustellenfotos: vor und nach der Montage, mit Klick-Vorschau. --}}
@php
    $fotos = $projekt->dokumente->filter(fn ($d) => str_starts_with($d->typ, 'foto_') && $d->pfad);
    $gruppen = [
        'foto_vorher' => 'Vor der Montage',
        'foto_nachher' => 'Nach der Montage',
    ];
@endphp

<div class="colstack">
    <div class="card">
        <div class="mc-h"><svg class="i"><use href="#ic-plus"/></svg>Foto hochladen</div>
        <form method="POST" action="{{ route('projekte.fotos.upload', $projekt) }}"
              enctype="multipart/form-data" class="fx ac gap8 wrap">
            @csrf
            <select class="inp" name="phase" style="width:200px">
                <option value="vorher">Vor der Montage</option>
                <option value="nachher">Nach der Montage</option>
            </select>
            <input class="inp" type="file" name="foto" accept="image/*" style="flex:1;min-width:200px">
            <button class="btn btns btnp" type="submit"><svg class="i"><use href="#ic-check"/></svg>Hochladen</button>
        </form>
        @error('foto')<p class="hint" style="color:var(--red);margin-top:8px">{{ $message }}</p>@enderror
        @error('phase')<p class="hint" style="color:var(--red);margin-top:8px">{{ $message }}</p>@enderror
    </div>

    @foreach ($gruppen as $typ => $titel)
        @php $bilder = $fotos->where('typ', $typ)->values(); @endphp
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">{{ $titel }}</span>
                <span class="pill">{{ $bilder->count() }} Fotos</span>
            </div>
            <div class="card-b">
                @if ($bilder->isEmpty())
                    <p class="hint">Noch keine Fotos {{ $typ === 'foto_vorher' ? 'vor' : 'nach' }} der Montage.</p>
                @else
                    <div class="fotogrid">
                        @foreach ($bilder as $foto)
                            <figure class="foto">
                                <img src="{{ route('dokumente.ansicht', $foto) }}" alt="{{ $foto->dateiname }}"
                                     loading="lazy" data-foto-preview="{{ route('dokumente.ansicht', $foto) }}"
                                     data-foto-name="{{ $foto->dateiname }}">
                                <figcaption class="hint">{{ \App\Support\Format::datumKurz($foto->datum) }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>

{{-- Vorschau-Fenster: JS setzt src aus data-foto-preview --}}
<div class="modal" id="fotoModal" hidden>
    <div class="modalc xl">
        <div class="modalh"><span id="fotoModalName">Foto</span>
            <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
        <div class="modalb" style="text-align:center">
            <img id="fotoModalImg" src="" alt="" style="max-width:100%;max-height:76vh;border-radius:9px">
        </div>
    </div>
</div>
