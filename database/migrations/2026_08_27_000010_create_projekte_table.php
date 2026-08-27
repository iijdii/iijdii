<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projekte', function (Blueprint $table) {
            $table->id();
            $table->string('nr', 32)->unique(); // PRJ-2026-011
            $table->string('titel');
            $table->foreignId('kunde_id')->constrained('kunden');
            $table->foreignId('angebot_id')->nullable()->constrained('angebote');
            $table->string('objekt_strasse')->nullable();
            $table->string('objekt_hausnummer', 16)->nullable();
            $table->string('objekt_plz', 10)->nullable();
            $table->string('objekt_stadt')->nullable();
            $table->string('status', 32)->default('in_planung')->index();
            $table->date('termin_von')->nullable();
            $table->date('termin_bis')->nullable();
            // Konfigurator-Payload: Maße, Glas, Pfosten, Entwässerung,
            // Wandanschluss, LED-Anzahl usw.
            $table->json('konfiguration')->nullable();
            // Ist-Werte aus dem Aufmaß vor Ort (Montage-Modus)
            $table->json('aufmass')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projekte');
    }
};
