@extends('layouts.montage')

@section('title', 'Abnahmeprotokoll · '.$projekt->nr)

@php $kunde = $projekt->kunde; @endphp

@section('content')
<div class="mm">
    <header class="mm-top">
        <a class="mm-back" href="{{ route('projekte.montage', $projekt) }}">
            <svg class="i"><use href="#ic-aleft"/></svg>Zurück zur Montage</a>
        <span class="mm-tt"><b>ABNAHMEPROTOKOLL</b><span>{{ $projekt->nr }} · {{ $projekt->titel }}</span></span>
    </header>

    <div class="mm-body" style="display:block;max-width:920px">
        <form id="abnahme-form" method="POST" action="{{ route('projekte.abnahme.speichern', $projekt) }}"
              class="card p0" data-default-frist="{{ $defaultFrist }}">
            @csrf

            <div class="apr-doch">
                <div>
                    <div class="apr-t">Abnahmeprotokoll</div>
                    <div class="apr-sub">Neues Protokoll · Terrassenüberdachung · gemäß § 640 BGB</div>
                </div>
                <button class="btn btns" type="button" data-toast="PDF wird nach der Bestätigung erzeugt">Als PDF</button>
            </div>

            @if ($errors->any())
                <div class="apr-sec"><div class="kwarn">{{ $errors->first() }}</div></div>
            @endif

            {{-- 1 · Vertragsparteien --}}
            <div class="apr-sec">
                <div class="apr-h"><svg class="i"><use href="#ic-kunden"/></svg>Vertragsparteien</div>
                <div class="apr-2">
                    <div>
                        <div class="apr-kv"><span class="k">Auftraggeber</span><span class="v">{{ $kunde->anzeigename }}</span></div>
                        <div class="apr-kv"><span class="k">Anschrift</span><span class="v">{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</span></div>
                        <div class="apr-kv"><span class="k">Telefon</span><span class="v mono">{{ $kunde->telefon ?? '–' }}</span></div>
                        <div class="apr-kv"><span class="k">E-Mail</span><span class="v">{{ $kunde->email ?? '–' }}</span></div>
                    </div>
                    <div>
                        <div class="apr-kv"><span class="k">Auftragnehmer</span><span class="v">{{ config('lea.firma') }}</span></div>
                        <div class="apr-kv"><span class="k">Anschrift</span><span class="v">{{ config('lea.anschrift') }}</span></div>
                        <div class="apr-kv"><span class="k">Bauleitung</span><span class="v">{{ config('lea.bauleitung') }}</span></div>
                        <div class="apr-kv"><span class="k">Montageteam</span><span class="v">{{ config('lea.montageteam') }}</span></div>
                    </div>
                </div>
            </div>

            {{-- 2 · Bauvorhaben & Leistungsgegenstand --}}
            <div class="apr-sec">
                <div class="apr-h"><svg class="i"><use href="#ic-projekte"/></svg>Bauvorhaben &amp; Leistungsgegenstand</div>
                <div class="apr-2">
                    <div>
                        <div class="apr-kv"><span class="k">Projekt-Nr.</span><span class="v mono">{{ $projekt->nr }}</span></div>
                        <div class="apr-kv"><span class="k">Angebots-Nr.</span><span class="v mono">{{ $projekt->angebot?->nr ?? '–' }}</span></div>
                        <div class="apr-kv"><span class="k">Montageort</span><span class="v">{{ trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? '')) }}, {{ trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? '')) }}</span></div>
                    </div>
                    <div>
                        <div class="apr-kv"><span class="k">Leistung</span><span class="v">{{ $projekt->titel }}</span></div>
                        <div class="apr-kv"><span class="k">Maße</span><span class="v mono">{{ number_format($projekt->konfiguration['width'] ?? 0, 0, ',', '.') }} × {{ number_format($projekt->konfiguration['depth'] ?? 0, 0, ',', '.') }} mm</span></div>
                        <div class="apr-kv"><span class="k">Fertiggestellt am</span><span class="v mono">{{ now()->format('d.m.Y') }}</span></div>
                    </div>
                </div>
                <table class="tbl" style="margin-top:8px">
                    <thead><tr><th>Pos</th><th>Ausgeführte Leistung</th><th>Menge</th><th class="num">Ausgeführt</th></tr></thead>
                    <tbody>
                    @foreach ($positionen as $position)
                        <tr>
                            <td><span class="pos">{{ $position['pos'] }}</span></td>
                            <td class="b">{{ $position['name'] }}</td>
                            <td class="mono">{{ $position['menge'] }}</td>
                            <td class="num"><span class="badge b-green">ja</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- 3 · Erklärung des Auftraggebers --}}
            <div class="apr-sec">
                <div class="apr-h"><svg class="i"><use href="#ic-check"/></svg>Erklärung des Auftraggebers</div>
                @foreach ([
                    ['ohne', 'Abnahme ohne Vorbehalt', 'Der Auftraggeber bestätigt, dass die Leistung vertragsgemäß, vollständig und mängelfrei ausgeführt wurde. Die Leistung wird hiermit ohne Vorbehalt abgenommen; Beanstandungen bestehen nicht.'],
                    ['vorbehalt', 'Abnahme unter Vorbehalt', 'Die Leistung wird abgenommen, jedoch unter Vorbehalt der nachstehend aufgeführten Mängel. Der Auftragnehmer verpflichtet sich zur Nachbesserung innerhalb der genannten Frist.'],
                    ['verweigert', 'Abnahme verweigert', 'Die Abnahme wird wegen der nachstehend aufgeführten wesentlichen Mängel verweigert. Ein neuer Abnahmetermin wird nach Mängelbeseitigung vereinbart.'],
                ] as [$wert, $label, $text])
                    <label class="apr-opt {{ $loop->first ? 'on' : '' }}" data-apr-opt>
                        <input type="radio" name="art" value="{{ $wert }}" @checked($loop->first) hidden>
                        <span class="apr-bx"></span>
                        <span><span class="apr-ol">{{ $label }}</span>
                            <span class="apr-od">{{ $text }}</span></span>
                    </label>
                @endforeach
            </div>

            {{-- 4 · Beanstandungen --}}
            <div class="apr-sec" id="maengelSection" hidden>
                <div class="apr-h"><svg class="i"><use href="#ic-bell"/></svg>Vorbehalte und Beanstandungen des Auftraggebers</div>
                <div id="maengelRows"></div>
                <button class="btn btns" id="maengelAdd" type="button" style="margin-top:8px">
                    <svg class="i"><use href="#ic-plus"/></svg>Beanstandung erfassen</button>
                <p class="apr-legal">Die aufgeführten Mängel sind bis zum jeweils genannten Termin nachzubessern.
                    Der Auftraggeber behält sich seine Rechte aus § 634 BGB ausdrücklich vor. Ein Zurückbehaltungsrecht
                    in Höhe des doppelten Mängelbeseitigungsaufwands bleibt unberührt.</p>
            </div>

            {{-- 5 · Übergabe & Einweisung --}}
            <div class="apr-sec">
                <div class="apr-h"><svg class="i"><use href="#ic-doc"/></svg>Übergabe &amp; Einweisung</div>
                @foreach ([
                    ['einweisung', 'Einweisung in Bedienung von Beleuchtung, Markise und Schiebe-Elementen erfolgt'],
                    ['pflege', 'Pflege- und Wartungshinweise sowie Reinigungsanleitung ausgehändigt'],
                    ['unterlagen', 'Konformitätserklärung, Statik und Bedienungsanleitungen übergeben'],
                    ['baustelle', 'Montagestelle gereinigt, Verpackungs- und Restmaterial abgeführt'],
                ] as [$key, $label])
                    <label class="apr-opt on" data-apr-check>
                        <input type="checkbox" name="checkliste[{{ $key }}]" value="1" checked hidden>
                        <span class="apr-bx"></span>
                        <span class="apr-ol">{{ $label }}</span>
                    </label>
                @endforeach
                <p class="apr-legal">Mit der Abnahme geht die Gefahr auf den Auftraggeber über. Die Verjährungsfrist
                    für Mängelansprüche beginnt am Tag der Abnahme und beträgt fünf Jahre gemäß § 634a Abs. 1 Nr. 2 BGB.
                    Die Schlussrechnung wird nach Abnahme gestellt und ist gemäß Zahlungsplan fällig.</p>
            </div>

            {{-- 6 · Ort, Datum und Unterschriften --}}
            <div class="apr-sec">
                <div class="apr-h"><svg class="i"><use href="#ic-pen"/></svg>Ort, Datum und Unterschriften</div>
                <div class="apr-2">
                    <div class="apr-kv"><span class="k">Ort</span>
                        <input class="inp" name="ort" value="{{ old('ort', $projekt->objekt_stadt ?? '') }}" style="max-width:220px"></div>
                    <div class="apr-kv"><span class="k">Datum</span><span class="v mono">{{ now()->format('d.m.Y') }}</span></div>
                </div>
                <div class="apr-sigs">
                    <div>
                        <div class="apr-sigl">Auftraggeber</div>
                        <div class="sigpad" style="height:158px" data-sigpad data-sigpad-input="#sigK" data-sigpad-clear="#sigKClear">
                            <canvas></canvas>
                            <span class="sigbase"></span>
                            <span class="sigx">✕ Unterschrift</span>
                            <span class="sigph">Hier unterschreiben</span>
                        </div>
                        <div class="jb" style="margin-top:6px">
                            <span class="apr-signame">{{ $kunde->anzeigename }}</span>
                            <button class="btn btns btng" id="sigKClear" type="button">Löschen</button>
                        </div>
                        <input type="hidden" name="sig_auftraggeber" id="sigK">
                    </div>
                    <div>
                        <div class="apr-sigl">Auftragnehmer · Monteur</div>
                        <div class="sigpad" style="height:158px" data-sigpad data-sigpad-input="#sigM" data-sigpad-clear="#sigMClear">
                            <canvas></canvas>
                            <span class="sigbase"></span>
                            <span class="sigx">✕ Unterschrift</span>
                            <span class="sigph">Hier unterschreiben</span>
                        </div>
                        <div class="jb" style="margin-top:6px">
                            <span class="apr-signame">{{ config('lea.monteur') }}</span>
                            <button class="btn btns btng" id="sigMClear" type="button">Löschen</button>
                        </div>
                        <input type="hidden" name="sig_monteur" id="sigM">
                    </div>
                </div>
            </div>

            <div class="mfoot">
                <span class="hint">Beide Parteien unterschreiben, dann bestätigen.</span>
                <span class="ctas">
                    <a class="btn btns" href="{{ route('projekte.montage', $projekt) }}">Abbrechen</a>
                    <button class="btn btns btnp" type="submit" data-apr-cta>Abnahme bestätigen</button>
                </span>
            </div>
        </form>
    </div>
</div>
@endsection
