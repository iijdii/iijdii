<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angebot als Vertrag (Spez. v3.2): Gültigkeitsdatum, globaler Rabatt,
 * Preis-Overrides je Position (JSON), permanenter Annahme-Token für die
 * Online-Annahme sowie Zeitpunkt/IP der Annahme. Idempotent für Shared
 * Hosting (abgebrochene Läufe müssen wiederholbar sein).
 */
return new class extends Migration
{
    public function up(): void
    {
        $spalten = [
            'gueltig_bis' => fn (Blueprint $t) => $t->date('gueltig_bis')->nullable(),
            'rabatt_prozent' => fn (Blueprint $t) => $t->decimal('rabatt_prozent', 5, 2)->default(0),
            'preise' => fn (Blueprint $t) => $t->json('preise')->nullable(),
            'accept_token' => fn (Blueprint $t) => $t->string('accept_token', 64)->nullable()->index(),
            'angenommen_am' => fn (Blueprint $t) => $t->timestamp('angenommen_am')->nullable(),
            'angenommen_ip' => fn (Blueprint $t) => $t->string('angenommen_ip', 45)->nullable(),
        ];

        foreach ($spalten as $name => $definition) {
            if (! Schema::hasColumn('angebote', $name)) {
                Schema::table('angebote', fn (Blueprint $table) => $definition($table));
            }
        }
    }

    public function down(): void
    {
        Schema::table('angebote', function (Blueprint $table) {
            $table->dropColumn(['gueltig_bis', 'rabatt_prozent', 'preise', 'accept_token', 'angenommen_am', 'angenommen_ip']);
        });
    }
};
