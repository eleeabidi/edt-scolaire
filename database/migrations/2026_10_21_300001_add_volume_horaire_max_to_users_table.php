<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T06.1 — Volume horaire maximum d'un enseignant : heures par semaine, entier, 18 par défaut.
 * Modifiable par le directeur (ex. : 19 h pour l'enseignant principal de 1re ou de 2e année).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('volume_horaire_max')->default(18)->after('ecole_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('volume_horaire_max');
        });
    }
};
