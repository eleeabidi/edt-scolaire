<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T06.1 — Volume horaire maximum d'un enseignant (en heures par semaine).
 * Les enseignants sont dans la table users (role = enseignant) depuis le sprint 1.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('volume_horaire_max')->nullable()->after('specialite'); // enseignants uniquement
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('volume_horaire_max');
        });
    }
};
