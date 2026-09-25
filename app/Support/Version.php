<?php

namespace App\Support;

/**
 * Sichtbarer Patch-Stand der Installation (Fuß der Sidebar, Login,
 * /einrichtung). Wird mit jedem Server-Patch hochgezählt — so ist auf
 * einen Blick klar, welcher Stand auf dem Server wirklich läuft.
 */
final class Version
{
    public const PATCH = 'v60';
}
