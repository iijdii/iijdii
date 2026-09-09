<?php

namespace App\Support;

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
}
