<?php

namespace App\Enums;

/**
 * Status einer Lieferantenbestellung, in Reihenfolge:
 * entwurf → geprueft → bestellt → bereit → geliefert → montiert,
 * plus storniert. Der Lieferant sieht ab «geprüft» (Portal) und meldet
 * selbst «in Arbeit» (bestellt) und «abholbereit» (bereit).
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

    public function label(): string
    {
        return match ($this) {
            self::Entwurf => 'Entwurf',
            self::Geprueft => 'Geprüft',
            self::Bestellt => 'Bestellt',
            self::Bereit => 'Bereit',
            self::Geliefert => 'Geliefert',
            self::Montiert => 'Montiert',
            self::Storniert => 'Storniert',
        };
    }

    /** Bezeichnung aus Sicht des Lieferanten (Portal). */
    public function portalLabel(): string
    {
        return match ($this) {
            self::Geprueft => 'Neu',
            self::Bestellt => 'In Arbeit',
            self::Bereit => 'Abholbereit',
            self::Geliefert => 'Abgeholt / geliefert',
            self::Montiert => 'Abgeschlossen',
            default => $this->label(),
        };
    }

    public function anzeige(bool $portal): string
    {
        return $portal ? $this->portalLabel() : $this->label();
    }

    /**
     * Portal-Übergänge: aus Neu / In Arbeit / Abholbereit darf der
     * Lieferant «In Arbeit» oder «Abholbereit» melden (auch zurück,
     * falls er sich vertan hat) — alles Weitere pflegt LEA.
     */
    public function portalDarf(self $ziel): bool
    {
        return $this !== $ziel
            && in_array($this, [self::Geprueft, self::Bestellt, self::Bereit], true)
            && in_array($ziel, [self::Bestellt, self::Bereit], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Entwurf => 'b-gray',
            self::Geprueft, self::Bestellt => 'b-blue',
            self::Bereit => 'b-yellow',
            self::Geliefert, self::Montiert => 'b-green',
            self::Storniert => 'b-red',
        };
    }

    public function stepClass(): string
    {
        return match ($this) {
            self::Entwurf => 's-gray',
            self::Geprueft, self::Bestellt => 's-blue',
            self::Bereit => 's-yellow',
            self::Geliefert, self::Montiert => 's-green',
            self::Storniert => 's-red',
        };
    }

    /** Akzentklasse der Bestellkarte. */
    public function accentClass(): string
    {
        return match ($this) {
            self::Entwurf => 'ac-gray',
            self::Geprueft, self::Bestellt => 'ac-blue',
            self::Bereit => 'ac-yellow',
            self::Geliefert, self::Montiert => 'ac-green',
            self::Storniert => 'ac-red',
        };
    }
}
