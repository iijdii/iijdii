<?php

namespace App\Support;

/**
 * Normalisiert Freitext-Artikelnamen für den Alias-Abgleich —
 * identisch zur norm()-Funktion des Prototyps:
 * Kleinschreibung, '×' → 'x', Whitespace kollabieren, trimmen.
 */
class AliasNormalizer
{
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace('×', 'x', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }
}
