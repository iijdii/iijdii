<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufmaß-Bestätigung (Einheitssystem M10): der Verkäufer bestätigt die
 * Maße am Objekt, erst danach dürfen Bestellungen aus dem Projekt
 * erzeugt werden (harte Sperre im BestellungController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekte', function (Blueprint $table) {
            $table->timestamp('aufmass_bestaetigt_am')->nullable();
            $table->foreignId('aufmass_von')->nullable()->constrained('users');
            $table->boolean('vor_ort_gewesen')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('projekte', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aufmass_von');
            $table->dropColumn(['aufmass_bestaetigt_am', 'vor_ort_gewesen']);
        });
    }
};
