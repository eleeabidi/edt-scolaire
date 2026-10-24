<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rapport final du sprint 2 : une salle n'est jamais supprimée, elle est mise hors service
 * (par exemple pendant des travaux), puis mise en service à nouveau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salles', function (Blueprint $table) {
            $table->boolean('en_service')->default(true)->after('capacite');
        });
    }

    public function down(): void
    {
        Schema::table('salles', function (Blueprint $table) {
            $table->dropColumn('en_service');
        });
    }
};
