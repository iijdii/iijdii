<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitpunkte der Lieferanten-Meldungen im Portal: «in Arbeit / bestellt»
 * und «fertig · abholbereit». Idempotent für Shared Hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bestellungen', function (Blueprint $table) {
            if (! Schema::hasColumn('bestellungen', 'in_arbeit_am')) {
                $table->timestamp('in_arbeit_am')->nullable();
            }
            if (! Schema::hasColumn('bestellungen', 'bereit_am')) {
                $table->timestamp('bereit_am')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bestellungen', function (Blueprint $table) {
            $table->dropColumn(['in_arbeit_am', 'bereit_am']);
        });
    }
};
