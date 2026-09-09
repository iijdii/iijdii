<?php

namespace App\Enums;

/**
 * Bestellbare Produkte einer Projekt-Position, in drei Gruppen
 * (Betreiber-Vorgabe): Dachprodukte (genau eine Position pro Projekt,
 * gemeinsamer Feldsatz = pcfg), Extras und Sonnenschutz (eigene Feldsätze,
 * Endmaße nach der Dachmontage — Phase 2).
 */
enum ProjektProdukt: string
{
    case Ueberdachung = 'ueberdachung';
    case Carport = 'carport';
    case Vordach = 'vordach';
    case Kube = 'kube';
    case Wand = 'wand';
    case Schiebe = 'schiebe';
    case Keil = 'keil';
    case Gelaender = 'gelaender';
    case Markise = 'markise';
    case Sonnensegel = 'sonnensegel';

    public function label(): string
    {
        return match ($this) {
            self::Ueberdachung => 'Überdachung',
            self::Carport => 'Carport',
            self::Vordach => 'Vordach',
            self::Kube => 'Kube',
            self::Wand => 'Wand / Festelement',
            self::Schiebe => 'Schiebe-Elemente',
            self::Keil => 'Keile',
            self::Gelaender => 'Geländer',
            self::Markise => 'Markise',
            self::Sonnensegel => 'Sonnensegel',
        };
    }

    public function gruppe(): string
    {
        return match ($this) {
            self::Ueberdachung, self::Carport, self::Vordach, self::Kube => 'dach',
            self::Wand, self::Schiebe, self::Keil, self::Gelaender => 'extra',
            self::Markise, self::Sonnensegel => 'sonnenschutz',
        };
    }

    public function gruppeLabel(): string
    {
        return match ($this->gruppe()) {
            'dach' => 'Überdachungen',
            'extra' => 'Extras',
            'sonnenschutz' => 'Sonnenschutz',
        };
    }

    public function istDach(): bool
    {
        return $this->gruppe() === 'dach';
    }

    /** Montagephase: Dach = 1, Extras/Sonnenschutz = 2 (nach Endmaßen). */
    public function phase(): int
    {
        return $this->istDach() ? 1 : 2;
    }

    /** Konfigurator-Produktname für den Rechenkern. */
    public function konfiguratorProdukt(): string
    {
        return match ($this) {
            self::Carport => 'Carport',
            self::Vordach => 'Vordach',
            self::Kube => 'Kube',
            default => 'Überdachung',
        };
    }

    /** @return array<string, list<self>> Gruppen → Produkte (für optgroup-Selects). */
    public static function nachGruppen(): array
    {
        $gruppen = [];
        foreach (self::cases() as $produkt) {
            $gruppen[$produkt->gruppeLabel()][] = $produkt;
        }

        return $gruppen;
    }
}
