<?php

namespace App\Http\Controllers\Warehouse\Concerns;

use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cek scope POP buat SN/roll individual — diekstrak dari
 * `WarehouseTraceabilityController` (2026-10-07) supaya `WarehouseSearchController`
 * (pencarian universal, analisa-ui-ux-warehouse.md §S2) pakai ATURAN YANG SAMA
 * PERSIS, bukan salinan kedua yang bisa drift kalau salah satu diubah belakangan.
 *
 * SN/roll dianggap dalam scope kalau PERNAH nyentuh gudang/pelanggan dalam
 * scope aktor — current_pop, issued_from_pop, ATAU (khusus SN) pop pelanggan
 * tempat dia terpasang. Dipakai buat "tidak ditemukan" (bukan 403 eksplisit)
 * supaya keberadaan SN/roll di cabang lain tidak ikut bocor lewat pesan error
 * yang beda.
 */
trait ChecksAssetScope
{
    protected function isSerialInScope(InventorySerial $serial, EffectiveAccessService $access, User $user): bool
    {
        if ($access->hasAllPopAccess($user)) {
            return true;
        }

        $allowed = $access->getAllowedPopIds($user);

        if ($serial->current_pop_id && in_array($serial->current_pop_id, $allowed, true)) {
            return true;
        }

        if ($serial->issued_from_pop_id && in_array($serial->issued_from_pop_id, $allowed, true)) {
            return true;
        }

        if ($serial->customer && in_array($serial->customer->pop_id, $allowed, true)) {
            return true;
        }

        return false;
    }

    protected function isRollInScope(InventoryRoll $roll, EffectiveAccessService $access, User $user): bool
    {
        if ($access->hasAllPopAccess($user)) {
            return true;
        }

        $allowed = $access->getAllowedPopIds($user);

        if ($roll->current_pop_id && in_array($roll->current_pop_id, $allowed, true)) {
            return true;
        }

        if ($roll->issued_from_pop_id && in_array($roll->issued_from_pop_id, $allowed, true)) {
            return true;
        }

        return false;
    }

    /**
     * Padanan `isSerialInScope()` sebagai constraint SQL, bukan predicate
     * per-baris — dipakai di query LIST (pencarian `q` Traceability & S2)
     * yang sebelumnya `->limit(100)->get()->filter(...)->take(N)`: ambil 100
     * baris mentah dari DB, filter scope di PHP, baru dipotong N. Kalau tabel
     * membesar, 100 baris mentah bisa semuanya di luar scope dan hasil
     * kosong padahal ada yang cocok — DAN tetap nge-query 100 baris yang
     * kebanyakan dibuang. Constraint di sini membatasi DI DALAM SQL, jadi
     * `->limit(N)` beneran ambil N baris yang SUDAH valid, bukan N dari 100
     * yang belum difilter.
     *
     * SENGAJA kolom yang sama persis dengan `isSerialInScope()` di atas (baca
     * dokblok trait) — kalau aturan scope berubah, UBAH DUA-DUANYA bareng.
     */
    protected function scopeSerialQuery(Builder $query, EffectiveAccessService $access, User $user): Builder
    {
        if ($access->hasAllPopAccess($user)) {
            return $query;
        }

        $allowed = $access->getAllowedPopIds($user);

        return $query->where(function ($w) use ($allowed) {
            $w->whereIn('current_pop_id', $allowed)
                ->orWhereIn('issued_from_pop_id', $allowed)
                ->orWhereHas('customer', fn ($c) => $c->whereIn('pop_id', $allowed));
        });
    }

    /**
     * Padanan `isRollInScope()` sebagai constraint SQL. Lihat docblock
     * `scopeSerialQuery()` — alasan & aturan "ubah dua-duanya bareng" sama.
     */
    protected function scopeRollQuery(Builder $query, EffectiveAccessService $access, User $user): Builder
    {
        if ($access->hasAllPopAccess($user)) {
            return $query;
        }

        $allowed = $access->getAllowedPopIds($user);

        return $query->where(function ($w) use ($allowed) {
            $w->whereIn('current_pop_id', $allowed)->orWhereIn('issued_from_pop_id', $allowed);
        });
    }
}
