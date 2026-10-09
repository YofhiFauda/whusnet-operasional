# Web Push Notification Laravel + Next.js yang Tahan Battery Saver

Sep 28, 2026

## Ringkasan

Web Push tidak bisa dibuat 100% kebal battery saver, karena keputusan akhir ada di OS. Yang bisa dilakukan: menaikkan peluang terkirim lewat konfigurasi server yang benar, PWA yang ter-install, edukasi user untuk whitelist browser, dan kanal cadangan untuk notifikasi kritis.

- **Android (Chrome, Edge, Samsung Internet):** push lewat FCM ke browser. Kalau MIUI/ColorOS melakukan force-stop pada Chrome, pesan tidak diterima sampai Chrome dibuka lagi.
- **iOS/iPadOS:** Web Push hanya jalan untuk situs yang di-*Add to Home Screen* sebagai PWA (iOS 16.4 ke atas), bukan di tab Safari biasa.
- **Strategi berlapis:** (1) kirim dengan `Urgency: high` dan TTL wajar, (2) wajibkan install PWA, (3) panduan whitelist per merek HP, (4) fallback Telegram/WhatsApp/email untuk yang kritis, (5) bila tetap kurang, bungkus jadi aplikasi native dengan FCM.

## Arsitektur

Laravel adalah pengirim (kunci VAPID + queue), Next.js adalah penerima (service worker), dan push service milik vendor browser yang mengantar pesan.

&#91;embedded content: alur Web Push · 5 komponen, 4 langkah\]

(1) Browser subscribe dan mengirim `endpoint`, `p256dh`, `auth` ke Laravel. (2) Saat ada event, job di queue mengenkripsi payload dan POST ke endpoint push service. (3) Push service meneruskan ke HP, di sinilah battery saver bisa menunda. (4) Browser membangunkan service worker yang menampilkan notifikasi.

## Implementasi Laravel

Pakai paket `laravel-notification-channels/webpush` (di atas `minishlink/web-push`): ia menyimpan subscription, mengenkripsi payload, dan otomatis menghapus subscription yang sudah mati (HTTP 404/410). Cek kompatibilitas versinya dengan versi Laravel Anda sebelum install.

**1. Install dan generate kunci VAPID**

```bash
composer require laravel-notification-channels/webpush
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
php artisan migrate
php artisan webpush:vapid   # menulis VAPID_PUBLIC_KEY & VAPID_PRIVATE_KEY ke .env
```

Tambahkan juga `VAPID_SUBJECT=mailto:admin@domainanda.id` di `.env`. Kunci VAPID jangan diganti setelah produksi, karena semua subscription lama akan tidak valid.

**2. Model User**

```php
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasPushSubscriptions, Notifiable;
}
```

**3. Endpoint subscribe / unsubscribe** (dilindungi Sanctum)

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/push/subscribe', [PushController::class, 'store']);
    Route::post('/push/unsubscribe', [PushController::class, 'destroy']);
});

// app/Http/Controllers/PushController.php
public function store(Request $request)
{
    $data = $request->validate([
        'endpoint'    => 'required|url',
        'keys.p256dh' => 'required|string',
        'keys.auth'   => 'required|string',
    ]);

    $request->user()->updatePushSubscription(
        $data['endpoint'],
        $data['keys']['p256dh'],
        $data['keys']['auth'],
        'aes128gcm'
    );

    return response()->json(['ok' => true]);
}

public function destroy(Request $request)
{
    $request->user()->deletePushSubscription($request->input('endpoint'));
    return response()->json(['ok' => true]);
}
```

**4. Class notifikasi** — perhatikan `urgency` dan `TTL`, keduanya kunci agar pesan tidak ditahan OS.

```php
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TicketAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Tiket baru #'.$this->ticket->id)
            ->body($this->ticket->subject)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag('ticket-'.$this->ticket->id)   // gabungkan notifikasi yang sama
            ->renotify(true)
            ->requireInteraction(true)
            ->data(['url' => '/tickets/'.$this->ticket->id])
            ->options([
                'urgency' => 'high',   // minta FCM/APNs mengirim segera, termasuk saat Doze
                'TTL'     => 3600,     // simpan maks 1 jam bila HP offline
            ]);
    }
}
```

**5. Kirim lewat queue**, jangan sinkron di request:

```php
$user->notify(new TicketAssigned($ticket));
```

Jalankan worker dengan Supervisor (`php artisan queue:work --tries=3`) supaya pengiriman tidak bergantung pada request HTTP.

## Implementasi Next.js

Next.js cukup menyediakan tiga hal: `public/sw.js`, manifest PWA, dan tombol "Aktifkan notifikasi" yang memanggil `subscribe()`. Contoh di bawah memakai App Router.

**1. Service worker — `public/sw.js`**

Setiap push **wajib** memanggil `showNotification()` di dalam `event.waitUntil()`. Push tanpa notifikasi yang terlihat membuat iOS mencabut subscription dan Chrome menampilkan notifikasi generik.

```js
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  event.waitUntil(
    self.registration.showNotification(data.title || 'Notifikasi', {
      body: data.body,
      icon: data.icon || '/icons/icon-192.png',
      badge: data.badge,
      tag: data.tag,
      renotify: !!data.renotify,
      requireInteraction: !!data.requireInteraction,
      data: data.data || {},
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      const tab = list.find((c) => c.url.includes(self.location.origin));
      if (tab) { tab.navigate(url); return tab.focus(); }
      return clients.openWindow(url);
    })
  );
});

// subscription diganti browser (mis. kunci rotasi) -> kirim ulang ke Laravel
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil(
    self.registration.pushManager
      .subscribe(event.oldSubscription.options)
      .then((sub) => fetch('/api/push/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(sub),
      }))
  );
});
```

**2. Helper subscribe — `lib/push.ts`**

```ts
const VAPID = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY!; // sama dengan VAPID_PUBLIC_KEY Laravel

function urlBase64ToUint8Array(b64: string) {
  const pad = '='.repeat((4 - (b64.length % 4)) % 4);
  const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export async function enablePush(api: (path: string, body: unknown) => Promise<unknown>) {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    throw new Error('Browser tidak mendukung push (di iPhone: install ke Home Screen dulu)');
  }
  const reg = await navigator.serviceWorker.register('/sw.js');
  const perm = await Notification.requestPermission();
  if (perm !== 'granted') throw new Error('Izin notifikasi ditolak');

  const sub =
    (await reg.pushManager.getSubscription()) ??
    (await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID),
    }));

  await api('/api/push/subscribe', sub.toJSON()); // fetch/axios dengan cookie Sanctum
}
```

**3. Tombol izin** — `requestPermission()` hanya boleh dipanggil dari klik user (iOS menolak bila dipanggil otomatis saat load).

```tsx
'use client';
export function EnablePushButton() {
  return (
    <button onClick={() => enablePush(postJson).catch((e) => alert(e.message))}>
      Aktifkan notifikasi
    </button>
  );
}
```

**4. Manifest PWA — `app/manifest.ts`** (wajib untuk iOS dan untuk prompt install di Android)

```ts
import type { MetadataRoute } from 'next';
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: 'Helpdesk',
    short_name: 'Helpdesk',
    start_url: '/',
    display: 'standalone',
    background_color: '#ffffff',
    theme_color: '#0f172a',
    icons: [
      { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
      { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
    ],
  };
}
```

**5. Jangan cache `sw.js`** — tambahkan di `next.config`:

```js
async headers() {
  return [{ source: '/sw.js', headers: [
    { key: 'Cache-Control', value: 'no-cache, no-store, must-revalidate' },
    { key: 'Service-Worker-Allowed', value: '/' },
  ]}];
}
```

Semua ini wajib lewat HTTPS (kecuali `localhost`). Jika Next.js dan Laravel beda domain, atur CORS dan `SANCTUM_STATEFUL_DOMAINS` agar request subscribe membawa cookie sesi.

## Kenapa notifikasi hilang di HP

Penyebab utamanya bukan pada kode Anda, melainkan pada aplikasi browser yang dibatasi atau di-force-stop oleh OS. Web Push menumpang pada proses browser, jadi nasib notifikasi sama dengan nasib Chrome di HP tersebut.

| Platform | Mekanisme yang menghambat | Dampak ke Web Push |
| --- | --- | --- |
| Android stok (Pixel, dll.) | Doze & App Standby | Pesan prioritas normal ditunda ke jendela maintenance; `Urgency: high` biasanya lolos |
| Xiaomi / Redmi / POCO (MIUI, HyperOS) | Autostart dimatikan, Battery saver "Restrict", app di-kill saat di-swipe dari Recents | Chrome ter-force-stop, pesan FCM tidak diterima sampai Chrome dibuka lagi |
| OPPO / realme (ColorOS, realme UI) | Auto launch mati, "Optimize battery usage", pembersihan background | Sama seperti MIUI; sering hilang setelah layar mati beberapa menit |
| vivo (Funtouch / OriginOS) | High background power consumption tidak diizinkan | Chrome dibatasi di background |
| Samsung (One UI) | "Sleeping apps" / "Deep sleeping apps" | Bila Chrome masuk daftar, push tertahan |
| iOS / iPadOS | Web Push hanya untuk PWA di Home Screen; tiap push wajib tampil | Tab Safari biasa tidak bisa subscribe; push tanpa notifikasi membuat subscription dicabut |

Selain battery saver, dua penyebab yang sering tertukar: izin notifikasi Chrome di level OS dimatikan (bukan izin situs), dan channel notifikasi situs di pengaturan Android di-set senyap. Referensi perilaku per merek: [dontkillmyapp.com](https://dontkillmyapp.com).

## Strategi agar tidak mudah diblokir

Gabungkan lima lapis ini; lapis 1–2 ada di kode, lapis 3 butuh kerja sama user, lapis 4–5 menjamin notifikasi kritis tetap sampai walau OS menang.

### 1. Kirim dengan benar dari server

- `urgency: high` untuk notifikasi yang harus segera (tiket baru, eskalasi). Header ini membuat push service mengirim sebagai prioritas tinggi yang boleh membangunkan HP dari Doze. Pakai `normal`/`low` untuk yang tidak mendesak, jangan semua di-set high.
- `TTL` sesuai umur informasi (mis. 3600 detik). TTL 0 berarti pesan dibuang bila HP sedang tidak terjangkau.
- Payload kecil (jauh di bawah 4 KB): cukup judul, isi singkat, dan URL. Detail diambil saat user membuka halaman.
- Pakai `tag` agar notifikasi untuk tiket yang sama saling menggantikan, bukan menumpuk. Notifikasi yang terlalu sering membuat user mematikan izin, dan itu tidak bisa Anda pulihkan dari server.

### 2. Wajibkan install sebagai PWA

- **iOS:** tanpa *Add to Home Screen*, Web Push tidak tersedia sama sekali. Deteksi `window.matchMedia('(display-mode: standalone)')` dan tampilkan panduan install bila belum.
- **Android:** PWA yang di-install lewat Chrome menjadi WebAPK dengan ikon dan pengaturan notifikasi sendiri, sehingga user tidak bingung mencari "notifikasi situs". Push tetap diterima oleh proses Chrome, jadi Chrome tetap perlu di-whitelist (lapis 3).

### 3. Halaman onboarding "Aktifkan notifikasi" per merek HP

Deteksi merek dari user agent (mis. `Xiaomi`, `Redmi`, `OPPO`, `CPH`, `RMX`, `vivo`, `SM-`) dan tampilkan hanya langkah yang relevan, lalu sediakan tombol **Kirim notifikasi uji**. Nama menu berbeda antar versi OS, jadi anggap ini perkiraan dan sesuaikan dengan HP yang dipakai tim Anda.

| Merek / OS | Langkah whitelist Chrome |
| --- | --- |
| Xiaomi, Redmi, POCO (MIUI / HyperOS) | Setelan > Aplikasi > Kelola aplikasi > Chrome: **Mulai otomatis** ON, **Penghemat baterai** = Tanpa batasan, Notifikasi semua ON. Kunci Chrome di Recents (tekan lama kartunya > ikon gembok). |
| OPPO, realme (ColorOS / realme UI) | Setelan > Aplikasi > Manajemen aplikasi > Chrome > Penggunaan baterai: izinkan **aktivitas latar belakang** dan **peluncuran otomatis**. Kunci di Recents. |
| vivo (Funtouch / OriginOS) | Setelan > Baterai > Konsumsi daya latar belakang > Chrome: Izinkan. Aktifkan juga Autostart di i Manager. |
| Samsung (One UI) | Setelan > Baterai > Batas penggunaan latar belakang: keluarkan Chrome dari Sleeping/Deep sleeping, masukkan ke **Never sleeping apps**. |
| Android stok (Pixel, Motorola, dll.) | Setelan > Aplikasi > Chrome > Baterai: **Tidak dibatasi**. |
| iPhone / iPad | Safari > Bagikan > Tambahkan ke Layar Utama, buka dari ikon, tekan "Aktifkan notifikasi". Pastikan mode Fokus mengizinkan aplikasi ini. |

### 4. Ukur: delivery receipt dari service worker

Setiap push membawa URL ack bertanda tangan. Service worker memanggilnya saat menerima push, sehingga Laravel tahu mana yang benar-benar sampai.

```php
// di toWebPush()
->data([
    'url' => '/tickets/'.$this->ticket->id,
    'ack' => URL::temporarySignedRoute('push.ack', now()->addDay(), ['id' => $this->id]),
])

// routes/api.php
Route::post('/push/ack/{id}', function (string $id) {
    PushDelivery::where('notification_id', $id)->update(['delivered_at' => now()]);
    return response()->noContent();
})->name('push.ack')->middleware('signed');
```

```js
// sw.js, di dalam handler 'push'
event.waitUntil(Promise.all([
  self.registration.showNotification(data.title, { /* ...opsi di atas... */ }),
  data.data?.ack ? fetch(data.data.ack, { method: 'POST', keepalive: true }) : null,
]));
```

### 5. Eskalasi ke kanal cadangan

Untuk notifikasi kritis, jadwalkan job pengecekan. Bila belum ada ack dalam N menit, kirim ulang lewat Telegram bot, WhatsApp (via penyedia API resmi), atau email. Telegram dan WhatsApp memakai koneksi push native yang biasanya sudah di-whitelist user.

```php
$user->notify(new TicketAssigned($ticket));
EscalateIfNotDelivered::dispatch($notificationId, $user->id)
    ->delay(now()->addMinutes(5));

// EscalateIfNotDelivered::handle()
if (! PushDelivery::where('notification_id', $this->notificationId)->whereNotNull('delivered_at')->exists()) {
    User::find($this->userId)->notify(new TicketAssignedTelegram(...)); // mis. laravel-notification-channels/telegram
}
```

Saat aplikasi sedang terbuka, pakai WebSocket (Laravel Reverb atau Pusher) untuk update real-time di layar; Web Push cukup untuk saat aplikasi di background.

## Opsi lanjutan: aplikasi pembungkus dengan FCM native

Jika setelah lapis 1–5 delivery rate di HP Xiaomi/OPPO tim Anda masih rendah, bungkus frontend Next.js menjadi aplikasi Android dengan FCM native. Push lalu ditujukan ke aplikasi Anda sendiri, bukan ke Chrome, sehingga user cukup whitelist satu aplikasi yang jelas namanya.

| Opsi | Push diterima oleh | Pengaruh battery saver | Usaha |
| --- | --- | --- | --- |
| PWA saja | Chrome / Safari | Ikut nasib browser | Rendah |
| TWA (Bubblewrap / PWABuilder) | Tetap Chrome (notifikasi didelegasikan) | Hampir sama dengan PWA | Rendah, dapat APK untuk Play Store |
| Capacitor + `@capacitor/push-notifications` | Aplikasi Anda via FCM native | Lebih baik; tetap perlu Autostart di MIUI/ColorOS, tapi user whitelist aplikasi Anda sendiri | Sedang: build APK, Firebase project, Laravel kirim via FCM HTTP v1 |

Untuk jalur Capacitor, Laravel menyimpan token FCM per device dan mengirim via paket seperti `kreait/laravel-firebase` atau `laravel-notification-channels/fcm`. Gunakan pesan dengan blok `notification` dan `android.priority = high`, karena pesan ini ditampilkan otomatis oleh FCM saat aplikasi di background (kecuali aplikasi di-force-stop). Web Push tetap dipertahankan untuk user desktop dan iPhone.

## Checklist testing & monitoring

- [ ] Uji di HP nyata tiap merek yang dipakai tim (minimal satu Xiaomi, satu OPPO/realme, satu Samsung, satu iPhone), bukan hanya emulator.
- [ ] Skenario per HP: layar mati 15 menit, Chrome di-swipe dari Recents, mode hemat daya aktif, lalu kirim push dan catat apakah dan kapan sampai.
- [ ] Debug di Android: `chrome://inspect` untuk service worker; di desktop Chrome, DevTools > Application > Service Workers > Push untuk simulasi.
- [ ] Log respons push service per kirim: 201 = diterima, 404/410 = subscription mati (paket menghapus otomatis), 413 = payload terlalu besar, 429 = kena rate limit.
- [ ] Dashboard delivery rate dari tabel `push_deliveries` (terkirim vs ack) per merek HP dan per OS, untuk tahu siapa yang perlu onboarding ulang.
- [ ] Endpoint re-subscribe tiap user login, supaya subscription yang hilang (clear data, ganti HP) otomatis terdaftar lagi.
- [ ] Pantau queue worker (Supervisor/Horizon); worker mati terlihat persis seperti "notifikasi tidak sampai".
