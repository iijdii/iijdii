<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rabatt je Angebotsposition (Prozent, JSON key => prozent) — ergänzt
 * den globalen rabatt_prozent. Idempotent für Shared Hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('angebote', 'rabatte')) {
            Schema::table('angebote', function (Blueprint $table) {
                $table->json('rabatte')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('angebote', function (Blueprint $table) {
            $table->dropColumn('rabatte');
        });
    }
};
