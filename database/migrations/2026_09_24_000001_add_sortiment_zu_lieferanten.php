<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sortiment des Lieferanten («was liefert er uns») — sichtbar in der
 * Lieferanten-Übersicht. Idempotent für Shared Hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lieferanten', 'sortiment')) {
            Schema::table('lieferanten', function (Blueprint $table) {
                $table->string('sortiment')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('lieferanten', function (Blueprint $table) {
            $table->dropColumn('sortiment');
        });
    }
};
