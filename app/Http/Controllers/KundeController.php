<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Support\Format;
use Illuminate\View\View;

class KundeController extends Controller
{
    public function index(): View
    {
        return view('kunden.index', [
            'kunden' => Kunde::query()->orderBy('kunden_nr')->get(),
        ]);
    }

    public function show(Kunde $kunde): View
    {
        $kunde->load(['anfragen', 'angebote', 'projekte.angebot']);

        // Aktivitäts-Timeline wie im Prototyp: Angebote, dann Anfragen,
        // zuletzt die Anlage des Kunden (bewusst nicht chronologisch).
        $timeline = collect();
        foreach ($kunde->angebote as $angebot) {
            $timeline->push([
                'titel' => 'Angebot '.$angebot->nr.' · '.($angebot->summe !== null ? Format::eur($angebot->summe) : 'in Konfiguration'),
                'sub' => 'Status '.$angebot->status->label(),
                'datum' => Format::datumKurz($angebot->datum),
                'status' => 'done',
            ]);
        }
        foreach ($kunde->anfragen as $anfrage) {
            $timeline->push([
                'titel' => 'Anfrage '.$anfrage->nummer.' erfasst',
                'sub' => $anfrage->produkt_notiz ?? '–',
                'datum' => Format::datumKurz($anfrage->besuchstermin_datum),
                'status' => 'done',
            ]);
        }
        $timeline->push([
            'titel' => 'Kunde angelegt',
            'sub' => 'Quelle '.($kunde->quelle ?? '–'),
            'datum' => Format::datumKurz($kunde->created_at),
            'status' => 'now',
        ]);

        return view('kunden.show', [
            'kunde' => $kunde,
            'timeline' => $timeline,
        ]);
    }
}
