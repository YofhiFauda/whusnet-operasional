<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('fop.{pop_id}', function ($user, $popId) {
    if (! $user->hasPermission('fop.dashboard')) {
        return false;
    }
    if ($user->hasFullAccess()) {
        return true;
    }

    return $user->pops()->where('pops.id', $popId)->exists();
});

Broadcast::channel('teknisi.{user_id}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
