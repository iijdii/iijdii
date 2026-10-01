<?php

use App\Models\Projekt;
use Illuminate\Database\Migrations\Migration;

/**
 * Datenkorrektur: Projekte mit Dach-Position trugen in konfiguration die
 * Default-Extras (Keile, Schiebe-Elemente, Markisen), obwohl sie im
 * Projekt nicht vorkommen — sie erschienen als Positionen im Angebot.
 * Extras ohne Entsprechung in den Dach-Feldern werden geleert.
 * Idempotent; Alt-Projekte ohne Positionen bleiben unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Projekt::query()->whereNotNull('konfiguration')->with('positionen')->each(function (Projekt $projekt) {
            $dach = $projekt->positionen->firstWhere('gruppe', 'dach');
            $konfiguration = $projekt->konfiguration;
            if ($dach === null || array_key_exists('extras', $dach->felder ?? []) || ($konfiguration['extras'] ?? []) === []) {
                return;
            }
            $konfiguration['extras'] = [];
            $projekt->forceFill(['konfiguration' => $konfiguration])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Datenkorrektur — kein Rückweg nötig.
    }
};
