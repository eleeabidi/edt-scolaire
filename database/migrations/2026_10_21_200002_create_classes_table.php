<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T07.1 — Classes d'une école (niveau de la 1re à la 6e année).
 * cours_samedi : choisi seulement en 3e année ; toujours non en 1re et 2e, toujours oui de la 4e à la 6e.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecole_id')->constrained('ecoles')->cascadeOnDelete();
            $table->string('nom');
            $table->unsignedTinyInteger('niveau'); // 1 à 6
            $table->unsignedSmallInteger('effectif');
            $table->boolean('cours_samedi')->default(false);
            $table->timestamps();

            $table->unique(['ecole_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
