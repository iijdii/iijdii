<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benutzer sperren statt löschen: gesperrte Zugänge können sich nicht
 * mehr anmelden, ihre Einträge (Bestellungen, Aufmaß …) bleiben erhalten.
 * Idempotent für Shared Hosting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'aktiv')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('aktiv')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('aktiv');
        });
    }
};
