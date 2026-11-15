<?php

namespace App\Console\Commands;

use App\Models\PushDelivery;
use Illuminate\Console\Command;

/**
 * Alat bantu debug manual (ADHOC-288, Tahap 6) — lihat status beberapa
 * delivery terakhir tanpa perlu nulis tinker one-liner tiap kali.
 */
class WebPushDeliveriesStatusCommand extends Command
{
    protected $signature = 'webpush:deliveries {count=5 : Jumlah baris terbaru yang ditampilkan}';

    protected $description = 'Tampilkan status beberapa push_deliveries terbaru';

    public function handle(): int
    {
        $rows = PushDelivery::with('notifiable')->latest()->take((int) $this->argument('count'))->get();

        $this->table(
            ['ID', 'User', 'Notification', 'Status', 'Sent', 'Delivered', 'Escalated'],
            $rows->map(fn (PushDelivery $d) => [
                $d->id,
                $d->notifiable?->name ?? "#{$d->notifiable_id}",
                class_basename($d->notification_class),
                $d->status->value,
                $d->sent_at?->format('H:i:s'),
                $d->delivered_at?->format('H:i:s') ?? '-',
                $d->escalated_at?->format('H:i:s') ?? '-',
            ])
        );

        return self::SUCCESS;
    }
}
