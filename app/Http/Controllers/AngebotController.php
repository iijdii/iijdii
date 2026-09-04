<?php

namespace App\Http\Controllers;

use App\Models\Angebot;
use App\Support\KonfiguratorRechner;
use Illuminate\View\View;

class AngebotController extends Controller
{
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
        ]);
    }
}
