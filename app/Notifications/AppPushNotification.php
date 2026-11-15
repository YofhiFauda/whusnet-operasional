<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\WebPushDeliveryTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push device buat 4 event bisnis yang disetujui user (ADHOC-288 Tahap 6):
 * Tiket Prioritas High/Critical, Task Assignment Survey/PSB/MTN, Setoran
 * Collector, Pendaftaran Pelanggan Baru. SENGAJA notification class
 * TERPISAH dari `AppNotification` (database+broadcast, sync) — `via()`
 * `AppNotification` dipakai SEMUA notif in-app termasuk yang TIDAK disetujui
 * push (mis. SLA Warning), jadi nggak bisa ditambah channel `webpush` di
 * situ tanpa ikut mem-push event yang user eksplisit tolak. Class ini
 * `ShouldQueue` — network call ke push service biarin lewat Horizon, bukan
 * numpuk di request thread yang manggil notifikasi in-app (beda dari
 * `AppNotification` yang sengaja sync).
 */
class AppPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * $url = path relatif tujuan saat notifikasi diklik (biasanya sama dengan
     * `actionUrl` AppNotification pasangannya). Dulu payload cuma bawa
     * ackUrl, jadi public/sw.js selalu membuka `/` — teknisi yang mengetuk
     * "Jadwal Baru" mendarat di Dashboard, bukan di task-nya (ADHOC-297).
     * Null = fallback `/` di service worker. Push ke pelanggan (portal,
     * origin lain) sengaja tidak mengisi ini.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
    ) {
        // Job antrean baru dikirim SETELAH transaksi pemanggil commit (review
        // ADHOC-297 #7). Semua koneksi queue `after_commit=false` dan worker
        // Horizon bisa mengambil job sebelum COMMIT — atau sesudah ROLLBACK:
        // batch kolektor / generate tagihan yang gagal di tengah tetap
        // mengirim "Pembayaran Diterima"/"Tagihan Terbit" untuk data yang
        // tidak pernah ada. Di luar transaksi, afterCommit langsung jalan.
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $tracker = app(WebPushDeliveryTracker::class);
        $delivery = $tracker->start($notifiable, static::class);

        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/images/logo-192.png')
            ->body($this->message)
            ->data(array_filter([
                'ackUrl' => $tracker->ackUrl($delivery),
                'url' => $this->url,
                // Penerima, supaya public/sw.js bisa menanyakan ke tab yang
                // terlihat apakah tab itu login sebagai user yang sama dan
                // sanggup menampilkan toast (review ADHOC-297 #1). Hanya
                // untuk staf (User) — push pelanggan portal tidak punya toast.
                'uid' => $notifiable instanceof User ? $notifiable->id : null,
            ]))
            ->tag('app-push-'.$delivery->id)
            ->options(['TTL' => 300]);
    }
}
