<?php

namespace App\Http\Controllers;

use App\Enums\ProjektStatus;
use App\Models\Projekt;
use App\Support\KonfiguratorRechner;
use App\Support\Nummern;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AbnahmeController extends Controller
{
    public function formular(Projekt $projekt): View
    {
        return view('abnahme.formular', [
            'projekt' => $projekt->load('kunde'),
            'positionen' => $this->positionen($projekt),
            'defaultFrist' => now()->addWeeks(6)->format('d.m.Y'),
        ]);
    }

    public function speichere(Request $request, Projekt $projekt): RedirectResponse
    {
        $daten = $request->validate([
            'art' => ['required', Rule::in(['ohne', 'vorbehalt', 'verweigert'])],
            'ort' => ['required', 'string', 'max:120'],
            'checkliste' => ['nullable', 'array'],
            'maengel' => ['nullable', 'array'],
            'maengel.*.text' => ['nullable', 'string', 'max:500'],
            'maengel.*.frist' => ['nullable', 'string', 'max:32'],
            'sig_auftraggeber' => ['required', 'string', 'regex:#^data:image/png;base64,#'],
            'sig_monteur' => ['required', 'string', 'regex:#^data:image/png;base64,#'],
        ], [
            'sig_auftraggeber.required' => 'Beide Unterschriften erforderlich',
            'sig_monteur.required' => 'Beide Unterschriften erforderlich',
        ]);

        $maengel = collect($daten['maengel'] ?? [])
            ->filter(fn ($m) => trim((string) ($m['text'] ?? '')) !== '')
            ->values();

        if ($daten['art'] !== 'ohne' && $maengel->isEmpty()) {
            return redirect()->route('projekte.abnahme', $projekt)
                ->with('toast', 'Mindestens eine Beanstandung erfassen');
        }

        $nr = Nummern::abnahme();
        $sigK = $this->speichereUnterschrift($daten['sig_auftraggeber'], $nr, 'auftraggeber');
        $sigM = $this->speichereUnterschrift($daten['sig_monteur'], $nr, 'monteur');

        $checkliste = [
            'einweisung' => (bool) ($daten['checkliste']['einweisung'] ?? false),
            'pflege' => (bool) ($daten['checkliste']['pflege'] ?? false),
            'unterlagen' => (bool) ($daten['checkliste']['unterlagen'] ?? false),
            'baustelle' => (bool) ($daten['checkliste']['baustelle'] ?? false),
        ];

        $protokoll = DB::transaction(function () use ($request, $projekt, $daten, $maengel, $nr, $sigK, $sigM, $checkliste) {
            // Vollständig in EINEM create — der updating-Guard macht das
            // Protokoll danach unveränderlich.
            $protokoll = $projekt->abnahmeprotokolle()->create([
                'nr' => $nr,
                'art' => $daten['art'],
                'checkliste' => $checkliste,
                'ort' => $daten['ort'],
                'datum' => now()->toDateString(),
                'unterschrift_auftraggeber_pfad' => $sigK,
                'unterschrift_monteur_pfad' => $sigM,
                'abgeschlossen_am' => now(),
                'abgeschlossen_von' => $request->user()->id,
            ]);

            foreach ($maengel as $mangel) {
                $protokoll->maengel()->create([
                    'text' => trim($mangel['text']),
                    'frist' => $this->frist($mangel['frist'] ?? null),
                ]);
            }

            // PDF mit eingebetteten Unterschriften rendern und ablegen.
            $suffix = match ($daten['art']) {
                'verweigert' => '_verweigert',
                'vorbehalt' => '_mit_Vorbehalt',
                default => '',
            };
            $dateiname = 'Abnahmeprotokoll_'.$nr.$suffix.'.pdf';
            $bytes = Pdf::loadView('abnahme.pdf', [
                'protokoll' => $protokoll,
                'projekt' => $projekt->load('kunde'),
                'positionen' => $this->positionen($projekt),
                'maengel' => $maengel,
                'sigK' => $daten['sig_auftraggeber'],
                'sigM' => $daten['sig_monteur'],
            ])->output();
            $pfad = 'dokumente/'.$dateiname;
            Storage::put($pfad, $bytes);

            [$badge] = match ($daten['art']) {
                'verweigert' => [['verweigert']],
                'vorbehalt' => [[$maengel->count().' Mängel']],
                default => [['abgenommen']],
            };
            $projekt->dokumente()->create([
                'typ' => 'abnahmeprotokoll',
                'dateiname' => $dateiname,
                'pfad' => $pfad,
                'groesse' => strlen($bytes),
                'datum' => now()->toDateString(),
                'badge' => $badge[0],
            ]);

            $projekt->aktivitaeten()->create([
                'titel' => 'Abnahmeprotokoll '.$nr.' unterschrieben',
                'wer' => $request->user()->name,
                'datum' => now()->format('d.m.'),
                'status' => 'done',
            ]);

            if ($daten['art'] === 'ohne') {
                $projekt->update(['status' => ProjektStatus::Abgeschlossen]);
            }

            return $protokoll;
        });

        $toast = match ($daten['art']) {
            'verweigert' => 'Abnahme verweigert — Protokoll archiviert',
            'vorbehalt' => 'Abnahme unter Vorbehalt · '.$maengel->count().' Mängel dokumentiert',
            default => 'Abnahmeprotokoll unterschrieben und archiviert',
        };

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'dokumente'])->with('toast', $toast);
    }

    /** Ausgeführte Positionen aus der Konfiguration (Sektion 2). */
    private function positionen(Projekt $projekt): array
    {
        $kalk = KonfiguratorRechner::berechne($projekt->konfiguration ?? []);

        return array_map(fn ($pos) => [
            'pos' => $pos['pos'],
            'name' => $pos['name'],
            'menge' => \App\Support\Format::menge($pos['menge']).' Stk',
        ], $kalk['positionen']);
    }

    private function speichereUnterschrift(string $dataUrl, string $nr, string $partei): string
    {
        $png = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        abort_if($png === false, 422, 'Ungültige Unterschrift');

        $pfad = 'unterschriften/'.$nr.'_'.$partei.'.png';
        Storage::put($pfad, $png);

        return $pfad;
    }

    private function frist(?string $eingabe): ?string
    {
        $eingabe = trim((string) $eingabe);
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $eingabe, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return $eingabe !== '' && strtotime($eingabe) ? date('Y-m-d', strtotime($eingabe)) : null;
    }
}
