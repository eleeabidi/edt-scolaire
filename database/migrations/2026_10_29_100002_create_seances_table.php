<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T13.2 — Séance : une classe, un enseignant et une salle pendant un créneau (horaire).
 * Les séances sont supprimées avec leur emploi du temps (composition). Une classe, un enseignant,
 * une salle ou un créneau utilisé dans une séance ne peut pas être supprimé (restrict).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emploi_du_temps_id')->constrained('emplois_du_temps')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('horaire_id')->constrained('horaires')->restrictOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('salle_id')->constrained('salles')->restrictOnDelete();
            $table->timestamps();

            $table->index(['emploi_du_temps_id', 'horaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
