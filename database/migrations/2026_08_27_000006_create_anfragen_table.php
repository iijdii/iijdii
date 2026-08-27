<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vollständiges Feldmapping aus docs/anfrage-fields.txt (Blöcke 1–11).
 * Block 12 (Fotos) und Block 14 (Aktivitäten) sind Kindtabellen;
 * Block 13: angebot_id entfällt (Verknüpfung über angebote.anfrage_id),
 * vertrag_id/montage_id folgen mit den jeweiligen Entitäten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anfragen', function (Blueprint $table) {
            // Block 1: Identifikation
            $table->id();
            $table->string('nummer', 32)->unique(); // ANF-2026-001
            $table->foreignId('erstellt_von')->nullable()->constrained('users');

            // Block 2: Kunde (FK + denormalisierte Kontaktdaten)
            $table->foreignId('kunde_id')->constrained('kunden');
            $table->string('kunden_vorname')->nullable();
            $table->string('kunden_nachname')->nullable();
            $table->string('kunden_telefon')->nullable();
            $table->string('kunden_email')->nullable();

            // Block 3: Objektadresse
            $table->string('objekt_strasse')->nullable();
            $table->string('objekt_hausnummer', 16)->nullable();
            $table->string('objekt_plz', 10)->nullable();
            $table->string('objekt_stadt')->nullable();
            $table->string('objekt_bundesland')->nullable();
            $table->string('objekt_land')->default('Deutschland');
            $table->string('objekt_etage', 32)->nullable();
            $table->text('objekt_zugang')->nullable();
            $table->boolean('objekt_ist_gleich_kunde')->default(false);

            // Block 4: Besuchstermin
            $table->date('besuchstermin_datum')->nullable();
            $table->time('besuchstermin_uhrzeit')->nullable();
            $table->unsignedSmallInteger('besuchstermin_dauer')->nullable(); // Minuten
            $table->string('besuchstermin_status', 32)->nullable(); // geplant/bestätigt/abgesagt/durchgeführt
            $table->foreignId('besuchstermin_mitarbeiter')->nullable()->constrained('users');
            $table->text('besuchstermin_notiz')->nullable();

            // Block 5: Status
            $table->string('status', 32)->default('neu')->index();
            $table->string('status_grund')->nullable();
            $table->string('prioritaet', 16)->default('normal');

            // Block 6: Interessierte Produkte
            $table->json('interessierte_produkte')->nullable();
            $table->text('produkt_notiz')->nullable();

            // Block 7: Technische Parameter
            $table->unsignedInteger('breite_cm')->nullable();
            $table->unsignedInteger('tiefe_cm')->nullable();
            $table->unsignedInteger('hoehe_cm')->nullable();
            $table->decimal('flaeche_m2', 8, 2)->nullable();
            $table->string('form', 32)->nullable(); // rechteckig/trapezfoermig/l-form/individuell
            $table->string('dachform', 32)->nullable();
            $table->unsignedTinyInteger('dachneigung_grad')->nullable();
            $table->string('verglasung_typ', 32)->nullable();
            $table->string('verglasung_farbe')->nullable();
            $table->string('profil_material', 32)->nullable();
            $table->string('profil_farbe_ral', 16)->nullable();
            $table->string('profil_farbe_name')->nullable();
            $table->string('profil_oberflaeche', 32)->nullable();
            $table->string('untergrund_typ', 32)->nullable();
            $table->string('untergrund_zustand', 16)->nullable();
            $table->string('befestigung_art', 32)->nullable();
            $table->string('wand_material', 32)->nullable();
            $table->unsignedTinyInteger('anzahl_stuetzen')->nullable();
            $table->text('stuetzen_position')->nullable();
            $table->string('dach_material', 32)->nullable();
            $table->string('entwaesserung', 32)->nullable();

            // Block 8: Zusatzoptionen
            $table->boolean('seitenwand_links')->default(false);
            $table->string('seitenwand_links_typ', 32)->nullable();
            $table->unsignedInteger('seitenwand_links_breite_cm')->nullable();
            $table->boolean('seitenwand_rechts')->default(false);
            $table->string('seitenwand_rechts_typ', 32)->nullable();
            $table->unsignedInteger('seitenwand_rechts_breite_cm')->nullable();
            $table->boolean('schiebesystem')->default(false);
            $table->string('schiebesystem_seite', 16)->nullable();
            $table->unsignedTinyInteger('schiebesystem_anzahl')->nullable();
            $table->boolean('markise')->default(false);
            $table->unsignedInteger('markise_breite_cm')->nullable();
            $table->unsignedInteger('markise_ausfall_cm')->nullable();
            $table->string('markise_antrieb', 16)->nullable();
            $table->boolean('led_beleuchtung')->default(false);
            $table->decimal('led_laenge_m', 6, 2)->nullable();
            $table->string('led_typ', 16)->nullable();
            $table->string('led_farbe', 16)->nullable();
            $table->boolean('sonnensegel')->default(false);
            $table->string('sonnensegel_masse')->nullable();
            $table->boolean('keil_links')->default(false);
            $table->unsignedTinyInteger('keil_links_winkel_grad')->nullable();
            $table->boolean('keil_rechts')->default(false);
            $table->unsignedTinyInteger('keil_rechts_winkel_grad')->nullable();

            // Block 9: Budget
            $table->boolean('budget_vorhanden')->default(false);
            $table->decimal('budget_ca_euro', 10, 2)->nullable();
            $table->boolean('finanzierung_gewuenscht')->default(false);
            $table->string('kaufentscheidung_zeitraum', 16)->nullable();

            // Block 10: Quelle
            $table->string('anfrage_quelle', 32)->nullable();
            $table->string('quelle_detail')->nullable();

            // Block 11: Kommentare
            $table->text('kommentar_intern')->nullable();
            $table->text('kommentar_extern')->nullable();
            $table->text('wunsche_sonstiges')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anfragen');
    }
};
