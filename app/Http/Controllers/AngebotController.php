<?php

namespace App\Http\Controllers;

use App\Enums\AngebotStatus;
use App\Models\Angebot;
use App\Support\AngebotsRechnung;
use App\Support\KonfiguratorRechner;
use App\Support\PdfArchiv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $angebot->load(['kunde', 'projekt.positionen', 'anfrage']);

        return view('angebote.show', [
            'angebot' => $angebot,
            'rechnung' => AngebotsRechnung::fuer($angebot),
            'naechsteStatus' => self::UEBERGAENGE[$angebot->status->value] ?? [],
            'annahmeUrl' => route('angebote.annahme', $angebot->stelleAnnahmeTokenSicher()),
        ]);
    }

    public function pdf(Angebot $angebot): Response
    {
        $angebot->load(['kunde', 'projekt.positionen']);
        $konfiguration = $angebot->projekt?->konfiguration ?? $angebot->konfiguration;

        return PdfArchiv::liefere('angebote.pdf', [
            'angebot' => $angebot,
            'rechnung' => AngebotsRechnung::fuer($angebot),
            'kalk' => $konfiguration !== null ? KonfiguratorRechner::berechne($konfiguration) : null,
            'annahmeUrl' => route('angebote.annahme', $angebot->stelleAnnahmeTokenSicher()),
        ], 'Angebot_'.$angebot->nr.'.pdf', $angebot->projekt, 'angebot', $angebot->status->label());
    }

    /** Preise je Position + globaler Rabatt; die Summe folgt den Positionen. */
    public function speicherePreise(Request $request, Angebot $angebot): RedirectResponse
    {
        if ($angebot->status === AngebotStatus::Angenommen) {
            return redirect()->route('angebote.show', $angebot)
                ->with('toast', 'Angenommene Angebote sind eingefroren');
        }

        $daten = $request->validate([
            'preise' => ['array'],
            'preise.*' => ['nullable', 'numeric', 'min:0'],
            'rabatt_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $preise = array_filter(
            $daten['preise'] ?? [],
            fn ($wert) => $wert !== null && $wert !== '',
        );
        $angebot->update([
            'preise' => $preise === [] ? null : array_map(fn ($w) => round((float) $w, 2), $preise),
            'rabatt_prozent' => (float) ($daten['rabatt_prozent'] ?? 0),
        ]);
        AngebotsRechnung::aktualisiereSumme($angebot);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Preise gespeichert');
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

        $angebot->update(['status' => $neu] + ($neu === AngebotStatus::Versendet && $angebot->gueltig_bis === null
            ? ['gueltig_bis' => now()->addDays(30)->toDateString()] : []));
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
        if ($angebot->status === AngebotStatus::Angenommen) {
            return redirect()->route('angebote.show', $angebot)
                ->with('toast', 'Angenommene Angebote sind eingefroren');
        }

        $daten = $request->validate(['summe' => ['required', 'numeric', 'min:0']]);
        $angebot->update(['summe' => $daten['summe']]);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Angebotssumme gespeichert');
    }
}
