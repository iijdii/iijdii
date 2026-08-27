<?php

namespace App\Enums;

enum ArtikelKategorie: string
{
    case Profile = 'profile';
    case Glas = 'glas';
    case Zubehoer = 'zubehoer';
    case Dicht = 'dicht';
    case Verbind = 'verbind';
    case Elektro = 'elektro';
    case Verbrauch = 'verbrauch';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Profile & Bauteile',
            self::Glas => 'Glas',
            self::Zubehoer => 'Zubehör & Schiebe',
            self::Dicht => 'Dichtungen',
            self::Verbind => 'Verbindungsmittel',
            self::Elektro => 'Elektro & LED',
            self::Verbrauch => 'Verbrauchsmaterial',
        };
    }
}
