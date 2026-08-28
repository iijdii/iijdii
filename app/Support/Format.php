<?php

namespace App\Support;

/** Deutsche Zahlen- und Datumsformate der UI. */
final class Format
{
    public static function eur(float|string|null $wert): string
    {
        return number_format((float) $wert, 2, ',', '.').' €';
    }

    public static function eur0(float|string|null $wert): string
    {
        return number_format(round((float) $wert), 0, ',', '.').' €';
    }

    /** Mengen ohne unnötige Nachkommastellen: "8.00" → "8", "2.50" → "2,5". */
    public static function menge(float|string $menge): string
    {
        $f = (float) $menge;

        return $f == (int) $f
            ? (string) (int) $f
            : rtrim(number_format($f, 2, ',', '.'), '0');
    }

    /** Vorzeichenbehaftete Menge mit typografischem Minus (U+2212). */
    public static function mengeSigniert(int $menge): string
    {
        return $menge < 0 ? '−'.abs($menge) : '+'.$menge;
    }

    public static function datum(?\DateTimeInterface $datum): string
    {
        return $datum?->format('d.m.Y') ?? '–';
    }

    /** Kurzdatum "15.07." wie im Prototyp. */
    public static function datumKurz(?\DateTimeInterface $datum): string
    {
        return $datum?->format('d.m.') ?? '–';
    }
}
