<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lieferanten', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('ansprechpartner')->nullable();
            $table->string('email')->nullable();
            $table->string('telefon')->nullable();
            $table->string('strasse')->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('stadt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieferanten');
    }
};
