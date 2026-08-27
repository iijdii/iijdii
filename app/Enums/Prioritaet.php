<?php

namespace App\Enums;

enum Prioritaet: string
{
    case Niedrig = 'niedrig';
    case Normal = 'normal';
    case Hoch = 'hoch';
    case Dringend = 'dringend';
}
