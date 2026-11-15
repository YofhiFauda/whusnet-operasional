<?php

namespace App\Notifications;

use App\Services\WebPushDeliveryTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notification khusus uji manual Web Push (ADHOC-288, Tahap 5→6).
 * Dipicu lewat `php artisan webpush:test {user}` — BUKAN dipakai di event
 * bisnis asli, cuma buat memverifikasi plumbing (subscribe → server kirim →
 * push service → device) sebelum Tahap 6 nyambungin event nyata.
 */
class TestWebPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $delivery = app(WebPushDeliveryTracker::class)->start($notifiable, static::class);

        return (new WebPushMessage)
            ->title('Tes Notifikasi Whusnet')
            ->icon('/images/logo-192.png')
            ->body('Kalau ini muncul di perangkatmu, Web Push beneran jalan.')
            ->data(['ackUrl' => app(WebPushDeliveryTracker::class)->ackUrl($delivery)])
            // Tag unik tiap kirim — default sw.js pakai 'default-tag' +
            // renotify:false, jadi kirim berkali-kali dgn tag sama cuma
            // nge-update notif lama diam-diam (gak muncul toast baru).
            ->tag('webpush-test-'.now()->valueOf())
            ->options(['TTL' => 300]);
    }
}
