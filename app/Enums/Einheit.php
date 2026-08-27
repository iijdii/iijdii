<?php

namespace App\Enums;

enum Einheit: string
{
    case Stueck = 'Stück';
    case Satz = 'Satz';
    case Feld = 'Feld';
    case Lfm = 'lfm';
    case Rolle = 'Rolle';
    case Kartusche = 'Kart.';
    case Flasche = 'Flasche';
}
