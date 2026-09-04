<?php

namespace App\Enums;

enum Prioritaet: string
{
    case Niedrig = 'niedrig';
    case Normal = 'normal';
    case Hoch = 'hoch';
    case Dringend = 'dringend';

    public function label(): string
    {
        return match ($this) {
            self::Niedrig => 'Niedrig',
            self::Normal => 'Normal',
            self::Hoch => 'Hoch',
            self::Dringend => 'Dringend',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Niedrig, self::Normal => 'b-gray',
            self::Hoch => 'b-yellow',
            self::Dringend => 'b-red',
        };
    }
}
