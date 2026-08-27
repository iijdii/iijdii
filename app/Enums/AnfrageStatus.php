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
}
