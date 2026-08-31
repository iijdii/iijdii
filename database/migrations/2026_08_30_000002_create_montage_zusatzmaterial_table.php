<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vor Ort erfasstes Zusatzmaterial — bewusst Freitext (Geschwindigkeit
 * auf der Baustelle, siehe Handoff); Nachberechnung erfolgt im Büro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('montage_zusatzmaterial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->string('bezeichnung');
            $table->string('menge', 32)->default('1');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('montage_zusatzmaterial');
    }
};
