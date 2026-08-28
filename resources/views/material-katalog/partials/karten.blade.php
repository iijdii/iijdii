@php use App\Enums\ArtikelKategorie; use App\Support\Format; @endphp

@foreach (ArtikelKategorie::cases() as $kat)
    @php $artikel = $gruppen->get($kat->value); @endphp
    @if ($artikel && $artikel->isNotEmpty())
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">{{ $kat->label() }}</span>
                <span class="pill">{{ $artikel->count() }} Artikel</span>
            </div>
            <div class="card-b">
                <div class="cgrid">
                    @foreach ($artikel as $a)
                        @php
                            [$kurz, $bc] = match ($a->bestandsstatus()) {
                                'ok' => ['verfügbar', 'b-green'],
                                'niedrig' => ['knapp', 'b-yellow'],
                                'leer' => ['leer', 'b-red'],
                            };
                        @endphp
                        <div class="ccard" data-modal-url="{{ route('lager.artikel', $a) }}">
                            <span class="cimg">
                                @if ($a->bild)
                                    <img src="{{ asset('images/comp/'.$a->bild.'.png') }}" alt="" loading="lazy">
                                @else
                                    <svg class="i" style="color:var(--ink3)"><use href="#ic-lager"/></svg>
                                @endif
                            </span>
                            <span class="cmeta">
                                <span class="cname">{{ $a->name }}</span>
                                <span class="csub">
                                    <span class="cart mono">{{ $a->art_nr }}</span>
                                    <span class="cqty mono">{{ Format::eur($a->ek_preis) }}</span>
                                </span>
                                <span class="csub">
                                    <span style="color:var(--ink3)">{{ $a->lieferant->name }}</span>
                                    <span class="badge {{ $bc }}">{{ $kurz }}</span>
                                </span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endforeach
