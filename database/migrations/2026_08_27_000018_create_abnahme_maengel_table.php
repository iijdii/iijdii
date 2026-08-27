<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abnahme_maengel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abnahmeprotokoll_id')->constrained('abnahmeprotokolle')->cascadeOnDelete();
            $table->text('text');
            $table->date('frist')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abnahme_maengel');
    }
};
