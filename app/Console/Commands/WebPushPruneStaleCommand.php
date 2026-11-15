<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Dev-only cleanup, BUKAN scheduled command produksi. `updatePushSubscription()`
 * (package `laravel-notification-channels/webpush`) upsert by endpoint — endpoint
 * beda per origin tempat Service Worker didaftar, jadi tiap kali tunnel
 * trycloudflare ganti domain (dev), browser subscribe ulang dengan endpoint
 * BARU dan baris lama (origin mati) nggak kehapus sendiri — baru keprune
 * otomatis kalau FCM coba kirim ke situ dan gagal 404/410. Sebelum itu
 * terjadi, user kebagian push dobel: satu buka domain baru, satu domain
 * lama yang udah mati (ketemu 2026-11-10, APP_URL produksi stabil jadi
 * ini nggak pernah kejadian di sana).
 */
class WebPushPruneStaleCommand extends Command
{
    protected $signature = 'webpush:prune-stale {--dry-run : Tampilkan yang akan dihapus tanpa menghapus}';

    protected $description = 'Hapus push_subscriptions duplikat per user/customer, sisakan yang terbaru (bersihkan subscription nyangkut dari tunnel domain lama)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $staleIds = PushSubscription::query()
            ->select('id', 'subscribable_type', 'subscribable_id', 'created_at')
            ->get()
            ->groupBy(fn (PushSubscription $s) => $s->subscribable_type.'#'.$s->subscribable_id)
            ->filter(fn ($group) => $group->count() > 1)
            ->flatMap(fn ($group) => $group->sortByDesc('created_at')->slice(1))
            ->pluck('id');

        if ($staleIds->isEmpty()) {
            $this->info('Tidak ada subscription duplikat.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Type', 'Subscribable ID', 'Created'],
            PushSubscription::whereIn('id', $staleIds)->get()
                ->map(fn (PushSubscription $s) => [$s->id, class_basename($s->subscribable_type), $s->subscribable_id, $s->created_at])
        );

        if ($dryRun) {
            $this->info("Dry-run — {$staleIds->count()} baris akan dihapus (tidak ada yang dieksekusi).");

            return self::SUCCESS;
        }

        PushSubscription::whereIn('id', $staleIds)->delete();
        $this->info("Terhapus {$staleIds->count()} subscription duplikat, sisa yang terbaru per subscribable.");

        return self::SUCCESS;
    }
}
