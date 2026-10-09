<?php

namespace App\Http\Controllers\Warehouse\Concerns;

use App\Models\Pop;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gerbang POP-scope buat controller Gudang (ADHOC-54).
 *
 * Ketauan pas audit mendalam 2026-09-02: controller LIST (`WarehouseController`,
 * `WarehouseCustodyController`, `WarehouseTraceabilityController`) udah benar
 * scope-nya, tapi HAMPIR SEMUA controller detail/mutasi (Transfer::show/receive,
 * Issue::show/availableStock/store, Adjustment & Reassign semua method) gak
 * pernah re-check scope — dropdown POP di halaman create cuma penyaring
 * TAMPILAN, `store()`/route-model-binding gak nolak POP di luar scope aktor
 * kalau id-nya dikirim langsung. Pelanggaran hard rule CLAUDE.md ("Setiap
 * query pelanggan/task/invoice/laporan wajib lewat POP scope").
 *
 * SATU-SATUNYA titik penegakan scope buat 4 controller ini — jangan duplikasi
 * pengecekan `hasAllPopAccess()`/`getAllowedPopIds()` inline di tempat lain,
 * panggil method di sini.
 */
trait AuthorizesWarehousePop
{
    /**
     * @throws HttpException 403 kalau $pop di luar scope $user
     */
    protected function assertPopInScope(Pop $pop, User $user, EffectiveAccessService $access): void
    {
        if ($access->hasAllPopAccess($user)) {
            return;
        }

        if (! in_array($pop->id, $access->getAllowedPopIds($user), true)) {
            abort(403, "Anda tidak memiliki akses ke Gudang {$pop->name}.");
        }
    }

    /**
     * Varian buat kolom `*_pop_id` nullable (mis. `InventorySerial::current_pop_id`
     * kosong begitu SN lagi di custody teknisi, bukan lagi di gudang manapun).
     * Null dianggap "gak ada POP buat dicek" — biarkan lolos, caller yang
     * putuskan fallback kolom lain kalau perlu (lihat `issued_from_pop_id`
     * dipakai sebagai fallback di titik-titik yang manggil ini).
     */
    protected function assertPopIdInScope(?int $popId, User $user, EffectiveAccessService $access): void
    {
        if ($popId === null) {
            return;
        }

        $this->assertPopInScope(Pop::findOrFail($popId), $user, $access);
    }

    /**
     * Kelompok H5, docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md
     * §7.2: begitu cabang sudah punya PIC Gudang aktif, konfirmasi terima
     * kiriman Pusat→Cabang jadi tugas PIC — `pop_admin` TIDAK LAGI boleh
     * konfirmasi sendiri (beda dari issue, yang pop_admin tetap boleh asal
     * ditujukan ke PIC — lihat InventoryIssueService::assertIssueAllowed()).
     *
     * @throws HttpException 403
     */
    protected function assertPopAdminNotBlockedByActivePicGudang(Pop $pop, User $user): void
    {
        if ($user->role?->code === 'pop_admin' && $pop->hasActivePicGudang()) {
            abort(403, "Gudang {$pop->name} sudah punya PIC Gudang — konfirmasi terima kiriman dilakukan oleh PIC Gudang cabang ini, bukan POP Admin.");
        }
    }

    /**
     * Kelompok H5/H2c: `pic_gudang` cuma boleh bertindak di cabang yang jadi
     * penunjukannya (`warehouse_pop_pics`) — PENTING buat PIC yang scope-nya
     * `all_pop` (teknisi keliling), scope mengizinkan dia MELIHAT semua
     * cabang tapi hak TULIS gudang tetap dibatasi ke cabang penunjukannya
     * (§5.3.6).
     *
     * @throws HttpException 403
     */
    protected function assertPicGudangCanActOnPop(Pop $pop, User $user): void
    {
        if ($user->role?->code === 'pic_gudang' && ! $user->isPicGudangOf($pop)) {
            abort(403, "Cabang {$pop->name} bukan gudang yang Anda kelola sebagai PIC Gudang.");
        }
    }
}
