<?php

namespace App\Enums;

/**
 * Status einer Lieferantenbestellung, in Reihenfolge:
 * entwurf → geprueft → bestellt → bereit → geliefert → montiert,
 * plus storniert.
 */
enum BestellungStatus: string
{
    case Entwurf = 'entwurf';
    case Geprueft = 'geprueft';
    case Bestellt = 'bestellt';
    case Bereit = 'bereit';
    case Geliefert = 'geliefert';
    case Montiert = 'montiert';
    case Storniert = 'storniert';

    public function label(): string
    {
        return match ($this) {
            self::Entwurf => 'Entwurf',
            self::Geprueft => 'Geprüft',
            self::Bestellt => 'Bestellt',
            self::Bereit => 'Bereit',
            self::Geliefert => 'Geliefert',
            self::Montiert => 'Montiert',
            self::Storniert => 'Storniert',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Entwurf => 'b-gray',
            self::Geprueft, self::Bestellt => 'b-blue',
            self::Bereit => 'b-yellow',
            self::Geliefert, self::Montiert => 'b-green',
            self::Storniert => 'b-red',
        };
    }

    public function stepClass(): string
    {
        return match ($this) {
            self::Entwurf => 's-gray',
            self::Geprueft, self::Bestellt => 's-blue',
            self::Bereit => 's-yellow',
            self::Geliefert, self::Montiert => 's-green',
            self::Storniert => 's-red',
        };
    }

    /** Akzentklasse der Bestellkarte. */
    public function accentClass(): string
    {
        return match ($this) {
            self::Entwurf => 'ac-gray',
            self::Geprueft, self::Bestellt => 'ac-blue',
            self::Bereit => 'ac-yellow',
            self::Geliefert, self::Montiert => 'ac-green',
            self::Storniert => 'ac-red',
        };
    }
}
