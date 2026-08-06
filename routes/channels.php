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

/**
 * Perubahan status/nominal invoice akibat payment (App\Events\InvoiceStatusUpdated).
 * Pola sama dengan tickets.{popId} di atas — EffectiveAccessService, bukan
 * $user->pops() legacy.
 */
Broadcast::channel('invoices.{popId}', function ($user, $popId) {
    if (! $user->hasPermission('invoices.view')) {
        return false;
    }

    $access = app(EffectiveAccessService::class);

    if ($access->hasAllPopAccess($user)) {
        return true;
    }

    return in_array((int) $popId, $access->getAllowedPopIds($user), true);
});

/**
 * Perubahan status workflow pelanggan (App\Events\CustomerVerificationStatusChanged) —
 * dipakai antrean verifikasi (verifications/queue.blade.php) biar dua admin
 * gak verifikasi pelanggan yang sama tanpa saling tahu.
 */
Broadcast::channel('customers.{popId}', function ($user, $popId) {
    if (! $user->hasPermission('customers.detail.installation.view')) {
        return false;
    }

    $access = app(EffectiveAccessService::class);

    if ($access->hasAllPopAccess($user)) {
        return true;
    }

    return in_array((int) $popId, $access->getAllowedPopIds($user), true);
});

/**
 * Perubahan baris Task FOP (App\Events\FopTaskUpdated) — dipakai /fop-tasks
 * (fop_tasks/index.blade.php), BEDA dari fop.{pop_id} di bawah (itu buat
 * /fop/dashboard, gate-nya fop.dashboard). Gate di sini fop_tasks.view biar
 * konsisten sama middleware route index-nya.
 */
Broadcast::channel('fop-tasks.{popId}', function ($user, $popId) {
    if (! $user->hasPermission('fop_tasks.view')) {
        return false;
    }

    $access = app(EffectiveAccessService::class);

    if ($access->hasAllPopAccess($user)) {
        return true;
    }

    return in_array((int) $popId, $access->getAllowedPopIds($user), true);
});

/**
 * Status teknisi realtime /fop/dashboard. Sebelumnya pakai $user->pops()
 * legacy (pivot user_pops langsung) — gak paham scope pop_tree, jadi user
 * dengan scope itu bisa lolos permission tapi gak captured di sini dan diam-
 * diam gak dapet broadcast walau tetap punya akses lewat EffectiveAccessService
 * (lihat CLAUDE.md § POP Scope, docs/plan/analisa-status-implementasi-notifikasi.md §6.4).
 * Diseragamkan ke pola yang sama dengan channel lain di file ini.
 */
Broadcast::channel('fop.{pop_id}', function ($user, $popId) {
    if (! $user->hasPermission('fop.dashboard')) {
        return false;
    }

    $access = app(EffectiveAccessService::class);

    if ($access->hasAllPopAccess($user)) {
        return true;
    }

    return in_array((int) $popId, $access->getAllowedPopIds($user), true);
});

Broadcast::channel('teknisi.{user_id}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
