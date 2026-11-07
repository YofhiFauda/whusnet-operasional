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

## Tahap 4: Web Push Fase 1 (Backend)

Dari `rancangan-arsitektur-web-push-pwa-nextjs.md` §8.

1. Install `laravel-notification-channels/webpush`. Cek kompatibilitas versi Laravel 13 dulu.
2. Generate VAPID, set `.env`. Kunci tidak boleh diganti setelah produksi.
3. Migrasi `push_subscriptions` (polymorphic). Kolom sesuai rancangan §3.
4. Trait `HasPushSubscriptions` untuk `User`. `Customer` ditunda sampai Fase 3.
5. `WebPushChannel` lewat queue Horizon (`ShouldQueue`).
6. `PushSubscriptionController` + route. Route statis dulu, dinamis belakangan (CLAUDE.md).
7. Test: subscribe, unsubscribe, pruning 404/410, notif terkirim lewat queue.

**Catatan:** Laravel Boost minta `search-docs` sebelum kode baru, dan `docker compose` perlu `package:discover` ulang setelah `composer require` (CLAUDE.md).

## Tahap 5: Web Push Fase 2 (Blade PWA Operasional)

1. `public/sw.js` dengan handler `push`, `notificationclick`, `pushsubscriptionchange`. Header no-cache untuk `sw.js`.
2. `public/manifest.json`.
3. Tombol "Aktifkan Notifikasi Perangkat" di dropdown lonceng. Alpine toggle.
4. `resources/js/webpush.js`: permission, subscribe, kirim ke `/webpush/subscribe`.
5. Build: `docker compose run --rm assets` (CLAUDE.md, jangan `exec`).
6. Uji manual di HP nyata: Task Teknisi, Tiket Gangguan, SLA Breach. Skenario layar mati dan Chrome di-swipe.

## Tahap 6: Delivery Receipt & Eskalasi

Dari dokumen battery saver, lapis 4–5. Dikerjakan setelah Fase 2 jalan.

1. Tabel `push_deliveries` + route ack bertanda tangan (`signed`).
2. Service worker memanggil ack saat push diterima.
3. Job `EscalateIfNotDelivered` dengan delay 5 menit. Fallback Telegram (`laravel-notification-channels/telegram`) untuk event kritis.
4. Dashboard delivery rate per merek HP dan OS.

## Tahap 7: Web Push Fase 3 (Portal Pelanggan)

Hanya setelah keputusan Tahap 1 poin 4.

1. Endpoint `/api/v1/customer/push/*` dengan Sanctum. `Customer` pakai trait yang sama.
2. Hook `useWebPush.ts` di Next.js.
3. Trigger: invoice terbit, pembayaran berhasil, maintenance jaringan.
4. Uji: pelanggan tutup tab, notif tampil di HP.

## Tahap 8: Panduan Battery Saver (Onboarding)

- Halaman "Aktifkan notifikasi" per merek HP (deteksi user agent).
- Tombol kirim notifikasi uji.
- Deteksi mode standalone iOS dan panduan install PWA.

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
