<?php

namespace App\Enums;

enum LagerbewegungTyp: string
{
    case Eingang = 'Eingang';
    case Ausgang = 'Ausgang';
    case Reservierung = 'Reservierung';
    case Korrektur = 'Korrektur';
}
