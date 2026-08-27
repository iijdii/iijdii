<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunden', function (Blueprint $table) {
            $table->id();
            $table->string('kunden_nr', 32)->unique();
            $table->string('typ', 16)->default('privat'); // privat | gewerbe
            $table->string('anzeigename');
            $table->string('vorname')->nullable();
            $table->string('nachname')->nullable();
            $table->string('firma')->nullable();
            $table->string('ansprechpartner')->nullable();
            $table->string('email')->nullable();
            $table->string('telefon')->nullable();
            $table->string('strasse')->nullable();
            $table->string('hausnummer', 16)->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('stadt')->nullable();
            $table->string('region')->nullable();
            $table->string('quelle')->nullable();
            $table->json('tags')->nullable();
            $table->text('notizen')->nullable();
            $table->string('status', 32)->default('Lead')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunden');
    }
};
