<?php

use App\Http\Controllers\LagerController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    $module = [
        // route-Name => Seitentitel
        'dashboard' => 'Dashboard',
        'kunden' => 'Kunden',
        'anfragen' => 'Anfragen',
        'angebote' => 'Angebote',
        'projekte' => 'Projekte',
        'bestellungen' => 'Bestellungen',
        'logistik' => 'Logistik',
        'kalender' => 'Kalender',
        'material-katalog' => 'Material-Katalog',
        'lieferanten' => 'Lieferanten',
    ];

    foreach ($module as $route => $titel) {
        Route::view('/'.$route, 'pages.placeholder', ['titel' => $titel])->name($route);
    }

    Route::get('/lager', [LagerController::class, 'index'])->name('lager');
    Route::get('/lager/artikel/{artikel}', [LagerController::class, 'artikel'])->name('lager.artikel');
    Route::post('/lager/wareneingang/{bestellung:nr}', [LagerController::class, 'bucheWareneingang'])
        ->name('lager.wareneingang.buchen');

    // Einstellungen: Admin und Projektleitung.
    Route::view('/einstellungen', 'pages.placeholder', ['titel' => 'Einstellungen'])
        ->middleware('role:projektleiter')
        ->name('einstellungen');
});
