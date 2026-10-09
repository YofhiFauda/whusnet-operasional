# Urutan Pengerjaan Notifikasi & Realtime

**Tanggal:** 2026-10-06
**Sifat:** Usulan urutan. Belum disetujui user dan belum masuk `docs/TASKS.md`.
**Pelengkap:** `analisa-status-implementasi-notifikasi.md`, `analisa-cakupan-realtime-per-halaman.md`, `analisa-cakupan-push-notifikasi.md`, `analisa-in-app-dan-push-notifikasi.md`, `rancangan-arsitektur-web-push-pwa-nextjs.md`, `Web Push Notification Laravel + Next.js yang Tahan Battery Saver.md`.

---

## Tahap 0: Sprint Aktif (Wajib Dulu)

Aturan CLAUDE.md: jangan loncat sprint.

- **`S8.10-T003` FOP Notification Dashboard**: status PAUSED di `docs/TASKS.md`, menunggu BATCH 1 & BATCH 2 selesai.
- Cek ulang scope T003 sebelum lanjut. `/notifications` sudah punya filter tanggal/type/user dan mark read/unread (lihat `analisa-status-implementasi-notifikasi.md` §1). Yang tersisa mungkin hanya filter aksi dan tampilan FOP.

## Tahap 1: Keputusan Sebelum Kode

Blocker. Tanpa ini tahap berikutnya tidak bisa dimulai.

1. **Pemetaan role** dari dokumen ke RBAC nyata (`analisa-cakupan-push-notifikasi.md` §5). Contoh: Finance Pusat, Supervisor, NOC On-Call.
2. **Daftar event push** yang disetujui. Kriteria usulan: butuh respons < 15 menit atau berdampak ke lapangan.
3. **Field prioritas tiket** (untuk push High/Critical). Belum ada di rancangan.
4. **Lokasi Fase 3** (portal pelanggan Next.js): repo terpisah atau di repo ini. Repo ini Laravel + Blade.
5. **Masuk sprint mana.** Web Push belum ada di `docs/TASKS.md`. Perlu sprint baru atau persetujuan user untuk masuk sprint aktif.

## Tahap 2: Realtime Halaman (Tanpa Push, Risiko Rendah)

Perbaiki reload manual yang sudah ada. Tidak butuh infrastruktur baru, hanya listener Echo yang sudah ada dipakai ulang.

| Prioritas | Lokasi | Pekerjaan |
|---|---|---|
| 1 | `fop_tasks/index.blade.php:1446` | Ganti reload setelah reassign tim dengan update state Alpine (doc realtime #13) |
| 2 | `fop/dashboard.blade.php:995, 999` | Ganti reload setelah aksi, dan hapus tombol "Status Teknisi" (doc realtime #12) |
| 3 | `collector-worksheet/show`, `partials/collector-pay-script`, `technician-payments/index`, `payments/partials/quick-payment-modal`, `customers/qr/show` | Reload setelah bayar. Perlu event dan channel baru jika ingin realtime |
| 4 | `tickets/history.blade.php:75` | Hapus tombol reload manual setelah listener ada |

Tiap perubahan wajib ada test (CLAUDE.md). Pola: `#[Test]`, nama sesuai gejala.

## Tahap 3: Gap Event In-App

Event yang sudah diputuskan di Tahap 1 dan belum punya notif in-app. Dikerjakan lewat `AppNotification` supaya ikut toast dan lonceng.

- Pendaftaran pelanggan baru (Admin POP, FOP, Sales)
- Aktivasi / Siap Billing (Sales, Admin POP)
- Ringkasan generate tagihan batch (Finance, Admin POP)
- Laporan survey/pemasangan selesai (FOP, Admin POP): sudah ada di `TaskService::complete()`, cek cakupan
- Import selesai: sudah ada (§8.6 status doc)

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
