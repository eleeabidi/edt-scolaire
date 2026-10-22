<?php

use App\Enums\Role;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirecteurController;
use App\Http\Controllers\EnseignantController;
use App\Http\Controllers\ProgrammeController;
use App\Http\Controllers\ReglesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Page d'accueil : envoie chaque utilisateur vers sa page de départ
Route::get('/', function (Request $request) {
    return match ($request->user()?->role) {
        Role::Admin => to_route('dashboard'),
        Role::Directeur => to_route('enseignants.index'),
        Role::Enseignant => to_route('profile.edit'),
        default => to_route('login'),
    };
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('role:admin')
        ->name('dashboard');

    // Gérer les enseignants
    Route::resource('enseignants', EnseignantController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('role:admin,directeur');

    Route::post('enseignants/{enseignant}/identifiants', [EnseignantController::class, 'renvoyerIdentifiants'])
        ->middleware('role:admin,directeur')
        ->name('enseignants.identifiants');

    // Sprint 2 : programme national et règles (consultation seule, directeur)
    Route::middleware('role:directeur')->group(function () {
        Route::get('programme', ProgrammeController::class)->name('programme.index');
        Route::get('regles', ReglesController::class)->name('regles.index');
    });

    // Gérer les directeurs (Admin)
    Route::middleware('role:admin')->group(function () {
        Route::resource('directeurs', DirecteurController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        Route::post('directeurs/{directeur}/valider', [DirecteurController::class, 'valider'])
            ->name('directeurs.valider');
        Route::post('directeurs/{directeur}/refuser', [DirecteurController::class, 'refuser'])
            ->name('directeurs.refuser');
        Route::post('directeurs/{directeur}/identifiants', [DirecteurController::class, 'renvoyerIdentifiants'])
            ->name('directeurs.identifiants');
    });
});

require __DIR__.'/settings.php';
