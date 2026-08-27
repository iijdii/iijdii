<?php

namespace App\Enums;

/**
 * Status einer Lieferantenbestellung, in Reihenfolge:
 * entwurf → geprueft → bestellt → bereit → geliefert → montiert,
 * plus storniert.
 */
enum BestellungStatus: string
{
    case Entwurf = 'entwurf';
    case Geprueft = 'geprueft';
    case Bestellt = 'bestellt';
    case Bereit = 'bereit';
    case Geliefert = 'geliefert';
    case Montiert = 'montiert';
    case Storniert = 'storniert';
}
