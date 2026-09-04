<?php

namespace App\Http\Controllers;

use App\Enums\AngebotStatus;
use App\Enums\ProjektStatus;
use App\Models\Projekt;
use App\Services\LagerService;
use App\Support\KonfiguratorRechner;
use App\Support\Nummern;
use App\Support\PdfArchiv;
use App\Support\RoofZeichnung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjektController extends Controller
{
    private const TABS = ['uebersicht', 'konfig', 'technik', 'material', 'dokumente', 'zahlungen', 'aktivitaet'];

    public function __construct(private readonly LagerService $lager)
    {
    }

    public function index(Request $request): View
    {
        $filter = $request->query('status', 'alle');
        $alle = Projekt::query()->with(['kunde', 'angebot'])->orderByDesc('nr')->get();

        $chips = collect([['alle', 'Alle']])
            ->concat(collect(ProjektStatus::cases())->map(fn ($s) => [$s->value, $s->label()]))
            ->map(fn (array $chip) => [
                'key' => $chip[0],
                'label' => $chip[1],
                'anzahl' => $chip[0] === 'alle'
                    ? $alle->count()
                    : $alle->filter(fn (Projekt $p) => $p->status->value === $chip[0])->count(),
                'aktiv' => $chip[0] === $filter,
            ]);

        return view('projekte.index', [
            'chips' => $chips,
            'filter' => $filter,
            'projekte' => $filter === 'alle'
                ? $alle
                : $alle->filter(fn (Projekt $p) => $p->status->value === $filter)->values(),
        ]);
    }

    public function show(Request $request, Projekt $projekt): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'uebersicht';
        $projekt->load(['kunde', 'angebot', 'dokumente', 'aktivitaeten']);

        // Vorschau aus „Berechnen" (PRG) hat Vorrang vor der gespeicherten Konfiguration.
        $pcfg = $request->session()->get('pcfg_preview.'.$projekt->nr, $projekt->konfiguration);
        $kalk = KonfiguratorRechner::berechne($pcfg ?? []);

        $daten = [
            'projekt' => $projekt,
            'tab' => $tab,
            'kalk' => $kalk,
            'roof' => RoofZeichnung::alle($kalk, [
                'projekt' => $projekt->nr.' · '.$projekt->kunde->anzeigename,
            ]),
            'vorschau' => $request->session()->has('pcfg_preview.'.$projekt->nr),
        ];

        $daten += match ($tab) {
            'uebersicht' => [
                'materialVorschau' => array_slice($this->lager->materialListe($projekt), 0, 5),
            ],
            'material' => ['materialListe' => $this->lager->materialListe($projekt)],
            'zahlungen' => ['zahlung' => $this->zahlungsplan($projekt)],
            default => [],
        };

        return view('projekte.show', $daten);
    }

    /** Projektmappe: Kopf, Kunde, Technische Daten, Positionen, Materialliste. */
    public function pdf(Projekt $projekt): \Illuminate\Http\Response
    {
        $projekt->load(['kunde', 'angebot']);

        return PdfArchiv::liefere('projekte.pdf', [
            'projekt' => $projekt,
            'kalk' => KonfiguratorRechner::berechne($projekt->konfiguration ?? []),
            'materialListe' => $this->lager->materialListe($projekt),
        ], 'Projektmappe_'.$projekt->nr.'.pdf', $projekt, 'projektmappe');
    }

    public function ladeDokumentHoch(Request $request, Projekt $projekt): RedirectResponse
    {
        $request->validate(
            ['datei' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,dwg,xlsx']],
            ['datei.mimes' => 'Nur PDF, Bilder, DWG oder XLSX bis 10 MB.', 'datei.max' => 'Nur PDF, Bilder, DWG oder XLSX bis 10 MB.'],
        );

        $datei = $request->file('datei');
        // Zeitstempel-Präfix gegen Namenskollisionen; privater Disk wie beim Protokoll.
        $name = $projekt->nr.'_'.now()->format('YmdHis').'_'.$datei->getClientOriginalName();
        $pfad = \Illuminate\Support\Facades\Storage::putFileAs('dokumente', $datei, $name);

        $projekt->dokumente()->create([
            'typ' => 'upload',
            'dateiname' => $datei->getClientOriginalName(),
            'pfad' => $pfad,
            'groesse' => $datei->getSize(),
            'datum' => now()->toDateString(),
        ]);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'dokumente'])
            ->with('toast', 'Dokument hochgeladen');
    }

    public function speichereKonfiguration(Request $request, Projekt $projekt): RedirectResponse
    {
        $pcfg = $this->pcfgAusRequest($request);
        $aktion = $request->input('aktion', 'berechnen');

        if ($aktion === 'speichern') {
            $projekt->update(['konfiguration' => $pcfg]);
            $request->session()->forget('pcfg_preview.'.$projekt->nr);
            $projekt->aktivitaeten()->create([
                'titel' => 'Konfiguration gespeichert',
                'wer' => $request->user()->name,
                'datum' => now()->format('d.m.'),
                'status' => 'done',
            ]);

            return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
                ->with('toast', 'Projekt-Konfiguration gespeichert');
        }

        $request->session()->put('pcfg_preview.'.$projekt->nr, $pcfg);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig']);
    }

    public function erstelleAngebot(Request $request, Projekt $projekt): RedirectResponse
    {
        if ($projekt->angebot_id) {
            return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
                ->with('toast', 'Angebot '.$projekt->angebot->nr.' ist bereits verknüpft');
        }

        $angebot = $projekt->kunde->angebote()->create([
            'nr' => Nummern::angebot(),
            'titel' => $projekt->titel,
            'status' => AngebotStatus::Entwurf,
            'datum' => now()->toDateString(),
            'konfiguration' => $projekt->konfiguration,
        ]);
        $projekt->update(['angebot_id' => $angebot->id]);
        $projekt->aktivitaeten()->create([
            'titel' => 'Angebot '.$angebot->nr.' erstellt',
            'wer' => $request->user()->name,
            'datum' => now()->format('d.m.'),
            'status' => 'done',
        ]);

        return redirect()->route('projekte.show', [$projekt, 'tab' => 'konfig'])
            ->with('toast', 'Angebot aus Konfiguration erstellt');
    }

    /** Formulareingaben whitelisten/casten → pcfg-Array. */
    private function pcfgAusRequest(Request $request): array
    {
        $enum = fn (?string $wert, array $erlaubt, string $default) => in_array($wert, $erlaubt, true) ? $wert : $default;
        $d = KonfiguratorRechner::defaults();

        $extras = array_values(array_intersect(
            (array) $request->input('extras', []),
            KonfiguratorRechner::EXTRAS,
        ));

        return [
            'product' => $enum($request->input('product'), KonfiguratorRechner::PRODUKTE, $d['product']),
            'mounting' => $enum($request->input('mounting'), ['an der Wand', 'freistehend'], $d['mounting']),
            'shape' => $enum($request->input('shape'), ['rechteck', 'trapez'], $d['shape']),
            'width' => (int) $request->input('width', $d['width']),
            'depth' => (int) $request->input('depth', $d['depth']),
            'wallH' => (int) $request->input('wallH', $d['wallH']),
            'gutterH' => (int) $request->input('gutterH', $d['gutterH']),
            'slope' => (int) $request->input('slope', $d['slope']),
            'color' => $enum($request->input('color'), KonfiguratorRechner::FARBEN, $d['color']),
            'covering' => $enum($request->input('covering'), KonfiguratorRechner::DECKUNGEN, $d['covering']),
            'glasTrans' => $enum($request->input('glasTrans'), ['Klar', 'Milch'], $d['glasTrans']),
            'thickness' => $enum($request->input('thickness'), KonfiguratorRechner::STAERKEN, $d['thickness']),
            'postN' => ((int) $request->input('postN')) ?: '',
            'snow' => $enum($request->input('snow'), KonfiguratorRechner::SCHNEELAST, $d['snow']),
            'wind' => $enum($request->input('wind'), KonfiguratorRechner::WINDZONE, $d['wind']),
            'extras' => $extras,
            'keil' => [
                'count' => max(1, (int) $request->input('keil.count', 1)),
                'hFront' => (int) $request->input('keil.hFront', $d['keil']['hFront']),
                'side' => $enum($request->input('keil.side'), ['Links', 'Rechts', 'Beidseitig'], $d['keil']['side']),
                'material' => $enum($request->input('keil.material'), ['Glas', 'Aluminium', 'Polycarbonat'], $d['keil']['material']),
                'trans' => $enum($request->input('keil.trans'), ['Klar', 'Opal', 'Matt'], $d['keil']['trans']),
            ],
            'fest' => [
                'count' => max(1, (int) $request->input('fest.count', 1)),
                'width' => (int) $request->input('fest.width', $d['fest']['width']),
                'height' => (int) $request->input('fest.height', $d['fest']['height']),
                'h2' => (int) $request->input('fest.h2', $d['fest']['h2']),
                'glas' => $enum($request->input('fest.glas'), ['VSG-Glas', 'Polycarbonat klar', 'Polycarbonat opal'], $d['fest']['glas']),
            ],
            'schiebe' => [
                'width' => (int) $request->input('schiebe.width', $d['schiebe']['width']),
                'height' => (int) $request->input('schiebe.height', $d['schiebe']['height']),
                'count' => max(1, (int) $request->input('schiebe.count', $d['schiebe']['count'])),
                'dir' => $enum($request->input('schiebe.dir'), ['left', 'right', 'center'], $d['schiebe']['dir']),
                'glas' => $enum($request->input('schiebe.glas'), ['Klar', 'Milchglas'], $d['schiebe']['glas']),
            ],
            'markise' => [
                'modell' => $enum($request->input('markise.modell'), ['Varisol T200', 'Varisol F413', 'Varisol F513'], $d['markise']['modell']),
                'width' => (int) $request->input('markise.width', $d['markise']['width']),
                'ausfall' => (int) $request->input('markise.ausfall', $d['markise']['ausfall']),
                'felder' => max(1, (int) $request->input('markise.felder', $d['markise']['felder'])),
            ],
            'segel' => [
                'count' => max(1, (int) $request->input('segel.count', 1)),
                'size' => (string) $request->input('segel.size', $d['segel']['size']),
                'color' => $enum($request->input('segel.color'), ['Sandbeige', 'Anthrazit', 'Weiß', 'Grau'], $d['segel']['color']),
            ],
            'drain' => [
                'post' => (int) $request->input('drain.post', $d['drain']['post']),
                'height' => (int) $request->input('drain.height', $d['drain']['height']),
                'dir' => $enum($request->input('drain.dir'), ['nach vorn', 'nach hinten', 'nach links', 'nach rechts'], $d['drain']['dir']),
            ],
            'duebel' => [
                'typ' => $enum($request->input('duebel.typ'), ['Schlagdübel', 'Bolzenanker', 'Injektionsanker', 'Porenbetonanker'], $d['duebel']['typ']),
                'size' => (string) $request->input('duebel.size', $d['duebel']['size']),
                'abstand' => (int) $request->input('duebel.abstand', $d['duebel']['abstand']),
            ],
            'led' => [
                'on' => true,
                'total' => (int) $request->input('led.total', 12) === 6 ? 6 : 12,
                'color' => $enum($request->input('led.color'), ['Warmweiß 3000K', 'Neutralweiß 4000K', 'RGBW'], $d['led']['color']),
            ],
        ];
    }

    /**
     * Zahlungsplan 30/40/30 aus der Brutto-Angebotssumme — bewusst nur
     * berechnet, keine eigene Tabelle in diesem Milestone: Rate 1 gilt
     * als bezahlt, sobald das Angebot angenommen ist; Rate 2 wird fällig,
     * sobald das Projekt die Planung verlassen hat.
     */
    private function zahlungsplan(Projekt $projekt): ?array
    {
        $summe = (float) ($projekt->angebot?->summe ?? 0);
        if ($summe <= 0) {
            return null;
        }

        $angenommen = $projekt->angebot->status === AngebotStatus::Angenommen;
        $inAbwicklung = $projekt->status !== ProjektStatus::InPlanung;

        $raten = [
            ['Anzahlung bei Auftrag', 30, $angenommen ? ['Bezahlt', 'b-green'] : ['Offen', 'b-gray']],
            ['Bei Produktionsbeginn', 40, $angenommen && $inAbwicklung ? ['Fällig', 'b-yellow'] : ['Offen', 'b-gray']],
            ['Nach Montage', 30, ['Offen', 'b-gray']],
        ];
        // Demo-Zustand des Prototyps: Rate 2 ist beim angenommenen Angebot fällig.
        if ($angenommen && ! $inAbwicklung) {
            $raten[1][2] = ['Fällig', 'b-yellow'];
        }

        $netto = round($summe / 1.19, 2);
        $bezahlt = $angenommen ? round($summe * 0.30, 2) : 0.0;

        return [
            'raten' => array_map(fn ($r) => [
                'label' => $r[0],
                'anteil' => $r[1].' %',
                'betrag' => round($summe * $r[1] / 100, 2),
                'status' => $r[2][0],
                'badge' => $r[2][1],
            ], $raten),
            'netto' => $netto,
            'mwst' => round($summe - $netto, 2),
            'brutto' => $summe,
            'bezahlt' => $bezahlt,
            'offen' => round($summe - $bezahlt, 2),
            'bezahltAnzahl' => $angenommen ? 1 : 0,
        ];
    }
}
