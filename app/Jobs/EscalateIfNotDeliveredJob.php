<?php

namespace App\Jobs;

use App\Enums\PushDeliveryStatus;
use App\Models\PushDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dijadwalkan 5 menit setelah push dikirim (lihat WebPushDeliveryTracker).
 * Kalau sampai sini belum ada ack dari device manapun, tandai "escalated".
 *
 * Fallback Telegram (rancangan battery saver, Tahap 6 item 3) SENGAJA belum
 * dipasang — butuh bot token & setup eksternal, ditunda sampai ada event
 * kritis nyata yang pakai ini (keputusan user 2026-11-07). Buat sementara,
 * eskalasi cuma di-log — gampang disambung ke channel lain nanti tanpa
 * ubah titik pemanggilan job ini.
 */
class EscalateIfNotDeliveredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $pushDeliveryId) {}

    public function handle(): void
    {
        $delivery = PushDelivery::find($this->pushDeliveryId);

        if (! $delivery || $delivery->status !== PushDeliveryStatus::Sent) {
            // Sudah di-ack (jadi Delivered) atau sudah di-escalate sebelumnya
            // (dobel job gak mungkin normal, tapi jaga-jaga) — tidak ada yang
            // perlu dilakukan.
            return;
        }

        $delivery->update([
            'status' => PushDeliveryStatus::Escalated,
            'escalated_at' => now(),
        ]);

        Log::warning('Push notification tidak ter-ack dalam 5 menit, dieskalasi.', [
            'push_delivery_id' => $delivery->id,
            'notification_class' => $delivery->notification_class,
            'notifiable_type' => $delivery->notifiable_type,
            'notifiable_id' => $delivery->notifiable_id,
        ]);
    }
}
