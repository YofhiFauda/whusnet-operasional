> **Arsip.** Dokumen analisa/rencana historis — sebagian rekomendasi sudah diimplementasi, sebagian belum. Lihat [../README.md](../README.md) untuk dokumentasi kondisi kode terkini.

# Analisa Final: Sistem Pembayaran & Billing (Gabungan, Terverifikasi Kode)

Dokumen ini menggabungkan `analisis_billing_pembayaran_advanced.md` (desain skema/UX) dan `ANALISA_KEBUTUHAN_OPERASIONAL_ADMIN_PEMBAYARAN.md` (audit status kode), dikoreksi ulang lewat pengecekan langsung ke kode per 2026-07-04. Basis kerangka pakai dokumen kedua karena tiap klaimnya dicek file:line — dokumen pertama menulis solusi seolah dari nol padahal sebagian sudah jalan.

Studi kasus acuan: admin mengurus pembayaran awal/bulanan/reaktivasi (cash/transfer), piutang bulan lalu, kembalian ditabung, cicilan, lihat tagihan siapa saja, lihat yang sudah lunas, bayar instan tanpa buka Detail Pelanggan, bayar massal per kolektor, rekap harian & bulanan.

## Status Terverifikasi (per poin studi kasus)

| # | Kebutuhan | Status | Bukti |
|---|-----------|--------|-------|
| 1 | Awal/bulanan/reaktivasi, cash/transfer | ✅ Selesai | `InvoiceType` enum (`app/Enums/InvoiceType.php:5-9`); `PaymentController.php:165` `payment_method in:cash,transfer,qris,lainnya` |
| 2 | Piutang (bayar bulan lalu) | ⚠️ Struktural jalan, UX belum bantu | Invoice lama tetap valid & bisa dibayar kapan saja; tidak ada badge "piutang" pembeda periode lewat |
| 3 | Kembalian ditabung bulan depan | ❌ Belum ada | `amount` divalidasi `max:remaining_amount` dua lapis — di rule (line 166) & re-check dalam transaction terkunci (line 190-194). Overpayment **ditolak total**, bukan ditabung |
| 4 | Cicilan | ✅ Selesai | `invoice_status='sebagian'` (line 198), `paid_amount` akumulasi tiap `Payment` baru |
| 5 | Lihat tagihan siapa saja | ✅ Selesai | `/invoices` list + search |
| 6 | Lihat yang sudah lunas | ✅ Selesai | `/invoices/lunas` |
| 7 | Bayar instan tanpa Detail Pelanggan | ✅ Selesai | Partial `partials/quick-payment-modal`, di-include di `invoices/index.blade.php:291` & `customers/show.blade.php:994` |
| 8 | Bayar massal per kolektor | ⚠️ Setengah — bulk-pay ada, kolektor tidak ada | `bulkStore` (`PaymentController.php:253-317`, route `invoices.payments.bulk-store`) jalan tapi: (a) tidak ada field/konsep kolektor sama sekali di seluruh kodebase, (b) **nominal per invoice dipaksa = sisa tagihan penuh**, tidak bisa custom per baris — jadi kalau kolektor setor kurang/lebih dari salah satu tagihan, tidak bisa direkam via jalur ini |
| 9 | Rekap hari ini | ⚠️ Mekanisme ada, belum ada preset | `InvoiceReportController`/`PaymentReportController` filter `whereDate('issue_date'/'payment_date', ...)`, tapi admin harus isi tanggal manual tiap buka |
| 10 | Rekap bulanan | ⚠️ Sama seperti #9 | Filter date-range sudah mendukung, tinggal preset UI |

Observer terkait sudah ada (`app/Observers/PaymentObserver.php`, `InvoiceObserver.php`) — cuma jaga amount>0 & anti duplikat-burst 300 detik, **tidak** menyentuh logic overpayment/deposit. Titik validasi overpayment satu-satunya masih di controller — kalau nanti buka jalur insert Payment lain (mis. bulk custom-amount), validasi ini wajib direplikasi/dipusatkan.

## Gap 1 — Saldo/Deposit Pelanggan (Prioritas Tertinggi, Paling Berisiko)

Adopsi desain ledger dari dokumen advanced, dikoreksi dengan prinsip audit dari dokumen kedua: **jangan simpan kolom saldo (`deposit_balance`) yang bisa desync** — cukup hitung on-the-fly dari ledger append-only.

### Skema

```sql
CREATE TABLE `customer_credit_entries` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('deposit', 'applied', 'refund') NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL, -- selalu positif; makna ditentukan oleh `type`
  `source_payment_id` BIGINT UNSIGNED NULL,   -- payment mana penghasil kelebihan (type=deposit)
  `applied_to_invoice_id` BIGINT UNSIGNED NULL, -- invoice mana yang terpotong (type=applied)
  `note` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_credit_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_credit_payment` FOREIGN KEY (`source_payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_credit_invoice` FOREIGN KEY (`applied_to_invoice_id`) REFERENCES `invoices`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_credit_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Saldo berjalan = `SUM(deposit) - SUM(applied) - SUM(refund)` per customer, dihitung tiap kali dibutuhkan (bukan kolom cache). Kalau perlu performa, boleh tambah kolom cache belakangan + job rekonsiliasi berkala — bukan di awal.

### Alur

1. `PaymentController::store` diubah: kalau `amount > remaining_amount`, **jangan langsung tolak** — munculkan konfirmasi kelebihan mau ditabung atau tidak (checkbox eksplisit di modal Bayar Cepat, bukan auto).
2. Kalau disetujui: invoice dilunasi dengan `paid_amount = total_amount` (bukan nominal aktual dibayar — invoice tidak boleh mencatat lebih dari totalnya sendiri), lalu insert `customer_credit_entries` (`type=deposit`, `amount` = selisih, `source_payment_id` = payment ini).
3. Saat admin buka modal Bayar Cepat invoice lain milik customer sama, tampilkan saldo tersedia + tombol "Pakai Saldo" — **manual klik, tidak auto-potong**. Sesuai poin piutang (#2): kalau ada piutang lama & saldo tersedia sekaligus, sistem **menyarankan** FIFO ke invoice tertua, keputusan final tetap admin.
4. Pakai saldo → insert `customer_credit_entries` (`type=applied`, `applied_to_invoice_id` diisi), update invoice `paid_amount`/`remaining_amount`/`invoice_status` seperti pembayaran normal.
5. Refund tunai (pelanggan berhenti, masih ada saldo): insert `type=refund`, dicatat bukan dihapus.

Validasi batas atas `amount` dan seluruh mutasi ledger wajib satu `DB::transaction` + `lockForUpdate()` pada invoice — pola ini sudah ada persis di `store()` sekarang (line 182-221), tinggal direplikasi untuk jalur deposit.

### Gap Tersisa — `Payment.amount` Uang Diterima vs Uang Diterapkan

Alur di atas belum menjawab satu hal: field `Payment.amount` diisi berapa saat ada kelebihan bayar?

- Kalau diisi **nominal yang diterapkan ke invoice** (165rb dari contoh Rp200rb bayar Rp165rb tagihan) — maka rekap harian/bulanan (`PaymentReportController`, poin 9-10) yang men-`sum('amount')` dari tabel `payments` akan **kurang catat** uang fisik yang benar-benar diterima kasir, selisih sebesar deposit yang ditabung, kecuali laporan itu sengaja ditambah `SUM` dari `customer_credit_entries` tipe `deposit` juga.
- Kalau diisi **nominal fisik yang diterima** (200rb) — maka `Payment.amount` tidak lagi 1:1 sama dengan jumlah yang dicatat lunas ke invoice (`paid_amount` cuma naik 165rb), sehingga rekonsiliasi "kenapa total Payment beda dengan total paid_amount invoice" perlu didokumentasikan jelas supaya tidak dikira bug oleh admin/auditor di kemudian hari.

**Keputusan yang perlu diambil sebelum implementasi**: `Payment.amount` = uang fisik diterima (opsi kedua) lebih jujur untuk rekap kas harian, dengan syarat rekap invoice-level (`paid_amount`/`remaining_amount`) tetap dihitung terpisah dari `Payment.amount` menggunakan bagian yang benar-benar diterapkan (query lewat `customer_credit_entries`, bukan asumsi `Payment.amount == invoice.paid_amount delta`). Laporan kas (`PaymentReportController`) dan laporan piutang (`InvoiceReportController`) perlu direview ulang formulanya begitu skema ini masuk, supaya tidak ada baris rekap yang diam-diam salah hitung.

## Gap 2 — Kolektor (Prioritas Kedua, Cepat & Berdampak)

Dari studi kasus: kolektor lapangan tidak login sistem (field legacy `deposited_by_old` di `payments` menandakan itu label bebas, bukan akun). Jangan buatkan mereka user sistem.

```sql
CREATE TABLE `collectors` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `pop_id` BIGINT UNSIGNED NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT `fk_collectors_pop` FOREIGN KEY (`pop_id`) REFERENCES `pops`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `payments` ADD COLUMN `collector_id` BIGINT UNSIGNED NULL AFTER `received_by`,
  ADD CONSTRAINT `fk_payments_collector` FOREIGN KEY (`collector_id`) REFERENCES `collectors`(`id`) ON DELETE SET NULL;
```

- `collector_id` beda dari `received_by` (siapa yang input di sistem) — siapa yang secara fisik setor.
- Di floating bar Bayar Massal (`/invoices`, sudah ada UI-nya), tambah 1 dropdown "Kolektor" — dipilih sekali berlaku untuk seluruh batch.
- **Sekalian benahi gap nominal custom**: `bulkStore` sekarang cuma terima `invoice_ids`, tiap invoice otomatis dibayar penuh sisa tagihan (line 277). Studi kasus butuh kolektor bisa setor beda-beda nominal per pelanggan (ada yang cicil/lebih). Ubah payload jadi array `{invoice_id, amount}` per baris, validasi tiap `amount` terhadap `remaining_amount` invoice terkait (reuse logic validasi dari `store()` line 190-194) — bukan hardcode `remaining_amount` seperti sekarang.
- Laporan (`PaymentReportController`) tambah filter/group-by `collector_id`.

## Gap 3 — Badge Piutang (Kecil, Murni Tampilan)

Computed di query, tanpa migrasi: invoice dengan `billing_period` < bulan berjalan DAN `invoice_status` in `['belum_dibayar','sebagian']` → badge "Piutang". Tampil di tab Tagihan customer & `/invoices` global.

## Gap 4 — Preset Tanggal Rekap (Kecil, Quality-of-Life)

Tombol "Hari Ini"/"Bulan Ini" di `reports/invoices` & `reports/payments`, isi otomatis `date_from`/`date_to` — filter backend sudah mendukung, tidak ada perubahan skema. Kalau kolektor (Gap 2) sudah ada, tambah breakdown per kolektor di laporan yang sama.

## Risiko & Mitigasi

1. **Selisih saldo deposit** — Penyebab: rounding desimal, double-submit. Mitigasi: seluruh mutasi dalam `DB::transaction` + `lockForUpdate()` (pola sudah ada di `store()`); saldo dihitung `SUM` dari ledger tiap saat, bukan kolom kaku yang bisa basi.
2. **Overlapping bulk payment** — Penyebab: admin klik proses berkali-kali (koneksi lambat). Mitigasi: disable tombol submit saat loading (UI), plus `lockForUpdate()` di level DB sebelum proses tiap invoice — pola ini **sudah ada** di `bulkStore` sekarang (line 275), tinggal dipertahankan saat nominal jadi custom.
3. **Kebocoran scope POP** — Penyebab: kasir cabang A proses bulk kolektor cabang B. Mitigasi: `applyUserScope()` sudah dipakai di `bulkStore` (line 264) — pastikan scope sama diterapkan ke endpoint deposit baru.
4. **Validasi overpayment tidak konsisten antar jalur insert Payment** — kalau deposit & bulk-custom-amount dibuka, tiap jalur insert `Payment` wajib pakai validasi batas atas yang sama (reuse method/service, jangan duplikasi rule manual) supaya `PaymentObserver` tetap jadi lapisan pengaman terakhir yang konsisten.

## Urutan Eksekusi

1. Migrasi: `customer_credit_entries`, `collectors`, `payments.collector_id`.
2. `DepositService`: `credit()` (catat kelebihan bayar) & `apply()` (potong saldo ke invoice), dipakai `PaymentController::store` setelah deteksi overpayment.
3. Ubah `bulkStore`: payload jadi per-invoice custom amount + `collector_id`, reuse validasi batas atas dari `store()`.
4. UI: checkbox "Tabung Kelebihan" + info saldo tersedia di modal Bayar Cepat (sudah ada, tinggal extend); dropdown Kolektor + input nominal per baris di floating bar bulk (sudah ada, tinggal extend); badge Piutang; tombol preset tanggal di halaman report.
5. Update `PaymentObserver`/audit log kalau perlu — pastikan tiap mutasi `customer_credit_entries` tercatat siapa admin yang proses (`created_by`).
