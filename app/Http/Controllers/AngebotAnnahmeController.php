<?php

namespace App\Http\Controllers;

use App\Enums\AngebotStatus;
use App\Models\Angebot;
use App\Support\AngebotsRechnung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Online-Annahme (Spez. v3.2): öffentliche Seite ohne Login, Schutz ist
 * die Kenntnis des permanenten accept_token aus dem Angebots-PDF. Die
 * Annahme setzt Status Angenommen + Zeitpunkt/IP und friert das Angebot
 * ein (Freeze-Guards in AngebotController/KonfigurationSync).
 */
class AngebotAnnahmeController extends Controller
{
    public function zeige(string $token): View
    {
        $angebot = $this->angebot($token);

        return view('angebote.annahme', [
            'angebot' => $angebot,
            'rechnung' => AngebotsRechnung::fuer($angebot),
            'abgelaufen' => $angebot->gueltig_bis !== null && $angebot->gueltig_bis->isPast()
                && $angebot->status !== AngebotStatus::Angenommen,
        ]);
    }

    public function bestaetige(Request $request, string $token): RedirectResponse
    {
        $angebot = $this->angebot($token);

        if ($angebot->status === AngebotStatus::Angenommen) {
            return redirect()->route('angebote.annahme', $token);
        }
        if (($angebot->gueltig_bis !== null && $angebot->gueltig_bis->isPast()) || (float) $angebot->summe <= 0) {
            return redirect()->route('angebote.annahme', $token);
        }

        $angebot->update([
            'status' => AngebotStatus::Angenommen,
            'angenommen_am' => now(),
            'angenommen_ip' => $request->ip(),
        ]);
        $angebot->projekt?->aktivitaeten()->create([
            'titel' => 'Angebot '.$angebot->nr.' online angenommen ('.$request->ip().')',
            'wer' => $angebot->kunde->anzeigename,
            'datum' => now()->format('d.m.'),
            'status' => 'done',
        ]);

        return redirect()->route('angebote.annahme', $token);
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
