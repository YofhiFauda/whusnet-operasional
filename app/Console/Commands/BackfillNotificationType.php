<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Fase 5.2 — isi kolom `notification_type` untuk notifikasi yang sudah ada
 * SEBELUM kolom ini dibuat, dari `data['type']`.
 *
 * Idempoten: hanya menyentuh baris yang `notification_type`-nya masih NULL.
 * Notifikasi baru sudah diisi otomatis via DatabaseNotification::creating
 * (AppServiceProvider), jadi command ini murni untuk data lama.
 */
class BackfillNotificationType extends Command
{
    protected $signature = 'notifications:backfill-type';

    protected $description = 'Isi notifications.notification_type dari data[type] untuk baris lama (Fase 5.2).';

    public function handle(): int
    {
        $count = 0;

        DatabaseNotification::query()
            ->whereNull('notification_type')
            ->select('id', 'data')
            ->chunkById(1000, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $data = $row->data; // sudah di-cast array oleh model
                    $type = is_array($data) ? ($data['type'] ?? null) : null;
                    if ($type !== null) {
                        DatabaseNotification::whereKey($row->id)->update(['notification_type' => $type]);
                        $count++;
                    }
                }
            });

        $this->info("notification_type diisi: {$count}");

        return self::SUCCESS;
    }
}
