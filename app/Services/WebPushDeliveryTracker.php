<?php

namespace App\Services;

use App\Enums\PushDeliveryStatus;
use App\Jobs\EscalateIfNotDeliveredJob;
use App\Models\PushDelivery;
use Illuminate\Support\Facades\URL;

/**
 * Dipanggil dari dalam `toWebPush()` tiap notification class yang mau
 * delivery-tracking (ADHOC-288, Tahap 6). Bukan dipanggil otomatis oleh
 * channel — package `laravel-notification-channels/webpush` bikin SATU
 * payload yang sama lalu dikirim ke semua subscription notifiable
 * (`WebPushChannel::send()`), jadi tracking-nya per PENGIRIMAN notifikasi,
 * bukan per subscription/device.
 */
class WebPushDeliveryTracker
{
    public function start(object $notifiable, string $notificationClass): PushDelivery
    {
        $delivery = PushDelivery::create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'notification_class' => $notificationClass,
            'status' => PushDeliveryStatus::Sent,
            'sent_at' => now(),
        ]);

        EscalateIfNotDeliveredJob::dispatch($delivery->id)->delay(now()->addMinutes(5));

        return $delivery;
    }

    public function ackUrl(PushDelivery $delivery): string
    {
        return URL::signedRoute('webpush.ack', ['delivery' => $delivery->id]);
    }
}
