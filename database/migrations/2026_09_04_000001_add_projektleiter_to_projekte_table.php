<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projektleitung als echte Verantwortung statt hartkodiertem
 * „Max Schneider" in den Views.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekte', function (Blueprint $table) {
            $table->foreignId('projektleiter_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('projekte', function (Blueprint $table) {
            $table->dropConstrainedForeignId('projektleiter_id');
        });
    }
};
