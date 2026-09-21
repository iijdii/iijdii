<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anrede für Privatkunden (Herr, Frau, Familie, Andere) — Grundlage der
 * automatischen Anzeigenamen («Familie Weber»). Idempotent für Shared
 * Hosting (abgebrochene Läufe müssen wiederholbar sein).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kunden', 'anrede')) {
            Schema::table('kunden', function (Blueprint $table) {
                $table->string('anrede', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('kunden', function (Blueprint $table) {
            $table->dropColumn('anrede');
        });
    }
};
