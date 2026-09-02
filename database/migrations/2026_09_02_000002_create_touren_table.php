<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Touren (Fahrzeugbeladung): mehrere Bestellungen je Fahrzeug/Termin
 * (Prototyp logiTours). Pivot ohne Zusatzfelder — der Ladefortschritt
 * lebt auf den Positionen (kommissioniert_am).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('touren', function (Blueprint $table) {
            $table->id();
            $table->string('nr', 20)->unique(); // TOUR-2026-042
            $table->string('fahrzeug');
            $table->string('kennzeichen', 20);
            $table->date('datum')->nullable();
            $table->string('fahrer');
            $table->timestamps();
        });

        Schema::create('tour_bestellung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('touren')->cascadeOnDelete();
            $table->foreignId('bestellung_id')->constrained('bestellungen')->cascadeOnDelete();
            $table->unique(['tour_id', 'bestellung_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_bestellung');
        Schema::dropIfExists('touren');
    }
};
