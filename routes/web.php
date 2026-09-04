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
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Logistik: /logistik/touren/* VOR dem {bestellung:nr}-Wildcard registrieren.
    Route::get('/logistik', [LogistikController::class, 'index'])->name('logistik');
    Route::post('/logistik/touren', [LogistikController::class, 'erstelleTour'])->name('logistik.touren.erstellen');
    Route::get('/logistik/touren/{tour:nr}', [LogistikController::class, 'tour'])->name('logistik.tour');
    Route::post('/logistik/touren/{tour:nr}/alle', [LogistikController::class, 'setzeTourAlle'])->name('logistik.tour.alle');
    Route::post('/logistik/touren/{tour:nr}/abschliessen', [LogistikController::class, 'schliesseTourAb'])->name('logistik.tour.abschliessen');
    Route::get('/logistik/touren/{tour:nr}/lade', [LogistikController::class, 'lade'])->name('logistik.lade');
    Route::post('/logistik/touren/{tour:nr}/abfahrt', [LogistikController::class, 'bestaetigeAbfahrt'])->name('logistik.abfahrt');
    Route::get('/logistik/{bestellung:nr}', [LogistikController::class, 'bestellung'])->name('logistik.bestellung');
    Route::post('/logistik/{bestellung:nr}/positionen/{position}/toggle', [LogistikController::class, 'togglePosition'])->name('logistik.position.toggle');
    Route::post('/logistik/{bestellung:nr}/positionen/{position}/notiz', [LogistikController::class, 'speichereNotiz'])->name('logistik.notiz');
    Route::post('/logistik/{bestellung:nr}/alle', [LogistikController::class, 'setzeAlle'])->name('logistik.alle');
    Route::post('/logistik/{bestellung:nr}/abschliessen', [LogistikController::class, 'schliesseAb'])->name('logistik.abschliessen');

    Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender');
    Route::get('/lieferanten', [LieferantController::class, 'index'])->name('lieferanten');

    Route::get('/kunden', [KundeController::class, 'index'])->name('kunden');
    Route::get('/kunden/neu', [KundeController::class, 'create'])->name('kunden.create');
    Route::post('/kunden', [KundeController::class, 'store'])->name('kunden.store');
    Route::get('/kunden/{kunde:kunden_nr}', [KundeController::class, 'show'])->name('kunden.show');
    Route::get('/kunden/{kunde:kunden_nr}/bearbeiten', [KundeController::class, 'edit'])->name('kunden.edit');
    Route::put('/kunden/{kunde:kunden_nr}', [KundeController::class, 'update'])->name('kunden.update');
    Route::get('/angebote', [AngebotController::class, 'index'])->name('angebote');
    Route::get('/angebote/{angebot:nr}', [AngebotController::class, 'show'])->name('angebote.show');
    Route::post('/angebote/{angebot:nr}/status', [AngebotController::class, 'setzeStatus'])->name('angebote.status');
    Route::post('/angebote/{angebot:nr}/summe', [AngebotController::class, 'speichereSumme'])->name('angebote.summe');
    Route::get('/angebote/{angebot:nr}/pdf', [AngebotController::class, 'pdf'])->name('angebote.pdf');

    Route::get('/anfragen', [AnfrageController::class, 'index'])->name('anfragen');
    Route::get('/anfragen/neu', [AnfrageController::class, 'create'])->name('anfragen.create');
    Route::post('/anfragen', [AnfrageController::class, 'store'])->name('anfragen.store');
    Route::get('/anfragen/{anfrage:nummer}', [AnfrageController::class, 'show'])->name('anfragen.show');
    Route::get('/anfragen/{anfrage:nummer}/bearbeiten', [AnfrageController::class, 'edit'])->name('anfragen.edit');
    Route::put('/anfragen/{anfrage:nummer}', [AnfrageController::class, 'update'])->name('anfragen.update');
    Route::post('/anfragen/{anfrage:nummer}/status', [AnfrageController::class, 'setzeStatus'])->name('anfragen.status');
    Route::post('/anfragen/{anfrage:nummer}/projekt', [AnfrageController::class, 'erstelleProjekt'])->name('anfragen.projekt');

    Route::get('/projekte', [ProjektController::class, 'index'])->name('projekte');
    Route::get('/projekte/{projekt:nr}', [ProjektController::class, 'show'])->name('projekte.show');
    Route::get('/projekte/{projekt:nr}/pdf', [ProjektController::class, 'pdf'])->name('projekte.pdf');
    Route::post('/projekte/{projekt:nr}/dokumente', [ProjektController::class, 'ladeDokumentHoch'])->name('projekte.dokumente.upload');
    Route::post('/projekte/{projekt:nr}/konfiguration', [ProjektController::class, 'speichereKonfiguration'])
        ->name('projekte.konfiguration');
    Route::post('/projekte/{projekt:nr}/angebot', [ProjektController::class, 'erstelleAngebot'])
        ->name('projekte.angebot');

    Route::get('/projekte/{projekt:nr}/montage', [MontageController::class, 'zeige'])->name('projekte.montage');
    Route::post('/projekte/{projekt:nr}/montage/aufmass', [MontageController::class, 'speichereAufmass'])->name('projekte.montage.aufmass');
    Route::post('/projekte/{projekt:nr}/montage/led', [MontageController::class, 'toggleLed'])->name('projekte.montage.led');
    Route::post('/projekte/{projekt:nr}/montage/notizen', [MontageController::class, 'speichereNotiz'])->name('projekte.montage.notizen');
    Route::post('/projekte/{projekt:nr}/montage/notizen/{notiz}/loeschen', [MontageController::class, 'loescheNotiz'])->name('projekte.montage.notizen.loeschen');
    Route::post('/projekte/{projekt:nr}/montage/material', [MontageController::class, 'speichereMaterial'])->name('projekte.montage.material');
    Route::post('/projekte/{projekt:nr}/montage/material/{zeile}/loeschen', [MontageController::class, 'loescheMaterial'])->name('projekte.montage.material.loeschen');
    Route::post('/projekte/{projekt:nr}/montage/aufgaben', [MontageController::class, 'speichereAufgabe'])->name('projekte.montage.aufgaben');
    Route::post('/projekte/{projekt:nr}/montage/aufgaben/{aufgabe}/erledigt', [MontageController::class, 'toggleAufgabe'])->name('projekte.montage.aufgaben.erledigt');

    Route::get('/projekte/{projekt:nr}/abnahme', [AbnahmeController::class, 'formular'])->name('projekte.abnahme');
    Route::post('/projekte/{projekt:nr}/abnahme', [AbnahmeController::class, 'speichere'])->name('projekte.abnahme.speichern');
    Route::get('/dokumente/{dokument}', [DokumentController::class, 'download'])->name('dokumente.download');

    Route::get('/bestellungen', [BestellungController::class, 'index'])->name('bestellungen');
    Route::get('/bestellungen/neu', [BestellungController::class, 'create'])->name('bestellungen.create');
    Route::post('/bestellungen', [BestellungController::class, 'store'])->name('bestellungen.store');
    Route::get('/bestellungen/{bestellung:nr}', [BestellungController::class, 'show'])->name('bestellungen.show');
    Route::get('/bestellungen/{bestellung:nr}/bearbeiten', [BestellungController::class, 'edit'])->name('bestellungen.edit');
    Route::put('/bestellungen/{bestellung:nr}', [BestellungController::class, 'update'])->name('bestellungen.update');
    Route::post('/bestellungen/{bestellung:nr}/positionen', [BestellungController::class, 'speicherePosition'])->name('bestellungen.positionen.store');
    Route::post('/bestellungen/{bestellung:nr}/positionen/{position}/loeschen', [BestellungController::class, 'loeschePosition'])->name('bestellungen.positionen.loeschen');
    Route::get('/bestellungen/{bestellung:nr}/pdf', [BestellungController::class, 'pdf'])->name('bestellungen.pdf');
    Route::post('/bestellungen/{bestellung:nr}/status', [BestellungController::class, 'setzeStatus'])
        ->name('bestellungen.status');

    Route::get('/lager', [LagerController::class, 'index'])->name('lager');
    Route::get('/lager/artikel/{artikel}', [LagerController::class, 'artikel'])->name('lager.artikel');
    Route::post('/lager/artikel/{artikel}/korrektur', [LagerController::class, 'bucheKorrektur'])->name('lager.korrektur');
    Route::get('/lager/wareneingang/{bestellung:nr}/lieferschein', [LagerController::class, 'lieferschein'])->name('lager.lieferschein');
    Route::post('/lager/wareneingang/{bestellung:nr}', [LagerController::class, 'bucheWareneingang'])
        ->name('lager.wareneingang.buchen');

    Route::get('/material-katalog', [MaterialKatalogController::class, 'index'])->name('material-katalog');
    Route::get('/material-katalog/neu', [MaterialKatalogController::class, 'create'])->name('material-katalog.create');
    Route::post('/material-katalog', [MaterialKatalogController::class, 'store'])->name('material-katalog.store');
    Route::get('/material-katalog/{artikel}/bearbeiten', [MaterialKatalogController::class, 'edit'])->name('material-katalog.edit');
    Route::put('/material-katalog/{artikel}', [MaterialKatalogController::class, 'update'])->name('material-katalog.update');
    Route::post('/material-katalog/{artikel}/aliase', [MaterialKatalogController::class, 'speichereAlias'])->name('material-katalog.aliase.store');
    Route::post('/material-katalog/{artikel}/aliase/{alias}/loeschen', [MaterialKatalogController::class, 'loescheAlias'])->name('material-katalog.aliase.loeschen');

    // Einstellungen: Admin und Projektleitung.
    Route::get('/einstellungen', [EinstellungenController::class, 'zeige'])
        ->middleware('role:projektleiter')
        ->name('einstellungen');
    Route::post('/einstellungen', [EinstellungenController::class, 'speichere'])
        ->middleware('role:projektleiter')
        ->name('einstellungen.speichern');
});
