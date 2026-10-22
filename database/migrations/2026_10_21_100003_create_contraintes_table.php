<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T11.2 — Règles nationales de l'emploi du temps (communes à toutes les écoles : pas d'ecole_id).
 * niveau vide = règle générale ; niveau rempli = règle propre à ce niveau, qui remplace la générale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contraintes', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('niveau')->nullable(); // 1 à 6, ou vide = tous les niveaux
            $table->string('type', 50);
            $table->string('description');
            $table->string('valeur');
            $table->timestamps();

            $table->index(['niveau', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contraintes');
    }
};
