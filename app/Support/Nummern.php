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

    /** K-NNNN — Kundennummern ohne Jahresteil (Seed: K-1031 … K-1071). */
    public static function kunde(): string
    {
        $max = 1000;
        foreach (\App\Models\Kunde::query()->pluck('kunden_nr') as $nummer) {
            if (preg_match('/^K-(\d+)$/', (string) $nummer, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'K-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** TOUR-2026-NNN — Seed endet bei 042, erste neue Tour = TOUR-2026-043. */
    public static function tour(): string
    {
        return self::naechste('TOUR', \App\Models\Tour::query()->pluck('nr'), 3, 42);
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
