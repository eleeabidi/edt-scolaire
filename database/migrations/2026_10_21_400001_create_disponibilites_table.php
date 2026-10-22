<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T10.1 — Créneaux où un enseignant est disponible (un créneau national par ligne).
 * Un enseignant sans aucune ligne est disponible partout (décision du 21 octobre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour'); // 1 = lundi … 6 = samedi
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();

            $table->unique(['enseignant_id', 'jour', 'heure_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilites');
    }
};
