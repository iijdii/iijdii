<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('montage_notizen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekte')->cascadeOnDelete();
            $table->string('typ', 16); // hinweis | problem | aenderung
            $table->text('text');
            $table->foreignId('erstellt_von')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('montage_notizen');
    }
};
