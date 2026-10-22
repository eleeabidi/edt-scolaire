<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T09.1 — Salles d'une école : type « normale » ou « sport » uniquement, capacité en élèves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecole_id')->constrained('ecoles')->cascadeOnDelete();
            $table->string('nom');
            $table->string('type', 20)->default('normale'); // normale, sport
            $table->unsignedSmallInteger('capacite');
            $table->timestamps();

            $table->unique(['ecole_id', 'nom']); // pas deux salles du même nom dans une école
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salles');
    }
};
