<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T08.1 — Matières du programme national (décret n° 2019-1085 modifié par le décret n° 2021-143).
 * Communes à toutes les écoles : pas d'ecole_id, pas de durée de séance, pas de type cours/TP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('type_salle', 20)->default('normale'); // normale, sport
            $table->boolean('autorisee_midi')->default(false); // peut être placée de 12 h à 13 h
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matieres');
    }
};
