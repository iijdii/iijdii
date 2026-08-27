<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservierter Bestand wird vom verfügbaren abgezogen,
 * bleibt aber im bestand-Feld des Artikels enthalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservierungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->foreignId('artikel_id')->constrained('artikel');
            $table->decimal('menge', 10, 2);
            $table->timestamps();
            $table->unique(['projekt_id', 'artikel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservierungen');
    }
};
