<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bestellungen', function (Blueprint $table) {
            $table->id();
            $table->string('nr', 32)->unique(); // BST-2026-112
            $table->foreignId('lieferant_id')->constrained('lieferanten');
            $table->string('titel');
            $table->string('kategorie', 32)->nullable(); // glas / aluminium / gemischt
            $table->foreignId('projekt_id')->nullable()->constrained('projekte');
            $table->foreignId('kunde_id')->nullable()->constrained('kunden');
            $table->foreignId('ersteller_id')->nullable()->constrained('users');
            $table->date('liefertermin')->nullable();
            $table->string('status', 32)->default('entwurf')->index();
            $table->text('notizen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bestellungen');
    }
};
