<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wareneingaenge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bestellung_id')->constrained('bestellungen');
            $table->date('datum');
            $table->foreignId('benutzer_id')->nullable()->constrained('users');
            $table->string('lieferschein_nr', 32); // LS-88214
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wareneingaenge');
    }
};
