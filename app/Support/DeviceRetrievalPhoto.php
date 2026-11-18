<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Satu sumber "di disk mana foto penarikan alat berada".
 *
 * Foto kondisi alat (laporan DEAC & modem diantar pelanggan) memuat rumah /
 * lokasi pelanggan — data pelanggan, jadi disimpan di disk PRIVAT `local`
 * dan hanya disajikan lewat `DeviceRetrievalPhotoController` yang mengecek
 * permission + POP scope (pola lampiran tiket, CLAUDE.md "File & lampiran").
 *
 * Dulu disimpan di disk `public` (URL `/storage/device-retrieval/...` bisa
 * dibuka siapa pun tanpa login, nama file memuat CID + nama pelanggan).
 * File lama dipindah oleh command one-off `device-retrieval:move-photos-private`;
 * sampai command itu dijalankan, pembacaan masih jatuh ke `public` supaya
 * foto lama tidak mendadak hilang dari layar setelah deploy.
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.6
 */
class DeviceRetrievalPhoto
{
    public const DISK = 'local';

    public const LEGACY_DISK = 'public';

    public const FOLDER = 'device-retrieval';

    /**
     * Disk tempat file ini sekarang berada, atau null kalau tidak ada di mana pun.
     */
    public static function diskFor(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function exists(?string $path): bool
    {
        return self::diskFor($path) !== null;
    }

    /**
     * Hapus dari kedua disk — file lama yang belum dipindah juga ikut terhapus.
     */
    public static function delete(?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '') {
            return;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            Storage::disk($disk)->delete($path);
        }
    }
}
