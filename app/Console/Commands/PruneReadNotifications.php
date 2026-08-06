<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Hapus baris `database_notifications` yang UDAH DIBACA dan lebih lama dari
 * retensi — sebelumnya gak ada pembersihan sama sekali (docs/plan/
 * analisa-status-implementasi-notifikasi.md §6.2), tabel ini tumbuh tanpa
 * batas. Notifikasi yang BELUM dibaca sengaja gak disentuh biar user gak
 * kehilangan info yang belum sempat dilihat, seberapa pun lamanya.
 */
class PruneReadNotifications extends Command
{
    protected $signature = 'notifications:prune-read {--days=90 : Hapus notifikasi terbaca yang lebih tua dari N hari}';

    protected $description = 'Hapus notifikasi in-app yang sudah dibaca dan lebih lama dari batas retensi';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $deleted = DatabaseNotification::whereNotNull('read_at')
            ->where('read_at', '<', $cutoff)
            ->delete();

        $this->info("Berhasil hapus {$deleted} notifikasi terbaca (lebih lama dari {$days} hari).");

        return self::SUCCESS;
    }
}
