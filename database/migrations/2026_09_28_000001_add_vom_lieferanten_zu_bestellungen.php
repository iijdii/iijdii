<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merker: der aktuelle Status (In Arbeit / Abholbereit) wurde vom
 * Lieferanten im Portal gemeldet — für die Kennzeichnung in LEAs
 * Bestelllisten. Idempotent für Shared Hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bestellungen', 'vom_lieferanten')) {
            Schema::table('bestellungen', function (Blueprint $table) {
                $table->boolean('vom_lieferanten')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('bestellungen', function (Blueprint $table) {
            $table->dropColumn('vom_lieferanten');
        });
    }
};
