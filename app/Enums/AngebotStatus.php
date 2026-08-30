<?php

namespace App\Enums;

enum AngebotStatus: string
{
    case Entwurf = 'entwurf';
    case Versendet = 'versendet';
    case Angenommen = 'angenommen';
    case Abgelehnt = 'abgelehnt';

    public function label(): string
    {
        return match ($this) {
            self::Entwurf => 'Entwurf',
            self::Versendet => 'Versendet',
            self::Angenommen => 'Angenommen',
            self::Abgelehnt => 'Abgelehnt',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Entwurf => 'b-gray',
            self::Versendet => 'b-yellow',
            self::Angenommen => 'b-green',
            self::Abgelehnt => 'b-red',
        };
    }
}
