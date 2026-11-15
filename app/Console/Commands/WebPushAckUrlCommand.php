<?php

namespace App\Console\Commands;

use App\Models\PushDelivery;
use App\Services\WebPushDeliveryTracker;
use Illuminate\Console\Command;

/**
 * Debug manual (ADHOC-288) — cetak ack URL delivery terakhir biar bisa
 * ditest curl langsung dari luar, mengisolasi apakah masalahnya di sw.js
 * (gak pernah motret fetch-nya) atau di server (signed URL-nya sendiri
 * gagal diverifikasi / gak ke-reach).
 */
class WebPushAckUrlCommand extends Command
{
    protected $signature = 'webpush:ack-url {delivery? : ID push_delivery, default terbaru}';

    protected $description = 'Cetak ack URL sebuah push_delivery (buat curl manual)';

    public function handle(WebPushDeliveryTracker $tracker): int
    {
        $id = $this->argument('delivery');
        $delivery = $id ? PushDelivery::find($id) : PushDelivery::latest()->first();

        if (! $delivery) {
            $this->error('Delivery tidak ditemukan.');

            return self::FAILURE;
        }

        $this->line("Delivery #{$delivery->id} status: {$delivery->status->value}");
        $this->line($tracker->ackUrl($delivery));

        return self::SUCCESS;
    }
}
