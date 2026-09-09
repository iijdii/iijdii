<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produkt-Positionen eines Projekts — der eine Konfigurator-Speicher der
 * Einheitssystem-Architektur (Betreiber-Entscheid 2026-09-09): die Anfrage
 * befüllt sie beim automatischen Projekt-Start, Angebot/Bestellungen/
 * Montage lesen sie. Drei Gruppen: dach (genau eine Position, wird nach
 * projekte.konfiguration gespiegelt), extra und sonnenschutz (Phase 2 —
 * Endmaße nach der Dachmontage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projekt_positionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->unsignedSmallInteger('pos')->default(1);
            $table->string('gruppe', 16)->index();
            $table->string('produkt', 32)->index();
            $table->json('felder')->nullable();
            $table->unsignedTinyInteger('phase')->default(1);
            $table->json('endmasse')->nullable();
            $table->foreignId('endmasse_von')->nullable()->constrained('users');
            $table->timestamp('endmasse_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projekt_positionen');
    }
};
