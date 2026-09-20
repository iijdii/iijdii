<?php

/**
 * LEA CRM — Deployment-Prüfung (Standalone, ohne Framework).
 *
 * Eine einzelne Datei neben die index.php legen und im Browser öffnen:
 * sie zeigt, welcher Patch-Stand wirklich auf der Platte liegt, ob die
 * Vite-Assets da sind und ob kompilierte Blade-Views veralteter sind
 * als die hochgeladenen Templates (häufigste Ursache für «Änderungen
 * sind nicht sichtbar» auf Shared Hosting: der Zip-Entpacker behält
 * alte Datumsstempel, Laravel hält die kompilierte Kopie für aktuell).
 *
 * Aktionen (Cache leeren) verlangen den SETUP_TOKEN aus der .env:
 *   pruefung.php?leeren=1&token=XYZ
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

// Laravel-Wurzel finden: das Skript liegt in public/ oder direkt im Docroot.
$wurzel = null;
foreach ([__DIR__.'/..', __DIR__, __DIR__.'/../..'] as $kandidat) {
    if (is_file($kandidat.'/artisan')) {
        $wurzel = realpath($kandidat);
        break;
    }
}

$z = [];
$z[] = '<h2>LEA CRM — Deployment-Prüfung</h2>';
$z[] = '<p>PHP '.PHP_VERSION.' · Serverzeit '.date('Y-m-d H:i:s').'</p>';

if ($wurzel === null) {
    $z[] = '<p style="color:red"><b>artisan nicht gefunden</b> — dieses Skript neben die
        index.php der Anwendung legen (im selben Ordner, in dem auch die Ordner
        app/, routes/, resources/ bzw. ../app liegen).</p>';
    echo implode("\n", $z);
    exit;
}

$mt = fn (string $pfad): string => is_file($pfad) ? date('d.m. H:i:s', (int) filemtime($pfad)) : '—';

// 1) Patch-Stand laut Dateien
$versionDatei = $wurzel.'/app/Support/Version.php';
$patch = 'Datei fehlt (Stand vor v20)';
if (is_file($versionDatei) && preg_match("/PATCH = '(v\\d+)'/", (string) file_get_contents($versionDatei), $t)) {
    $patch = $t[1];
}
$z[] = '<h3>1 · Dateien auf der Platte</h3><ul>';
$z[] = '<li>Laravel-Wurzel: <code>'.htmlspecialchars($wurzel).'</code></li>';
$z[] = '<li>Patch-Stand laut app/Support/Version.php: <b>'.htmlspecialchars($patch).'</b></li>';
$z[] = '<li>routes/web.php: '.$mt($wurzel.'/routes/web.php').'</li>';
$z[] = '<li>login.blade.php: '.$mt($wurzel.'/resources/views/auth/login.blade.php').'</li>';
$z[] = '<li>montage.blade.php (LED-Partial): '.$mt($wurzel.'/resources/views/projekte/montage-partials/led.blade.php').'</li>';
$z[] = '</ul>';

// 2) Vite-Build
$buildOrdner = null;
foreach ([$wurzel.'/public/build', __DIR__.'/build'] as $kandidat) {
    if (is_dir($kandidat)) {
        $buildOrdner = $kandidat;
        break;
    }
}
$z[] = '<h3>2 · Vite-Build (public/build)</h3>';
if ($buildOrdner === null) {
    $z[] = '<p style="color:red"><b>public/build fehlt komplett</b> — die Assets aus dem
        Patch wurden nicht hochgeladen.</p>';
} else {
    $assets = glob($buildOrdner.'/assets/*') ?: [];
    $z[] = '<ul><li>Ordner: <code>'.htmlspecialchars($buildOrdner).'</code></li>';
    foreach ($assets as $asset) {
        $z[] = '<li><code>'.htmlspecialchars(basename($asset)).'</code> · '.$mt($asset).'</li>';
    }
    $manifest = $buildOrdner.'/manifest.json';
    $z[] = '</ul>';
    if (is_file($manifest) && preg_match_all('/assets\/[a-z-]+-[A-Za-z0-9_-]+\.(?:js|css)/i', (string) file_get_contents($manifest), $t)) {
        $z[] = '<p>Laut manifest.json lädt die Seite: <code>'
            .implode('</code> · <code>', array_map('htmlspecialchars', array_unique($t[0])))
            .'</code> — jede dieser Dateien muss oben in der Liste stehen, sonst fehlt der Upload.</p>';
    } else {
        $z[] = '<p style="color:red"><b>manifest.json fehlt</b> — public/build unvollständig hochgeladen.</p>';
    }
}

// 3) Kompilierte Views vs. Templates
$viewCache = $wurzel.'/storage/framework/views';
$kompiliert = is_dir($viewCache) ? (glob($viewCache.'/*.php') ?: []) : [];
$neuesteKompilierung = 0;
foreach ($kompiliert as $datei) {
    $neuesteKompilierung = max($neuesteKompilierung, (int) filemtime($datei));
}
// Entscheidender Frische-Test: das Login-Template (ab v20) enthält
// Version::PATCH — steckt der Marker in KEINER kompilierten Datei,
// obwohl die Login-Seite besucht wurde, serviert Laravel alte Kopien.
$markerKompiliert = false;
foreach ($kompiliert as $datei) {
    if (str_contains((string) file_get_contents($datei), 'Version::PATCH')) {
        $markerKompiliert = true;
        break;
    }
}
$loginBlade = $wurzel.'/resources/views/auth/login.blade.php';
$markerImTemplate = is_file($loginBlade)
    && str_contains((string) file_get_contents($loginBlade), 'Version::PATCH');
$z[] = '<h3>3 · Kompilierte Views</h3><ul>';
$z[] = '<li>'.count($kompiliert).' kompilierte Templates, neueste vom '
    .($neuesteKompilierung ? date('d.m. H:i:s', $neuesteKompilierung) : '—').'</li>';
$z[] = '</ul>';
if (! $markerImTemplate) {
    $z[] = '<p style="color:red"><b>login.blade.php ist noch der alte Stand (vor v20)</b> —
        der Datei-Upload kommt nicht an. Prüfen, ob wirklich in diesen Ordner
        (siehe Laravel-Wurzel oben) hochgeladen wird.</p>';
} elseif ($markerKompiliert) {
    $z[] = '<p style="color:green">View-Cache ist aktuell — die Login-Seite zeigt den
        Patch-Stand bereits an.</p>';
} else {
    $z[] = '<p style="color:#b45309"><b>Templates sind neu, aber noch keine kompilierte
        Version davon</b> — unten den Cache leeren und die Login-Seite neu laden.</p>';
}

// 4) Aktion: Caches leeren (Token aus .env nötig)
$token = '';
$envDatei = $wurzel.'/.env';
if (is_file($envDatei) && preg_match('/^SETUP_TOKEN=(.*)$/m', (string) file_get_contents($envDatei), $t)) {
    $token = trim($t[1], " \"'");
}
$z[] = '<h3>4 · View-/Bootstrap-Cache leeren</h3>';
if (isset($_GET['leeren'])) {
    $uebergeben = (string) ($_GET['token'] ?? '');
    if ($token === '' || ! hash_equals($token, $uebergeben)) {
        $z[] = '<p style="color:red">Token fehlt oder falsch — SETUP_TOKEN in der .env setzen
            und als ?leeren=1&amp;token=… übergeben.</p>';
    } else {
        $geloescht = 0;
        foreach (array_merge($kompiliert, glob($wurzel.'/bootstrap/cache/*.php') ?: []) as $datei) {
            if (@unlink($datei)) {
                $geloescht++;
            }
        }
        $z[] = '<p style="color:green"><b>'.$geloescht.' Cache-Dateien gelöscht.</b> Jetzt die
            Login-Seite neu laden — unten muss der Patch-Stand stehen.</p>';
    }
} elseif ($token !== '') {
    $z[] = '<p><a href="?leeren=1&amp;token='.rawurlencode($token).'">Jetzt leeren</a>
        (Token aus der .env erkannt).</p>';
} else {
    $z[] = '<p>Zum Leeren SETUP_TOKEN in der .env setzen und diese Seite mit
        <code>?leeren=1&amp;token=…</code> aufrufen.</p>';
}

$z[] = '<hr><p>Nach der Diagnose diese Datei (pruefung.php) wieder vom Server löschen.</p>';
echo implode("\n", $z);
