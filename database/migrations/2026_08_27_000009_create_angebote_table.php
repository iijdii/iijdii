<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('angebote', function (Blueprint $table) {
            $table->id();
            $table->string('nr', 32)->unique(); // ANG-2026-010
            $table->foreignId('kunde_id')->constrained('kunden');
            $table->foreignId('anfrage_id')->nullable()->constrained('anfragen');
            $table->string('titel')->nullable();
            $table->string('status', 32)->default('entwurf')->index();
            $table->date('datum')->nullable();
            $table->decimal('summe', 12, 2)->nullable();
            $table->json('konfiguration')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('angebote');
    }
};
