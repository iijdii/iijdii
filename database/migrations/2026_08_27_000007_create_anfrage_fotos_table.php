<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anfrage_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anfrage_id')->constrained('anfragen')->cascadeOnDelete();
            $table->string('pfad');
            $table->string('thumbnail_pfad')->nullable();
            $table->string('beschreibung')->nullable();
            $table->foreignId('erstellt_von')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anfrage_fotos');
    }
};
