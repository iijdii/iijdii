<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reiche Konstruktionsdaten der Anfrage (Keile mit Maßen, Seitenwände,
 * Schiebesysteme, Trapez-Längen, Fassade …) — Struktur wie anfCfg im
 * Prototyp. Die skalaren Spalten bleiben führend; details ergänzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anfragen', function (Blueprint $table) {
            $table->json('details')->nullable()->after('wunsche_sonstiges');
        });
    }

    public function down(): void
    {
        Schema::table('anfragen', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
