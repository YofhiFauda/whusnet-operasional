# Modul Pendaftaran Pelanggan

Modul Pendaftaran Pelanggan adalah inti dari sistem billing ISP yang menangani siklus hidup awal pelanggan mulai dari pendaftaran hingga aktivasi layanan dan pembuatan tagihan pertama. 

## Struktur Dokumentasi
Berikut adalah daftar dokumentasi yang berkaitan dengan proses onboarding:
1. [Flowchart](flowchart.md) - Alur logika sistem.
2. [User Flow](user-flow.md) - Alur interaksi pengguna (UX).
3. [Database Schema](database-schema.md) - Relasi dan skema tabel yang terlibat.

## Scope Modul
- **Registrasi Pelanggan Baru**: Input data awal, draft, kelengkapan berkas.
- **Survey Lapangan**: Proses survey oleh teknisi, pencatatan SLA/countdown.
- **Verifikasi & Pemasangan**: Proses ACC instalasi, instalasi fisik, pencatatan data teknis (OLT, Port, ONU, IP).
- **Verifikasi Akhir & Aktivasi**: Pengecekan akhir oleh admin, penetapan paket, harga, diskon, dan aktivasi billing.
- **Penerbitan Tagihan Awal**: Auto-generate invoice pertama ketika pelanggan aktif.

## Service Terkait
- `CustomerWorkflowService`: Mengatur transisi *state machine* status pelanggan secara terpusat.
- Laravel Scheduler (`php artisan schedule:run`): Mengatur SLA Auto-Reminder untuk pelanggan yang *stuck* di tahapan tertentu lebih dari batas waktu SLA.

## Pola Redirect (PRG)

Setelah registrasi berhasil (`store`) → redirect ke `customers.show` (halaman Detail pelanggan baru),
bukan ke daftar. Registrasi = awal workflow, user langsung lanjut di record itu. Validasi gagal →
`back()` + errors + old input. Aturan lengkap + visualisasi:
**[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.
