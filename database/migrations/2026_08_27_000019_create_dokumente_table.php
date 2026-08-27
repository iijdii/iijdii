<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->string('typ', 32); // angebot / auftrag / lieferschein / abnahmeprotokoll / ...
            $table->string('dateiname');
            $table->string('pfad')->nullable();
            $table->unsignedBigInteger('groesse')->default(0); // Bytes
            $table->date('datum')->nullable();
            $table->string('badge', 32)->nullable(); // Status-Chip
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumente');
    }
};
