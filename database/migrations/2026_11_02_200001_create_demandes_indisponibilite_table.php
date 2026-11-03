<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T21.1 — Demande d'indisponibilité signalée par un enseignant et traitée par le directeur de son école.
 * Le jour est un entier de 1 (lundi) à 6 (samedi), comme pour les disponibilités et les horaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_indisponibilite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('motif');
            $table->date('date_envoi');
            $table->string('statut')->default('en_attente');
            $table->timestamps();

            $table->index(['enseignant_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_indisponibilite');
    }
};
