<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abnahmeprotokoll nach deutschem Baurecht. Nach Unterzeichnung
 * (abgeschlossen_am gesetzt) unveränderlich — durchgesetzt per
 * updating-Guard im Model; PDF-Ablage folgt in einem späteren Milestone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abnahmeprotokolle', function (Blueprint $table) {
            $table->id();
            $table->string('nr', 32)->unique(); // AP-2026-0114
            $table->foreignId('projekt_id')->constrained('projekte');
            $table->string('art', 16); // ohne / vorbehalt / verweigert
            // Übergabe & Einweisung: 4 Checklisten-Flags
            $table->json('checkliste')->nullable();
            $table->string('ort')->nullable();
            $table->date('datum')->nullable();
            $table->string('unterschrift_auftraggeber_pfad')->nullable();
            $table->string('unterschrift_monteur_pfad')->nullable();
            $table->dateTime('abgeschlossen_am')->nullable();
            $table->foreignId('abgeschlossen_von')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abnahmeprotokolle');
    }
};
