<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artikel', function (Blueprint $table) {
            $table->id();
            $table->string('art_nr', 64)->unique();
            $table->string('name');
            $table->string('kategorie', 32)->index();
            $table->string('bild')->nullable();
            $table->string('einheit', 16);
            $table->string('lagerort', 32)->nullable();
            $table->integer('bestand')->default(0);
            $table->integer('min_bestand')->default(0);
            $table->decimal('ek_preis', 10, 2)->default(0);
            $table->foreignId('lieferant_id')->constrained('lieferanten');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artikel');
    }
};
