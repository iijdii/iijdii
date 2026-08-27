<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine Tabelle für alle drei Positionsarten (typ-Diskriminator:
 * glas / schiebe / material). Ein einziger FK erlaubt es dem
 * Wareneingang und der Projekt-Materialliste, Positionen einheitlich
 * zu referenzieren — Grundlage der automatischen Einlagerung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestellung_positionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bestellung_id')->constrained('bestellungen')->cascadeOnDelete();
            $table->string('typ', 16)->index(); // glas / schiebe / material
            $table->unsignedSmallInteger('pos')->default(1);
            $table->string('bezeichnung');
            // Auflösung Freitext → Artikel über artikel_aliase
            $table->foreignId('artikel_id')->nullable()->constrained('artikel');
            $table->decimal('menge', 10, 2)->default(1);
            $table->string('einheit', 16)->nullable();
            $table->unsignedInteger('breite_mm')->nullable();
            $table->unsignedInteger('hoehe_mm')->nullable();
            // Typspezifisches: Glasform (Rechteck/Trapez, hL/hR),
            // Schiebe (Elementanzahl, Richtung), Herkunft (live/manuell)
            $table->json('details')->nullable();
            $table->boolean('eingelagert')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestellung_positionen');
    }
};
