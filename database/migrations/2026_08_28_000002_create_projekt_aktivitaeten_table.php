<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projekt_aktivitaeten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->string('titel');
            $table->string('wer')->nullable();
            $table->string('datum', 16)->nullable(); // Anzeige wie im Prototyp ("05.05.", "gestern")
            $table->string('status', 8)->nullable(); // done | now | null
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projekt_aktivitaeten');
    }
};
