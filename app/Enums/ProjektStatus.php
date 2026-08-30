<?php

namespace App\Enums;

enum ProjektStatus: string
{
    case InPlanung = 'in_planung';
    case InMontageplanung = 'in_montageplanung';
    case InMontage = 'in_montage';
    case Abgeschlossen = 'abgeschlossen';

    public function label(): string
    {
        return match ($this) {
            self::InPlanung => 'In Planung',
            self::InMontageplanung => 'In Montageplanung',
            self::InMontage => 'In Montage',
            self::Abgeschlossen => 'Abgeschlossen',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::InPlanung, self::InMontageplanung => 'b-blue',
            self::InMontage => 'b-yellow',
            self::Abgeschlossen => 'b-green',
        };
    }
}
