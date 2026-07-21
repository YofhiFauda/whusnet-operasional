# Modul Billing & Pembayaran

Tagihan (`Invoice`) dan pembayaran (`Payment`) pelanggan ISP. Tagihan lahir dari 2 jalur: **otomatis** (aktivasi & bulanan rutin) dan **manual** (admin input lewat form). Pembayaran dicatat per-tagihan (partial atau lunas) atau massal (bulk-pay banyak tagihan sekaligus).

## Konsep Inti

| Entity | Peran |
|--------|-------|
| `Invoice` | 1 row = 1 tagihan periode tertentu. Tipe: `awal` (PSB/aktivasi), `bulanan` (rutin), `reaktivasi`. |
| `Payment` | 1 row = 1 transaksi pembayaran terhadap 1 Invoice. Invoice bisa punya banyak Payment (cicilan/partial). |

**Invoice status** (derived dari akumulasi Payment): `belum_dibayar` → `sebagian` → `lunas`, atau `batal` (dibatalkan, gak bisa terima pembayaran lagi).

**Payment status**: `pending`, `valid` (default saat dicatat admin), `ditolak` (dibatalkan/reject).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [flowchart.md](flowchart.md) | Alur pembuatan invoice (auto & manual), alur pembayaran, status transition |
| [user-flow.md](user-flow.md) | Langkah admin di `/invoices`, `/payments`, bulk-pay, laporan |
| [database-schema.md](database-schema.md) | Tabel, kolom, relasi, index dedup-guard |
| [perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md) | Cara membedakan tagihan awal vs bulanan: sistem lama vs sekarang, rumus prorata, celah yang tidak diwarisi |
| [analisa-ux-form-verifikasi-aktivasi.md](analisa-ux-form-verifikasi-aktivasi.md) | Rencana sederhanakan form tagihan pertama jadi kwitansi (4 input, sisanya turunan) |
| [archive/](archive/) | Analisa & rencana historis (sebagian sudah diimplementasi, sebagian belum) |

## Routes & Permission

| Route | Method | Permission | Controller |
|-------|--------|------------|------------|
| `/invoices` | GET | `invoices.view` | `InvoiceController@index` |
| `/invoices/lunas` | GET | `invoices.view` | `InvoiceController@lunas` |
| `/invoices/belum-lunas` | GET | `invoices.view` | `InvoiceController@belumLunas` |
| `/invoices/{invoice}` | GET | `invoices.view` | `InvoiceController@show` |
| `/customers/{customer}/invoices/manual` | POST | `invoices.create` | `CustomerController@storeManualInvoice` |
| `/payments` | GET | `payments.view` | `PaymentController@index` |
| `/payments/{payment}` | GET | `payments.view` | `PaymentController@show` |
| `/invoices/{invoice}/payments/create` | GET | `payments.create` | `PaymentController@create` |
| `/invoices/{invoice}/payments` | POST | `payments.create` | `PaymentController@store` |
| `/invoices/bulk-pay` | POST | `payments.create` | `PaymentController@bulkStore` |
| `/customers/{customer}/payment-info` | GET | (login) | `CustomerController@paymentInfo` |
| `/reports/invoices`, `/reports/invoices/export` | GET | (report perm) | `InvoiceReportController` |
| `/reports/payments`, `/reports/payments/export` | GET | (report perm) | `PaymentReportController` |

**POP scope:** semua query pakai `applyUserScope()` (trait `HasPopScope`) — admin non-owner cuma lihat invoice/payment di POP yang di-assign ke dia.

## Console Command

- `billing:generate-monthly-invoices [--dry-run]` — generate tagihan `bulanan` flat untuk semua pelanggan aktif/suspended yang belum punya tagihan bulanan di periode berjalan. Skip pelanggan yang baru aktivasi di bulan yang sama (udah kena tagihan `awal`). File: `app/Console/Commands/GenerateMonthlyInvoicesCommand.php`.

## Guard Anti-Duplikat

Dua lapis proteksi dari insiden migrasi data lama (retry submit bikin invoice/payment dobel):

1. **`InvoiceObserver::creating()`** — tolak insert kalau ada Invoice lain dengan customer+type+billing_period+total_amount yang sama dalam 5 menit terakhir.
2. **`payments_invoice_date_amount_unique`** — unique index DB (`invoice_id`, `payment_date`, `amount`) di tabel `payments`.

Invoice gak punya unique index setara (data lama sebelum fix migrasi masih ada pelanggaran) — guard invoice level DB baru ditegakkan di `CustomerController::storeManualInvoice` (app-layer check), belum hard constraint.

## Teknologi

| Komponen | Stack |
|----------|-------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade, vanilla JS (bulk-pay bar), Alpine.js (quick-payment-modal) |
| Database | MySQL — `invoices`, `payments` |
| File | `FileUploadService::uploadPaymentProof()` — bukti transfer/foto |

---

## Pola Redirect (PRG)

Catat pembayaran (`store`) → redirect ke `invoices.show` (Detail invoice induk, aturan "aksi child →
Detail parent"). Aturan lengkap + kenapa: **[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

---

**Last updated:** 2026-07-07
