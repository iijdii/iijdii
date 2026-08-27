<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wareneingang_positionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wareneingang_id')->constrained('wareneingaenge')->cascadeOnDelete();
            $table->foreignId('artikel_id')->constrained('artikel');
            $table->foreignId('bestellung_position_id')->nullable()->constrained('bestellung_positionen');
            $table->decimal('menge', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wareneingang_positionen');
    }
};
