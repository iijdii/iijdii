<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestell-Entwurf aus Projektpositionen (M10): der Entwurf entsteht ohne
 * Lieferanten (der Verkäufer wählt ihn im Entwurf), und jede erzeugte
 * Position kennt ihre Herkunfts-Projektposition (Rückverfolgbarkeit,
 * Grundlage der Endmaße-Nachbestellung in M11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bestellungen', function (Blueprint $table) {
            $table->foreignId('lieferant_id')->nullable()->change();
        });
        Schema::table('bestellung_positionen', function (Blueprint $table) {
            $table->foreignId('projekt_position_id')->nullable()
                ->constrained('projekt_positionen')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bestellung_positionen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('projekt_position_id');
        });
        Schema::table('bestellungen', function (Blueprint $table) {
            $table->foreignId('lieferant_id')->nullable(false)->change();
        });
    }
};
