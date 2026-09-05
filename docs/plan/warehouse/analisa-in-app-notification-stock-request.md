# Analisa In-App Notification untuk Permintaan Stok (2026-09-03)

Lanjutan [ADHOC-57](../../TASKS.md) (Ambang Stok Rendah + Permintaan Stok Cabang→Pusat). Permintaan Stok sekarang masih **pull** — Pusat harus buka halaman buat lihat antrean. User tanya soal push notification biar Pusat gak perlu nunggu notice sendiri. Sebelum mutusin kanal (in-app / Telegram / Web Push), dokumen ini nge-audit **seberapa siap sistem in-app notification yang udah ada** (`App\Notifications\AppNotification` + Reverb/Echo) buat dijadiin fondasi.

**Kesimpulan singkat:** sistemnya solid buat cakupannya sekarang (Task/FOP, Ticketing, Verifikasi, Billing, SLA breach, Customer lifecycle, Import) — udah 2 kali diaudit & dibenerin, py POP-scope, cache unread-count, sync antar-tab, pruning otomatis, ~40 test. Tapi ada **1 kesalahan asumsi desain yang cukup penting** + 1 pola disiplin yang belum konsisten, dua-duanya harus diperhitungkan SEBELUM dipakai buat alert Stock Request.

---

## 1. Klaim "gak gantung ke queue worker" cuma benar SETENGAH

`app/Notifications/AppNotification.php:9-17` — docblock-nya eksplisit bilang sengaja bukan `ShouldQueue`, biar bell notification gak ketunda diam-diam kalau Horizon down/hang. Ini rujuk ke `docs/plan/analisa-status-implementasi-notifikasi.md` §6.3 yang klaim masalah itu "DIHILANGKAN (bukan cuma dimonitor)".

**Ditelusuri sampai ke framework Laravel — klaim itu cuma berlaku buat separuh jalur:**

1. `AppNotification::via()` = `['database', 'broadcast']`. Baris **database** ditulis sinkron, langsung — ini yang bikin `notify()` "kelihatan" instan.
2. Tapi channel **broadcast** (yang bikin lonceng/toast nyala real-time) dikirim lewat `Illuminate\Notifications\Events\BroadcastNotificationCreated` — event ini `implements ShouldBroadcast`, **BUKAN** `ShouldBroadcastNow`.
3. `Illuminate\Broadcasting\BroadcastManager::queue()` — cuma event `ShouldBroadcastNow` yang dikirim langsung (`dispatchNow()`). `BroadcastNotificationCreated` gak termasuk, jadi otomatis di-push ke **queue** (`BroadcastEvent implements ShouldQueue`).

**Artinya:** apapun keputusan `AppNotification` soal `ShouldQueue`, bagian yang bikin notifikasi "real-time" (push ke lonceng/toast) **SELALU lewat Horizon**. Kalau Horizon lagi mati/nyangkut:
- Baris DB tetap tersimpan (kelihatan kalau user buka `/notifications` manual, atau nunggu cache unread-count refresh 20 detik).
- Tapi lonceng/toast **gak nyala seketika** — persis skenario yang katanya udah "dihilangkan", padahal cuma dihilangin di jalur database, bukan di jalur push-nya sendiri.

## 2. Reverb mati = gagal senyap, nol alerting

Reverb (websocket server) dipanggil lewat protokol Pusher (`BroadcastManager::createReverbDriver()` cuma manggil `createPusherDriver()`). Kalau `php artisan reverb:start` gak jalan, `PusherBroadcaster::broadcast()` throw `BroadcastException`. Karena ini kejadian **di dalam job queue**, error-nya cuma nongol jadi "failed job" di dashboard Horizon — **gak ada notifikasi/alert ke siapa pun** kalau ini kejadian. Digrep seluruh repo: nol monitoring buat skenario spesifik ini (`docker-compose.yml` cuma healthcheck "proses Horizon jalan", bukan "job-nya sukses").

## 3. Disiplin `try/catch` belum merata di semua pemanggil

Cuma 2 dari 6 Service yang manggil `notify()` (`CashDepositService`, `CollectorDepositService`) yang bungkus pakai helper `safelyNotify()` (try/catch + `report()`) — dan itu ADA ALASANNYA, bukan kebetulan:

> `app/Services/CollectorDepositService.php:458-462` — *"Bungkus pengiriman notifikasi supaya kegagalannya tak pernah merambat ke transaksi uang. Satu tempat, dipakai semua jalur — kalau tiap pemanggil menulis try/catch-nya sendiri, cepat atau lambat ada satu yang lupa (persis yang terjadi di jalur pembayaran, review #2)."*

~20 titik pemanggil lain (`TaskService`, `TicketService`, `CustomerVerificationController`, `PaymentController`, `CustomerController` import, `CheckFopTaskSlaBreach`, dll — total 27 call site) manggil `->notify()` polos, **tanpa** try/catch, inline sama transaksi bisnis utamanya. Kalau baris database notifikasi gagal (mis. masalah DB transien), exception-nya bisa ikut nge-gagalin aksi utama (task complete, ticket close, verifikasi approve, dst) — bukan cuma notifnya yang gagal.

## 4. Apa yang SUDAH beres (biar gak dianggap semuanya rusak)

- POP-scope query notifikasi udah bener (`EffectiveAccessService`, bukan `$user->pops()` lawas).
- Unread-count di-cache 20 detik (`unreadNotificationsCountCached()`), gak query tiap page load.
- Cross-tab sync pas mark-as-read (`NotificationsMarkedRead`, `ShouldBroadcastNow` — ini BENERAN sinkron, beda dari notif barunya).
- Pruning otomatis: `notifications:prune-read --days=90`, jadwal harian, cuma hapus yang udah dibaca.
- Dedup per-fitur ditangani manual & konsisten (self-skip aktor, existence-guard, kolom `sla_breach_notified_at`) — tapi ini disiplin per-titik, BUKAN mekanisme generik dari `AppNotification` sendiri.
- Channel auth (`App.Models.User.{id}`) simpel & benar — identity-only check.
- ~40 test dedicated, tapi **semuanya jalan dengan `BROADCAST_CONNECTION=null`** (`phpunit.xml`) — nol test yang beneran nge-exercise Reverb/Pusher protocol asli. Skenario "Reverb mati" di atas gak ke-cover test sama sekali.

## 5. Implikasi buat Permintaan Stok

Kalau notifikasi "Permintaan Stok Baru" dibangun di atas sistem ini apa adanya:

- **Kondisi normal** (Horizon + Reverb sehat, yang emang harus sehat buat fitur lain juga): jalan bagus, real-time.
- **Kondisi Horizon/Reverb lagi gangguan** (bisa kapan aja, nol alerting): notifikasi cuma numpuk di DB, Pusat baru sadar kalau buka halaman Permintaan Stok manual — **persis kembali ke masalah awal** ("Pusat gak sadar") yang mau diselesaikan pake push ini.
- Wajib: panggilan `notify()` buat Stock Request **dibungkus `safelyNotify()`**-style (try/catch + `report()`) — biar kegagalan kirim notif gak ikut nge-block proses ajuin/fulfill Permintaan Stok itu sendiri. Bukan opsional, ini pelajaran dari insiden nyata di jalur pembayaran.

## 6. Keputusan (belum final, nunggu user)

Tiga opsi kanal push dibahas di percakapan ini:

| Kanal | Infra baru? | Keandalan | Catatan |
|---|---|---|---|
| **In-app** (`AppNotification`, Reverb/Echo) | Tidak | Tergantung Horizon+Reverb sehat, gagal senyap kalau enggak | Sistem existing, tapi py 2 gap di atas |
| **Telegram** (`TelegramBotService`) | Tidak (reuse) | Tinggi — Telegram app sendiri yang urus delivery, di luar kendali Horizon/Reverp | Ditolak user: dianggap ribet, orang males |
| **Web Push** (VAPID + service worker) | Ya (`web-push` package, tabel subscription baru) | Tergantung izin browser (bisa ditolak permanen) | Ditolak user: khawatir "berat" (yang beneran berat itu maintenance-nya, bukan performa) |

**Belum diputuskan** kanal mana yang jalan. Kalau pilih tetap in-app: harus terima resiko §1-2 di atas (sama kayak semua notif lain di sistem), plus WAJIB pola `safelyNotify()` di titik pemanggilnya (§3).
