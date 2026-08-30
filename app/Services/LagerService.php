<?php

namespace App\Services;

use App\Enums\BestellungPositionTyp;
use App\Enums\BestellungStatus;
use App\Enums\LagerbewegungTyp;
use App\Models\Artikel;
use App\Models\Bestellung;
use App\Models\Lagerbewegung;
use App\Models\User;
use App\Models\Wareneingang;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Zentrale Lagerregel des Handoffs: erreicht eine Bestellung
 * „geliefert", werden ihre Positionen in EINER Transaktion eingelagert —
 * Wareneingang + Positionen, Bestandserhöhung, eine Lagerbewegung pro
 * Artikel, Status und eingelagert-Flags.
 *
 * Buchungen sind idempotent (Kriterium: es existiert bereits ein
 * Wareneingang) und werden bei einem Status-Rückschritt NICHT
 * rückabgewickelt — Bestand, Bewegungen und Beleg bleiben stehen;
 * das manuelle Werkzeug dafür ist eine Korrekturbuchung.
 */
final class LagerService
{
    /**
     * Positionen einer Bestellung zu Artikelmengen aggregieren
     * (Port von _lagerOrderItems): Glas löst über den Glastyp auf,
     * Schiebe immer auf das Schiebe-Element, Material über die
     * Bezeichnung — jeweils via Alias-Abgleich. Nicht auflösbare
     * Zeilen behalten artikel = null und berühren den Bestand nie.
     *
     * @return list<array{artikel: ?Artikel, bezeichnung: string, menge: float, einheit: string, position_id: int}>
     */
    public function aggregierePositionen(Bestellung $bestellung): array
    {
        $gruppen = [];

        foreach ($bestellung->positionen()->orderBy('pos')->get() as $position) {
            $artikel = $position->artikel ?? match ($position->typ) {
                BestellungPositionTyp::Glas => Artikel::findeNachName(
                    $position->details['glas'] ?? $position->bezeichnung
                ),
                BestellungPositionTyp::Schiebe => Artikel::findeNachName('Schiebe-Element'),
                BestellungPositionTyp::Material => Artikel::findeNachName($position->bezeichnung),
            };

            $key = $artikel ? 'A|'.$artikel->id : 'X|'.$position->bezeichnung;

            if (! isset($gruppen[$key])) {
                $gruppen[$key] = [
                    'artikel' => $artikel,
                    'bezeichnung' => $artikel->name ?? $position->bezeichnung,
                    'menge' => 0.0,
                    'einheit' => $artikel->einheit->value ?? ($position->einheit ?: 'Stück'),
                    'position_id' => $position->id,
                ];
            }

            $gruppen[$key]['menge'] += (float) $position->menge;
        }

        return array_values($gruppen);
    }

    public function istGebucht(Bestellung $bestellung): bool
    {
        return $bestellung->wareneingaenge()->exists();
    }

    /**
     * Nächste Lieferschein-Nummer: max über LS-\d+ plus eins.
     * PHP-seitig (sqlite-portabel); nicht racefest — Einzelnutzer-CRM,
     * und die Buchung läuft ohnehin in einer Transaktion.
     */
    public function naechsteLieferscheinNr(): string
    {
        $max = Wareneingang::query()->pluck('lieferschein_nr')
            ->map(fn (string $nr) => preg_match('/^LS-(\d+)$/', $nr, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'LS-'.(max($max, 88214) + 1);
    }

    /**
     * Live-Materialliste eines Projekts (README-Regel): Status, Lagerort
     * und Beleg je Position kommen aus dem Lagerzustand — Geliefert bei
     * gebuchtem Wareneingang, Bestellt bei offener Bestellung, sonst
     * Auf Lager.
     *
     * @return list<array{pos:int,artikel:\App\Models\Artikel,menge:float,status:string,badge:string,beleg:string}>
     */
    public function materialListe(\App\Models\Projekt $projekt): array
    {
        $bestellungen = $projekt->bestellungen()
            ->with(['wareneingaenge.positionen'])
            ->get();

        $zeilen = [];
        $pos = 0;

        foreach ($projekt->reservierungen()->with('artikel')->get() as $reservierung) {
            $artikel = $reservierung->artikel;
            $status = 'Auf Lager';
            $badge = 'b-gray';
            $beleg = '–';

            foreach ($bestellungen as $bestellung) {
                $wareneingang = $bestellung->wareneingaenge
                    ->first(fn ($we) => $we->positionen->contains('artikel_id', $artikel->id));
                if ($wareneingang) {
                    $status = 'Geliefert';
                    $badge = 'b-green';
                    $beleg = $bestellung->nr.' · '.$wareneingang->datum->format('d.m.Y');
                    break;
                }

                $offen = ! in_array($bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Storniert], true)
                    && $this->aggregierePositionen($bestellung) !== []
                    && collect($this->aggregierePositionen($bestellung))->contains(
                        fn ($zeile) => $zeile['artikel']?->id === $artikel->id
                    );
                if ($offen) {
                    $status = 'Bestellt';
                    $badge = 'b-yellow';
                    $beleg = $bestellung->nr.($bestellung->liefertermin ? ' · '.$bestellung->liefertermin->format('d.m.') : '');
                }
            }

            $zeilen[] = [
                'pos' => ++$pos,
                'artikel' => $artikel,
                'menge' => (float) $reservierung->menge,
                'status' => $status,
                'badge' => $badge,
                'beleg' => $beleg,
            ];
        }

        return $zeilen;
    }

    public function bucheWareneingang(Bestellung $bestellung, User $benutzer): Wareneingang
    {
        if ($vorhanden = $bestellung->wareneingaenge()->first()) {
            return $vorhanden;
        }

        if (in_array($bestellung->status, [BestellungStatus::Entwurf, BestellungStatus::Storniert], true)) {
            throw new LogicException(
                'Bestellung '.$bestellung->nr.' ist im Status '.$bestellung->status->value.' und kann nicht eingelagert werden.'
            );
        }

        return DB::transaction(function () use ($bestellung, $benutzer) {
            $wareneingang = $bestellung->wareneingaenge()->create([
                'datum' => now()->toDateString(),
                'benutzer_id' => $benutzer->id,
                'lieferschein_nr' => $this->naechsteLieferscheinNr(),
            ]);

            foreach ($this->aggregierePositionen($bestellung) as $zeile) {
                if (! $zeile['artikel']) {
                    continue;
                }

                $wareneingang->positionen()->create([
                    'artikel_id' => $zeile['artikel']->id,
                    'bestellung_position_id' => $zeile['position_id'],
                    'menge' => $zeile['menge'],
                ]);

                $zeile['artikel']->increment('bestand', (int) $zeile['menge']);

                Lagerbewegung::query()->create([
                    'datum' => now(),
                    'typ' => LagerbewegungTyp::Eingang,
                    'artikel_id' => $zeile['artikel']->id,
                    'menge' => (int) $zeile['menge'],
                    'referenz' => $bestellung->nr,
                    'benutzer_id' => $benutzer->id,
                    'benutzer_name' => $benutzer->name.' · Lager',
                ]);
            }

            if ($bestellung->status !== BestellungStatus::Montiert) {
                $bestellung->update(['status' => BestellungStatus::Geliefert]);
            }
            $bestellung->positionen()->update(['eingelagert' => true]);

            return $wareneingang;
        });
    }
}
