<?php

use App\Services\EffectiveAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Antrean Ticketing per POP — dipakai worksheet panel & index bucket buat
 * auto-refresh (App\Events\TicketQueueUpdated). SENGAJA pakai
 * EffectiveAccessService::getAllowedPopIds() (jalur benar, paham pop_tree),
 * BUKAN $user->pops() legacy yang dipakai channel fop.{pop_id} lama —
 * lihat CLAUDE.md § POP Scope.
 */
Broadcast::channel('tickets.{popId}', function ($user, $popId) {
    if (! $user->hasPermission('tickets.view')) {
        return false;
    }

    $access = app(EffectiveAccessService::class);

    if ($access->hasAllPopAccess($user)) {
        return true;
    }

    return in_array((int) $popId, $access->getAllowedPopIds($user), true);
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
