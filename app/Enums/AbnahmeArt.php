<?php

namespace App\Enums;

enum AbnahmeArt: string
{
    case Ohne = 'ohne';
    case Vorbehalt = 'vorbehalt';
    case Verweigert = 'verweigert';

    public function label(): string
    {
        return match ($this) {
            self::Ohne => 'Abgenommen',
            self::Vorbehalt => 'Unter Vorbehalt',
            self::Verweigert => 'Verweigert',
        };
    }
}
