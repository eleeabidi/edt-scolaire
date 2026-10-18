<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T07.1 — Classes d'une école (ex. : « 3e A », niveau « 3e année », 28 élèves).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecole_id')->constrained('ecoles')->cascadeOnDelete();
            $table->string('nom');
            $table->string('niveau');
            $table->unsignedSmallInteger('effectif');
            $table->timestamps();

            $table->unique(['ecole_id', 'nom']); // pas deux classes du même nom dans une école
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
