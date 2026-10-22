<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T08.2 — Volume hebdomadaire de chaque matière par niveau (1re à 6e année).
 * En minutes, car le décret compte en demi-heures (6 h 30 = 390).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveau_matiere', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('niveau'); // 1 à 6
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes_par_semaine');
            $table->timestamps();

            $table->unique(['niveau', 'matiere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveau_matiere');
    }
};
