<?php

namespace App\Http\Controllers;

use App\Enums\AngebotStatus;
use App\Models\Angebot;
use App\Support\AngebotsRechnung;
use App\Support\KonfiguratorRechner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Online-Annahme (Spez. v3.2): öffentliche, dokumentgetreue Ansicht des
 * Angebots ohne Login — Schutz ist die Kenntnis des permanenten
 * accept_token aus dem Angebots-PDF. Der Kunde kann annehmen (mit
 * Bestätigungs-Checkliste), ablehnen oder das Angebot mit Kommentar zur
 * Überarbeitung zurückgeben (Status → Entwurf, Freeze-Regeln unberührt).
 */
class AngebotAnnahmeController extends Controller
{
    public function zeige(string $token): View
    {
        $angebot = $this->angebot($token);
        $konfiguration = $angebot->projekt?->konfiguration ?? $angebot->konfiguration;

        return view('angebote.annahme', [
            'angebot' => $angebot,
            'rechnung' => AngebotsRechnung::fuer($angebot),
            'kalk' => $konfiguration !== null ? KonfiguratorRechner::berechne($konfiguration) : null,
            'abgelaufen' => $this->istAbgelaufen($angebot),
        ]);
    }

    public function antwort(Request $request, string $token): RedirectResponse
    {
        $angebot = $this->angebot($token);
        $zurueck = redirect()->route('angebote.annahme', $token);

        if ($angebot->status === AngebotStatus::Angenommen) {
            return $zurueck;
        }

        $daten = $request->validate([
            'aktion' => ['nullable', 'in:annehmen,ablehnen,ueberarbeitung'],
            'kommentar' => ['nullable', 'string', 'max:2000'],
            'bestaetigung' => ['nullable', 'array'],
        ]);
        $aktion = $daten['aktion'] ?? 'annehmen';
        $kommentar = trim((string) ($daten['kommentar'] ?? ''));

        if ($aktion === 'annehmen') {
            if ($this->istAbgelaufen($angebot) || (float) $angebot->summe <= 0) {
                return $zurueck;
            }
            if (count($daten['bestaetigung'] ?? []) < 4) {
                return $zurueck->with('annahme_fehler', 'Bitte bestätigen Sie alle vier Punkte der Annahme.');
            }
            $angebot->update([
                'status' => AngebotStatus::Angenommen,
                'angenommen_am' => now(),
                'angenommen_ip' => $request->ip(),
                'kunden_kommentar' => $kommentar !== '' ? $kommentar : $angebot->kunden_kommentar,
            ]);
            $this->protokolliere($angebot, 'Angebot '.$angebot->nr.' online angenommen ('.$request->ip().')');

            return $zurueck;
        }

        if ($aktion === 'ueberarbeitung' && $kommentar === '') {
            return $zurueck->with('annahme_fehler', 'Bitte beschreiben Sie kurz, was überarbeitet werden soll.');
        }

        $angebot->update([
            'status' => $aktion === 'ablehnen' ? AngebotStatus::Abgelehnt : AngebotStatus::Entwurf,
            'kunden_kommentar' => $kommentar !== '' ? $kommentar : $angebot->kunden_kommentar,
        ]);
        $this->protokolliere($angebot, $aktion === 'ablehnen'
            ? 'Angebot '.$angebot->nr.' online abgelehnt'
            : 'Angebot '.$angebot->nr.': Kunde bittet um Überarbeitung');

        return $zurueck->with('annahme_info', $aktion === 'ablehnen'
            ? 'Vielen Dank für Ihre Rückmeldung — das Angebot wurde abgelehnt.'
            : 'Vielen Dank — wir überarbeiten das Angebot und melden uns.');
    }

    private function protokolliere(Angebot $angebot, string $titel): void
    {
        $angebot->projekt?->aktivitaeten()->create([
            'titel' => $titel,
            'wer' => $angebot->kunde->anzeigename,
            'datum' => now()->format('d.m.'),
            'status' => 'done',
        ]);
    }

    private function istAbgelaufen(Angebot $angebot): bool
    {
        return $angebot->gueltig_bis !== null && $angebot->gueltig_bis->isPast()
            && $angebot->status !== AngebotStatus::Angenommen;
    }

    private function angebot(string $token): Angebot
    {
        abort_if($token === '', 404);

        return Angebot::query()
            ->with(['kunde', 'projekt.positionen'])
            ->where('accept_token', $token)
            ->firstOrFail();
    }
}
