<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferanten-Portal (M14): ein Benutzer mit Rolle «lieferant» gehört zu
 * genau einem Lieferanten und sieht im Portal nur dessen Bestellungen.
 *
 * Wie 2026_09_10_000002 idempotent aufgebaut: Shared Hosting bricht
 * Requests mitten im DDL ab, ein Wiederholungslauf muss auf halb
 * angewendetem Schema durchlaufen; der Fremdschlüssel ist optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'lieferant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('lieferant_id')->nullable();
            });
        }

        $fkFehlt = ! collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $fk) => $fk['columns'] === ['lieferant_id']);
        if ($fkFehlt) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('lieferant_id')
                        ->references('id')->on('lieferanten')->nullOnDelete();
                });
            } catch (Throwable) {
                // Reine Absicherung — manche Shared-Hosting-Datenbanken
                // legen den Fremdschlüssel nicht an (errno 150).
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lieferant_id');
        });
    }
};
