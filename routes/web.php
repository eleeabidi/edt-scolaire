<?php

use App\Enums\Role;
use App\Http\Controllers\AffectationController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirecteurController;
use App\Http\Controllers\DisponibiliteController;
use App\Http\Controllers\EmploiDuTempsController;
use App\Http\Controllers\EnseignantController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MonEmploiDuTempsController;
use App\Http\Controllers\ProgrammeController;
use App\Http\Controllers\ReglesController;
use App\Http\Controllers\SalleController;
use App\Http\Controllers\SeanceController;
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

    // Sprint 2 : guide pédagogique et règles de gestion (consultation seule, directeur)
    Route::middleware('role:directeur')->group(function () {
        Route::get('guide-pedagogique', ProgrammeController::class)->name('programme.index');
        Route::get('regles', ReglesController::class)->name('regles.index');

        // Salles (jamais supprimées : mises hors service) et classes de l'école du directeur
        Route::resource('salles', SalleController::class)->only(['index', 'store', 'update']);
        Route::patch('salles/{salle}/hors-service', [SalleController::class, 'mettreHorsService'])->name('salles.hors-service');
        Route::patch('salles/{salle}/en-service', [SalleController::class, 'mettreEnService'])->name('salles.en-service');
        Route::resource('classes', ClasseController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['classes' => 'classe']);

        // Affectations d'un enseignant et volume horaire maximum
        Route::get('enseignants/{enseignant}/affectations', [AffectationController::class, 'show'])->name('affectations.show');
        Route::post('enseignants/{enseignant}/affectations', [AffectationController::class, 'store'])->name('affectations.store');
        Route::delete('enseignants/{enseignant}/affectations/{affectation}', [AffectationController::class, 'destroy'])->name('affectations.destroy');
        Route::patch('enseignants/{enseignant}/volume', [AffectationController::class, 'volume'])->name('affectations.volume');

        // Disponibilités des enseignants
        Route::get('disponibilites', [DisponibiliteController::class, 'index'])->name('disponibilites.index');
        Route::put('disponibilites/{enseignant}', [DisponibiliteController::class, 'update'])->name('disponibilites.update');

        // Import Excel/CSV des enseignants, des classes et des salles
        Route::get('import', [ImportController::class, 'index'])->name('import.index');
        Route::get('import/modele/{type}', [ImportController::class, 'modele'])->name('import.modele');
        Route::post('import', [ImportController::class, 'store'])->name('import.store');
        Route::get('import/rapport', [ImportController::class, 'rapport'])->name('import.rapport');

        // Sprint 3 : emploi du temps de l'école (génération)
        Route::get('emploi-du-temps', [EmploiDuTempsController::class, 'index'])->name('emploi.index');
        Route::post('emploi-du-temps/generer', [EmploiDuTempsController::class, 'generer'])->name('emploi.generer');
        Route::get('emploi-du-temps/{emploi}/conflits', [EmploiDuTempsController::class, 'conflits'])->name('emploi.conflits');
        Route::post('emploi-du-temps/{emploi}/publier', [EmploiDuTempsController::class, 'publier'])->name('emploi.publier');

        // Modifier une séance : la déplacer (glisser-déposer) ou changer sa salle
        Route::patch('seances/{seance}/deplacer', [SeanceController::class, 'deplacer'])->name('seances.deplacer');
        Route::patch('seances/{seance}/salle', [SeanceController::class, 'changerSalle'])->name('seances.salle');
    });

    // Sprint 3 : l'enseignant consulte son emploi du temps (seulement s'il est publié)
    Route::get('mon-emploi-du-temps', MonEmploiDuTempsController::class)
        ->middleware('role:enseignant')
        ->name('mon-emploi.index');

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
