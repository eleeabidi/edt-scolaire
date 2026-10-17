<?php

use App\Enums\Role;
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
    Route::inertia('dashboard', 'Dashboard')
        ->middleware('role:admin')
        ->name('dashboard');

    Route::inertia('enseignants', 'enseignants/Index')
        ->middleware('role:admin,directeur')
        ->name('enseignants.index');
});

require __DIR__.'/settings.php';
