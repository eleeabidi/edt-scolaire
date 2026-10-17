<?php

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

// Mon profil (les trois acteurs)
Route::middleware(['auth'])->group(function () {
    Route::get('profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profil', [ProfileController::class, 'update'])->name('profile.update');

    Route::put('profil/mot-de-passe', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});
