<?php

namespace App\Console\Commands;

use App\Models\WebhookOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Hapus baris `webhook_outbox` yang SUDAH `delivered` dan lebih lama dari
 * retensi. Payload `installation.*` memuat nama, desa, paket, dan perangkat
 * pelanggan — dibiarkan tumbuh, tabel ini jadi salinan data pelanggan kedua
 * yang tidak pernah diaudit siapa pun (docs/api/database-schema.md).
 *
 * Baris `failed` SENGAJA TIDAK disentuh — ia daftar rekonsiliasi "event mana
 * yang belum sampai", menghapusnya berarti kegagalan hilang diam-diam.
 * `pending`/`skipped` juga tidak disentuh (pending masih diproses; skipped
 * jadi jawaban "kenapa mitra tidak dapat kabar").
 */
class PruneWebhookOutbox extends Command
{
    protected $signature = 'webhook-outbox:prune {--days=90 : Hapus baris delivered yang lebih tua dari N hari}';

    protected $description = 'Hapus baris webhook_outbox yang sudah delivered dan lebih lama dari batas retensi';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $deleted = WebhookOutbox::where('status', 'delivered')
            ->where('delivered_at', '<', $cutoff)
            ->delete();

        $this->info("Berhasil hapus {$deleted} baris webhook_outbox delivered (lebih lama dari {$days} hari).");

        return self::SUCCESS;
    }
}
