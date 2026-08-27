<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Montage-Checkliste (offener Punkt des Handoffs): Büro legt Aufgaben
 * pro Termin an, Monteur hakt ab. Nur Schema in diesem Milestone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('montage_aufgaben', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->date('datum')->nullable();
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->foreignId('erstellt_von')->nullable()->constrained('users');
            $table->foreignId('erledigt_von')->nullable()->constrained('users');
            $table->dateTime('erledigt_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('montage_aufgaben');
    }
};
