<?php

namespace App\Support;

use App\Models\Dokument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Gemeinsamer PDF-Weg (Muster: AbnahmeController): View → dompdf →
 * Download; bei verknüpftem Projekt zusätzlich Ablage auf dem privaten
 * Disk + idempotente Dokument-Zeile (updateOrCreate über dateiname —
 * heilt auch die geseedeten Platzhalter-Zeilen mit pfad = null).
 */
final class PdfArchiv
{
    public static function liefere(
        string $view,
        array $daten,
        string $dateiname,
        ?\App\Models\Projekt $projekt,
        string $typ,
        ?string $badge = null,
    ): Response {
        $bytes = Pdf::loadView($view, $daten)->output();

        if ($projekt !== null) {
            $pfad = 'dokumente/'.$dateiname;
            Storage::put($pfad, $bytes);
            Dokument::query()->updateOrCreate(
                ['projekt_id' => $projekt->id, 'dateiname' => $dateiname],
                [
                    'typ' => $typ,
                    'pfad' => $pfad,
                    'groesse' => strlen($bytes),
                    'datum' => now()->toDateString(),
                    'badge' => $badge,
                ],
            );
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$dateiname.'"',
        ]);
    }
}
