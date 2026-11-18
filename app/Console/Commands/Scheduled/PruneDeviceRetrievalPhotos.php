<?php

namespace App\Console\Commands\Scheduled;

use App\Enums\DeviceRetrievalReviewStatus;
use App\Models\TaskDeviceRetrieval;
use App\Support\DeviceRetrievalPhoto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retensi foto Review Penarikan Alat (keputusan user K5): foto percobaan
 * yang DITOLAK dihapus 30 hari setelah ditolak. Alasan teks teknisi &
 * catatan FOP TIDAK dihapus — cuma file fotonya, lalu `photo_purged_at`
 * diisi supaya UI menulis "foto dihapus" alih-alih gambar rusak.
 *
 * Sengaja TIDAK menyentuh: `disetujui` (bukti modem tidak kembali — permanen),
 * `menunggu` (belum diputuskan), dan laporan `diambil` (review_status NULL).
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.5
 */
#[Signature('device-retrieval:prune-photos {--days=30 : Hapus foto percobaan ditolak yang lebih tua dari N hari} {--dry-run : Tampilkan jumlah tanpa menghapus}')]
#[Description('Hapus foto percobaan penarikan alat yang ditolak setelah masa retensi')]
class PruneDeviceRetrievalPhotos extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $query = TaskDeviceRetrieval::query()
            ->where('review_status', DeviceRetrievalReviewStatus::DITOLAK->value)
            ->where('reviewed_at', '<', $cutoff)
            ->whereNull('photo_purged_at')
            ->whereNotNull('condition_photo');

        if ($dryRun) {
            $this->info("[dry-run] {$query->count()} foto percobaan ditolak (lebih lama dari {$days} hari) akan dihapus.");

            return self::SUCCESS;
        }

        $purged = 0;

        $query->chunkById(100, function ($retrievals) use (&$purged): void {
            foreach ($retrievals as $retrieval) {
                DeviceRetrievalPhoto::delete($retrieval->condition_photo);
                // Path dibiarkan (jejak audit nama file); purged_at yang
                // menandai filenya sudah tidak ada.
                $retrieval->update(['photo_purged_at' => now()]);
                $purged++;
            }
        });

        $this->info("Berhasil hapus {$purged} foto percobaan ditolak (lebih lama dari {$days} hari).");

        return self::SUCCESS;
    }
}
