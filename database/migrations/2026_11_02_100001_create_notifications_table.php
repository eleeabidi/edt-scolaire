<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 — Notification reçue par un enseignant (dictionnaire de données) : type (publication ou
 * modification), message, date et heure d'envoi, lue (faux par défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->text('message');
            $table->dateTime('date_envoi');
            $table->boolean('lue')->default(false);
            $table->timestamps();

            $table->index(['enseignant_id', 'lue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
