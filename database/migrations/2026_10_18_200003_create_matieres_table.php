<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T08.1 — Matières d'une école. Pas de type cours/TP (écoles primaire, collège, lycée).
 * type_salle : type de salle où placer la matière (servira à la génération au sprint 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecole_id')->constrained('ecoles')->cascadeOnDelete();
            $table->string('nom');
            $table->unsignedSmallInteger('duree_seance')->default(60); // en minutes
            $table->string('type_salle', 20)->default('normale'); // normale, sport
            $table->timestamps();

            $table->unique(['ecole_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matieres');
    }
};
