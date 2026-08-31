<section class="mm-card c12" id="s8">
    <div class="mm-h"><span class="n">8</span>Endmaße der Extras<span class="mm-sub" style="margin-left:auto">Aufmaß nach Montage</span></div>

    @if ($gruppen === [])
        <div class="mm-note"><svg class="i"><use href="#ic-help"/></svg>Für dieses Projekt sind keine Extras konfiguriert — es sind keine Endmaße zu erfassen.</div>
    @else
        <div class="ex-tabs">
            @foreach ($gruppen as $gruppe)
                <a class="ex-tab {{ $aktiveGruppe && $gruppe['ek'] === $aktiveGruppe['ek'] ? 'on' : '' }}"
                   href="{{ route('projekte.montage', [$projekt, 'gruppe' => $gruppe['ek']]) }}#s8">
                    <span class="ex-tt">{{ $gruppe['title'] }}</span>
                    <span class="ex-tb">{{ $gruppe['badge'] }}</span>
                    <span class="badge {{ $gruppe['progCls'] }}">{{ $gruppe['gefuellt'] }}/{{ $gruppe['gesamt'] }}</span>
                </a>
            @endforeach
        </div>

        <form id="aufmass-form" method="POST" action="{{ route('projekte.montage.aufmass', $projekt) }}"
              data-tol-gruen="{{ $tolGruen }}" data-tol-gelb="{{ $tolGelb }}">
            @csrf
            <div class="ex-panel">
                <div class="ex-draw">
                    <div class="ex-dh">
                        <span><b>{{ $aktiveGruppe['title'] }}</b><span class="ex-dsub"> · {{ $zeichnung['sub'] }}</span></span>
                        <span class="pill mono">{{ $aktiveGruppe['badge'] }}</span>
                    </div>
                    @include('projekte.montage-partials.extra-zeichnung', ['z' => $zeichnung, 'klein' => false])
                    <div class="mm-note" style="margin-top:10px"><svg class="i"><use href="#ic-help"/></svg>{{ $aktiveGruppe['note'] }}</div>
                </div>

                <div class="ex-fields">
                    {{-- Alle Gruppen liegen im Formular; nur die aktive ist sichtbar. --}}
                    @foreach ($gruppen as $gruppe)
                        <div data-gruppe="{{ $gruppe['ek'] }}" @if (! $aktiveGruppe || $gruppe['ek'] !== $aktiveGruppe['ek']) hidden @endif>
                            @foreach ($gruppe['fields'] as $feld)
                                <div class="ex-f {{ $feld['ampel'] ?? '' }}" data-soll="{{ $feld['soll'] }}">
                                    <span class="ex-tag">{{ $feld['tag'] }}</span>
                                    <span class="ex-lab"><b>{{ $feld['label'] }}</b><span>Soll {{ $feld['sollText'] }} mm</span></span>
                                    <input class="ex-in" type="text" inputmode="decimal" placeholder="Ist"
                                           name="mess[{{ $gruppe['ek'] }}.{{ $feld['tag'] }}]"
                                           value="{{ $feld['ist'] !== null ? rtrim(rtrim(number_format($feld['ist'], 2, ',', ''), '0'), ',') : '' }}">
                                    <span class="badge {{ $feld['badge'] }} ex-d">{{ $feld['deltaText'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ex-foot">
                <span style="flex:1">
                    <span class="mm-prog"><i style="width:{{ $mmTotal ? round($mmFilled / $mmTotal * 100) : 0 }}%"></i></span>
                    <span class="mm-sub">{{ $mmFilled }} / {{ $mmTotal }} Maße</span>
                </span>
                <span class="mm-sub">Δ &gt; {{ $tolGelb }} mm muss im Büro freigegeben werden</span>
                <button class="btn btnp" type="submit">
                    <svg class="i"><use href="#ic-check"/></svg>Aufmaß senden</button>
            </div>
        </form>
    @endif
</section>
