<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T07.2 — Groupes d'une classe (une classe contient 0..* groupes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('nom');
            $table->unsignedSmallInteger('effectif');
            $table->timestamps();

            $table->unique(['classe_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupes');
    }
};
