# Urutan Pengerjaan Notifikasi & Realtime

**Tanggal:** 2026-10-06
**Sifat:** Usulan urutan. Belum disetujui user dan belum masuk `docs/TASKS.md`.
**Pelengkap:** `analisa-status-implementasi-notifikasi.md`, `analisa-cakupan-realtime-per-halaman.md`, `analisa-cakupan-push-notifikasi.md`, `analisa-in-app-dan-push-notifikasi.md`, `rancangan-arsitektur-web-push-pwa-nextjs.md`, `Web Push Notification Laravel + Next.js yang Tahan Battery Saver.md`.

---

## Tahap 0: Sprint Aktif (Wajib Dulu)

Aturan CLAUDE.md: jangan loncat sprint.

- **`S8.10-T003` FOP Notification Dashboard**: ditutup dari task aktif atas keputusan user 2026-10-23. Riwayat, filter, dan baca/belum baca sudah berkembang pada modul notifikasi; acceptance criteria historis tidak diverifikasi ulang dalam keputusan ini.
- Tahap Web Push dan perluasan realtime di bawah tetap usulan terpisah, bukan sisa otomatis S8.10-T003.

## Tahap 1: Keputusan Sebelum Kode

Blocker. Tanpa ini tahap berikutnya tidak bisa dimulai.

1. ~~**Pemetaan role** dari dokumen ke RBAC nyata (`analisa-cakupan-push-notifikasi.md` §5). Contoh: Finance Pusat, Supervisor, NOC On-Call.~~ **SELESAI, bukan blocker lagi (keputusan user 2026-11-07):** role di dokumen analisa (Finance Pusat, Supervisor, NOC On-Call) draf lama, tidak dipetakan satu-satu. Penerima push ditentukan langsung dari permission/role yang ADA SEKARANG, pola sama dengan Tahap 3 (`EffectiveAccessService::usersWithPermission()` + scope POP) — bukan nama role dari dokumen. Tidak ada role/permission baru yang perlu dibikin.
2. ~~**Daftar event push** yang disetujui.~~ **SELESAI, bukan blocker lagi (keputusan user 2026-11-07):**
   - **Disetujui jadi push** (staf internal, Tahap 4-6): Tiket Gangguan Prioritas High/Critical (NOC & Teknisi); New Task Assignment Survey/PSB/Ticket (Teknisi); Setoran Collector Approved/Rejected (Collector); Pendaftaran Pelanggan Baru (reviewer — permission `customer_registration_verification.approve`, sama penerima dengan notif in-app Tahap 3).
   - **Ditolak jadi push, tetap in-app saja:** SLA Warning/Overdue (H-2 jam) — user eksplisit bilang TIDAK perlu push buat ini.
   - **Push Fase 3 (portal pelanggan, Tahap 7):** Tagihan & Pembayaran (invoice terbit, pembayaran berhasil) — dikonfirmasi user. "Maintenance jaringan" di `analisa-in-app-dan-push-notifikasi.md` §3 BELUM dikonfirmasi — jangan diasumsikan ikut, tanya user kalau Tahap 7 mulai digarap.
3. ~~**Field prioritas tiket** (untuk push High/Critical). Belum ada di rancangan.~~ **SELESAI, bukan blocker lagi (2026-11-07):** `tickets.priority` sudah ada (migration `2026_07_23_000001_create_tickets_table.php`), di-cast `App\Enums\FopTaskPriority` (`LOW/MEDIUM/HIGH/URGENT`, ada `sortOrder()`). `ticket_issue_categories.default_priority` juga ada. Dicatat salah di versi sebelumnya dokumen ini — dikoreksi lewat verifikasi kode langsung, bukan cuma baca rancangan.
4. ~~**Lokasi Fase 3** (portal pelanggan Next.js): repo terpisah atau di repo ini.~~ **SELESAI, bukan blocker lagi (keputusan user 2026-11-07):** repo terpisah. Repo Laravel ini cuma menyediakan endpoint API (`/api/v1/customer/push/*`, Sanctum) buat dikonsumsi Next.js-nya — lihat Tahap 7.
5. ~~**Masuk sprint mana.** Web Push belum ada di `docs/TASKS.md`.~~ **SELESAI, bukan blocker lagi (keputusan user 2026-11-07):** sprint baru, bukan nebeng ADHOC-202. Dicatat sebagai `ADHOC-288` di `docs/TASKS.md` (status `TODO`), pola sama dengan ad-hoc lain yang jalan "di luar sprint ADHOC-202".

**Semua blocker Tahap 1 selesai.** Tahap 4 (Web Push Fase 1 Backend) sudah bisa mulai.

## Tahap 2: Realtime Halaman (Tanpa Push, Risiko Rendah)

Perbaiki reload manual yang sudah ada. Tidak butuh infrastruktur baru, hanya listener Echo yang sudah ada dipakai ulang.

| Prioritas | Lokasi | Pekerjaan | Status |
|---|---|---|---|
| 1 | `fop_tasks/index.blade.php:1446` | Ganti reload setelah reassign tim dengan update state Alpine (doc realtime #13) | **Selesai (2026-11-07).** `submitBulkAssignTeam()` manggil `refreshFopTaskRow()` per task, bukan `window.location.reload()`. Reuse `FopTaskUpdated` yang sudah di-dispatch server-side (`FopTaskController::bulkAssignTeam()`). |
| 2 | `fop/dashboard.blade.php:995, 999` | Ganti reload setelah aksi, dan hapus tombol "Status Teknisi" (doc realtime #12) | **Sudah selesai sebelumnya** — baris dokumen ini usang. Kode saat ini sudah pakai `refreshTeamsBoard()`/partial swap; `window.location.reload()` yang tersisa cuma fallback `catch`/elemen hilang. Tombol "Status Teknisi" sudah tidak ada. |
| 3 | `collector-worksheet/show`, `partials/collector-pay-script`, `technician-payments/index`, `payments/partials/quick-payment-modal`, `customers/qr/show` | Reload setelah bayar. Perlu event dan channel baru jika ingin realtime | **Sebagian selesai (2026-11-07).** `customers/qr/show` dikeluarkan dari daftar — reload-nya BUKAN soal pembayaran (reset PIN/portal account, salah masuk daftar ini sejak awal). `collector-pay-script` (dipakai `collector-worksheet/show` & `technician-payments/index`): reload yang dipicu `use_balance_amount > 0` DIHAPUS — `CollectorPaymentService::record()` sekarang kirim `customer_remaining_balance` per baris, dipatch client-side (`cbUpdateCustomerBalance()`) ke semua baris pelanggan yang sama tanpa reload. `technician-payments/index` TETAP reload (flag `reloadOnSuccess=true` eksplisit dari pemanggil — statistik lain di halaman itu belum di-patch, bukan soal saldo). `quick-payment-modal`/`payments/create` TIDAK disentuh — form 1-invoice, reload di situ masuk akal (bukan halaman monitoring). Test: `CollectorBatchPaymentParityTest::response_includes_customer_remaining_balance_for_client_side_patch`. |
| 4 | `tickets/history.blade.php:75` | Hapus tombol reload manual setelah listener ada | **Selesai (2026-11-07).** Halaman ini sebelumnya TIDAK punya listener Echo sama sekali (beda dari `tickets/partials/archive.blade.php` yang dikira sama). Ditambah: `allowedPopIds` di `TicketHistoryController`, container id di stat/table/card/pagination, listener `tickets.{popId}` → `.TicketQueueUpdated` → `refetchAndSwap()`. Tombol Refresh tetap ada tapi dipakai buat refetch manual (bukan reload), konsisten sama pola `archive.blade.php`. Test baru: `TicketHistoryTest::test_allowed_pop_ids_for_realtime_match_user_scope`. |

Tiap perubahan wajib ada test (CLAUDE.md). Pola: `#[Test]`, nama sesuai gejala.

## Tahap 3: Gap Event In-App

Event yang sudah diputuskan di Tahap 1 dan belum punya notif in-app. Dikerjakan lewat `AppNotification` supaya ikut toast dan lonceng.

- Pendaftaran pelanggan baru (Admin POP, FOP, Sales) — **Selesai (2026-11-07).** `CustomerController::store()` → `notifyRegistrationReviewers()`: notif ke user dengan permission `customer_registration_verification.approve`, di-scope ke POP pelanggan (ALL_POP atau SELECTED_POP/POP_TREE target POP yang sama), actor dikecualikan. Bukan role hardcode "FOP" seperti di dokumen analisa awal — real gate-nya permission, dan FOP baru masuk setelah approve (lihat `CustomerRegistrationVerificationController`). Test: `CustomerCreateTest::test_registration_notifies_approvers_scoped_to_same_pop`.
- Aktivasi / Siap Billing (Sales, Admin POP) — **Ternyata SUDAH ADA sebelum sesi ini**, cuma belum ketauan sampai dibaca kodenya langsung (grep sebelumnya cuma nyentuh `CustomerWorkflowService`, padahal transisi ACTIVE yang umum ada di `CustomerVerificationController::finalVerify()`). Notif ke pendaftar asli (`notifyCustomerCreatorIfDifferentActor()`), SUCCESS, cuma utk jalur non-Bisnis (Bisnis notif menyusul di `BusinessDevelopmentVerificationController::verify()` begitu BD kelar — sudah ada juga). Test: `CustomerLifecycleNotificationTest::test_final_verify_notifies_original_registrar_with_success_type`.
- Ringkasan generate tagihan batch (Finance, Admin POP) — **Selesai (2026-11-07).** `GenerateMonthlyInvoicesCommand::notifyBatchSummary()`: ringkasan global ke `invoices.approve` (admin/owner) + ringkasan per-POP ke `invoices.create` yang di-scope POP tersebut (pop_admin) — cuma POP yang BENERAN kebagian `created`/`failed` di run ini yang dapat kabar, bukan broadcast semua pop_admin tiap hari. Tidak jalan sama sekali untuk `--dry-run` atau run tanpa aktivitas. Test: `GenerateMonthlyInvoicesNotifiesSummaryTest` (3 skenario: sukses+scope, dry-run, nihil aktivitas).
- Laporan survey/pemasangan selesai (FOP, Admin POP): sudah ada di `TaskService::complete()`, cek cakupan
- Import selesai: sudah ada (§8.6 status doc)

**Catatan 2026-11-07:** sebelum menulis notif baru buat event mana pun, baca controller/service yang BENERAN menjalankan transisinya — grep per nama kelas yang "kelihatan benar" bisa meleng ke kelas yang salah (kasus Aktivasi/Siap Billing di atas: logic-nya bukan di `CustomerWorkflowService`, tapi di `CustomerVerificationController`).

Setiap event wajib dipetakan ke `NotificationType` dan `actionUrl` yang sudah ada route-nya (CLAUDE.md: jangan tulis string status baru).

## Tahap 4: Web Push Fase 1 (Backend) — **Selesai (2026-11-07)**

Dari `rancangan-arsitektur-web-push-pwa-nextjs.md` §8.

1. ~~Install `laravel-notification-channels/webpush`.~~ **Selesai.** `composer require laravel-notification-channels/webpush` → `v13.0.1`, compatible Laravel 13 (dry-run dicek dulu). MCP `laravel-boost` gagal connect (timeout) di sesi ini — `search-docs` tidak bisa dipanggil; kompatibilitas diverifikasi lewat `composer require --dry-run` langsung, bukan dilewati.
2. ~~Generate VAPID, set `.env`.~~ **Selesai.** `php artisan webpush:vapid` → `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` di `.env`.
3. ~~Migrasi `push_subscriptions`.~~ **Selesai, dengan penyesuaian.** Package sudah menyediakan migration sendiri (`vendor:publish --provider=NotificationChannels\WebPush\WebPushServiceProvider`): `morphs('subscribable')`, `endpoint` (ascii, unique, `ENDPOINT_MAX_LENGTH=1024` lewat migration tambahan `increase_push_subscriptions_endpoint_length`), `public_key`/`auth_token`/`content_encoding` nullable. Kolom metadata rancangan §3 (`device_name`, `user_agent`, `last_active_at`) **SENGAJA tidak ditambah** — belum ada pemakainya (baru relevan Tahap 8 dashboard per-brand), nambah sekarang = tabel kolom nganggur (CLAUDE.md: hindari tabel/kolom sebelum dibutuhkan). Tambah nanti kalau Tahap 8 digarap.
4. ~~Trait `HasPushSubscriptions` untuk `User`.~~ **Selesai.** Package sudah menyediakan trait lengkap (`updatePushSubscription()`, `deletePushSubscription()`, `routeNotificationForWebPush()`) — tinggal `use HasPushSubscriptions` di `User` model, tidak perlu ditulis ulang. `Customer` tetap ditunda ke Tahap 7.
5. ~~`WebPushChannel` lewat queue Horizon.~~ **Sudah tersedia dari package**, standar Laravel: notifikasi yang `implements ShouldQueue` dan punya `webpush` di `via()` otomatis lewat Horizon (connection `QUEUE_CONNECTION` yang sudah terpasang). Belum ada notification class yang pakai `webpush` channel — itu scope Tahap 6 (event nyata), bukan Tahap 4 (infrastruktur).
6. ~~`PushSubscriptionController` + route.~~ **Selesai.** `app/Http/Controllers/PushSubscriptionController.php` (4 endpoint sesuai rancangan §5A: `vapidPublicKey`, `subscribe`, `unsubscribe`, `subscriptions`), route statis semua (tanpa parameter dinamis, tidak ada risiko urutan) di `routes/web.php` dalam grup `auth` existing, sebelum `/panduan`.
7. ~~Test: subscribe, unsubscribe, pruning 404/410, notif lewat queue.~~ **Selesai sebagian, sisanya bukan scope Tahap 4.** `tests/Feature/WebPushSubscriptionTest.php` (6 test): vapid key endpoint, subscribe simpan ke DB, validasi endpoint wajib, unsubscribe cuma punya sendiri, list subscription ter-scope per user, guest ditolak 401. **Pruning 404/410 TIDAK ditulis test baru** — itu logic bawaan package (`ReportHandler::handleReport()` hapus subscription kalau `$report->isSubscriptionExpired()`), bukan kode kita; dan **"notif lewat queue" belum ada test** karena belum ada notification class nyata yang pakai channel `webpush` (menunggu Tahap 6).

**Catatan:** Laravel Boost `search-docs` tidak terpakai (MCP timeout) — kompatibilitas tervalidasi lewat `composer require --dry-run` pada host nyata, bukan asumsi. `docker compose exec app rm -f bootstrap/cache/{packages,services,config}.php && package:discover` dijalankan setelah `composer require` sesuai CLAUDE.md.

## Tahap 5: Web Push Fase 2 (Blade PWA Operasional) — **Selesai (2026-11-09)**

1. ~~`public/sw.js` dengan handler `push`, `notificationclick`, `pushsubscriptionchange`.~~ **Selesai.** 3 handler sesuai rancangan §6 + tambahan. `pushsubscriptionchange` SENGAJA cuma `pushManager.subscribe()` ulang (jaga subscription hidup di level browser), TIDAK langsung POST ke `/webpush/subscribe` dari dalam SW — SW tidak bisa baca `meta[csrf-token]` dari DOM, dan endpoint itu sengaja tetap di-CSRF-protect (tanpa itu, POST lintas situs bisa daftarin subscription penyerang atas nama korban = kebocoran notifikasi pribadi). Sinkron ke server menyusul otomatis lewat `webpush.js` tiap halaman dimuat (idempotent).
2. ~~`public/manifest.json`.~~ **Selesai.** Icon pakai `images/logo-192.png` yang sudah ada (tidak ada aset badge-72 terpisah, tidak dibikin baru — ikon sama dipakai utk badge juga).
3. ~~Tombol "Aktifkan Notifikasi Perangkat".~~ **Selesai, lokasinya DIPINDAH dari rancangan awal (keputusan user 2026-11-07).** Bukan di dropdown lonceng (toggle yang sempat ditaruh di `notification-dropdown.blade.php` SUDAH DICABUT) — jadi 2 tempat: (a) `components/webpush-banner.blade.php`, popup `window.Dialog.show()` sekali muncul pas login kalau belum aktif & belum di-dismiss (`localStorage` key per-user `webpush_banner_dismissed_{id}`); (b) toggle permanen di halaman Profil (`profile/show.blade.php#webpush-settings`, dicapai juga dari link baru di dropdown avatar "Notifikasi Perangkat"). Alasan: dropdown lonceng gampang ke-salah antara device vs user aplikasi (lihat poin 6 di bawah).
4. ~~`resources/js/webpush.js`.~~ **Selesai.** `window.WebPush` (permission, subscribe, unsubscribe, getActiveSubscription), di-import ke `app.js`.
5. ~~Build: `docker compose run --rm assets`~~ **CATATAN: command CLAUDE.md ini SUDAH TIDAK BERLAKU.** `docker-compose.yml` saat ini (file di-root, bukan `docker-compose.dev.yml`) ternyata berisi config PRODUKSI (build dari `Dockerfile.prod`, tanpa service `assets`, tanpa bind-mount kode) — beda dari stack dev yang BENERAN jalan (dibuat dari `docker-compose.dev.yml`, py service `assets` ada di situ). `docker compose run --rm assets` gagal "no such service". Build dilakukan manual: container `node:26-alpine` ad-hoc mount repo (`npm ci && npm run build`) lalu `chown -R 1000:1000 public/build node_modules` via container root (bukan `sudo` host — sempat dicoba, macet nunggu password, task di-stop). **Perlu diperbaiki ke depannya:** entah `docker-compose.yml` dikembalikan ke isi dev, atau dokumentasi CLAUDE.md diarahkan eksplisit ke `docker-compose.dev.yml`. Di luar scope Tahap 5, dilaporkan sebagai temuan, bukan didiamkan.
6. ~~**Uji manual HP nyata.**~~ **Sebagian selesai (2026-11-09), lewat `webpush:test` bukan event bisnis nyata.** Device-sharing bug ketemu+fix: `PushManager` subscription itu per-browser/device, BUKAN per-user aplikasi — kalau 2 user login gantian di 1 device/browser, status "aktif" yang ditampilkan bisa punya user yang salah. Fix: `isEnabledForCurrentUser()` di `webpush.js` cocokin endpoint browser ke `/webpush/subscriptions` milik user yang LOGIN SEKARANG, bukan asumsi dari `Notification.permission` doang. Banner diubah total jadi `window.Dialog.show()` (bukan banner custom) + toggle dipindah ke halaman Profil (section tersendiri `#webpush-settings`), BUKAN di dropdown lonceng lagi — keputusan user: biar gak numpuk di tempat yang gampang keliru device/user. Uji full event bisnis nyata (Task Teknisi, Tiket Gangguan, SLA) masih nunggu Tahap 6 event wiring — bukan scope Tahap 5.

**Insiden sampingan 2026-11-07:** edit `docker/nginx/default.conf`/`default.prod.conf` (nambah blok `location = /sw.js` no-cache) lewat path Windows (`\\wsl.localhost\...`) memicu bug stale bind-mount Docker Desktop WSL2 — container `nginx` gagal start/restart/recreate ("error mounting ... no such file or directory") sampai Docker Desktop di-restart oleh user. Bukan soal kode Laravel; dicatat di sini supaya kalau nginx down lagi setelah edit file yang di-bind-mount tunggal, ini penyebab yang sudah dikenal, bukan regresi baru.

## Tahap 6: Delivery Receipt & Eskalasi — **Item 1-3 selesai+terverifikasi e2e (2026-11-09), item 4 ditunda ke Tahap 8**

Dari dokumen battery saver, lapis 4–5.

1. ~~Tabel `push_deliveries` + route ack bertanda tangan (`signed`).~~ **Selesai.** Migration `push_deliveries` (`morphs('notifiable')`, `notification_class`, `status` cast `App\Enums\PushDeliveryStatus` [`sent`/`delivered`/`escalated`], `sent_at`/`delivered_at`/`escalated_at`). Satu row per PANGGILAN `toWebPush()` (satu notifikasi dispatch), bukan per subscription/device — `WebPushChannel` bawaan package kirim SATU payload yang sama ke semua subscription user dalam satu loop, jadi tracking per-device tidak didukung level channel stok. Route `webpush.ack` (`PushDeliveryAckController`, `signed` middleware, bukan `auth` — service worker gak reliable punya akses cookie session).
2. ~~Service worker memanggil ack saat push diterima.~~ **Selesai.** `public/sw.js` `push` handler kirim `fetch(ackUrl)` paralel dengan `showNotification()` lewat `event.waitUntil(Promise.all([...]))`, ack URL dikirim lewat `data.ackUrl` di payload (`WebPushDeliveryTracker::ackUrl()`).
3. ~~Job `EscalateIfNotDelivered` dengan delay 5 menit.~~ **Selesai, Telegram DITUNDA (keputusan user 2026-11-07).** `EscalateIfNotDeliveredJob` (`ShouldQueue`, delay `now()->addMinutes(5)` dari `WebPushDeliveryTracker::start()`): kalau status masih `sent` pas job jalan, tandai `escalated` + `Log::warning()` — **action eskalasinya cuma log**, belum ada fallback Telegram (`laravel-notification-channels/telegram`) karena belum ada bot token. Wiring Telegram nyata ditunda sampai token tersedia; struktur job sudah siap nerima action tambahan tanpa ubah skema.
4. **Dashboard delivery rate per merek HP dan OS — selesai di Tahap 8 (2026-11-09), approximate per keputusan user.** Lihat Tahap 8 untuk detail.

**Verifikasi e2e 2026-11-09:** `webpush:test` ke superadmin & teknisi (`Teknisi Joko`), keduanya `sent`→`delivered` dalam ~1 detik (`webpush:deliveries`). Test otomatis: `WebPushDeliveryTest` (5 test, 12 assertion) — tracker bikin row `sent`+dispatch job, ack endpoint tandai `delivered` dengan signature valid, ack endpoint tolak signature di-tamper (403), job tandai `escalated` kalau belum di-ack, job no-op kalau udah `delivered`.

**Wiring 4 event bisnis nyata — selesai 2026-11-09.** `App\Notifications\AppPushNotification` (baru, `ShouldQueue`, via `WebPushChannel`) dipakai BERDAMPINGAN dengan `AppNotification` yang sudah ada (database+broadcast, sync) di tiap titik — sengaja class TERPISAH, bukan nambah channel `webpush` ke `AppNotification::via()`, karena `AppNotification` dipakai SEMUA notif in-app termasuk yang user TOLAK push (SLA Warning). Titik wiring:

1. **Tiket Prioritas High/Critical** (`TicketService::notifyRoleUsersInPop()`, dipanggil dari `escalateToNoc()`+`escalateToFop()`) — param baru `bool $push`, dinyalakan cuma kalau `$ticket->priority` masuk `[FopTaskPriority::HIGH, FopTaskPriority::URGENT]`. Tiket LOW/MEDIUM tetap in-app saja. **Catatan interpretasi:** dokumen analisa bilang penerima "NOC & Teknisi" — karena Teknisi gak pernah pegang Ticket langsung (cuma lewat FopTask/Task sesudah FOP assign, itu event #2), diimplementasi sebagai NOC (pas `escalateToNoc`) + FOP (pas `escalateToFop`), penerima SAMA PERSIS dengan yang sudah dapat notif in-app — bukan grup "teknisi on-call" yang gak ada mekanismenya di RBAC sistem ini.
2. **New Task Assignment Survey/PSB/Ticket** (`TaskService::notifyTeam()`, eventType `created`) — push cuma kalau `$task->task_type` masuk `[SURVEY, PEMASANGAN (PSB), MAINTENANCE (MTN)]`; C-REQ/O-REQ/INFR REQ/DEAC sengaja TIDAK push. **Catatan testing:** TIDAK ada test otomatis buat titik ini — `notifyTeam()` dibungkus `DB::afterCommit()` (disengaja, lihat komentar di kode, soal race Reverb), dan `DB::afterCommit()` tidak pernah fire di dalam test `RefreshDatabase` (transaksi test di-rollback, bukan di-commit, jadi callback level-0 gak pernah tereksekusi) — keterbatasan Laravel yang SAMA dipakai alasan di `tests/Feature/FopTaskCancelTest.php:161` buat skip assert serupa, bukan sesuatu yang baru dibuat di sini. Logic-nya di-review manual (termasuk nemu+fix bug `$task->type` vs `$task->task_type` yang benar sebelum nulis test — kalau salah, `in_array()` diam-diam selalu `false` tanpa error).
3. **Setoran Collector Approved/Rejected** (`CollectorDepositService::notifyCollectorOnVerification()`) — push tiap kali verifikasi selesai, "approved" dipetakan ke `TERVERIFIKASI` (clean) dan "rejected" ke `SELISIH` (kurang setor) karena sistem ini gak literal pakai 2 kata itu.
4. **Pendaftaran Pelanggan Baru** (`CustomerController::notifyRegistrationReviewers()`) — push ke reviewer yang sama persis dengan penerima in-app (permission `customer_registration_verification.approve`, scope POP).

Test baru/diperluas: `TicketNotificationTest` (+3 test: push HIGH/URGENT ke NOC & FOP, push TIDAK jalan buat LOW — total 9 lulus), `CollectorDepositTest` (+2 test: push verifikasi clean & shortfall — total 36 lulus), `CustomerCreateTest::test_registration_notifies_approvers_scoped_to_same_pop` (assert push ditambah ke test existing — 6 lulus). Semua lewat `Notification::fake()`, bukan e2e device nyata (yang sudah diverifikasi terpisah lewat `webpush:test` di atas, dengan `TestWebPushNotification`, bukan ke-4 event ini).

**Bug infrastruktur ketemu+fix di sesi ini (bukan bug kode, dicatat biar gak keulang):**
- **Opcache beku:** base image pakai `php.ini-production` (`opcache.validate_timestamps=Off`) lewat `zz-production.ini` yang cuma ada di `Dockerfile.prod` tapi entah kenapa ikut kepakai di dev — PHP-FPM compile sekali, abaikan perubahan source selamanya (gejala: route baru 404 di HTTP padahal `route:list` CLI normal, notification channel "Driver not supported" padahal provider sudah di-`package:discover`). Fix: `opcache.validate_timestamps=1`+`opcache.revalidate_freq=0` ditambah ke `docker/php/local.ini`.
- **Akar sebenarnya opcache beku — tag image dev/prod kolisi:** `docker-compose.dev.yml` (`app`/`horizon`/`scheduler`/`reverb`) pakai `image: app:${APP_VERSION:-latest}` — SAMA PERSIS tag yang dipakai `docker-compose.yml` produksi. Docker Compose gak rebuild image kalau tag udah ada (tanpa `--build`), jadi dev stack bisa diam-diam kepakein image PRODUKSI (dari `Dockerfile.prod`, yang punya `zz-production.ini`) kalau image prod sempat dibangun duluan di host yang sama. Fix permanen: tag dev diganti `app-dev:latest` di keempat service, dibedakan total dari prod.
- **Container ter-recreate dari compose file salah:** setelah Docker Desktop di-restart (recovery bug stale bind-mount WSL2 lain), container `app` sempat kebentuk ulang dari `docker-compose.yml` (bukan `.dev.yml`) — ketauan dari nama default `whusnet-operasional-app-1` (harusnya `whusnet-app`) dan `local.ini` yang hilang total dari `/usr/local/etc/php/conf.d/`. Fix manual: stop+rm container salah, `up -d` ulang eksplisit pakai `-f docker-compose.dev.yml -p whusnet-operasional`.
- **Efek samping:** setelah rebuild `app-dev:latest` dan recreate 4 container, DB dev (`whusnet_operasional`, service `db`) ternyata KOSONG TOTAL — beda isi dari sebelum insiden. `migrate --force` + `db:seed --force` (seeder default `DatabaseSeeder`, ringan, BUKAN seeder 15k yang di-comment di seeder itu) dijalankan ulang buat recovery; push subscription lama ikut hilang (user subscribe ulang manual, bagian dari verifikasi e2e di atas).

## Tahap 7: Web Push Fase 3 (Portal Pelanggan) — **Item 1+3 selesai (2026-11-09)**

Hanya setelah keputusan Tahap 1 poin 4.

1. ~~Endpoint `/api/v1/customer/push/*` dengan Sanctum. `Customer` pakai trait yang sama.~~ **Selesai, tapi convention DIKOREKSI dari rencana lama.** Saat mulai digarap, ketemu konflik: API portal pelanggan asli sudah ada duluan di `/api/customer-portal/*` (routes/api.php) pakai token custom (`PortalAuthController`, middleware `portal_client`+`portal_token`), BUKAN Sanctum — plan ini ditulis sebelum ketahuan portal API itu sudah ada. Ditanya ke user, jawaban: ikut convention existing, bukan Sanctum baru. Hasil: `Customer` dapat trait `Notifiable`+`HasPushSubscriptions` (sama seperti `User`); endpoint baru `GET /api/customer-portal/push/vapid-key`, `POST /push/subscribe`, `POST /push/unsubscribe`, `GET /push/subscriptions` lewat `PortalPushSubscriptionController`, di grup middleware `portal_token` yang sama dengan `/me/*` — identitas pelanggan dari `$request->attributes->get('portal_customer_id')` (hasil token), TIDAK PERNAH dari request, sama aturan `PortalMeController`. Test `PortalPushSubscriptionTest` 5 lulus.
2. Hook `useWebPush.ts` di Next.js. **Di luar scope repo ini** — ranah repo portal pelanggan terpisah (Next.js), tidak dikerjakan/tidak bisa diverifikasi dari sini.
3. ~~Trigger: invoice terbit, pembayaran berhasil, maintenance jaringan.~~ **Invoice+Pembayaran selesai, maintenance jaringan DITUNDA** (ditanya ke user sesuai catatan Tahap 1 poin 2 — jawaban: tunda, belum ada sumber event/data gangguan terjadwal yang jelas di sistem). Dipasang di `InvoiceObserver::created()` dan `PaymentObserver::created()` — BUKAN di satu controller/service tertentu — supaya semua jalur insert (form manual admin, `GenerateMonthlyInvoicesCommand`, kolektor/teknisi batch, command legacy) otomatis ikut terpush tanpa duplikasi logic per jalur. `old_invoice_id`/`old_payment_id` (replay data legacy/migrasi) dikecualikan — bukan tagihan/pembayaran BARU, jangan sampai migrasi ulang memicu banjir push ke pelanggan. Notification class reuse `AppPushNotification` yang sudah ada (generik title+message), tidak bikin kelas baru. Test `InvoiceAndPaymentPushCustomerTest` 4 lulus (termasuk kasus legacy tidak push); regresi `CustomerCreateTest`+`PortalBalanceTest`+`PortalInvoiceIndexTest` 22 lulus (trait baru di `Customer` tidak mengganggu test existing).
4. Uji: pelanggan tutup tab, notif tampil di HP. **Di luar scope repo ini** — butuh portal pelanggan Next.js nyata buat subscribe perangkat, belum bisa diverifikasi dari sisi Laravel saja.

## Tahap 8: Panduan Battery Saver (Onboarding) + Dashboard Delivery (Tahap 6 item 4) — **selesai (2026-11-09)**

**Dashboard delivery rate (dipindah sini dari Tahap 6 item 4) — selesai:**
- Migration tambah `device_name`/`user_agent` (nullable, 512 char) ke `push_subscriptions`.
- `DeviceBrandDetector::detect()` (regex UA: Xiaomi/Redmi/POCO, OPPO/realme, vivo, Samsung, iPhone/iPad, fallback Lainnya/Tidak diketahui), dipanggil dari `PushSubscriptionController::subscribe()` dan `PortalPushSubscriptionController::subscribe()`.
- **Keterbatasan data ditanya ke user:** `push_deliveries` cuma satu baris per notifikasi per notifiable (bukan per device), jadi delivery rate per brand tidak bisa presisi tanpa redesain skema ack per-device. User pilih **approximate, dilabel jelas** — tiap delivery diatribusikan ke SEMUA brand yang sedang dimiliki notifiable itu (bukan device spesifik yang beneran terima), bukan redesain skema.
- `WebPushDashboardController` + view `webpush/dashboard.blade.php` dengan disclosure teks approximation eksplisit, badge warna delivery rate.
- RBAC baru `webpush_dashboard.view` (seeder `WebPushDashboardFeatureSeeder`, `config/rbac.php`, assign role `atasan`+`admin`), link sidebar grup Laporan.
- Test: `DeviceBrandDetectorTest` (unit, 9 kasus) + `WebPushDashboardTest` (3 kasus: permission ditolak, agregasi 1 brand, atribusi approximate 2 brand) + `WebPushSubscriptionTest` diperluas assert `device_name`. Total 23 test lulus (50 assertion); Pint lulus; migrate sukses.

**Onboarding (scope asli Tahap 8) — selesai:**
- Panduan battery saver per merek HP ditambah langsung di kartu "Notifikasi Perangkat" existing (`profile/show.blade.php` §5), bukan halaman tersendiri — deteksi brand di klien (`window.WebPush.detectBrand()`, pola regex disalin manual dari `DeviceBrandDetector` PHP ke JS, bukan lewat endpoint) nunjukin instruksi matiin battery optimization spesifik Xiaomi/MIUI, OPPO/realme, vivo, Samsung.
- Tombol "Kirim Notifikasi Uji" — reuse `TestWebPushNotification` (sebelumnya cuma dipicu artisan `webpush:test`). Endpoint baru `POST /webpush/test` (`PushSubscriptionController::sendTest()`, balas 422 kalau user belum punya device terdaftar).
- Deteksi standalone iOS — `window.WebPush.isIosSafariNonStandalone()` (UA iPhone/iPad/iPod + bukan `navigator.standalone`/`display-mode: standalone`) nunjukin panduan "Add to Home Screen" terpisah dari pesan "browser tidak didukung" generik, karena iOS Safari non-standalone emang gak punya Push API, bukan izin ditolak.
- Test baru di `WebPushSubscriptionTest`: `send_test_dispatches_notification_when_device_registered`, `send_test_rejected_without_registered_device` — total 8 lulus (17 assertion). Pint lulus. `npm run build` sukses via container ad-hoc `node:22-alpine` (service `assets` gak ada di `docker-compose.dev.yml` — convention lama repo, `docker-compose.yml` root itu config produksi) + chown balik ke host.

Telegram fallback eskalasi (Tahap 6 item action) tetap blocked — belum ada bot token, bukan keputusan baru.

Dikerjakan bersama Fase 2, tapi bisa dipisah jika Fase 2 sudah stabil.

---

## Ringkasan Urutan

```
Tahap 0 (sprint aktif, S8.10-T003)
  └─ Tahap 1 (keputusan: role, event, prioritas tiket, repo Fase 3, sprint)
       ├─ Tahap 2 (realtime halaman)       ← bisa jalan paralel
       ├─ Tahap 3 (gap event in-app)        ← bisa jalan paralel
       └─ Tahap 4 → 5 → 6 (Web Push operasional)
                         └─ Tahap 7 (portal pelanggan)
                         └─ Tahap 8 (onboarding, bisa bersama Tahap 5)
```

Tahap 2 dan 3 tidak butuh push, jadi bisa lebih dulu. Tahap 4 hanya boleh mulai setelah Tahap 1 poin 2 disetujui, supaya tidak ada event push yang dibangun lalu dibuang.
