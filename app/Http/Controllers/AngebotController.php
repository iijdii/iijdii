<?php

namespace App\Http\Controllers;

use App\Enums\AngebotStatus;
use App\Models\Angebot;
use App\Support\KonfiguratorRechner;
use App\Support\PdfArchiv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AngebotController extends Controller
{
    /** Erlaubte Status-Übergänge: entwurf→versendet→angenommen|abgelehnt; abgelehnt→entwurf. */
    private const UEBERGAENGE = [
        'entwurf' => ['versendet'],
        'versendet' => ['angenommen', 'abgelehnt'],
        'angenommen' => [],
        'abgelehnt' => ['entwurf'],
    ];

    public function index(): View
    {
        return view('angebote.index', [
            'angebote' => Angebot::query()
                ->with(['kunde', 'projekt'])
                ->orderByDesc('nr')
                ->get(),
        ]);
    }

    public function show(Angebot $angebot): View
    {
        $angebot->load(['kunde', 'projekt', 'anfrage']);

        // Konfiguration des Angebots, sonst die des verknüpften Projekts
        // (Seed-Angebote tragen keine eigene Kopie).
        $konfiguration = $angebot->konfiguration ?? $angebot->projekt?->konfiguration;

        return view('angebote.show', [
            'angebot' => $angebot,
            'positionen' => $konfiguration !== null
                ? KonfiguratorRechner::berechne($konfiguration)['positionen']
                : [],
            'naechsteStatus' => self::UEBERGAENGE[$angebot->status->value] ?? [],
        ]);
    }

    public function pdf(Angebot $angebot): \Illuminate\Http\Response
    {
        $angebot->load(['kunde', 'projekt']);
        $konfiguration = $angebot->konfiguration ?? $angebot->projekt?->konfiguration;

        return PdfArchiv::liefere('angebote.pdf', [
            'angebot' => $angebot,
            'positionen' => $konfiguration !== null
                ? KonfiguratorRechner::berechne($konfiguration)['positionen']
                : [],
        ], 'Angebot_'.$angebot->nr.'.pdf', $angebot->projekt, 'angebot', $angebot->status->label());
    }

    public function setzeStatus(Request $request, Angebot $angebot): RedirectResponse
    {
        $daten = $request->validate(['status' => ['required', Rule::enum(AngebotStatus::class)]]);
        $neu = AngebotStatus::from($daten['status']);

        if (! in_array($neu->value, self::UEBERGAENGE[$angebot->status->value] ?? [], true)) {
            return redirect()->route('angebote.show', $angebot)->with('toast', 'Übergang nicht möglich');
        }
        if ($neu === AngebotStatus::Angenommen && (float) $angebot->summe <= 0) {
            return redirect()->route('angebote.show', $angebot)
                ->with('toast', 'Bitte zuerst die Angebotssumme erfassen');
        }

        $angebot->update(['status' => $neu]);
        $angebot->projekt?->aktivitaeten()->create([
            'titel' => 'Angebot '.$angebot->nr.' '.mb_strtolower($neu->label()),
            'wer' => $request->user()->name,
            'datum' => now()->format('d.m.'),
            'status' => 'done',
        ]);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Status: '.$neu->label());
    }

    public function speichereSumme(Request $request, Angebot $angebot): RedirectResponse
    {
        $daten = $request->validate(['summe' => ['required', 'numeric', 'min:0']]);
        $angebot->update(['summe' => $daten['summe']]);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Angebotssumme gespeichert');
    }
}
