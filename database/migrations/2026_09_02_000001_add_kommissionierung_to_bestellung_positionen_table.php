<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kommissionier-Zustand je Position (Logistik/Rüstliste): gepackt-Häkchen
 * und optionaler Kommentar — Persistenz des Prototyp-States logiTaken /
 * logiNotes (Schlüssel «BST-…|g1/s0/m0» ↦ 1:1 eine Positionszeile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bestellung_positionen', function (Blueprint $table) {
            $table->timestamp('kommissioniert_am')->nullable();
            $table->string('kommissionier_notiz')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bestellung_positionen', function (Blueprint $table) {
            $table->dropColumn(['kommissioniert_am', 'kommissionier_notiz']);
        });
    }
};
