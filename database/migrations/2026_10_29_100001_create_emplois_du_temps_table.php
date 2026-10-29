<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T13.1 — Emploi du temps d'une école pour une année scolaire (ex. : « 2026-2027 »),
 * au statut brouillon ou publié. Une école a un seul emploi du temps par année scolaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emplois_du_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecole_id')->constrained('ecoles')->cascadeOnDelete();
            $table->string('annee_scolaire', 9); // 2026-2027
            $table->string('statut', 20)->default('brouillon'); // brouillon, publie
            $table->timestamps();

            $table->unique(['ecole_id', 'annee_scolaire']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emplois_du_temps');
    }
};
