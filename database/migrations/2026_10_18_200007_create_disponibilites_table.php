<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T10.1 — Plages de disponibilité d'un enseignant (ex. : lundi 08:00–12:00).
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

            $table->index(['enseignant_id', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilites');
    }
};
