<?php

namespace App\Http\Controllers\Warehouse\Concerns;

use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;

/**
 * Konteks cabang global (analisa-ui-ux-warehouse.md §S1) — switcher di header
 * nyimpen pilihan cabang ke session (`WarehouseSwitchPopController`), dibaca
 * BALIK di sini jadi default filter tiap halaman list gudang.
 *
 * ATURAN KERAS biar TIDAK mengubah perilaku lama satu pun: `pop_id` di query
 * string SELALU menang telak, apa pun isinya — termasuk string kosong
 * (artinya user mau lihat "Semua Gudang" di halaman itu, BUKAN "belum
 * pilih"). Session HANYA dipakai kalau `pop_id` BENAR-BENAR TIDAK ADA di
 * request. Semua test lama ngirim `pop_id` eksplisit atau gak sama sekali
 * (session gak pernah keisi tanpa lewat switcher baru) — jadi hasilnya
 * identik sebelum/sesudah trait ini ada.
 */
trait ResolvesSelectedPop
{
    protected function resolveSelectedPopId(Request $request, EffectiveAccessService $access, User $user, string $key = 'pop_id'): ?int
    {
        if ($request->has($key)) {
            return $request->integer($key) ?: null;
        }

        $sessionPopId = session('warehouse.pop_id');
        if ($sessionPopId === null) {
            return null;
        }

        // Scope bisa berubah sejak session diisi (role/scope staf diedit) —
        // validasi ulang, jangan percaya buta pada nilai lama di session.
        if ($access->hasAllPopAccess($user) || in_array((int) $sessionPopId, $access->getAllowedPopIds($user), true)) {
            return (int) $sessionPopId;
        }

        return null;
    }
}
