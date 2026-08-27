<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anfrage_aktivitaeten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anfrage_id')->constrained('anfragen')->cascadeOnDelete();
            $table->string('typ', 48); // status_geaendert / kommentar_hinzugefuegt / ...
            $table->foreignId('von_user_id')->nullable()->constrained('users');
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anfrage_aktivitaeten');
    }
};
