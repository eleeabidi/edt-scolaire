<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T13.3 — Les matières d'une séance, avec leur heure de début et de fin
 * (ex. : arabe 08:00 → 09:00, mathématiques 09:00 → 09:55). Au moins 30 min par matière.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seance_matiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->restrictOnDelete();
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seance_matiere');
    }
};
