<?php

namespace App\Enums;

enum Rolle: string
{
    case Admin = 'admin';
    case Verkaeufer = 'verkaeufer';
    case Projektleiter = 'projektleiter';
    case Lager = 'lager';
    case Monteur = 'monteur';
    case Lieferant = 'lieferant';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Verkaeufer => 'Verkäufer',
            self::Projektleiter => 'Projektleiter',
            self::Lager => 'Lager',
            self::Monteur => 'Monteur',
            self::Lieferant => 'Lieferant (Portal)',
        };
    }
}
