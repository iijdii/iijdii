<?php

namespace App\Support;

use App\Enums\AngebotStatus;
use App\Enums\ProjektProdukt;
use App\Models\Projekt;

/**
 * Spiegelt die Dach-Position eines Projekts nach projekte.konfiguration.
 * Die Dach-Felder sind bewusst im pcfg-Format gehalten, damit alle
 * bestehenden Konsumenten (Rechner, Zeichnungen, Montage, Abnahme)
 * unverändert weiterlesen; dieser Sync ist der einzige Schreiber der
 * konfiguration, sobald Positionen existieren.
 */
final class KonfigurationSync
{
    public static function spiegleDach(Projekt $projekt): void
    {
        $dach = $projekt->positionen()->where('gruppe', 'dach')->orderBy('pos')->first();
        if ($dach === null) {
            return;
        }

        // Extras sind im Einheitssystem eigene Positionen: ohne explizite
        // Alt-Extras in den Dach-Feldern bleibt die Liste leer — sonst
        // brächte merge() die Default-Extras (Keile, Schiebe, Markisen)
        // als Phantom-Positionen ins Angebot.
        $pcfg = ($dach->felder ?? []) + ['product' => $dach->produkt->konfiguratorProdukt(), 'extras' => []];

        $projekt->forceFill(['konfiguration' => KonfiguratorRechner::merge($pcfg)])->saveQuietly();

        // Die Angebotssumme folgt den Positionen, solange das Angebot noch
        // Entwurf ist — versendete/angenommene Summen bleiben unangetastet.
        $angebot = $projekt->angebot()->first();
        if ($angebot !== null && $angebot->status === AngebotStatus::Entwurf) {
            $angebot->setRelation('projekt', $projekt);
            AngebotsRechnung::aktualisiereSumme($angebot);
        }
    }

    /**
     * Konfiguration für Rechner/Angebot: Projekte mit Dach-Position, deren
     * Felder keine Alt-Extras tragen, rechnen ohne pcfg-Extras (bereinigt
     * auch vor dem Sync gespeicherte Konfigurationen).
     */
    public static function bereinigt(Projekt $projekt): ?array
    {
        $konfiguration = $projekt->konfiguration;
        $dach = $projekt->positionen->firstWhere('gruppe', 'dach');
        if ($konfiguration !== null && $dach !== null && ! array_key_exists('extras', $dach->felder ?? [])) {
            $konfiguration['extras'] = [];
        }

        return $konfiguration;
    }

    /**
     * Hebt Legacy-Projekte (nur konfiguration, keine Positionen) ins
     * Einheitssystem: die Dach-Position entsteht aus der Konfiguration,
     * damit Produktpass und Konfigurator-Fenster überall funktionieren.
     */
    public static function ergaenzeDachPosition(Projekt $projekt): void
    {
        if ($projekt->konfiguration === null || $projekt->positionen()->where('gruppe', 'dach')->exists()) {
            return;
        }

        $produktName = $projekt->konfiguration['product'] ?? 'Überdachung';
        $produkt = collect(ProjektProdukt::cases())
            ->first(fn (ProjektProdukt $p) => $p->istDach() && $p->konfiguratorProdukt() === $produktName)
            ?? ProjektProdukt::Ueberdachung;

        $projekt->positionen()->create([
            'pos' => ((int) $projekt->positionen()->max('pos')) + 1,
            'gruppe' => 'dach',
            'produkt' => $produkt,
            'phase' => 1,
            'felder' => $projekt->konfiguration,
        ]);
    }
}
