<?php

namespace App\Http\Controllers;

use App\Enums\AngebotStatus;
use App\Models\Angebot;
use App\Support\AngebotsRechnung;
use App\Support\KonfiguratorRechner;
use App\Support\PdfArchiv;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AngebotController extends Controller
{
    /**
     * Erlaubte Status-Übergänge — inklusive Rückwege: ein angenommenes
     * Angebot kann zurück auf Entwurf gesetzt werden (hebt den Freeze auf),
     * ein abgelehntes erneut versendet werden.
     */
    private const UEBERGAENGE = [
        'entwurf' => ['versendet', 'abgelehnt'],
        'versendet' => ['angenommen', 'abgelehnt', 'entwurf'],
        'angenommen' => ['entwurf'],
        'abgelehnt' => ['entwurf', 'versendet'],
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
        $annahmeUrl = route('angebote.annahme', $angebot->stelleAnnahmeTokenSicher());

        return PdfArchiv::liefere('angebote.pdf', [
            'angebot' => $angebot,
            'rechnung' => AngebotsRechnung::fuer($angebot),
            'kalk' => $konfiguration !== null ? KonfiguratorRechner::berechne($konfiguration) : null,
            'annahmeUrl' => $annahmeUrl,
            'annahmeQr' => QrCode::svgDataUri($annahmeUrl),
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
            'rabatte' => ['array'],
            'rabatte.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rabatt_prozent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $gefuellt = fn (array $werte) => array_filter($werte, fn ($wert) => $wert !== null && $wert !== '');
        $preise = $gefuellt($daten['preise'] ?? []);
        $rabatte = $gefuellt($daten['rabatte'] ?? []);
        $angebot->update([
            'preise' => $preise === [] ? null : array_map(fn ($w) => round((float) $w, 2), $preise),
            'rabatte' => $rabatte === [] ? null : array_map(fn ($w) => round((float) $w, 2), $rabatte),
            'rabatt_prozent' => (float) ($daten['rabatt_prozent'] ?? 0),
        ]);
        AngebotsRechnung::aktualisiereSumme($angebot);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Preise gespeichert');
    }

    /** Freie Zusatzposition des Verkäufers (Titel, Menge, Preis, Rabatt). */
    public function positionHinzufuegen(Request $request, Angebot $angebot): RedirectResponse
    {
        if ($angebot->status === AngebotStatus::Angenommen) {
            return redirect()->route('angebote.show', $angebot)
                ->with('toast', 'Angenommene Angebote sind eingefroren');
        }

        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'menge' => ['nullable', 'integer', 'min:1', 'max:999'],
            'preis' => ['nullable', 'numeric', 'min:0'],
            'rabatt' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $angebot->update(['freie_positionen' => [...$angebot->freie_positionen ?? [], [
            'id' => uniqid(),
            'titel' => $daten['titel'],
            'menge' => (int) ($daten['menge'] ?? 1),
            'preis' => $daten['preis'] !== null && $daten['preis'] !== '' ? round((float) $daten['preis'], 2) : null,
            'rabatt' => (float) ($daten['rabatt'] ?? 0),
        ]]]);
        AngebotsRechnung::aktualisiereSumme($angebot);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Position hinzugefügt');
    }

    /** Entfernt eine Position: freie werden gelöscht, generierte ausgeblendet. */
    public function positionEntfernen(Request $request, Angebot $angebot): RedirectResponse
    {
        if ($angebot->status === AngebotStatus::Angenommen) {
            return redirect()->route('angebote.show', $angebot)
                ->with('toast', 'Angenommene Angebote sind eingefroren');
        }

        $key = (string) $request->input('key');
        if ($key === 'dach') {
            return redirect()->route('angebote.show', $angebot)
                ->with('toast', 'Die Dach-Position folgt dem Konfigurator und bleibt im Angebot');
        }

        if (str_starts_with($key, 'f')) {
            $angebot->update(['freie_positionen' => array_values(array_filter(
                $angebot->freie_positionen ?? [],
                fn (array $frei) => 'f'.($frei['id'] ?? '') !== $key,
            ))]);
        } else {
            $angebot->update(['ausgeblendet' => array_values(array_unique([...$angebot->ausgeblendet ?? [], $key]))]);
        }
        AngebotsRechnung::aktualisiereSumme($angebot);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Position entfernt');
    }

    /** Holt alle ausgeblendeten generierten Positionen zurück. */
    public function positionenWiederherstellen(Angebot $angebot): RedirectResponse
    {
        if ($angebot->status === AngebotStatus::Angenommen) {
            return redirect()->route('angebote.show', $angebot)
                ->with('toast', 'Angenommene Angebote sind eingefroren');
        }

        $angebot->update(['ausgeblendet' => null]);
        AngebotsRechnung::aktualisiereSumme($angebot);

        return redirect()->route('angebote.show', $angebot)->with('toast', 'Positionen wiederhergestellt');
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
