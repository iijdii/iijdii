<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vom Verkäufer verwaltete Angebotspositionen: freie Zusatzpositionen
 * (JSON-Liste mit id/titel/menge/preis/rabatt) und ausgeblendete
 * generierte Positionen (JSON-Liste der Keys). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['freie_positionen', 'ausgeblendet'] as $spalte) {
            if (! Schema::hasColumn('angebote', $spalte)) {
                Schema::table('angebote', fn (Blueprint $table) => $table->json($spalte)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::table('angebote', function (Blueprint $table) {
            $table->dropColumn(['freie_positionen', 'ausgeblendet']);
        });
    }
};
