<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direkte Herkunfts-Verknüpfung Anfrage → Projekt. Bisher lief die Kette
 * nur über angebote.anfrage_id und riss bei app-erstellten Projekten ab —
 * „gibt es schon ein Projekt zu dieser Anfrage?" war nicht beantwortbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekte', function (Blueprint $table) {
            $table->foreignId('anfrage_id')->nullable()->constrained('anfragen');
        });
    }

    public function down(): void
    {
        Schema::table('projekte', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anfrage_id');
        });
    }
};
