<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bewegungsjournal: jede Bestandsänderung schreibt genau eine Zeile.
 * Der Bewegungen-Tab im Lager zeigt diese Tabelle unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lagerbewegungen', function (Blueprint $table) {
            $table->id();
            $table->dateTime('datum')->index();
            $table->string('typ', 16); // Eingang / Ausgang / Reservierung / Korrektur
            $table->foreignId('artikel_id')->constrained('artikel');
            $table->integer('menge'); // vorzeichenbehaftet
            $table->string('referenz')->nullable(); // BST-…, PRJ-…, Inventur KW 28
            $table->foreignId('benutzer_id')->nullable()->constrained('users');
            $table->string('benutzer_name')->nullable(); // Anzeige, z. B. "S. Krüger · Lager"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lagerbewegungen');
    }
};
