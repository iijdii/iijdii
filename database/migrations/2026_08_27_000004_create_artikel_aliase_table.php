<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artikel_aliase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artikel_id')->constrained('artikel')->cascadeOnDelete();
            $table->string('alias');
            // Kollationsunabhängiger Abgleich (MySQL CI vs. SQLite CS):
            // Lookups laufen immer über die normalisierte Spalte.
            $table->string('alias_normalized')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artikel_aliase');
    }
};
