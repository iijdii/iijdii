<div class="card" style="max-width:640px">
    <div class="mc-h"><svg class="i"><use href="#ic-clock"/></svg>Projektverlauf</div>
    @if ($projekt->aktivitaeten->isEmpty())
        <p class="hint">Noch keine Aktivitäten.</p>
    @endif
    <div class="tl">
        @foreach ($projekt->aktivitaeten as $aktivitaet)
            <div class="tli">
                <span class="tld {{ $aktivitaet->status }}">
                    @if ($aktivitaet->status === 'done')
                        <svg class="i" style="width:11px;height:11px"><use href="#ic-check"/></svg>
                    @endif
                </span>
                <span style="flex:1">
                    <span class="tlt">{{ $aktivitaet->titel }}</span>
                    <div class="hint">{{ $aktivitaet->wer }} · {{ $aktivitaet->datum }}</div>
                </span>
            </div>
        @endforeach
    </div>
</div>
