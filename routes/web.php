<?php

use App\Http\Controllers\AbnahmeController;
use App\Http\Controllers\AnfrageController;
use App\Http\Controllers\AngebotController;
use App\Http\Controllers\BestellungController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumentController;
use App\Http\Controllers\EinstellungenController;
use App\Http\Controllers\KalenderController;
use App\Http\Controllers\KundeController;
use App\Http\Controllers\LagerController;
use App\Http\Controllers\LieferantController;
use App\Http\Controllers\LogistikController;
use App\Http\Controllers\MaterialKatalogController;
use App\Http\Controllers\MontageController;
use App\Http\Controllers\ProjektController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Einmalige Server-Einrichtung ohne Shell-Zugang (Shared Hosting):
// SETUP_TOKEN in der .env setzen, /einrichtung/{token} im Browser aufrufen,
// danach den Token aus der .env entfernen (Route liefert dann 404).
Route::get('/einrichtung/{token}', function (string $token) {
    $erwartet = (string) config('app.setup_token', '');
    abort_unless($erwartet !== '' && hash_equals($erwartet, $token), 404);

    Artisan::call('migrate', ['--force' => true, '--seed' => true]);

    return response(
        '<pre>'.e(Artisan::output()).'</pre>'
        .'<p>Einrichtung abgeschlossen. Bitte SETUP_TOKEN jetzt aus der .env entfernen.</p>'
    );
})->name('einrichtung');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Logistik: /logistik/touren/* VOR dem {bestellung:nr}-Wildcard registrieren.
    Route::get('/logistik', [LogistikController::class, 'index'])->name('logistik');
    Route::post('/logistik/touren', [LogistikController::class, 'erstelleTour'])->middleware('role:lager,projektleiter')->name('logistik.touren.erstellen');
    Route::get('/logistik/touren/{tour:nr}', [LogistikController::class, 'tour'])->name('logistik.tour');
    Route::post('/logistik/touren/{tour:nr}/alle', [LogistikController::class, 'setzeTourAlle'])->middleware('role:lager,projektleiter')->name('logistik.tour.alle');
    Route::post('/logistik/touren/{tour:nr}/abschliessen', [LogistikController::class, 'schliesseTourAb'])->middleware('role:lager,projektleiter')->name('logistik.tour.abschliessen');
    Route::get('/logistik/touren/{tour:nr}/lade', [LogistikController::class, 'lade'])->name('logistik.lade');
    Route::post('/logistik/touren/{tour:nr}/abfahrt', [LogistikController::class, 'bestaetigeAbfahrt'])->middleware('role:lager,projektleiter')->name('logistik.abfahrt');
    Route::get('/logistik/{bestellung:nr}', [LogistikController::class, 'bestellung'])->name('logistik.bestellung');
    Route::post('/logistik/{bestellung:nr}/positionen/{position}/toggle', [LogistikController::class, 'togglePosition'])->middleware('role:lager,projektleiter')->name('logistik.position.toggle');
    Route::post('/logistik/{bestellung:nr}/positionen/{position}/notiz', [LogistikController::class, 'speichereNotiz'])->middleware('role:lager,projektleiter')->name('logistik.notiz');
    Route::post('/logistik/{bestellung:nr}/alle', [LogistikController::class, 'setzeAlle'])->middleware('role:lager,projektleiter')->name('logistik.alle');
    Route::post('/logistik/{bestellung:nr}/abschliessen', [LogistikController::class, 'schliesseAb'])->middleware('role:lager,projektleiter')->name('logistik.abschliessen');

    Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender');
    Route::get('/kalender/termin', [KalenderController::class, 'terminFormular'])->name('kalender.termin');
    Route::post('/kalender/termin', [KalenderController::class, 'speichereTermin'])->middleware('role:verkaeufer,projektleiter')->name('kalender.termin.speichern');
    Route::get('/lieferanten', [LieferantController::class, 'index'])->name('lieferanten');

    Route::get('/kunden', [KundeController::class, 'index'])->name('kunden');
    Route::get('/kunden/neu', [KundeController::class, 'create'])->name('kunden.create');
    Route::post('/kunden', [KundeController::class, 'store'])->middleware('role:verkaeufer,projektleiter')->name('kunden.store');
    Route::get('/kunden/{kunde:kunden_nr}', [KundeController::class, 'show'])->name('kunden.show');
    Route::get('/kunden/{kunde:kunden_nr}/bearbeiten', [KundeController::class, 'edit'])->name('kunden.edit');
    Route::put('/kunden/{kunde:kunden_nr}', [KundeController::class, 'update'])->middleware('role:verkaeufer,projektleiter')->name('kunden.update');
    Route::get('/angebote', [AngebotController::class, 'index'])->name('angebote');
    Route::get('/angebote/{angebot:nr}', [AngebotController::class, 'show'])->name('angebote.show');
    Route::post('/angebote/{angebot:nr}/status', [AngebotController::class, 'setzeStatus'])->middleware('role:verkaeufer,projektleiter')->name('angebote.status');
    Route::post('/angebote/{angebot:nr}/summe', [AngebotController::class, 'speichereSumme'])->middleware('role:verkaeufer,projektleiter')->name('angebote.summe');
    Route::get('/angebote/{angebot:nr}/pdf', [AngebotController::class, 'pdf'])->name('angebote.pdf');

    Route::get('/anfragen', [AnfrageController::class, 'index'])->name('anfragen');
    Route::get('/anfragen/neu', [AnfrageController::class, 'create'])->name('anfragen.create');
    Route::post('/anfragen', [AnfrageController::class, 'store'])->middleware('role:verkaeufer,projektleiter')->name('anfragen.store');
    Route::get('/anfragen/{anfrage:nummer}', [AnfrageController::class, 'show'])->name('anfragen.show');
    Route::get('/anfragen/{anfrage:nummer}/bearbeiten', [AnfrageController::class, 'edit'])->name('anfragen.edit');
    Route::put('/anfragen/{anfrage:nummer}', [AnfrageController::class, 'update'])->middleware('role:verkaeufer,projektleiter')->name('anfragen.update');
    Route::post('/anfragen/{anfrage:nummer}/status', [AnfrageController::class, 'setzeStatus'])->middleware('role:verkaeufer,projektleiter')->name('anfragen.status');
    Route::post('/anfragen/{anfrage:nummer}/projekt', [AnfrageController::class, 'erstelleProjekt'])->middleware('role:verkaeufer,projektleiter')->name('anfragen.projekt');

    Route::get('/projekte', [ProjektController::class, 'index'])->name('projekte');
    Route::get('/projekte/{projekt:nr}', [ProjektController::class, 'show'])->name('projekte.show');
    Route::get('/projekte/{projekt:nr}/pdf', [ProjektController::class, 'pdf'])->name('projekte.pdf');
    Route::post('/projekte/{projekt:nr}/dokumente', [ProjektController::class, 'ladeDokumentHoch'])->middleware('role:verkaeufer,projektleiter')->name('projekte.dokumente.upload');
    Route::post('/projekte/{projekt:nr}/stammdaten', [ProjektController::class, 'speichereStammdaten'])->middleware('role:verkaeufer,projektleiter')->name('projekte.stammdaten');
    Route::post('/projekte/{projekt:nr}/status', [ProjektController::class, 'setzeStatus'])->middleware('role:verkaeufer,projektleiter')->name('projekte.status');
    Route::post('/projekte/{projekt:nr}/reservierungen', [ProjektController::class, 'speichereReservierung'])->middleware('role:verkaeufer,projektleiter')->name('projekte.reservierungen.store');
    Route::post('/projekte/{projekt:nr}/reservierungen/{reservierung}/loeschen', [ProjektController::class, 'loescheReservierung'])->middleware('role:verkaeufer,projektleiter')->name('projekte.reservierungen.loeschen');
    Route::post('/projekte/{projekt:nr}/konfiguration', [ProjektController::class, 'speichereKonfiguration'])
        ->middleware('role:verkaeufer,projektleiter')->name('projekte.konfiguration');
    Route::post('/projekte/{projekt:nr}/angebot', [ProjektController::class, 'erstelleAngebot'])
        ->middleware('role:verkaeufer,projektleiter')->name('projekte.angebot');

    Route::get('/projekte/{projekt:nr}/montage', [MontageController::class, 'zeige'])->name('projekte.montage');
    Route::post('/projekte/{projekt:nr}/montage/aufmass', [MontageController::class, 'speichereAufmass'])->middleware('role:monteur,projektleiter')->name('projekte.montage.aufmass');
    Route::post('/projekte/{projekt:nr}/montage/led', [MontageController::class, 'toggleLed'])->middleware('role:monteur,projektleiter')->name('projekte.montage.led');
    Route::post('/projekte/{projekt:nr}/montage/notizen', [MontageController::class, 'speichereNotiz'])->middleware('role:monteur,projektleiter')->name('projekte.montage.notizen');
    Route::post('/projekte/{projekt:nr}/montage/notizen/{notiz}/loeschen', [MontageController::class, 'loescheNotiz'])->middleware('role:monteur,projektleiter')->name('projekte.montage.notizen.loeschen');
    Route::post('/projekte/{projekt:nr}/montage/material', [MontageController::class, 'speichereMaterial'])->middleware('role:monteur,projektleiter')->name('projekte.montage.material');
    Route::post('/projekte/{projekt:nr}/montage/material/{zeile}/loeschen', [MontageController::class, 'loescheMaterial'])->middleware('role:monteur,projektleiter')->name('projekte.montage.material.loeschen');
    Route::post('/projekte/{projekt:nr}/montage/aufgaben', [MontageController::class, 'speichereAufgabe'])->middleware('role:monteur,projektleiter')->name('projekte.montage.aufgaben');
    Route::post('/projekte/{projekt:nr}/montage/aufgaben/{aufgabe}/erledigt', [MontageController::class, 'toggleAufgabe'])->middleware('role:monteur,projektleiter')->name('projekte.montage.aufgaben.erledigt');
    Route::post('/projekte/{projekt:nr}/montage/aufgaben/{aufgabe}/loeschen', [MontageController::class, 'loescheAufgabe'])->middleware('role:monteur,projektleiter')->name('projekte.montage.aufgaben.loeschen');

    Route::get('/projekte/{projekt:nr}/abnahme', [AbnahmeController::class, 'formular'])->name('projekte.abnahme');
    Route::post('/projekte/{projekt:nr}/abnahme', [AbnahmeController::class, 'speichere'])->middleware('role:monteur,projektleiter')->name('projekte.abnahme.speichern');
    Route::get('/dokumente/{dokument}', [DokumentController::class, 'download'])->name('dokumente.download');

    Route::get('/bestellungen', [BestellungController::class, 'index'])->name('bestellungen');
    Route::get('/bestellungen/neu', [BestellungController::class, 'create'])->name('bestellungen.create');
    Route::post('/bestellungen', [BestellungController::class, 'store'])->middleware('role:lager,projektleiter')->name('bestellungen.store');
    Route::get('/bestellungen/{bestellung:nr}', [BestellungController::class, 'show'])->name('bestellungen.show');
    Route::get('/bestellungen/{bestellung:nr}/bearbeiten', [BestellungController::class, 'edit'])->name('bestellungen.edit');
    Route::put('/bestellungen/{bestellung:nr}', [BestellungController::class, 'update'])->middleware('role:lager,projektleiter')->name('bestellungen.update');
    Route::post('/bestellungen/{bestellung:nr}/positionen', [BestellungController::class, 'speicherePosition'])->middleware('role:lager,projektleiter')->name('bestellungen.positionen.store');
    Route::post('/bestellungen/{bestellung:nr}/positionen/{position}/loeschen', [BestellungController::class, 'loeschePosition'])->middleware('role:lager,projektleiter')->name('bestellungen.positionen.loeschen');
    Route::get('/bestellungen/{bestellung:nr}/pdf', [BestellungController::class, 'pdf'])->name('bestellungen.pdf');
    Route::post('/bestellungen/{bestellung:nr}/status', [BestellungController::class, 'setzeStatus'])
        ->middleware('role:lager,projektleiter')->name('bestellungen.status');

    Route::get('/lager', [LagerController::class, 'index'])->name('lager');
    Route::get('/lager/artikel/{artikel}', [LagerController::class, 'artikel'])->name('lager.artikel');
    Route::post('/lager/artikel/{artikel}/korrektur', [LagerController::class, 'bucheKorrektur'])->middleware('role:lager,projektleiter')->name('lager.korrektur');
    Route::post('/lager/artikel/{artikel}/nachbestellen', [LagerController::class, 'nachbestellen'])->middleware('role:lager,projektleiter')->name('lager.nachbestellen');
    Route::post('/lager/bestellvorschlag', [LagerController::class, 'erstelleBestellvorschlag'])->middleware('role:lager,projektleiter')->name('lager.bestellvorschlag');
    Route::get('/lager/wareneingang/{bestellung:nr}/lieferschein', [LagerController::class, 'lieferschein'])->name('lager.lieferschein');
    Route::post('/lager/wareneingang/{bestellung:nr}', [LagerController::class, 'bucheWareneingang'])
        ->middleware('role:lager,projektleiter')->name('lager.wareneingang.buchen');

    Route::get('/material-katalog', [MaterialKatalogController::class, 'index'])->name('material-katalog');
    Route::get('/material-katalog/neu', [MaterialKatalogController::class, 'create'])->name('material-katalog.create');
    Route::post('/material-katalog', [MaterialKatalogController::class, 'store'])->middleware('role:lager,projektleiter')->name('material-katalog.store');
    Route::get('/material-katalog/{artikel}/bearbeiten', [MaterialKatalogController::class, 'edit'])->name('material-katalog.edit');
    Route::put('/material-katalog/{artikel}', [MaterialKatalogController::class, 'update'])->middleware('role:lager,projektleiter')->name('material-katalog.update');
    Route::post('/material-katalog/{artikel}/aliase', [MaterialKatalogController::class, 'speichereAlias'])->middleware('role:lager,projektleiter')->name('material-katalog.aliase.store');
    Route::post('/material-katalog/{artikel}/aliase/{alias}/loeschen', [MaterialKatalogController::class, 'loescheAlias'])->middleware('role:lager,projektleiter')->name('material-katalog.aliase.loeschen');

    // Einstellungen: Admin und Projektleitung.
    Route::get('/einstellungen', [EinstellungenController::class, 'zeige'])
        ->middleware('role:projektleiter')
        ->name('einstellungen');
    Route::post('/einstellungen', [EinstellungenController::class, 'speichere'])
        ->middleware('role:projektleiter')
        ->name('einstellungen.speichern');
});
