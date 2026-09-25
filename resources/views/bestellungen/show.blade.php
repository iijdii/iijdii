@extends('layouts.app')

@section('title', 'Bestellung '.$bestellung->nr)

@php
    use App\Enums\BestellungStatus;
    use App\Support\Format;
    // Lieferanten-Portal (M14): schlanke Sicht — kein Kundenbezug, kein
    // Status-Stepper, keine internen Links; stattdessen «Bereit melden».
    $portal = auth()->user()->istLieferant();
@endphp

@section('content')
<div class="colstack">

    <div class="card">
        <div class="jb wrap">
            <a class="btn btns" href="{{ route('bestellungen') }}">
                <svg class="i"><use href="#ic-aleft"/></svg>Zurück zur Liste</a>
            <span class="ctas">
                @if (! $portal && in_array($bestellung->status->value, ['entwurf', 'geprueft'], true))
                    <a class="btn btns" href="{{ route('bestellungen.edit', $bestellung) }}">
                        <svg class="i"><use href="#ic-edit"/></svg>Bearbeiten</a>
                @endif
                <a class="btn btns btnp" href="{{ route('bestellungen.pdf', $bestellung) }}"
                   data-pdf data-pdf-titel="Bestellung {{ $bestellung->nr }}">
                    <svg class="i"><use href="#ic-doc"/></svg>PDF drucken</a>
            </span>
        </div>
        <div class="fx" style="margin-top:14px">
            <span class="anf-nr mono">{{ $bestellung->nr }}</span>
            <span class="badge {{ $bestellung->status->badgeClass() }}">{{ $bestellung->status->label() }}</span>
            <span class="pill {{ $bestellung->kategoriePillClass() }}">{{ $bestellung->kategorieLabel() }}</span>
        </div>
        <h2 class="serif" style="margin:8px 0 14px;font-size:23px">{{ $bestellung->titel }}</h2>
        <div class="metarow">
            <span><span class="meta-k">Lieferant</span><span class="meta-v">{{ $bestellung->lieferant?->name ?? '— Lieferant wählen —' }}</span></span>
            @unless ($portal)
                <span><span class="meta-k">Kunde</span><span class="meta-v">{{ $bestellung->kunde?->anzeigename ?? '–' }}</span></span>
            @endunless
            <span><span class="meta-k">Projekt</span><span class="meta-v mono">{{ $bestellung->projekt?->nr ?? '–' }}</span></span>
            <span><span class="meta-k">Ersteller</span><span class="meta-v">{{ $bestellung->ersteller?->name ?? '–' }}</span></span>
            <span><span class="meta-k">Erstellt</span><span class="meta-v mono">{{ Format::datum($bestellung->created_at) }}</span></span>
            <span><span class="meta-k">Liefertermin</span><span class="meta-v mono">{{ Format::datumKurz($bestellung->liefertermin) }}</span></span>
        </div>

        @if ($portal)
            @if ($bestellung->status === BestellungStatus::Bestellt)
                <form method="POST" action="{{ route('bestellungen.status', $bestellung) }}" style="margin-top:14px">
                    @csrf
                    <input type="hidden" name="status" value="bereit">
                    <button class="btn btns btnp" type="submit"
                            onclick="return confirm('Bestellung als „Bereit zur Abholung/Lieferung“ melden?')">
                        <svg class="i"><use href="#ic-check"/></svg>Bereit melden</button>
                </form>
                <p class="hint" style="margin-top:8px">Damit melden Sie der LEA-Disposition, dass die Ware
                    produziert und bereit ist.</p>
            @else
                <p class="hint" style="margin-top:14px">Statuspflege übernimmt ab hier die LEA-Disposition.</p>
            @endif
        @else
        <div class="stp">
            <span class="stp-l">Status</span>
            <span class="stp-bs">
                @foreach (BestellungStatus::cases() as $status)
                    <form method="POST" action="{{ route('bestellungen.status', $bestellung) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $status->value }}">
                        <button class="stp-b {{ $status->stepClass() }} {{ $status === $bestellung->status ? 'cur' : '' }}"
                                type="submit" @disabled($status === $bestellung->status)>
                            @if ($status === $bestellung->status)
                                <svg class="i"><use href="#ic-check"/></svg>
                            @endif
                            {{ $status->label() }}
                        </button>
                    </form>
                @endforeach
            </span>
        </div>
        @endif
    </div>

    @if ($wareneingang)
        <div class="card lg-ok">
            <div class="jb wrap">
                <span class="fx">
                    <svg class="i" style="color:var(--green)"><use href="#ic-check"/></svg>
                    <span>
                        <b>Geliefert &amp; eingelagert</b>
                        <div class="hint">Wareneingang gebucht am {{ Format::datum($wareneingang->datum) }}
                            · {{ $wareneingang->benutzer?->name ?? '–' }} · Lager
                            · {{ Format::menge($wareneingang->positionen->sum('menge')) }} Stück
                            auf {{ $wareneingang->positionen->count() }} Artikeln
                            · Lieferschein {{ $wareneingang->lieferschein_nr }}</div>
                    </span>
                </span>
                @unless ($portal)
                    <a class="btn btns" href="{{ route('lager', ['tab' => 'wareneingang']) }}">
                        <svg class="i"><use href="#ic-lager"/></svg>Im Lager anzeigen</a>
                @endunless
            </div>
        </div>
    @endif

    @if ($segelPositionen->isNotEmpty())
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Sonnensegel (Tuch)</span>
                <span class="pill">{{ Format::menge($segelPositionen->sum(fn ($s) => (float) $s->menge)) }} Stück</span>
            </div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl">
                    <thead><tr><th>Pos.</th><th>Artikel</th><th class="num">Breite</th><th class="num">Länge</th><th>Farbe</th><th class="num">Menge</th></tr></thead>
                    <tbody>
                    @foreach ($segelPositionen as $segel)
                        @php $sd = $segel->details ?? []; @endphp
                        <tr>
                            <td><span class="posn">{{ $loop->iteration }}</span></td>
                            <td><span class="b">Sonnensegel</span> · Tuch
                                @if (! empty($sd['projekt_pos']))<div class="hint">Projekt-Pos. {{ $sd['projekt_pos'] }}</div>@endif</td>
                            <td class="num mono">{{ number_format((int) ($sd['breite_mm'] ?? $segel->breite_mm), 0, ',', '.') }} mm</td>
                            <td class="num mono">{{ number_format((int) ($sd['laenge_mm'] ?? $segel->hoehe_mm), 0, ',', '.') }} mm</td>
                            <td>{{ ($sd['farbe'] ?? '') !== '' ? $sd['farbe'] : '–' }}</td>
                            <td class="num mono b">{{ Format::menge($segel->menge) }} {{ $segel->einheit }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($markisenPositionen->isNotEmpty())
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Markisen · Bestellblatt Varisol</span>
                <a class="btn btns" href="{{ route('bestellungen.pdf', $bestellung) }}"
                   data-pdf data-pdf-titel="Bestellung {{ $bestellung->nr }}">Bestellblätter (PDF)</a>
            </div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl">
                    <thead><tr><th>Pos.</th><th>Modell</th><th class="num">Breite</th><th class="num">Ausfall</th>
                        <th>Antrieb</th><th>Antriebsseite</th><th>Gestellfarbe</th><th>Dessin</th><th class="num">Stück</th></tr></thead>
                    <tbody>
                    @foreach ($markisenPositionen as $markise)
                        @php $mf = $markise->details['formular'] ?? []; @endphp
                        <tr>
                            <td><span class="posn">{{ $loop->iteration }}</span></td>
                            <td class="b">{{ \App\Support\MarkisenFormular::MODELLE[\App\Support\MarkisenFormular::modell($mf)] }}
                                @if (($mf['typ'] ?? '') !== '')<div class="hint">{{ $mf['typ'] }}</div>@endif</td>
                            <td class="num mono">{{ number_format((int) ($mf['breite_mm'] ?? 0), 0, ',', '.') }} mm</td>
                            <td class="num mono">{{ number_format((int) ($mf['ausfall_mm'] ?? 0), 0, ',', '.') }} mm</td>
                            <td>{{ $mf['antrieb'] ?? '–' }}</td>
                            <td>{{ $mf['antriebsseite'] ?? '–' }}</td>
                            <td>{{ $mf['gestellfarbe'] ?? '–' }}</td>
                            <td>{{ $mf['dessin'] ?? '–' }}</td>
                            <td class="num mono b">{{ \App\Support\Format::menge($markise->menge) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($materialPositionen->isNotEmpty())
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Material-Positionen</span>
                <span class="pill">{{ $materialPositionen->count() }} Positionen</span>
            </div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                    <tr><th>Bezeichnung</th><th>Art.-Nr.</th><th>Menge</th><th>Einheit</th>
                        @unless ($portal)<th>Lagerort</th><th>Lager</th>@endunless</tr>
                    </thead>
                    <tbody>
                    @foreach ($materialPositionen as $position)
                        <tr>
                            <td><span class="b">{{ $position->bezeichnung }}</span>
                                @if (! empty($position->details['komponenten']))
                                    <table class="tbl" style="margin-top:6px;font-size:12px">
                                        <thead><tr><th>Bauteil</th><th class="num">Menge</th><th class="num">Zuschnitt</th></tr></thead>
                                        <tbody>
                                        @foreach ($position->details['komponenten'] as $teil)
                                            <tr>
                                                <td>{{ $teil['name'] }}</td>
                                                <td class="num mono">{{ $teil['menge'] }} {{ $teil['einheit'] }}</td>
                                                <td class="num mono">{{ ! empty($teil['laenge_mm']) ? number_format((int) $teil['laenge_mm'], 0, ',', '.').' mm' : '–' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </td>
                            <td class="mono">{{ $position->artikel?->art_nr ?? '—' }}</td>
                            <td class="mono">{{ Format::menge($position->menge) }}</td>
                            <td>{{ $position->einheit ?? $position->artikel?->einheit->value ?? 'Stück' }}</td>
                            @unless ($portal)
                                <td class="mono">{{ $position->artikel?->lagerort ?? '–' }}</td>
                                <td><span class="badge {{ $position->eingelagert ? 'b-green' : 'b-gray' }}">{{ $position->eingelagert ? 'Eingelagert' : 'Offen' }}</span></td>
                            @endunless
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($glasPositionen->isNotEmpty())
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Glas-Positionen</span>
                <span class="pill">{{ Format::menge($glasPositionen->sum(fn ($g) => (float) $g['position']->menge)) }} Stück</span>
            </div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl postbl">
                    <thead><tr><th>Pos.</th><th>Skizze</th><th>Maße / Hinweise</th></tr></thead>
                    <tbody>
                    @foreach ($glasPositionen as $glas)
                        <tr>
                            <td><span class="posn">{{ $glas['nr'] }}</span></td>
                            <td>@include('bestellungen.partials.glas-skizze', ['glas' => $glas])</td>
                            <td>
                                @php
                                    $gd = $glas['position']->details ?? [];
                                    $ghL = (int) ($gd['hL'] ?? $glas['position']->hoehe_mm);
                                    $ghR = (int) ($gd['hR'] ?? $ghL);
                                @endphp
                                <div class="pinfo">
                                    <span class="pk"><span>Bezeichnung</span><b>{{ $glas['position']->bezeichnung }}</b></span>
                                    <span class="pk"><span>Form</span><b>{{ $glas['form'] }}</b></span>
                                    <span class="pk"><span>Breite</span><b class="mono">{{ number_format((int) $glas['position']->breite_mm, 0, ',', '.') }} mm</b></span>
                                    @if ($glas['form'] === 'Trapez')
                                        <span class="pk"><span>Höhe links/rechts</span><b class="mono">{{ number_format($ghL, 0, ',', '.') }} / {{ number_format($ghR, 0, ',', '.') }} mm</b></span>
                                    @else
                                        <span class="pk"><span>Höhe</span><b class="mono">{{ number_format($ghL, 0, ',', '.') }} mm</b></span>
                                    @endif
                                    <span class="pk"><span>Menge</span><b>{{ Format::menge($glas['position']->menge) }} Stück</b></span>
                                    <span class="pk"><span>Glas</span><b>{{ $glas['glas'] }}</b></span>
                                    <span class="pk"><span>Quelle</span>
                                        <span class="badge {{ $glas['quelle'] === 'live' ? 'b-blue' : 'b-gray' }}">{{ $glas['quelle'] === 'live' ? 'aus Projekt' : 'manuell' }}</span></span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($schiebePositionen->isNotEmpty())
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Schiebe-Elemente</span>
                <span class="pill">{{ $schiebePositionen->count() }} Anlagen</span>
            </div>
            <div class="card-b" style="overflow-x:auto">
                <table class="tbl postbl">
                    <thead><tr><th>Pos.</th><th>Skizze</th><th>Öffnung / Hinweise</th></tr></thead>
                    <tbody>
                    @foreach ($schiebePositionen as $schiebe)
                        <tr>
                            <td><span class="posn">{{ $schiebe['nr'] }}</span></td>
                            <td>@include('bestellungen.partials.schiebe-skizze', ['schiebe' => $schiebe])</td>
                            <td>
                                <div class="pinfo">
                                    <span class="pk"><span>Bezeichnung</span><b>{{ $schiebe['position']->bezeichnung }}</b></span>
                                    <span class="pk"><span>Öffnung Breite</span><b class="mono">{{ number_format((int) $schiebe['position']->breite_mm, 0, ',', '.') }} mm</b></span>
                                    <span class="pk"><span>Öffnung Höhe</span><b class="mono">{{ number_format((int) $schiebe['position']->hoehe_mm, 0, ',', '.') }} mm</b></span>
                                    <span class="pk"><span>Elemente</span><b>{{ $schiebe['anzahl'] }} Stück</b></span>
                                    <span class="pk"><span>Glas</span><b>{{ $schiebe['glas'] }}</b></span>
                                    <span class="pk"><span>Richtung</span><b>{{ $schiebe['richtung'] }}</b></span>
                                    <span class="pk"><span>Einbauort</span><b>{{ $schiebe['einbauort'] !== '' ? $schiebe['einbauort'] : '–' }}</b></span>
                                    <span class="pk"><span>Quelle</span>
                                        <span class="badge {{ $schiebe['quelle'] === 'live' ? 'b-blue' : 'b-gray' }}">{{ $schiebe['quelle'] === 'live' ? 'aus Projekt' : 'manuell' }}</span></span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Im Prototyp versehentlich in den Schiebe-Zweig verschachtelt — hier bewusst immer sichtbar. --}}
    <div class="cols-2">
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-anfragen"/></svg>Notiz</div>
            <p class="note">{{ $bestellung->notizen ?? '–' }}</p>
        </div>
        <div class="card">
            <div class="mc-h"><svg class="i"><use href="#ic-doc"/></svg>PDF-Vorschau</div>
            <div class="jb">
                <span class="hint">{{ $bestellung->kategorieLabel() }}<br>{{ $bestellung->nr }}.pdf</span>
                <a class="btn btns" href="{{ route('bestellungen.pdf', $bestellung) }}"
                   data-pdf data-pdf-titel="Bestellung {{ $bestellung->nr }}">Öffnen</a>
            </div>
        </div>
    </div>

    @if (in_array($bestellung->status->value, ['entwurf', 'geprueft'], true))
        <div class="card p0">
            <div class="card-h">
                <span class="card-t">Positionen erfassen</span>
                <span class="pill">nur im Entwurf/Geprüft</span>
            </div>
            <div class="card-b colstack" style="gap:14px">

                <form method="POST" action="{{ route('bestellungen.positionen.store', $bestellung) }}" class="fx ac gap8" style="flex-wrap:wrap">
                    @csrf<input type="hidden" name="typ" value="material">
                    <span class="pill" style="width:74px;justify-content:center">Material</span>
                    <input class="inp" name="bezeichnung" placeholder="Bezeichnung (Alias-Auflösung: z. B. Pfosten 110×110)" style="flex:2;min-width:220px" required>
                    <input class="inp mono" type="number" step="0.5" min="0.5" name="menge" placeholder="Menge" style="width:90px" required>
                    <button class="btn btns" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Hinzufügen</button>
                </form>

                <form method="POST" action="{{ route('bestellungen.positionen.store', $bestellung) }}" class="fx ac gap8" style="flex-wrap:wrap">
                    @csrf<input type="hidden" name="typ" value="glas">
                    <span class="pill" style="width:74px;justify-content:center">Glas</span>
                    <input class="inp" name="bezeichnung" placeholder="Bezeichnung" style="flex:2;min-width:160px" required>
                    <select class="inp" name="form" style="width:110px">
                        <option>Rechteck</option><option>Trapez</option>
                    </select>
                    <input class="inp mono" type="number" name="breite_mm" placeholder="Breite mm" style="width:100px" required>
                    <input class="inp mono" type="number" name="hL" placeholder="Höhe L" style="width:90px" required>
                    <input class="inp mono" type="number" name="hR" placeholder="Höhe R" style="width:90px">
                    <input class="inp mono" type="number" step="0.5" min="0.5" name="menge" placeholder="Menge" style="width:80px" required>
                    <input class="inp" name="glas" placeholder="Glas (z. B. VSG 8 mm klar)" style="width:170px">
                    <button class="btn btns" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Hinzufügen</button>
                </form>

                <form method="POST" action="{{ route('bestellungen.positionen.store', $bestellung) }}" class="fx ac gap8" style="flex-wrap:wrap">
                    @csrf<input type="hidden" name="typ" value="schiebe">
                    <span class="pill" style="width:74px;justify-content:center">Schiebe</span>
                    <input class="inp" name="bezeichnung" placeholder="Bezeichnung" style="flex:2;min-width:160px" required>
                    <input class="inp mono" type="number" name="breite_mm" placeholder="Breite mm" style="width:100px" required>
                    <input class="inp mono" type="number" name="hoehe_mm" placeholder="Höhe mm" style="width:100px" required>
                    <input class="inp mono" type="number" name="count" placeholder="Elemente" style="width:90px" required>
                    <input class="inp" name="glas" placeholder="Glas" style="width:130px">
                    <input class="inp" name="einbauort" placeholder="Einbauort" list="einbauorte-bst" style="width:130px">
                    <datalist id="einbauorte-bst">
                        @foreach (['Links', 'Rechts', 'Vorne', 'Vorne links', 'Vorne Mitte', 'Vorne rechts'] as $ort)
                            <option value="{{ $ort }}"></option>
                        @endforeach
                    </datalist>
                    <button class="btn btns" type="submit"><svg class="i"><use href="#ic-plus"/></svg>Hinzufügen</button>
                </form>

                @if ($errors->any())
                    <div class="kwarn">{{ $errors->first() }}</div>
                @endif

                @if ($bestellung->positionen->isNotEmpty())
                    <table class="tbl">
                        <thead><tr><th>Pos</th><th>Typ</th><th>Bezeichnung</th><th class="num">Menge</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($bestellung->positionen->sortBy('pos') as $position)
                            <tr>
                                <td class="mono">{{ $position->pos }}</td>
                                <td>{{ ucfirst($position->typ->value) }}</td>
                                <td class="b">{{ $position->bezeichnung }}</td>
                                <td class="num mono">{{ \App\Support\Format::menge($position->menge) }} {{ $position->einheit }}</td>
                                <td class="num">
                                    <span class="fx ac gap8" style="justify-content:flex-end">
                                        <button class="btn btns" type="button" data-modal-target="pos-edit-{{ $position->id }}">Bearbeiten</button>
                                        <form method="POST" action="{{ route('bestellungen.positionen.loeschen', [$bestellung, $position]) }}">
                                            @csrf
                                            <button class="btn btns" type="submit" style="color:var(--red)">Entfernen</button>
                                        </form>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Bearbeiten-Fenster je Position (Validierung wie beim Anlegen) --}}
        @foreach ($bestellung->positionen->sortBy('pos') as $position)
            @php $d = $position->details ?? []; @endphp
            <div class="modal" id="pos-edit-{{ $position->id }}" hidden>
                <div class="modalc">
                    <div class="modalh">Position {{ $position->pos }} · {{ ucfirst($position->typ->value) }} bearbeiten
                        <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button></div>
                    <div class="modalb">
                        <form method="POST" action="{{ route('bestellungen.positionen.update', [$bestellung, $position]) }}" class="colstack" style="gap:10px">
                            @csrf
                            <div class="fld"><label>Bezeichnung</label>
                                <input class="inp" name="bezeichnung" value="{{ $position->bezeichnung }}" required></div>
                            @if ($position->typ->value === 'glas')
                                <div class="fgrid2">
                                    <div class="fld"><label>Form</label>
                                        <select class="inp" name="form">
                                            <option @selected(($d['form'] ?? 'Rechteck') === 'Rechteck')>Rechteck</option>
                                            <option @selected(($d['form'] ?? '') === 'Trapez')>Trapez</option>
                                        </select></div>
                                    <div class="fld"><label>Breite (mm)</label>
                                        <input class="inp mono" type="number" name="breite_mm" value="{{ (int) $position->breite_mm }}" required></div>
                                    <div class="fld"><label>Höhe links (mm)</label>
                                        <input class="inp mono" type="number" name="hL" value="{{ (int) ($d['hL'] ?? $position->hoehe_mm) }}" required></div>
                                    <div class="fld"><label>Höhe rechts (mm)</label>
                                        <input class="inp mono" type="number" name="hR" value="{{ (int) ($d['hR'] ?? $d['hL'] ?? $position->hoehe_mm) }}"></div>
                                    <div class="fld"><label>Menge</label>
                                        <input class="inp mono" type="number" step="0.5" min="0.5" name="menge" value="{{ rtrim(rtrim(number_format((float) $position->menge, 1, '.', ''), '0'), '.') }}" required></div>
                                    <div class="fld"><label>Glas</label>
                                        <input class="inp" name="glas" value="{{ $d['glas'] ?? '' }}"></div>
                                </div>
                            @elseif ($position->typ->value === 'schiebe')
                                <div class="fgrid2">
                                    <div class="fld"><label>Breite (mm)</label>
                                        <input class="inp mono" type="number" name="breite_mm" value="{{ (int) $position->breite_mm }}" required></div>
                                    <div class="fld"><label>Höhe (mm)</label>
                                        <input class="inp mono" type="number" name="hoehe_mm" value="{{ (int) $position->hoehe_mm }}" required></div>
                                    <div class="fld"><label>Elemente</label>
                                        <input class="inp mono" type="number" name="count" value="{{ (int) ($d['count'] ?? $position->menge) }}" required></div>
                                    <div class="fld"><label>Glas</label>
                                        <input class="inp" name="glas" value="{{ $d['glas'] ?? '' }}"></div>
                                    <div class="fld"><label>Einbauort</label>
                                        <input class="inp" name="einbauort" value="{{ $d['einbauort'] ?? '' }}" list="einbauorte-bst" placeholder="z. B. Vorne links"></div>
                                </div>
                            @else
                                <div class="fld"><label>Menge</label>
                                    <input class="inp mono" type="number" step="0.5" min="0.5" name="menge" value="{{ rtrim(rtrim(number_format((float) $position->menge, 1, '.', ''), '0'), '.') }}" required></div>
                            @endif
                            <div class="jb">
                                <span class="hint">Änderungen gelten für PDF und Skizzen sofort.</span>
                                <button class="btn btnp" type="submit">Speichern</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

</div>
@endsection
