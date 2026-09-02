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

/**
 * Siklus hidup setoran kolektor per POP (App\Events\CollectorDepositUpdated) —
 * dipakai Worksheet Admin supaya admin tahu kolektor mana yang baru menyetor
 * tanpa memuat ulang halaman.
 *
 * Digerbang `collector_worksheet.view`, bukan `payments.view`: yang berhak
 * mendengar kabar setoran adalah orang yang memang mengurus worksheet kolektor.
 * Pola scope-nya sama dengan channel lain di file ini — EffectiveAccessService,
 * bukan $user->pops() legacy.
 *
 * Sisi KOLEKTOR tidak punya channel sendiri di sini: event yang sama juga
 * disiarkan ke `App.Models.User.{id}` miliknya, yang otorisasinya sudah
 * terdefinisi di paling atas file ini.
 */
/**
 * Setoran Kas Admin → Owner/Bank (App\Events\CashDepositUpdated) — dipakai
 * halaman Setoran Kas (`cash-deposits/index.blade.php`). Global, BUKAN
 * per-POP: pemeriksa (`cash_deposit.view`) selalu Owner/atasan, yang sudah
 * bypass scope POP sepenuhnya (CLAUDE.md § RBAC). Sisi admin penyetor
 * mendengar lewat `App.Models.User.{id}` yang sudah generik di atas.
 */
Broadcast::channel('cash-deposits', function ($user) {
    return $user->hasPermission('cash_deposit.view');
});

Broadcast::channel('collector-activity.{popId}', function ($user, $popId) {
    if (! $user->hasPermission('collector_worksheet.view')) {
        return false;
    }

    $access = app(EffectiveAccessService::class);

    if ($access->hasAllPopAccess($user)) {
        return true;
    }

    return in_array((int) $popId, $access->getAllowedPopIds($user), true);
});
