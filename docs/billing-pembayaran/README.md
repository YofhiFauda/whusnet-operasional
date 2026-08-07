# Modul Billing & Pembayaran

Tagihan (`Invoice`) dan pembayaran (`Payment`) pelanggan ISP. Tagihan lahir dari 2 jalur: **otomatis** (aktivasi & bulanan rutin) dan **manual** (admin input lewat form). Pembayaran dicatat per-tagihan (partial atau lunas) atau massal (bulk-pay banyak tagihan sekaligus).

## Konsep Inti

| Entity | Peran |
|--------|-------|
| `Invoice` | 1 row = 1 tagihan periode tertentu. Tipe: `awal` (PSB/aktivasi), `bulanan` (rutin), `reaktivasi`. |
| `Payment` | 1 row = 1 transaksi pembayaran terhadap 1 Invoice. Invoice bisa punya banyak Payment (cicilan/partial) — urutannya dihitung `Payment::installmentContext()` ("Cicilan Ke-N"). |
| `PaymentBatch` | 1 row = 1 sesi submit batch kolektor (idempotency + pengelompokan). BUKAN rekonsiliasi kas — fitur Setoran Kolektor di-drop dari scope. |

**Invoice status** (derived dari akumulasi Payment VALID, dihitung `Invoice::recalculateFromPayments()`): `belum_dibayar` → `sebagian` → `lunas`, atau `batal` (dibatalkan, gak bisa terima pembayaran lagi).

**Payment status**: `valid` (default saat dicatat, semua jalur insert langsung valid — TAK ADA alur verifikasi bertahap), `ditolak` (via `POST /payments/{id}/reject`, wajib alasan). **`pending` sudah dihapus dari enum (2026-08-03)** — kalau menemukan referensi ke `pending` di kode lama, itu bug/sisa, bukan status yang valid.

**Notifikasi in-app (2026-08-06/07)** — `PaymentController::reject()` notif ke pencatat pembayaran (`collected_by` kalau ada / fallback `received_by`), skip kalau yang reject = pencatat sendiri. `CollectorBatchController::store()` sukses notif role `pop_admin` di POP invoice yang kena (pengganti "Finance Pusat" — role itu gak ada di RBAC sistem ini, `pop_admin` dipilih karena pegang `payments.validate`/`reject` per POP). **Pesannya sengaja murni informatif** ("dicatat"), bukan "perlu direkonsiliasi" — selaras sama keputusan produk di atas (`PaymentBatch` BUKAN rekonsiliasi kas, fitur Setoran Kolektor formal di-drop dari scope). Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.3.

**Lebih bayar** (`payments.overpay_amount`, 2026-08-04): admin ketik SATU nominal total diterima, sistem otomatis pisah bagian yang menutup tagihan (`amount`, tetap tak pernah melebihi sisa tagihan) dari kelebihannya (`overpay_amount`). **Bukan saldo kredit** — tak punya sisi debit, tak pernah dipakai otomatis untuk tagihan berikutnya. Tab khusus read-only di `/payments/overpay`.

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [flowchart.md](flowchart.md) | Alur pembuatan invoice (auto & manual), alur pembayaran, status transition |
| [user-flow.md](user-flow.md) | Langkah admin di `/invoices`, `/payments`, bulk-pay, laporan |
| [database-schema.md](database-schema.md) | Tabel, kolom, relasi, index dedup-guard |
| [perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md) | Cara membedakan tagihan awal vs bulanan: sistem lama vs sekarang, rumus prorata, celah yang tidak diwarisi |
| [analisa-ux-form-verifikasi-aktivasi.md](analisa-ux-form-verifikasi-aktivasi.md) | Form tagihan pertama jadi kwitansi (5 input, sisanya turunan server) |
| [analisa-pencegahan-tagihan-dobel.md](analisa-pencegahan-tagihan-dobel.md) | Lima lapis pencegahan tagihan dobel, mana yang bolong, urutan perbaikan (BILLING-B0b–B0e) |
| [analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md](analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md) | Enam cacat migrasi legacy yang bikin tagihan & pembayaran dobel (kasus Ardiyanto, Wiyono) |
| [archive/](archive/) | Analisa & rencana historis (sebagian sudah diimplementasi, sebagian belum) |
| [`../plan/analisa-billing-tagihan-pembayaran-kolektor.md`](../plan/analisa-billing-tagihan-pembayaran-kolektor.md) | Dokumen rancangan sumber untuk kolektor, batch bayar, reject payment, lebih bayar, cicilan — histori keputusan lengkap (termasuk yang di-drop: Setoran Kolektor & Saldo Kredit) |

## Routes & Permission

| Route | Method | Permission | Controller |
|-------|--------|------------|------------|
| `/invoices` | GET | `invoices.view` | `InvoiceController@index` |
| `/invoices/lunas` | GET | `invoices.view` | `InvoiceController@lunas` |
| `/invoices/belum-lunas` | GET | `invoices.view` | `InvoiceController@belumLunas` |
| `/invoices/{invoice}` | GET | `invoices.view` | `InvoiceController@show` |
| `/customers/{customer}/invoices/manual` | POST | `invoices.create` | `CustomerController@storeManualInvoice` |
| `/payments` | GET | `payments.view` | `PaymentController@index` |
| `/payments/overpay` | GET | `payments.view` | `PaymentController@overpay` |
| `/payments/{payment}` | GET | `payments.view` | `PaymentController@show` |
| `/payments/{payment}/kwitansi` | GET | `payments.view` | `PaymentController@receipt` |
| `/payments/{payment}/reject` | POST | `payments.reject` | `PaymentController@reject` |
| `/invoices/{invoice}/payments/create` | GET | `payments.create` | `PaymentController@create` |
| `/invoices/{invoice}/payments` | POST | `payments.create` | `PaymentController@store` |
| `/invoices/bulk-pay` | POST | `payments.create` | `PaymentController@bulkStore` (dipicu dari `/customers`, bukan `/invoices` lagi) |
| `/collectors` | GET | `customers.update` \| `payments.create` | `CollectorController@index` |
| `/collectors/{collector}` | GET | `customers.update` \| `payments.create` | `CollectorController@show` |
| `/collectors/{collector}/assign` | POST | `customers.update` | `CollectorController@assign` |
| `/collectors/{collector}/customers/{customer}/release` | POST | `customers.update` | `CollectorController@release` |
| `/collector-batch/{collector}` | POST | `payments.create` | `CollectorBatchController@store` |
| `/customers/{customer}/payment-info` | GET | (login) | `CustomerController@paymentInfo` |
| `/reports/invoices`, `/reports/invoices/export` | GET | (report perm) | `InvoiceReportController` |
| `/reports/payments`, `/reports/payments/export`, `/reports/payments/export-xlsx` | GET | (report perm) | `PaymentReportController` |

**POP scope:** semua query pakai `applyUserScope()` (trait `HasPopScope`) — admin non-owner cuma lihat invoice/payment di POP yang di-assign ke dia.

## Console Command

- `billing:generate-monthly-invoices [--dry-run]` — generate tagihan `bulanan` flat untuk semua pelanggan aktif/suspended yang belum punya tagihan bulanan di periode berjalan. Skip pelanggan yang baru aktivasi di bulan yang sama (udah kena tagihan `awal`). File: `app/Console/Commands/GenerateMonthlyInvoicesCommand.php`.

## Guard Anti-Duplikat

**`payments_invoice_date_amount_unique` DI-DROP 2026-08-03** — dulu satu-satunya guard dobel-submit di DB, tapi ikut menolak cicilan sah (nominal sama, invoice sama, tanggal sama). Sekarang tiga lapis proteksi:

1. **`InvoiceObserver::creating()`** — tolak insert Invoice kalau ada yang identik (customer+type+billing_period+total_amount) dalam 5 menit terakhir.
2. **`PaymentObserver::rejectBurstDuplicate()`** — tolak insert Payment identik (customer+invoice+amount+date) dalam 300 detik, jalur single-payment.
3. **`payment_batches.idempotency_key`** — dedup per sesi submit batch kolektor.

Invoice gak punya unique index setara (data lama sebelum fix migrasi masih ada pelanggaran, dan invoice `batal` menempati slot periode — lihat catatan di `database-schema.md`) — guard invoice level DB baru ditegakkan di `CustomerController::storeManualInvoice` (app-layer check), belum hard constraint.

## Teknologi

| Komponen | Stack |
|----------|-------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade, vanilla JS (bulk-pay bar `/customers`, batch bar `/collectors/{id}`), modal AJAX (`quick-payment-modal`) |
| Dialog/Alert | Komponen global `window.Dialog`/`window.Toast` (`resources/views/components/dialog.blade.php`, `toast.blade.php`) — bukan `alert()`/`confirm()` native, bukan modal ad-hoc per halaman |
| Database | MySQL — `invoices`, `payments`, `payment_batches`, `payment_number_sequences` |
| File | `FileUploadService::uploadPaymentProof()` — bukti transfer/foto |

---

## Pola Redirect (PRG)

Catat pembayaran (`store`) → redirect ke `invoices.show` (Detail invoice induk, aturan "aksi child →
Detail parent"). Aturan lengkap + kenapa: **[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

---

**Last updated:** 2026-08-04 — sinkronisasi penuh setelah drift ~1 bulan (Fase 1: payment_batches/idempotency/burst-dedup; Fase 2: collector_id/collected_by; reject payment; `PaymentStatus::PENDING` dihapus; lebih bayar auto-split; tampilan cicilan; migrasi Dialog/Toast global). Rujukan lengkap keputusan desain: [`docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md`](../plan/analisa-billing-tagihan-pembayaran-kolektor.md).
