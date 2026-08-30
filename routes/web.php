<?php

use App\Http\Controllers\BestellungController;
use App\Http\Controllers\LagerController;
use App\Http\Controllers\MaterialKatalogController;
use App\Http\Controllers\ProjektController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    $module = [
        // route-Name => Seitentitel
        'dashboard' => 'Dashboard',
        'kunden' => 'Kunden',
        'anfragen' => 'Anfragen',
        'angebote' => 'Angebote',
        'logistik' => 'Logistik',
        'kalender' => 'Kalender',
        'lieferanten' => 'Lieferanten',
    ];

    foreach ($module as $route => $titel) {
        Route::view('/'.$route, 'pages.placeholder', ['titel' => $titel])->name($route);
    }

    Route::get('/projekte', [ProjektController::class, 'index'])->name('projekte');
    Route::get('/projekte/{projekt:nr}', [ProjektController::class, 'show'])->name('projekte.show');
    Route::post('/projekte/{projekt:nr}/konfiguration', [ProjektController::class, 'speichereKonfiguration'])
        ->name('projekte.konfiguration');
    Route::post('/projekte/{projekt:nr}/angebot', [ProjektController::class, 'erstelleAngebot'])
        ->name('projekte.angebot');

    Route::get('/bestellungen', [BestellungController::class, 'index'])->name('bestellungen');
    Route::get('/bestellungen/{bestellung:nr}', [BestellungController::class, 'show'])->name('bestellungen.show');
    Route::post('/bestellungen/{bestellung:nr}/status', [BestellungController::class, 'setzeStatus'])
        ->name('bestellungen.status');

    Route::get('/lager', [LagerController::class, 'index'])->name('lager');
    Route::get('/lager/artikel/{artikel}', [LagerController::class, 'artikel'])->name('lager.artikel');
    Route::post('/lager/wareneingang/{bestellung:nr}', [LagerController::class, 'bucheWareneingang'])
        ->name('lager.wareneingang.buchen');

    Route::get('/material-katalog', [MaterialKatalogController::class, 'index'])->name('material-katalog');

    // Einstellungen: Admin und Projektleitung.
    Route::view('/einstellungen', 'pages.placeholder', ['titel' => 'Einstellungen'])
        ->middleware('role:projektleiter')
        ->name('einstellungen');
});
