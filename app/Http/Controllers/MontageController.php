<?php

namespace App\Http\Controllers;

use App\Models\MontageAufgabe;
use App\Models\MontageNotiz;
use App\Models\MontageZusatzmaterial;
use App\Models\Projekt;
use App\Models\Setting;
use App\Support\AufmassRechner;
use App\Support\KonfiguratorRechner;
use App\Support\LedPlan;
use App\Support\MontageZeichnung;
use App\Support\RoofZeichnung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MontageController extends Controller
{
    public function zeige(Request $request, Projekt $projekt): View
    {
        $projekt->load(['kunde', 'montageNotizen', 'montageZusatzmaterial', 'montageAufgaben.ersteller']);

        $kalk = KonfiguratorRechner::berechne($projekt->konfiguration ?? []);
        $p = $kalk['pcfg'];
        $aufmass = $projekt->aufmass ?? [];
        $gesetzt = $aufmass['led'] ?? [];
        [$tolGruen, $tolGelb] = $this->toleranzen();

        $gruppen = AufmassRechner::gruppen($p, $aufmass['mess'] ?? [], $tolGruen, $tolGelb);
        $aktiveGruppe = collect($gruppen)->firstWhere('ek', $request->query('gruppe')) ?? ($gruppen[0] ?? null);

        // Verglasungs-Zeichnung: synthetisches Festelement (Prototyp).
        $gw = $kalk['glasB'];
        $gt = $kalk['glasT'];
        $glas = MontageZeichnung::extra([
            'shape' => 'fest', 'noWall' => true, 'qty' => $kalk['fields'],
            'fields' => [$gw, $gt, $gt, (int) round(hypot($gw, $gt))],
        ]);

        // Aufgaben nach Datum gruppieren → „Tag N".
        $aufgabenTage = $projekt->montageAufgaben
            ->sortBy([['datum', 'asc'], ['sortierung', 'asc']])
            ->groupBy(fn (MontageAufgabe $a) => $a->datum?->toDateString() ?? '–')
            ->values()
            ->map(fn ($aufgaben, $i) => [
                'datum' => $aufgaben->first()->datum,
                'label' => 'Tag '.($i + 1),
                'von' => $aufgaben->first()->ersteller?->name ?? '–',
                'aufgaben' => $aufgaben,
                'erledigt' => $aufgaben->whereNotNull('erledigt_am')->count(),
            ]);

        $alleFelder = collect($gruppen)->flatMap(fn ($g) => $g['fields']);

        $adresse = trim(($projekt->objekt_strasse ?? '').' '.($projekt->objekt_hausnummer ?? ''))
            .' · '.trim(($projekt->objekt_plz ?? '').' '.($projekt->objekt_stadt ?? ''));

        return view('projekte.montage', [
            'projekt' => $projekt,
            'kalk' => $kalk,
            'pcfg' => $p,
            'tolGruen' => $tolGruen,
            'tolGelb' => $tolGelb,
            'gruppen' => $gruppen,
            'aktiveGruppe' => $aktiveGruppe,
            'zeichnung' => $aktiveGruppe ? MontageZeichnung::extra($this->zeichnungsDaten($aktiveGruppe)) : null,
            'glasZeichnung' => $glas,
            'roof' => RoofZeichnung::alle($kalk, [
                'projekt' => $projekt->nr.' · '.$projekt->kunde->anzeigename,
            ]),
            'ledZeichnung' => LedPlan::zeichnung($kalk, $gesetzt),
            'ledKandidaten' => LedPlan::kandidaten($kalk),
            'ledGesetzt' => $gesetzt,
            'aufgabenTage' => $aufgabenTage,
            'mmFilled' => $alleFelder->filter(fn ($f) => $f['ist'] !== null)->count(),
            'mmTotal' => $alleFelder->count(),
            'duebelSize' => $p['duebel']['size'] ?? '',
            'adresse' => $adresse,
            'mapsQuery' => urlencode($adresse),
        ]);
    }

    public function speichereAufmass(Request $request, Projekt $projekt): RedirectResponse
    {
        $projekt->refresh();
        $aufmass = $projekt->aufmass ?? [];

        $mess = [];
        foreach ((array) $request->input('mess', []) as $key => $wert) {
            if (! preg_match('/^(keil|fest|schiebe|markise|segel)\.[A-F]$/', $key)) {
                continue;
            }
            $ist = AufmassRechner::parseIst((string) $wert);
            if ($ist !== null) {
                $mess[$key] = $ist;
            }
        }
        $aufmass['mess'] = $mess;
        $aufmass['gesendet_am'] = now()->toIso8601String();
        $projekt->update(['aufmass' => $aufmass]);

        // Dübelgröße gehört zur Konfiguration (Prototyp: setPcfgV('duebel.size')).
        if ($request->filled('duebel_size')) {
            $konfiguration = $projekt->konfiguration ?? [];
            $konfiguration['duebel'] = array_merge(
                $konfiguration['duebel'] ?? [],
                ['size' => (string) $request->input('duebel_size')],
            );
            $projekt->update(['konfiguration' => $konfiguration]);
        }

        [$tolGruen, $tolGelb] = $this->toleranzen();
        $gruppen = AufmassRechner::gruppen($projekt->konfiguration ?? [], $mess, $tolGruen, $tolGelb);
        $alle = collect($gruppen)->flatMap(fn ($g) => $g['fields']);
        $gefuellt = $alle->filter(fn ($f) => $f['ist'] !== null)->count();

        $projekt->aktivitaeten()->create([
            'titel' => 'Aufmaß übermittelt ('.$gefuellt.'/'.$alle->count().' Maße)',
            'wer' => $request->user()->name,
            'datum' => now()->format('d.m.'),
            'status' => 'done',
        ]);

        return redirect()->to(route('projekte.montage', $projekt).'#s8')
            ->with('toast', 'Aufmaß übermittelt · '.$gefuellt.'/'.$alle->count().' Maße');
    }

    public function toggleLed(Request $request, Projekt $projekt): RedirectResponse
    {
        $projekt->refresh();
        $aufmass = $projekt->aufmass ?? [];
        $gesetzt = $aufmass['led'] ?? [];

        if ($request->input('aktion') === 'reset') {
            $aufmass['led'] = [];
            $projekt->update(['aufmass' => $aufmass]);

            return redirect()->to(route('projekte.montage', $projekt).'#s7');
        }

        $pos = (string) $request->input('pos');
        if (! preg_match('/^s\d+\.[0-2]$/', $pos)) {
            return redirect()->to(route('projekte.montage', $projekt).'#s7');
        }

        $kalk = KonfiguratorRechner::berechne($projekt->konfiguration ?? []);

        if (in_array($pos, $gesetzt, true)) {
            $gesetzt = array_values(array_diff($gesetzt, [$pos]));
        } elseif (count($gesetzt) >= $kalk['ledTot']) {
            return redirect()->to(route('projekte.montage', $projekt).'#s7')
                ->with('toast', 'Laut Konfiguration sind nur '.$kalk['ledTot'].' Spots vorgesehen');
        } else {
            $gesetzt[] = $pos;
            sort($gesetzt);
        }

        $aufmass['led'] = $gesetzt;
        $projekt->update(['aufmass' => $aufmass]);

        return redirect()->to(route('projekte.montage', $projekt).'#s7');
    }

    public function speichereNotiz(Request $request, Projekt $projekt): RedirectResponse
    {
        $daten = $request->validate([
            'typ' => ['required', 'in:hinweis,problem,aenderung'],
            'text' => ['nullable', 'string', 'max:2000'],
        ]);

        if (trim((string) ($daten['text'] ?? '')) === '') {
            return redirect()->to(route('projekte.montage', $projekt).'#s9')
                ->with('toast', 'Bitte Text eingeben');
        }

        $projekt->montageNotizen()->create([
            'typ' => $daten['typ'],
            'text' => trim($daten['text']),
            'erstellt_von' => $request->user()->id,
        ]);

        return redirect()->to(route('projekte.montage', $projekt).'#s9')
            ->with('toast', 'Notiz erfasst');
    }

    public function loescheNotiz(Projekt $projekt, MontageNotiz $notiz): RedirectResponse
    {
        abort_unless($notiz->projekt_id === $projekt->id, 404);
        $notiz->delete();

        return redirect()->to(route('projekte.montage', $projekt).'#s9');
    }

    public function speichereMaterial(Request $request, Projekt $projekt): RedirectResponse
    {
        $bezeichnung = trim((string) $request->input('bezeichnung'));
        if ($bezeichnung === '') {
            return redirect()->to(route('projekte.montage', $projekt).'#s10')
                ->with('toast', 'Bitte Material eingeben');
        }

        $projekt->montageZusatzmaterial()->create([
            'bezeichnung' => $bezeichnung,
            'menge' => trim((string) $request->input('menge')) ?: '1',
        ]);

        return redirect()->to(route('projekte.montage', $projekt).'#s10')
            ->with('toast', 'Zusatzmaterial erfasst');
    }

    public function loescheMaterial(Projekt $projekt, MontageZusatzmaterial $zeile): RedirectResponse
    {
        abort_unless($zeile->projekt_id === $projekt->id, 404);
        $zeile->delete();

        return redirect()->to(route('projekte.montage', $projekt).'#s10');
    }

    public function speichereAufgabe(Request $request, Projekt $projekt): RedirectResponse
    {
        $titel = trim((string) $request->input('titel'));
        $datum = $request->input('datum');
        if ($titel === '' || ! $datum || strtotime((string) $datum) === false) {
            return redirect()->to(route('projekte.montage', $projekt).'#s11')
                ->with('toast', 'Bitte Datum und Aufgabe angeben');
        }
        $projekt->montageAufgaben()->create([
            'datum' => $datum,
            'titel' => $titel,
            'beschreibung' => 'vom Büro ergänzt',
            'sortierung' => $projekt->montageAufgaben()->where('datum', $datum)->max('sortierung') + 1,
            'erstellt_von' => $request->user()->id,
        ]);

        return redirect()->to(route('projekte.montage', $projekt).'#s11')
            ->with('toast', 'Aufgabe zum Termin hinzugefügt');
    }

    public function loescheAufgabe(Projekt $projekt, MontageAufgabe $aufgabe): RedirectResponse
    {
        abort_unless($aufgabe->projekt_id === $projekt->id, 404);
        $aufgabe->delete();

        return redirect()->to(route('projekte.montage', $projekt).'#s11')
            ->with('toast', 'Aufgabe entfernt');
    }

    public function toggleAufgabe(Request $request, Projekt $projekt, MontageAufgabe $aufgabe): RedirectResponse
    {
        abort_unless($aufgabe->projekt_id === $projekt->id, 404);

        $aufgabe->update($aufgabe->erledigt_am
            ? ['erledigt_am' => null, 'erledigt_von' => null]
            : ['erledigt_am' => now(), 'erledigt_von' => $request->user()->id]);

        return redirect()->to(route('projekte.montage', $projekt).'#s11');
    }

    /** @return array{0:int, 1:int} */
    private function toleranzen(): array
    {
        return [
            (int) Setting::wert('toleranz_gruen_mm', 5),
            (int) Setting::wert('toleranz_gelb_mm', 15),
        ];
    }

    /** Gruppen-Daten → MontageZeichnung-Eingabe. */
    private function zeichnungsDaten(array $gruppe): array
    {
        return [
            'shape' => $gruppe['shape'],
            'fields' => array_column($gruppe['fields'], 'soll'),
            'side' => $gruppe['side'] ?? '',
            'dir' => $gruppe['dir'] ?? '',
            'qty' => $gruppe['qty'] ?? 0,
            'unterzug' => $gruppe['unterzug'] ?? '110×110',
        ];
    }
}
