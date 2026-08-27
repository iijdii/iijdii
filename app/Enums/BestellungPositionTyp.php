<?php

namespace App\Enums;

enum BestellungPositionTyp: string
{
    case Glas = 'glas';
    case Schiebe = 'schiebe';
    case Material = 'material';
}
