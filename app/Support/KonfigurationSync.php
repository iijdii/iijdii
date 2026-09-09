<?php

namespace App\Support;

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

        $pcfg = ($dach->felder ?? []) + ['product' => $dach->produkt->konfiguratorProdukt()];

        $projekt->forceFill(['konfiguration' => KonfiguratorRechner::merge($pcfg)])->saveQuietly();
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
