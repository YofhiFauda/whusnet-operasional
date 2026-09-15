# Modul Pendaftaran Pelanggan

Modul Pendaftaran Pelanggan adalah inti dari sistem billing ISP yang menangani siklus hidup awal pelanggan mulai dari pendaftaran hingga aktivasi layanan dan pembuatan tagihan pertama. 

## Struktur Dokumentasi
Berikut adalah daftar dokumentasi yang berkaitan dengan proses onboarding:
1. [Flowchart](flowchart.md) - Alur logika sistem.
2. [User Flow](user-flow.md) - Alur interaksi pengguna (UX).
3. [Database Schema](database-schema.md) - Relasi dan skema tabel yang terlibat.

## Scope Modul
- **Registrasi Pelanggan Baru**: Input data awal, draft, kelengkapan berkas.
- **Verifikasi Registrasi (Admin/CS)**: Registrasi non-Skip-Survey berhenti dulu di status `registered` — TANPA Task/FopTask Survey — sampai Admin/CS approve di `/customer-registration-verifications`. Approve → Task+FopTask Survey kebentuk + status pindah `waiting_survey` (baru di sini masuk Antrean Survey/Task FOP). Tolak → `WorkflowTransition::REJECTED` + alasan wajib diisi. Skip Survey TIDAK melewati gerbang ini (tetap langsung `waiting_installation` seperti sebelumnya). Lihat `CustomerRegistrationVerificationController`, ADHOC-73.
- **Skip Survey (Sales)**: Role dengan permission `customers.registration.skip_survey` (default Sales) bisa lewat tahap survey lapangan — input data survey (ODP terdekat, estimasi kabel, tingkat kesulitan, foto rumah, foto ODP) + titik koordinat langsung di form registrasi. Pelanggan lompat langsung ke antrean ACC Admin (`waiting_acc`), gak pernah masuk antrean Survey teknisi. Lihat § Skip Survey di [`business-logic.md`](../customer-lifecycle/business-logic.md) dan [`flowchart.md`](flowchart.md).
- **Survey Lapangan**: Proses survey oleh teknisi, pencatatan SLA/countdown.
- **Verifikasi & Pemasangan**: Proses ACC instalasi, instalasi fisik, pencatatan data teknis (OLT, Port, ONU, IP).
- **Verifikasi Akhir & Aktivasi**: Pengecekan akhir oleh admin, penetapan paket, harga, diskon, dan aktivasi billing.
- **Penerbitan Tagihan Awal**: Auto-generate invoice pertama ketika pelanggan aktif.

## Service Terkait
- `CustomerWorkflowService`: Mengatur transisi *state machine* status pelanggan secara terpusat.
- Laravel Scheduler (`php artisan schedule:run`): Mengatur SLA Auto-Reminder untuk pelanggan yang *stuck* di tahapan tertentu lebih dari batas waktu SLA.

## Perubahan Terbaru (2026-09-15)

**Verifikasi Registrasi (ADHOC-73)** — sebelumnya `CustomerController::store()`
langsung bikin Task+FopTask Survey seketika submit form Registrasi (non-Skip-
Survey), status pelanggan pun langsung `waiting_survey` — gak ada gerbang
manusia sama sekali sebelum Task FOP kebanjiran entri. Sekarang pelanggan
berhenti di `registered` sampai Admin/CS approve di antrean baru "Verifikasi
Registrasi" (`CustomerRegistrationVerificationController`, permission
`customer_registration_verification.view/approve/reject`, default digrant ke
role `helpdesk`). Rancangan lengkap:
[`../plan/pendaftaran-pelanggan/analisa-verifikasi-registrasi.md`](../plan/pendaftaran-pelanggan/analisa-verifikasi-registrasi.md).

## Perubahan Terbaru (2026-09-14)

**NPWP & Jenis Kontrak sekarang beneran tersimpan.** Dua input itu ADA di form
sejak awal (`create.blade.php` step 1 & 2) tapi `CustomerRegistrationRequest`
tidak pernah memvalidasinya — kekirim, lalu di-drop diam-diam. Sekarang
`npwp` (nullable, max 30) → `customers.npwp`, `jenis_kontrak` (`sewa`/`beli`)
→ `customer_services.contract_type`. Sama-sama diperbaiki juga di Edit
Pelanggan — detail: [`../data-pelanggan/README.md` §Perubahan Terbaru
2026-09-14](../data-pelanggan/README.md).

## Pola Redirect (PRG)

Setelah registrasi berhasil (`store`) → redirect ke `customers.show` (halaman Detail pelanggan baru),
bukan ke daftar. Registrasi = awal workflow, user langsung lanjut di record itu. Validasi gagal →
`back()` + errors + old input. Aturan lengkap + visualisasi:
**[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.
