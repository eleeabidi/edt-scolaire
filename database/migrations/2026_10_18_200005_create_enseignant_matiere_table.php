<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T08.3 — Matières qu'un enseignant peut enseigner (les enseignants sont dans users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enseignant_matiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['enseignant_id', 'matiere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enseignant_matiere');
    }
};
