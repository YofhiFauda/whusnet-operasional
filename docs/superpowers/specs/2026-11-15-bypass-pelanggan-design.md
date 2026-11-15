# Bypass Pelanggan (Data Migrasi Terlewat) — Rancangan

Tanggal: 2026-11-15

## Tujuan & alasan

Ada pelanggan lama yang terlewat saat migrasi legacy: sudah terpasang, sudah
bayar, administrasi sudah beres di luar sistem — tapi tidak ada di sistem ini.
Butuh jalur untuk memasukkan mereka **langsung sebagai pelanggan aktif** tanpa
verifikasi registrasi, survey, pemasangan, maupun verifikasi pemasangan.

Beda dengan **Skip Survey**: Skip Survey cuma melompati survey, pelanggan tetap
masuk antrean Pemasangan + verifikasi. Bypass melompati seluruh workflow.

## Goals

- Halaman `/bypass`: form registrasi (isian sama dengan pendaftaran biasa,
  tanpa bagian survey) → simpan → pelanggan `active`, layanan `aktif`.
- Wajib = hanya isian yang wajib di form registrasi. Sisanya (dokumen,
  perangkat, ODP, Mini POP, Distribusi, dll) dilengkapi belakangan lewat Edit —
  pelanggan tampil sebagai "data belum lengkap" seperti biasa.
- Hak akses lewat RBAC (Role Matrix), di-seed ke `owner` dan `customer_service`.

## Non-goals

- Tidak bikin Task / FopTask (Survey maupun Pemasangan).
- Tidak masuk antrean Verifikasi Registrasi maupun Verifikasi Pemasangan.
- **Tidak menerbitkan Tagihan Awal** (administrasi sudah selesai di luar sistem).
- Tidak ada isian "CID Lama" — CID digenerate seperti sistem sekarang.
- Tidak memindahkan riwayat tagihan/pembayaran legacy.

## Anti-goals

- Bypass tidak boleh jadi jalan pintas pendaftaran pelanggan baru sungguhan
  (dibatasi permission + jejak audit yang jelas).
- Tidak boleh bocor lintas cabang: POP yang dipilih wajib dalam POP scope actor.
- Tidak boleh menimbulkan tagihan dobel bulan berjalan.

## Keputusan (dari user)

1. **CID**: digenerate otomatis seperti sekarang (`CustomerCidService::resolve()`
   dari POP). Mini POP & Distribusi default kosong (segmen kosong = '0',
   sudah didukung `Pop::generateComplexCid()`). CID otomatis menyesuaikan
   kalau Mini POP/Distribusi diisi belakangan (`CustomerObserver::updating()`).
2. **Tagihan bulanan**: tanggal aktivasi diisi di form; tagihan otomatis mulai
   periode **berikutnya**. Sudah dijamin `GenerateMonthlyInvoicesCommand`
   (skip periode yang sama dengan bulan `activation_date`).
3. **Wajib** = isian wajib form registrasi saja. Paket & harga termasuk
   (dibutuhkan tagihan bulanan).

## Approaches

1. **Flag di form registrasi biasa** (checkbox "Bypass" seperti Skip Survey) —
   ditolak: `store()` sudah padat cabang Skip Survey; tambah cabang ketiga
   bikin jalur rawan makin kusut, dan user minta URL sendiri.
2. **Controller + halaman terpisah (`/bypass`) memakai ulang logika simpan
   registrasi** — **dipilih**. Isolasi jelas, permission sendiri, form
   reuse partial registrasi.
3. Import Excel per baris — ditolak: kasusnya sedikit & sporadis, user minta form.

## Rancangan

- **Route**: `GET /bypass` (form), `POST /bypass` (simpan) — middleware
  `permission:customers.bypass`. Static route, ditaruh sebelum route dinamis.
- **Controller tipis** `CustomerBypassController`; logika di service
  `CustomerBypassService` (buat customer + alamat + layanan, status `active`,
  CID via `CustomerCidService`, catat riwayat status/audit
  "Aktif lewat Bypass (data migrasi terlewat)" + actor).
- Logika pembuatan customer/alamat/layanan dari `CustomerController::store()`
  **diekstrak/dipakai ulang**, bukan disalin, supaya rumus `total_monthly_bill`
  dkk tidak menyimpang.
- **Validasi**: pakai aturan `CustomerRegistrationRequest` minus field survey/
  skip_survey; POP wajib dalam scope (`EffectiveAccessService`).
- `customer_services`: `service_status = aktif`, `billing_status = active`,
  `activation_date` dari form, `due_date` seperti registrasi biasa.
- **Redirect** PRG ke `customers.show`.
- **Permission**: feature seeder baru (pola `*FeatureSeeder` yang ada) →
  `customers.bypass`, di-attach ke role `owner` & `customer_service`.
- **Menu**: link "Bypass Pelanggan" muncul hanya untuk pemegang permission.

## Test (regresi, dinamai sesuai gejala)

- Bypass sukses → `active`, CID terisi, tanpa Task/FopTask, tanpa invoice.
- Role tanpa `customers.bypass` → 403.
- POP di luar scope → ditolak.
- Tagihan bulanan tidak terbit untuk bulan aktivasi, terbit bulan berikutnya.

## Diserahkan ke builder

- Detail ekstraksi logika simpan dari `CustomerController::store()`.
- Penempatan menu & nama feature/action persis mengikuti konvensi seeder yang ada.
