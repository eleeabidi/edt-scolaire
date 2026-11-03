<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Sprint 4 — Canal privé d'un enseignant : seul cet enseignant peut l'écouter
Broadcast::channel('enseignant.{id}', function (User $user, int $id) {
    return $user->isEnseignant() && $user->id === $id;
});
