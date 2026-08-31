<?php

namespace App\Support;

use App\Models\Abnahmeprotokoll;
use App\Models\Anfrage;
use App\Models\Angebot;
use App\Models\Projekt;

/**
 * Belegnummern-Sequenzen (ANF-/PRJ-/ANG-2026-NNN): max + 1, PHP-seitig
 * (sqlite-portabel, Einzelnutzer-CRM — analog zur Lieferschein-Sequenz).
 */
final class Nummern
{
    public static function anfrage(): string
    {
        return self::naechste('ANF', Anfrage::query()->pluck('nummer'));
    }

    public static function projekt(): string
    {
        return self::naechste('PRJ', Projekt::query()->pluck('nr'));
    }

    public static function angebot(): string
    {
        return self::naechste('ANG', Angebot::query()->pluck('nr'));
    }

    /** AP-2026-NNNN — Start 113, damit das erste Protokoll AP-2026-0114 ist (Prototyp-Nummer). */
    public static function abnahme(): string
    {
        return self::naechste('AP', Abnahmeprotokoll::query()->pluck('nr'), 4, 113);
    }

    private static function naechste(string $prefix, iterable $nummern, int $pad = 3, int $start = 0): string
    {
        $max = $start;
        foreach ($nummern as $nummer) {
            if (preg_match('/^'.$prefix.'-2026-(\d+)$/', (string) $nummer, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $prefix.'-2026-'.str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT);
    }
}
