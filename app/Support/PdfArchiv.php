<?php

namespace App\Support;

use App\Models\Dokument;
use App\Models\Kunde;
use App\Models\Projekt;
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
    /**
     * «_Nachname»-Suffix für PDF-Dateinamen (Firmenkunden: Firma).
     * Umlaute werden transliteriert und alles außer [A-Za-z0-9-]
     * entfernt — Dateiname bleibt Header- und Hosting-sicher.
     */
    public static function kundenTeil(?Kunde $kunde): string
    {
        $name = trim((string) ($kunde?->nachname ?: $kunde?->firma ?: $kunde?->anzeigename));
        if ($name === '') {
            return '';
        }
        $name = strtr($name, ['Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $name = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-');

        return $name === '' ? '' : '_'.$name;
    }

    public static function liefere(
        string $view,
        array $daten,
        string $dateinameBasis,
        ?Projekt $projekt,
        string $typ,
        ?string $badge = null,
        ?Kunde $kunde = null,
    ): Response {
        $dateiname = $dateinameBasis.self::kundenTeil($kunde).'.pdf';
        $bytes = Pdf::loadView($view, $daten)->output();

        if ($projekt !== null) {
            // Ältere Ablagen desselben Dokuments (z. B. noch ohne
            // Kunden-Suffix) ersetzen, damit die Projektmappe keine
            // Duplikate sammelt.
            $veraltete = Dokument::query()
                ->where('projekt_id', $projekt->id)
                ->where('dateiname', 'like', $dateinameBasis.'%')
                ->where('dateiname', '!=', $dateiname)
                ->get();
            foreach ($veraltete as $veraltet) {
                if ($veraltet->pfad !== null) {
                    Storage::delete($veraltet->pfad);
                }
                $veraltet->delete();
            }

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

        // ?ansicht=1 → inline für die Vorschau im Fenster (iframe),
        // sonst wie bisher als Download.
        $disposition = request()->boolean('ansicht') ? 'inline' : 'attachment';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$dateiname.'"',
        ]);
    }
}
