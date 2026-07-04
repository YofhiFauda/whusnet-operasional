# Rencana Pencegahan & UX Tagihan/Pembayaran

Dokumen ini analisa lanjutan dari `ANALISA_BUG_MIGRASI_TAGIHAN_AWAL_BULANAN.md` — fokus ke **sistem baru** (bukan lagi data legacy): bagaimana mencegah kasus serupa terulang, bagaimana admin bisa membedakan tagihan AWAL vs BULANAN serta status bayar tanpa buka detail, dan bagaimana admin input pembayaran secara instan. Berdasarkan investigasi langsung ke kode saat ini (bukan rencana di ruang kosong).

## A. Kenapa Kasus Serupa Bisa Berulang di Sistem Baru

Root cause di data legacy bukan cuma "data lama jelek" — pola yang sama masih punya celah di sistem baru saat ini:

1. **`invoice_type` masih nebak, bukan dipaksa eksplisit.**
   Migration `database/migrations/2026_07_02_133000_add_invoice_type_to_invoices_table.php:20-23` men-tag invoice `'awal'` hanya kalau `extra_installation_fee > 0` ATAU `prorate_amount > 0`; selain itu default diam-diam ke `'bulanan'`. Ini heuristik yang sama persis yang menyebabkan migrasi salah tag — cuma berpindah lokasi, bukan hilang.

2. **Tidak ada guard duplikat di level database.**
   Tabel `payments` tidak punya unique constraint (invoice_id + tanggal + amount). Tabel `invoices` cuma dicek unik per `billing_period` per customer (`app/Http/Controllers/CustomerController.php:2592-2599`) — kombinasi `invoice_type` tidak diperiksa. Bug retry-submit yang terjadi di sistem lama bisa terulang di sistem baru kalau ada double-click atau retry form.

3. **Validasi menempel di controller, bukan di lapisan terpusat.**
   `PaymentController::store` (`app/Http/Controllers/PaymentController.php:139-218`) sudah benar: validasi `amount` min:1 & max:remaining_amount, `lockForUpdate`, recompute `paid_amount`/`remaining_amount`/`invoice_status` transaksional. Tapi ini HANYA berlaku kalau jalur lewat controller ini. Command lain (migrasi, calon command generate-bulanan, API masa depan) yang memanggil `Payment::create()`/`Invoice::create()` langsung BISA BYPASS validasi ini — persis seperti row `BAYAR=0` di legacy yang tidak lewat jalur normal.

4. **Tidak ada recurring invoice generator.**
   `routes/console.php` hanya menjadwalkan `check:countdown` dan `fop:reset-cancelled-tasks` — tidak ada job otomatis generate tagihan BULANAN tiap bulan. Semua invoice dibuat manual lewat `CustomerController::storeManualInvoice`, artinya nominal bulanan bergantung siapa yang input tiap bulan — sumber human-error berulang, sama seperti kenapa data legacy formatnya tidak konsisten.

### Rekomendasi Pencegahan

- Pindahkan business rule invoice/payment ke Service class atau Model Observer, supaya semua jalur (controller, command, API masa depan) wajib lewat validasi yang sama — tidak bisa "insert langsung bypass" seperti yang terjadi pada command migrasi.
- Kolom `invoice_type` wajib diisi eksplisit tiap insert (hilangkan default diam-diam `'bulanan'`); tambah kolom baru semacam `origin_event` (`pemasangan_awal` / `reaktivasi` / `manual`) supaya dua invoice AWAL yang sah (kasus reaktivasi Wiyono) jelas beda dari duplikat beneran.
- Idempotency check berbasis signature (pola yang baru dipasang di `MigrateLegacyDataCommand.php`) dijadikan pola standar dipakai ulang di command/import manapun ke depan — bukan tempelan sekali pakai.
- Scheduled job untuk generate invoice BULANAN otomatis dari `customer_service.monthly_price`, menghilangkan faktor manual per-admin.

## B. Cara Admin Melihat AWAL vs BULANAN + Status Bayar Tanpa Buka Detail

Kondisi sekarang: tab "Tagihan" di halaman detail pelanggan (`resources/views/customers/show.blade.php:690-739`) **tidak menampilkan kolom `invoice_type`** — admin harus klik ke `invoices.show` untuk melihat badge jenisnya. Sebaliknya, halaman global `/invoices` sudah punya filter "JENIS TAGIHAN" + kolom invoice_type (`resources/views/invoices/index.blade.php:54-58, 124-126`), tapi ini diposisikan sebagai halaman laporan, bukan tempat kerja harian.

### Rekomendasi

1. Tambah kolom/badge `invoice_type` di tabel Tagihan halaman detail pelanggan — pisah visual jadi dua bagian: **Tagihan Awal** (atas) dan **Tagihan Bulanan** (bawah).
2. Jadikan `/invoices` (yang sudah punya filter jenis+status) sebagai pusat kerja harian admin, bukan sekadar laporan — tambah stat tile di atas: total AWAL belum dibayar, total BULANAN menunggak, agar terlihat tanpa membuka satu per satu.

## C. Cara Admin Input Pembayaran Instan Tanpa Buka Layer Detail

Kondisi sekarang (dikonfirmasi lewat investigasi kode, tidak ada modal/quick-entry sama sekali): Customer → klik invoice → `invoices.show` → klik "Input Pembayaran" → pindah ke halaman penuh terpisah `payments.create` → submit → kembali ke `invoices.show`. Minimal 3 page-load, tidak ada modal Alpine.js atau Livewire untuk payment (yang ada cuma modal lain: manual-invoice, survey, installation, test-report, device — pola modalnya sudah established di `customers/show.blade.php:772, 1198-1323`, tinggal diikuti).

### Rekomendasi

1. Modal "Bayar Cepat" (Alpine.js, mengikuti pola modal yang sudah ada) — tombol "Bayar" di tiap baris invoice, baik di tab Tagihan halaman detail pelanggan maupun di list global `/invoices`. Submit via AJAX ke endpoint `PaymentController::store` yang sudah ada (logic direuse, tidak duplikasi validasi) — baris update in-place tanpa reload halaman.
2. Karena tombol ini juga tersedia di `/invoices` (list global), admin bisa melunasi invoice pelanggan mana pun **tanpa masuk ke halaman detail pelanggan sama sekali**.
3. Opsional lanjutan: checkbox multi-select + tombol "Bayar Massal" di list global, untuk skenario kolektor menyetorkan banyak pembayaran bulanan sekaligus.

## Ringkasan Prioritas

| # | Perbaikan | Dampak |
|---|-----------|--------|
| 1 | Kolom `invoice_type` wajib eksplisit + kolom `origin_event` | Cegah salah-tag & bedain invoice ganda sah vs duplikat |
| 2 | Validasi terpusat (Service/Observer) untuk semua jalur insert | Cegah bypass seperti kasus BAYAR=0 legacy |
| 3 | Idempotency check standar di semua command/import | Cegah duplicate-insert berulang |
| 4 | Scheduled job generate invoice bulanan | Hilangkan human-error nominal manual |
| 5 | Badge invoice_type + split section di tab Tagihan customer | Admin lihat AWAL/BULANAN tanpa buka invoice detail |
| 6 | Stat tile ringkasan di `/invoices` | Admin lihat total nunggak tanpa hitung manual |
| 7 | Modal "Bayar Cepat" di baris invoice (customer page & list global) | Input pembayaran tanpa navigasi berlapis |
| 8 | Bayar massal (multi-select) | Percepat input kolektif oleh kolektor |
