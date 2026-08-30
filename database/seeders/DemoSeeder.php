<?php

namespace Database\Seeders;

use App\Models\Anfrage;
use App\Models\Angebot;
use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\Kunde;
use App\Models\Lagerbewegung;
use App\Models\Lieferant;
use App\Models\Projekt;
use App\Models\Reservierung;
use App\Models\User;
use App\Models\Wareneingang;
use Illuminate\Database\Seeder;

/**
 * Demodaten aus dem Prototyp: Kunden, Anfragen, Angebote, Projekte,
 * Bestellungen mit Positionen, Reservierungen, Lagerbewegungen und
 * die zwei bereits gebuchten Wareneingänge.
 */
class DemoSeeder extends Seeder
{
    /** Anfrage-Statusschlüssel des Prototyps → AnfrageStatus-Werte. */
    private const ANFRAGE_STATUS = [
        'neu' => 'neu',
        'bearb' => 'in_bearbeitung',
        'aufmass' => 'termin_vereinbart',
        'angebot' => 'angebot_erstellt',
    ];

    private const ANGEBOT_STATUS = [
        'Angenommen' => 'angenommen',
        'Versendet' => 'versendet',
        'Entwurf' => 'entwurf',
        'Abgelehnt' => 'abgelehnt',
    ];

    private const PROJEKT_STATUS = [
        'In Planung' => 'in_planung',
        'In Montageplanung' => 'in_montageplanung',
        'In Montage' => 'in_montage',
    ];

    public function run(): void
    {
        $kunden = $this->seedKunden();
        $anfragen = $this->seedAnfragen($kunden);
        $this->seedAngebote($kunden, $anfragen);
        $projekte = $this->seedProjekte($kunden);
        $this->seedBestellungen($kunden, $projekte);
        $this->seedReservierungen($projekte);
        $this->seedBewegungen();
        $this->seedDokumente($projekte);
        $this->seedProjektAktivitaeten($projekte);
    }

    private function data(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/data/'.$name.'.json'), true);
    }

    /** '15.07.2026' | '15.07.' | '–' → ISO-Datum oder null (Jahr 2026). */
    private function datum(?string $d): ?string
    {
        if (! $d || ! preg_match('/^(\d{2})\.(\d{2})\.(\d{4})?/', $d, $m)) {
            return null;
        }

        return ($m[3] ?? '2026').'-'.$m[2].'-'.$m[1];
    }

    /** '6.000 mm' → 6000, '–' → null. */
    private function mm(?string $v): ?int
    {
        $n = (int) preg_replace('/[^\d]/', '', (string) $v);

        return $n > 0 ? $n : null;
    }

    /** @return array<string, Kunde> Anzeigename → Kunde */
    private function seedKunden(): array
    {
        $cfg = $this->data('kunden');
        $out = [];

        foreach ($this->data('clients') as [$nr, $name, $typ, , , $status]) {
            $g = $cfg[$nr] ?? [];
            $gewerbe = $typ === 'Gewerbe';
            [$strasse, $hausnummer] = $this->strasseHausnummer($g['street'] ?? null);

            $out[$name] = Kunde::query()->updateOrCreate(['kunden_nr' => $nr], [
                'typ' => $gewerbe ? 'gewerbe' : 'privat',
                'anzeigename' => $name,
                'firma' => $gewerbe ? $name : null,
                'ansprechpartner' => $g['contact'] ?? null,
                'email' => $g['email'] ?? null,
                'telefon' => $g['phone'] ?? null,
                'strasse' => $strasse,
                'hausnummer' => $hausnummer,
                'plz' => $g['plz'] ?? null,
                'stadt' => $g['ort2'] ?? null,
                'region' => $g['region'] ?? null,
                'quelle' => $g['quelle'] ?? null,
                'tags' => $g['tags'] ?? [],
                'notizen' => ($g['notes'] ?? '') !== '' ? $g['notes'] : null,
                'status' => $status,
            ]);
        }

        return $out;
    }

    /** 'Musterstraße 12' → ['Musterstraße', '12'] */
    private function strasseHausnummer(?string $street): array
    {
        if (! $street) {
            return [null, null];
        }
        if (preg_match('/^(.*?)\s+(\d+\S*)$/u', $street, $m)) {
            return [$m[1], $m[2]];
        }

        return [$street, null];
    }

    /** @return array<string, Anfrage> Nummer → Anfrage */
    private function seedAnfragen(array $kunden): array
    {
        $cfg = $this->data('anfrageCfg');
        $verkaeufer = User::query()->where('email', 'verkauf@lea.test')->first();
        $out = [];

        foreach ($this->data('anfragen') as [$nr, $kundeName, $ort, $produkt, $statusKey, $quelle, , , $eingang]) {
            // Prototyp nennt bei ANF-2026-012 "DEMO Demo" — im Kundenstamm K-1071.
            $kunde = $kunden[$kundeName]
                ?? $kunden[str_replace(['Familie ', 'Hausverwaltung '], ['', 'HV '], $kundeName)]
                ?? Kunde::query()->where('anzeigename', 'like', '%'.last(explode(' ', $kundeName)).'%')->first();
            if (! $kunde) {
                continue;
            }
            $g = $cfg[$nr] ?? [];
            [$strasse, $hausnummer] = $this->strasseHausnummer($g['street'] ?? null);

            $out[$nr] = Anfrage::query()->updateOrCreate(['nummer' => $nr], [
                'erstellt_von' => $verkaeufer?->id,
                'kunde_id' => $kunde->id,
                'kunden_vorname' => $kunde->vorname,
                'kunden_nachname' => $kunde->nachname ?? $kunde->anzeigename,
                'kunden_telefon' => $g['phone'] ?? $kunde->telefon,
                'kunden_email' => $g['email'] ?? $kunde->email,
                'objekt_strasse' => $strasse,
                'objekt_hausnummer' => $hausnummer,
                'objekt_plz' => $g['plz'] ?? null,
                'objekt_stadt' => $g['ort2'] ?? $ort,
                'besuchstermin_datum' => $this->datum($g['terminDate'] ?? null),
                'besuchstermin_status' => isset($g['terminDate']) && $g['terminDate'] !== '–' ? 'geplant' : null,
                'status' => self::ANFRAGE_STATUS[$statusKey] ?? 'neu',
                'interessierte_produkte' => $g['produkte'] ?? [$produkt],
                'produkt_notiz' => $produkt,
                'breite_cm' => $this->mm($g['breite'] ?? null) !== null ? intdiv($this->mm($g['breite']), 10) : null,
                'tiefe_cm' => $this->mm($g['tiefe'] ?? null) !== null ? intdiv($this->mm($g['tiefe']), 10) : null,
                'form' => isset($g['form']) ? mb_strtolower($g['form']) : null,
                'dachneigung_grad' => isset($g['neigung']) ? (int) $g['neigung'] : null,
                'profil_farbe_name' => $g['farbe'] ?? null,
                'befestigung_art' => match ($g['montage'] ?? null) {
                    'Wandmontage' => 'wandmontage',
                    'Freistehend' => 'freistehend',
                    default => null,
                },
                'anfrage_quelle' => mb_strtolower(str_replace(' ', '_', $quelle)),
                'details' => $g ?: null,
                'created_at' => $this->datum(str_replace('Eingang ', '', $eingang).'2026'),
            ]);
        }

        return $out;
    }

    private function seedAngebote(array $kunden, array $anfragen): void
    {
        // Angebot → Quellanfrage laut Prototyp (Kundenzuordnung).
        $anfrageVon = [
            'ANG-2026-010' => 'ANF-2026-012',
            'ANG-2026-069' => 'ANF-2026-014',
            'ANG-2026-070' => 'ANF-2026-011',
        ];

        foreach ($this->data('angebote') as [$nr, $kundeName, $summe, $datum, $status]) {
            $kunde = $kunden[$kundeName] ?? null;
            if (! $kunde) {
                continue;
            }

            Angebot::query()->updateOrCreate(['nr' => $nr], [
                'kunde_id' => $kunde->id,
                'anfrage_id' => isset($anfrageVon[$nr]) ? ($anfragen[$anfrageVon[$nr]]->id ?? null) : null,
                'status' => self::ANGEBOT_STATUS[$status] ?? 'entwurf',
                'datum' => $this->datum($datum ? $datum.'2026' : null),
                'summe' => (float) str_replace(['.', ',', ' €'], ['', '.', ''], $summe),
            ]);
        }
    }

    /** @return array<string, Projekt> Nr → Projekt */
    private function seedProjekte(array $kunden): array
    {
        $out = [];

        foreach ($this->data('projekte') as [$nr, $kundeName, $titel, $status, , $zeit]) {
            // Prototyp mischt "P-2026-038" und "PRJ-2026-038" — kanonisch PRJ-.
            $nr = preg_replace('/^P-/', 'PRJ-', $nr);
            $kunde = $kunden[$kundeName]
                ?? $kunden[str_replace('Hausverwaltung ', 'HV ', $kundeName)]
                ?? null;
            if (! $kunde) {
                continue;
            }

            $termine = [null, null];
            if (preg_match('/^(\d{2}\.\d{2}\.)–(\d{2}\.\d{2}\.)$/u', $zeit, $m)) {
                $termine = [$this->datum($m[1].'2026'), $this->datum($m[2].'2026')];
            }

            $konfiguration = null;
            if ($nr === 'PRJ-2026-011') {
                // Konfigurator-Payload des Demo-Projekts aus dem Prototyp.
                $konfiguration = [
                    'product' => 'Überdachung', 'shape' => 'trapez',
                    'mounting' => 'an der Wand', 'width' => 8630, 'depth' => 3500,
                    'wallH' => 2900, 'gutterH' => 2500, 'slope' => 8,
                    'color' => 'Weiß · RAL 9016', 'covering' => 'VSG-Glas',
                    'thickness' => '8 mm', 'postN' => 4, 'ledN' => 8,
                    'extras' => ['Keile'],
                ];
            }

            $out[$nr] = Projekt::query()->updateOrCreate(['nr' => $nr], [
                'titel' => $titel.' '.$kunde->anzeigename,
                'kunde_id' => $kunde->id,
                'objekt_strasse' => $kunde->strasse,
                'objekt_hausnummer' => $kunde->hausnummer,
                'objekt_plz' => $kunde->plz,
                'objekt_stadt' => $kunde->stadt,
                'status' => self::PROJEKT_STATUS[$status] ?? 'in_planung',
                'termin_von' => $termine[0],
                'termin_bis' => $termine[1],
                'konfiguration' => $konfiguration,
            ]);
        }

        // Angebot ANG-2026-010 gehört zum Demo-Projekt.
        if (isset($out['PRJ-2026-011'])) {
            $angebot = Angebot::query()->where('nr', 'ANG-2026-010')->first();
            $out['PRJ-2026-011']->update(['angebot_id' => $angebot?->id]);
        }

        return $out;
    }

    private function seedBestellungen(array $kunden, array $projekte): void
    {
        $lieferanten = Lieferant::query()->pluck('id', 'name');
        $lager = User::query()->where('email', 'lager@lea.test')->first();

        // Bereits gebuchte Wareneingänge laut Prototyp (_liefState).
        $gebucht = [
            'BST-2026-112' => ['datum' => '2026-07-15', 'ls' => 'LS-88214'],
            'BST-2026-108' => ['datum' => '2026-06-22', 'ls' => 'LS-87903'],
        ];

        foreach ($this->data('bestellungen') as $o) {
            $bestellung = Bestellung::query()->updateOrCreate(['nr' => $o['nr']], [
                'lieferant_id' => $lieferanten[$o['lief']],
                'titel' => $o['title'],
                'kategorie' => $o['cat'],
                'projekt_id' => $projekte[$o['projekt']]->id ?? null,
                'kunde_id' => ($kunden[$o['kunde']] ?? $kunden['Hausverwaltung Nord'] ?? null)?->id,
                'ersteller_id' => User::query()->where('name', $o['ersteller'])->first()?->id,
                'liefertermin' => $this->datum(str_ends_with($o['termin'], '.') ? $o['termin'].'2026' : $o['termin']),
                'status' => $o['stat'],
                'notizen' => $o['notes'],
                'created_at' => $this->datum($o['erstellt']),
            ]);

            // Reihenfolge wegen FK: erst Wareneingangs-, dann Bestellpositionen.
            foreach ($bestellung->wareneingaenge as $we) {
                $we->positionen()->delete();
            }
            $bestellung->positionen()->delete();
            $geliefert = isset($gebucht[$o['nr']]);
            $pos = 0;

            foreach ($o['glas'] as $g) {
                $bestellung->positionen()->create([
                    'typ' => 'glas',
                    'pos' => ++$pos,
                    'bezeichnung' => $g['name'],
                    'artikel_id' => Artikel::findeNachName($g['glas'])?->id,
                    'menge' => $g['menge'],
                    'einheit' => 'Feld',
                    'breite_mm' => $g['w'],
                    'hoehe_mm' => max($g['hL'], $g['hR']),
                    'details' => [
                        'form' => $g['form'], 'hL' => $g['hL'], 'hR' => $g['hR'],
                        'glas' => $g['glas'], 'quelle' => $g['src'],
                    ],
                    'eingelagert' => $geliefert,
                ]);
            }

            foreach ($o['schiebe'] as $s) {
                $bestellung->positionen()->create([
                    'typ' => 'schiebe',
                    'pos' => ++$pos,
                    'bezeichnung' => $s['name'],
                    'artikel_id' => Artikel::findeNachName('Schiebe-Element')?->id,
                    'menge' => $s['count'] ?? 1,
                    'einheit' => 'Stück',
                    'breite_mm' => $s['w'] ?? null,
                    'hoehe_mm' => $s['h'] ?? null,
                    'details' => $s,
                    'eingelagert' => $geliefert,
                ]);
            }

            foreach ($o['mat'] as [$name, $menge]) {
                $artikel = Artikel::findeNachName($name);
                $bestellung->positionen()->create([
                    'typ' => 'material',
                    'pos' => ++$pos,
                    'bezeichnung' => $name,
                    'artikel_id' => $artikel?->id,
                    'menge' => $menge,
                    'einheit' => $artikel?->einheit->value ?? 'Stück',
                    'eingelagert' => $geliefert,
                ]);
            }

            if ($geliefert) {
                $wareneingang = Wareneingang::query()->updateOrCreate(
                    ['bestellung_id' => $bestellung->id, 'lieferschein_nr' => $gebucht[$o['nr']]['ls']],
                    ['datum' => $gebucht[$o['nr']]['datum'], 'benutzer_id' => $lager?->id],
                );
                $wareneingang->positionen()->delete();
                foreach ($bestellung->positionen as $position) {
                    if ($position->artikel_id) {
                        $wareneingang->positionen()->create([
                            'artikel_id' => $position->artikel_id,
                            'bestellung_position_id' => $position->id,
                            'menge' => $position->menge,
                        ]);
                    }
                }
            }
        }
    }

    private function seedReservierungen(array $projekte): void
    {
        foreach ($this->data('reservierungen') as [$projektNr, , $artNr, $menge]) {
            $projekt = $projekte[$projektNr] ?? null;
            $artikel = Artikel::query()->where('art_nr', $artNr)->first();
            if (! $projekt || ! $artikel) {
                continue;
            }

            Reservierung::query()->updateOrCreate(
                ['projekt_id' => $projekt->id, 'artikel_id' => $artikel->id],
                ['menge' => $menge],
            );
        }
    }

    /** Projektdokumente des Demo-Projekts (Prototyp, Dokumente-Tab). */
    private function seedDokumente(array $projekte): void
    {
        $projekt = $projekte['PRJ-2026-011'] ?? null;
        if (! $projekt) {
            return;
        }

        $dateien = [
            ['angebot', 'Angebot_ANG-2026-010.pdf', 245 * 1024, '2026-05-12'],
            ['zeichnung', 'Zeichnung_PRJ-2026-011.dwg', 1228 * 1024, '2026-05-12'],
            ['materialliste', 'Materialliste_PRJ-2026-011.xlsx', 98 * 1024, '2026-05-14'],
            ['statik', 'Statik-Nachweis.pdf', 512 * 1024, '2026-05-15'],
            ['aufmass', 'Aufmassprotokoll_05-05.pdf', 176 * 1024, '2026-05-05'],
            ['auftrag', 'Auftragsbestaetigung.pdf', 88 * 1024, '2026-05-13'],
        ];

        foreach ($dateien as [$typ, $name, $groesse, $datum]) {
            $projekt->dokumente()->updateOrCreate(
                ['dateiname' => $name],
                ['typ' => $typ, 'groesse' => $groesse, 'datum' => $datum],
            );
        }
    }

    /** Projektverlauf des Demo-Projekts (Prototyp, Aktivität-Tab). */
    private function seedProjektAktivitaeten(array $projekte): void
    {
        $projekt = $projekte['PRJ-2026-011'] ?? null;
        if (! $projekt || $projekt->aktivitaeten()->exists()) {
            return;
        }

        $eintraege = [
            ['Aufmaß abgeschlossen', 'Max Schneider', '05.05.', 'done'],
            ['Angebot versendet', 'Max Schneider', '12.05.', 'done'],
            ['Auftrag bestätigt', 'DEMO Demo', '13.05.', 'done'],
            ['Anzahlung eingegangen', 'Buchhaltung', '14.05.', 'done'],
            ['Produktion gestartet', 'Fertigungsteam', '26.05.', 'done'],
            ['Glasbestellung ausgelöst', 'Einkauf', '28.05.', 'done'],
            ['Lieferung geplant', 'Logistik', '15.07.', 'now'],
            ['In Planung', 'Montageteam', '18.07.', null],
        ];

        foreach ($eintraege as [$titel, $wer, $datum, $status]) {
            $projekt->aktivitaeten()->create([
                'titel' => $titel, 'wer' => $wer, 'datum' => $datum, 'status' => $status,
            ]);
        }
    }

    private function seedBewegungen(): void
    {
        if (Lagerbewegung::query()->exists()) {
            return;
        }

        foreach ($this->data('bewegungen') as [$datum, $typ, , $artNr, $menge, $referenz, $benutzer]) {
            $artikel = Artikel::query()->where('art_nr', $artNr)->first();
            if (! $artikel) {
                continue;
            }

            Lagerbewegung::query()->create([
                'datum' => $this->datum($datum),
                'typ' => $typ,
                'artikel_id' => $artikel->id,
                // Prototyp nutzt U+2212 als Minuszeichen
                'menge' => (int) str_replace('−', '-', $menge),
                'referenz' => $referenz,
                'benutzer_name' => $benutzer,
            ]);
        }
    }
}
