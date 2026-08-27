<?php

namespace App\Enums;

enum AbnahmeArt: string
{
    case Ohne = 'ohne';
    case Vorbehalt = 'vorbehalt';
    case Verweigert = 'verweigert';
}
