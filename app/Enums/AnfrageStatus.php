<?php

namespace App\Enums;

/**
 * Status einer Anfrage. Erlaubte Übergänge (docs/anfrage-fields.txt):
 * neu → in_bearbeitung → termin_vereinbart → aufmass_gemacht
 *     → angebot_erstellt → abgeschlossen | abgelehnt;
 * kein_interesse und abgelehnt sind aus jedem Status erreichbar.
 */
enum AnfrageStatus: string
{
    case Neu = 'neu';
    case InBearbeitung = 'in_bearbeitung';
    case TerminVereinbart = 'termin_vereinbart';
    case AufmassGemacht = 'aufmass_gemacht';
    case AngebotErstellt = 'angebot_erstellt';
    case Abgeschlossen = 'abgeschlossen';
    case Abgelehnt = 'abgelehnt';
    case KeinInteresse = 'kein_interesse';

    public function label(): string
    {
        return match ($this) {
            self::Neu => 'Neu',
            self::InBearbeitung => 'In Bearbeitung',
            self::TerminVereinbart, self::AufmassGemacht => 'Aufmaß geplant',
            self::AngebotErstellt, self::Abgeschlossen => 'Angebot erstellt',
            self::Abgelehnt => 'Abgelehnt',
            self::KeinInteresse => 'Kein Interesse',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Neu => 'b-gray',
            self::InBearbeitung => 'b-yellow',
            self::TerminVereinbart, self::AufmassGemacht => 'b-blue',
            self::AngebotErstellt, self::Abgeschlossen => 'b-green',
            self::Abgelehnt, self::KeinInteresse => 'b-red',
        };
    }

    /** Zuordnung auf die vier UI-Stufen des Prototyps (1–4, 0 = außerhalb). */
    public function uiStufe(): int
    {
        return match ($this) {
            self::Neu => 1,
            self::InBearbeitung => 2,
            self::TerminVereinbart, self::AufmassGemacht => 3,
            self::AngebotErstellt, self::Abgeschlossen => 4,
            self::Abgelehnt, self::KeinInteresse => 0,
        };
    }
}
