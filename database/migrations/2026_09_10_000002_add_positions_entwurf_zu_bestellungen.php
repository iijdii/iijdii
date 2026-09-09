<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestell-Entwurf aus Projektpositionen (M10): der Entwurf entsteht ohne
 * Lieferanten (der Verkäufer wählt ihn im Entwurf), und jede erzeugte
 * Position kennt ihre Herkunfts-Projektposition (Rückverfolgbarkeit,
 * Grundlage der Endmaße-Nachbestellung in M11).
 *
 * Jeder Schritt prüft den Ist-Zustand: Shared Hosting bricht lange
 * Requests mitten im DDL ab, und MySQL kennt keine DDL-Transaktionen —
 * ein Wiederholungslauf muss auf halb angewendetem Schema durchlaufen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bestellungen', function (Blueprint $table) {
            $table->foreignId('lieferant_id')->nullable()->change();
        });

        if (! Schema::hasColumn('bestellung_positionen', 'projekt_position_id')) {
            Schema::table('bestellung_positionen', function (Blueprint $table) {
                $table->foreignId('projekt_position_id')->nullable();
            });
        }

        $fkFehlt = ! collect(Schema::getForeignKeys('bestellung_positionen'))
            ->contains(fn (array $fk) => $fk['columns'] === ['projekt_position_id']);
        if ($fkFehlt) {
            Schema::table('bestellung_positionen', function (Blueprint $table) {
                $table->foreign('projekt_position_id')
                    ->references('id')->on('projekt_positionen')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('bestellung_positionen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('projekt_position_id');
        });
        Schema::table('bestellungen', function (Blueprint $table) {
            $table->foreignId('lieferant_id')->nullable(false)->change();
        });
    }
};
