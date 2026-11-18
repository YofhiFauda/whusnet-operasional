<?php

namespace App\Console\Commands\OneOff;

use App\Support\DeviceRetrievalPhoto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sekali jalan setelah deploy Review Penarikan Alat: pindahkan foto
 * `device-retrieval/*` dari disk `public` (bisa dibuka lewat URL
 * /storage tanpa login) ke disk privat `local`. Path di kolom DB tidak
 * berubah — cuma disknya. Sebelum command ini dijalankan, aplikasi tetap
 * bisa menampilkan foto lama (DeviceRetrievalPhoto jatuh ke `public`), tapi
 * URL publiknya juga masih terbuka — jadi jalankan segera setelah deploy.
 *
 * File yang sudah ada di `local` dengan nama sama: kalau isinya identik,
 * salinan publiknya dihapus; kalau berbeda, TIDAK ditimpa — dilaporkan
 * sebagai konflik (foto masih terbuka di public, wajib dicek manual).
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.6
 */
#[Signature('device-retrieval:move-photos-private {--dry-run : Tampilkan rencana tanpa memindahkan}')]
#[Description('Pindahkan foto penarikan alat dari disk public ke disk privat local')]
class MoveDeviceRetrievalPhotosPrivateCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk(DeviceRetrievalPhoto::LEGACY_DISK);
        $private = Storage::disk(DeviceRetrievalPhoto::DISK);

        $files = $public->allFiles(DeviceRetrievalPhoto::FOLDER);
        $moved = 0;
        $conflicts = [];

        foreach ($files as $path) {
            if ($private->exists($path)) {
                // Salinan identik (mis. command dijalankan ulang setelah
                // berhenti di tengah) — hapus salinan publik, kalau tidak foto
                // pelanggan tetap terbuka lewat /storage tanpa login. Isi
                // berbeda TIDAK ditimpa/dihapus: dua foto beda bernama sama
                // harus dicek manusia.
                if (hash_equals(hash('sha256', (string) $private->get($path)), hash('sha256', (string) $public->get($path)))) {
                    if (! $dryRun) {
                        $public->delete($path);
                    }

                    $moved++;

                    continue;
                }

                $conflicts[] = $path;

                continue;
            }

            if (! $dryRun) {
                $stream = $public->readStream($path);
                $private->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                // Hapus salinan publik hanya kalau salinan privat benar-benar ada.
                if ($private->exists($path)) {
                    $public->delete($path);
                }
            }

            $moved++;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}".count($files)." file ditemukan di disk public, {$moved} ".($dryRun ? 'akan dipindah' : 'dipindah').' ke disk privat.');

        foreach ($conflicts as $path) {
            $this->warn("Konflik (nama sama, isi berbeda — salinan public MASIH terbuka, cek manual): {$path}");
        }

        return self::SUCCESS;
    }
}
