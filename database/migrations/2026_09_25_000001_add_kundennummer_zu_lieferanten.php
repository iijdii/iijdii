<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unsere Kundennummer beim Lieferanten (z. B. Varisol «D.60986») — steht
 * im Kopf der Bestellblätter. Idempotent für Shared Hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lieferanten', 'kundennummer')) {
            Schema::table('lieferanten', function (Blueprint $table) {
                $table->string('kundennummer', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('lieferanten', function (Blueprint $table) {
            $table->dropColumn('kundennummer');
        });
    }
};
