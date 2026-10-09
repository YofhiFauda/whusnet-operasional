# Analisa Cakupan Push & In-App Notifikasi (Rancangan)

**Tanggal:** 2026-10-06
**Sifat:** Analisa rancangan (belum diimplementasi). Sumber: `analisa-in-app-dan-push-notifikasi.md` (matriks §3), `rancangan-arsitektur-web-push-pwa-nextjs.md` (checklist §8), dan `Web Push Notification Laravel + Next.js yang Tahan Battery Saver.md`.
**Pelengkap:** `analisa-status-implementasi-notifikasi.md` (kondisi kode), `analisa-cakupan-realtime-per-halaman.md` (realtime halaman).

---

## 1. Event yang Hanya In-App (Push: NO di Matriks)

| Event | Penerima (rancangan) | Penerima (role RBAC nyata) |
|---|---|---|
| Laporan Survey/Pemasangan selesai | FOP / Admin POP | `fop`, `pop_admin` |
| Setoran Collector di-submit | Finance Pusat | `pop_admin` (lihat §5) |
| Import data pelanggan selesai | Admin Import | uploader (`auth()->user()`) |
| Pengajuan diskon/adjustment invoice | Owner / Finance Lead | `owner`, `pop_admin` (perlu dikonfirmasi) |

## 2. Event Belum Ada di Matriks (In-App Pun Belum)

| Event | Penerima (rancangan) | Catatan |
|---|---|---|
| Pendaftaran pelanggan baru | Admin POP, FOP, Sales | |
| Aktivasi / status Siap Billing | Sales, Admin POP | Sebagian sudah: `active` ke pendaftar (§8.5 status doc) |
| Ringkasan generate tagihan batch | Finance, Admin POP | Command `GenerateMonthlyInvoicesCommand` belum notif |
| Anomali keamanan (brute force, perubahan role/permission) | Owner | Ada di §E.2 rancangan in-app, tidak di matriks dan roadmap web push |

## 3. Pelanggan (Next.js Portal, Fase 3)

Trigger yang direncanakan: invoice terbit, pembayaran berhasil, maintenance jaringan.

Belum direncanakan:
- Status layanan (aktif, isolir, putus)
- Perubahan status tiket pelanggan
- Info gangguan per tiket
- Pengingat jatuh tempo

Kanal in-app untuk pelanggan tidak ada.

## 4. Matriks Push Tidak Tercakup Checklist Implementasi

| Event (Push: YES di matriks) | Penerima | Status di checklist Fase 2 |
|---|---|---|
| Setoran collector approved/rejected | Collector (`kolektor`) | Tidak diuji |
| Tiket High/Critical | NOC & Teknisi | Belum ada field prioritas tiket di rancangan |
| SLA warning H-2 / overdue | FOP & Supervisor (`atasan`) | Tidak disebut role `atasan` |
| Task dibatalkan saat in_progress | Teknisi | Tercakup umum sebagai "Task Teknisi", belum spesifik |

## 5. Ketidaksesuaian Role

Dokumen rancangan memakai nama role yang tidak ada di RBAC:

| Nama di dokumen | Padanan RBAC | Keterangan |
|---|---|---|
| Finance Pusat | — | Tidak ada. Dialihkan ke `pop_admin` (§8.3 status doc) |
| Finance Lead | — | Tidak ada |
| Owner / Lead Admin | `owner` | "Lead" tidak ada |
| Supervisor | `atasan` | |
| NOC On-Call | `noc` | Tidak ada konsep on-call |
| Admin Data / Admin Import | `admin` / uploader | |

Perlu dikonfirmasi sebelum implementasi: pemetaan di atas, dan apakah role `helpdesk`, `sales`, dan `admin` perlu push.

## 6. Helpdesk, Sales, Admin POP, Owner

Rancangan Web Push hanya menargetkan teknisi, FOP, NOC, dan supervisor. Role lain hanya dapat in-app, termasuk:
- Helpdesk: close, cancel, return tiket
- Sales: verifikasi/reject pendaftaran
- Admin POP & Owner: reject pembayaran, revisi verifikasi, anomali keamanan

## 7. Batasan Teknis (Bukan Gap Fitur)

- In-app hanya jalan saat user online. Tidak ada fallback offline (status doc §4.4).
- Web Push di iOS hanya untuk PWA di Home Screen (iOS 16.4+).
- Battery saver bisa menunda push. Dokumen battery saver menyarankan Telegram/WhatsApp untuk event kritis, tapi belum ada checklist untuk fallback itu.
- Telegram di matriks berstatus opsional di status doc. Belum ada jaminan delivery.

## 8. Usulan Langkah Lanjut

1. Konfirmasi pemetaan role di §5 dengan pemilik produk.
2. Putuskan event mana yang masuk push (§1, §2, §4) dengan kriteria: butuh respons < 15 menit atau berdampak ke lapangan.
3. Tambah field prioritas tiket sebelum mengaktifkan push tiket High/Critical.
4. Tambah checklist Fase 2 untuk setiap event push yang disetujui.
5. Tambah trigger pelanggan di Fase 3 (§3) jika disetujui.
