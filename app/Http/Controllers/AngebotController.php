<?php

namespace App\Http\Controllers;

use App\Models\Angebot;
use Illuminate\View\View;

class AngebotController extends Controller
{
    public function index(): View
    {
        // Kein Detail-Screen im Handoff: verknüpfte Angebote springen
        // ins Projekt, unverknüpfte zeigen nur einen Hinweis-Toast.
        return view('angebote.index', [
            'angebote' => Angebot::query()
                ->with(['kunde', 'projekt'])
                ->orderByDesc('nr')
                ->get(),
        ]);
    }
}
