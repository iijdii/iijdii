<?php

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
        'lager' => 'Lager',
        'material-katalog' => 'Material-Katalog',
        'lieferanten' => 'Lieferanten',
    ];

    foreach ($module as $route => $titel) {
        Route::view('/'.$route, 'pages.placeholder', ['titel' => $titel])->name($route);
    }

    // Einstellungen: Admin und Projektleitung.
    Route::view('/einstellungen', 'pages.placeholder', ['titel' => 'Einstellungen'])
        ->middleware('role:projektleiter')
        ->name('einstellungen');
});
