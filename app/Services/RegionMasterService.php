<?php

namespace App\Services;

use App\Models\City;
use App\Models\District;
use App\Models\Village;
use Illuminate\Database\Eloquent\Model;

/**
 * CRUD master wilayah (Kota/Kabupaten → Kecamatan → Desa).
 *
 * Wilayah TIDAK pernah dinonaktifkan — dia cuma rujukan alamat, tidak punya
 * status. Jadi hapus = blokir kalau masih dipakai: ada pelanggan di dalamnya
 * (`customers`/`customer_addresses`/`fop_tasks`/tiket, dsb.) ATAU masih punya
 * wilayah anak (kecamatan di bawah kota, desa di bawah kecamatan). Cek-nya
 * lewat FK introspection yang sama dengan MasterRecordRemovalService.
 */
class RegionMasterService
{
    public const LEVEL_KOTA = 'kota';

    public const LEVEL_KECAMATAN = 'kecamatan';

    public const LEVEL_DESA = 'desa';

    public function __construct(private MasterRecordRemovalService $removal) {}

    /**
     * @return class-string<Model>
     */
    public function modelClass(string $level): string
    {
        return match ($level) {
            self::LEVEL_KOTA => City::class,
            self::LEVEL_KECAMATAN => District::class,
            self::LEVEL_DESA => Village::class,
        };
    }

    public function isInUse(Model $region): bool
    {
        return $this->removal->hasDependents($region);
    }

    /**
     * @return bool true kalau dihapus; false kalau diblokir karena masih dipakai
     */
    public function delete(Model $region): bool
    {
        if ($this->isInUse($region)) {
            return false;
        }

        $region->delete();

        return true;
    }
}
