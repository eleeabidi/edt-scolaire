<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T11.1 — Créneaux horaires d'une école (générés à partir des règles en T11.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecole_id')->constrained('ecoles')->cascadeOnDelete();
            $table->unsignedTinyInteger('jour'); // 1 = lundi … 6 = samedi
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->timestamps();

            $table->unique(['ecole_id', 'jour', 'heure_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horaires');
    }
};
