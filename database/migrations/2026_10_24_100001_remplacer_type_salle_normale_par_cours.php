<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rapport final du sprint 2 : le type de salle « normale » s'appelle désormais « cours »
 * (types possibles : cours, sport, technologie ; « cours » par défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('salles')->where('type', 'normale')->update(['type' => 'cours']);
        DB::table('matieres')->where('type_salle', 'normale')->update(['type_salle' => 'cours']);

        Schema::table('salles', function (Blueprint $table) {
            $table->string('type', 20)->default('cours')->change();
        });

        Schema::table('matieres', function (Blueprint $table) {
            $table->string('type_salle', 20)->default('cours')->change();
        });
    }

    public function down(): void
    {
        DB::table('salles')->where('type', 'cours')->update(['type' => 'normale']);
        DB::table('matieres')->where('type_salle', 'cours')->update(['type_salle' => 'normale']);

        Schema::table('salles', function (Blueprint $table) {
            $table->string('type', 20)->default('normale')->change();
        });

        Schema::table('matieres', function (Blueprint $table) {
            $table->string('type_salle', 20)->default('normale')->change();
        });
    }
};
