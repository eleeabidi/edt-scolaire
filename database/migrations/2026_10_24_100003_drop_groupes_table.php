<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rapport final du sprint 2 : pas de groupes dans le primaire (pas de cours par groupe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('groupes');
    }

    public function down(): void
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
};
