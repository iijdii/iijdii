<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rückmeldung des Kunden aus der Online-Annahme (Kommentar bei Annahme,
 * Ablehnung oder Überarbeitungswunsch). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('angebote', 'kunden_kommentar')) {
            Schema::table('angebote', function (Blueprint $table) {
                $table->text('kunden_kommentar')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('angebote', function (Blueprint $table) {
            $table->dropColumn('kunden_kommentar');
        });
    }
};
