# Flowchart — Modul Billing & Pembayaran

## 1. Pembuatan Invoice — 3 Jalur

```
┌─────────────────────────┐  ┌──────────────────────────┐  ┌───────────────────────────┐
│ A. Aktivasi (AWAL)      │  │ B. Bulanan Rutin          │  │ C. Manual (admin input)   │
│ CustomerVerification    │  │ Console: billing:generate-│  │ CustomerController::      │
│ Controller::finalVerify │  │ monthly-invoices          │  │ storeManualInvoice        │
└──────────┬───────────────┘  └──────────┬─────────────────┘  └──────────┬──────────────┘
           │                             │                              │
           ▼                             ▼                              ▼
   FOP approve verifikasi        Cron/manual jalanin           Admin isi form invoice_type,
   instalasi terakhir       →    command tiap awal bulan  →    billing_period, fee-fee →
   → invoice_type=awal,          → loop customer                cek dobel (customer+type+
     subtotal+prorate+           active/suspended yang           period) → InvoiceObserver
     extra fee dihitung           belum punya invoice              cek lagi (dedup 5 menit)
     manual di form                bulanan periode ini
           │                             │                              │
           └─────────────┬───────────────┴──────────────────────────────┘
                         ▼
              InvoiceObserver::creating()
              - invoice_type wajib diisi
              - tolak kalau ada Invoice sama persis
                (customer+type+period+total_amount)
                dibuat < 5 menit lalu
                         │
                         ▼
              Invoice tersimpan, invoice_status = belum_dibayar,
              paid_amount = 0, remaining_amount = total_amount
```

**Catatan jalur B (Bulanan):** kalau `customer_service.activation_date` di bulan yang sama dengan periode berjalan → di-skip (bulan aktivasi udah ditagih lewat jalur A, gak boleh dobel tagih).

## 2. Status Invoice

```
        create
           │
           ▼
   ┌────────────────┐
   │ belum_dibayar  │──────────┐
   └───────┬────────┘          │ (admin batalkan,
           │ payment masuk      │  di luar controller
           │ (partial)          │  yang di-cover di sini)
           ▼                    ▼
   ┌────────────────┐    ┌───────────┐
   │   sebagian     │    │   batal   │  (tidak bisa terima
   └───────┬────────┘    └───────────┘   pembayaran lagi)
           │ payment sampai
           │ remaining_amount = 0
           ▼
   ┌────────────────┐
   │     lunas      │
   └────────────────┘
```

Transisi `belum_dibayar`/`sebagian` → `lunas` dihitung otomatis tiap kali `Payment` baru masuk — `remaining_amount = max(0, total_amount - paid_amount)`, status `lunas` kalau `remaining_amount <= 0`.

## 3. Alur Pembayaran Single (`POST /invoices/{invoice}/payments`)

Dua pintu masuk sampai di route yang sama: halaman penuh `payments/create.blade.php`
(via tombol "Bayar"/"Bayar Cicil" di `invoices/show`) dan modal AJAX
`payments/partials/quick-payment-modal.blade.php` (via tombol "Bayar"/"Bayar Cicil"
di `/invoices` list & tab Tagihan Detail Pelanggan) — jalur AJAX ini yang paling
sering dipakai sehari-hari.

```
Admin buka form bayar (halaman penuh ATAU modal cepat)
        │
        ▼
authorizeInvoiceAccess() — cek Invoice masuk applyUserScope() user
        │
        ▼
invoice_status == lunas? ──── ya ──▶ tolak, "Tagihan ini sudah lunas."
        │ tidak
        ▼
invoice_status == batal? ──── ya ──▶ tolak, "Tagihan batal tidak dapat menerima pembayaran."
        │ tidak
        ▼
validasi: amount ≥ 1 (TIDAK dibatasi remaining_amount lagi — lihat catatan
          lebih bayar di bawah), payment_method in (cash/transfer/qris/lainnya),
          proof_file opsional (jpg/jpeg/png/pdf, max 2MB)
        │
        ▼
upload proof_file (kalau ada) → FileUploadService::uploadPaymentProof()
        │
        ▼
DB transaction:
  lock Invoice (lockForUpdate)
  remaining ≤ 0? ──▶ tolak, "Tagihan ini sudah lunas." (TOCTOU guard)
  appliedAmount = min(amount_diketik, remaining)
  overpayAmount = amount_diketik − appliedAmount
  Payment::create(amount=appliedAmount, overpay_amount=overpayAmount>0?..:null,
                   status=valid, payment_number=PAY-YYYYMM-NNNN)
  Invoice::recalculateFromPayments() → paid_amount, remaining_amount, invoice_status
        │
        ▼
PaymentObserver::creating() — tolak amount ≤ 0, DAN rejectBurstDuplicate()
  (tolak insert identik customer+invoice+amount+date dalam 300 detik)
        │
        ▼
Response: redirect ke invoices.show + flash success, atau JSON (kalau AJAX)
```

**Catatan lebih bayar (2026-08-04):** admin cukup ketik SATU nominal — total uang
yang diterima dari pelanggan, boleh lebih besar dari `remaining_amount`. Sistem
yang membagi otomatis: bagian yang menutup tagihan masuk `payments.amount`
(tetap dijamin tak pernah melebihi sisa tagihan), sisanya masuk
`payments.overpay_amount` sebagai catatan — BUKAN saldo kredit, tak pernah
dipakai otomatis untuk tagihan berikutnya, diselesaikan manual di luar sistem
(refund fisik / potong tagihan berikutnya secara manual). Kedua form (halaman
penuh & modal cepat) menampilkan pratinjau hidup sebelum submit: "Rp X
diterapkan ke tagihan (Lunas), Rp Y tercatat sebagai lebih bayar."

**Catatan cicilan:** kalau `amount` yang diterapkan lebih kecil dari
`remaining_amount`, `invoice_status` jadi `sebagian` dan payment ini otomatis
jadi bagian dari urutan cicilan invoice tersebut — lihat `Payment::installmentContext()`
di [database-schema.md](database-schema.md). Ditampilkan sebagai baris anak
"Cicilan Ke-N" yang bisa di-expand di `/invoices`, kolom Cicilan Ke-N di
`invoices/show`, dan badge Cicilan Ke-N/Melunasi Tagihan di `payments/show` &
kwitansi cetak.

## 3b. Tolak Pembayaran (`POST /payments/{payment}/reject`)

```
Admin buka Detail Pembayaran (/payments/{id}), payment_status = valid
        │
        ▼
Klik "Tolak Pembayaran" → window.Dialog (components/dialog.blade.php)
  minta alasan wajib diisi (reject_reason)
        │
        ▼
Submit form tersembunyi → POST /payments/{payment}/reject
  permission: payments.reject
        │
        ▼
Validasi: reject_reason wajib (ReasonValidationRule::required(1000)),
          payment_status harus masih valid (tolak reject-dobel)
        │
        ▼
DB transaction:
  Payment update: payment_status=ditolak, reject_reason, rejected_at, rejected_by
  Invoice::recalculateFromPayments() — invoice ikut terkoreksi (paid_amount/
    remaining_amount/invoice_status turun sesuai payment valid yang tersisa)
        │
        ▼
Payment::booted() 'updated' event → audit log action=cancel (payment_status
  berubah jadi ditolak)
        │
        ▼
Response: redirect ke payments.show + flash success/error
```

Payment yang `ditolak` TIDAK ikut dihitung `Invoice::recalculateFromPayments()`
dan TIDAK dapat nomor cicilan (`Payment::installmentContext()` return null) —
mencegah nomor cicilan bolong/menyimpang kalau ada yang direject di tengah.

## 4. Bayar Massal (`POST /invoices/bulk-pay`)

**Catatan lokasi UI (2026-08):** checkbox bulk-pay sudah tak ada lagi di
`/invoices` — jalur ini sekarang dipicu dari **List Pelanggan** (`/customers`,
`resources/views/customers/index.blade.php`). Kolektor punya jalur bulk-pay
SENDIRI yang lebih kaya (nominal per-baris, bukan cuma lunas penuh) — lihat
§4b di bawah.

```
Admin pilih banyak invoice (checkbox) di /customers → isi tanggal+metode bayar → submit
        │
        ▼
Filter invoice_ids: hanya yang applyUserScope() lolos DAN status BUKAN lunas/batal
        │
        ▼
Loop tiap invoice (masing-masing DB transaction terpisah):
  lock invoice → amount = remaining_amount penuh (lunas sekaligus, bukan partial)
  amount ≤ 0? ──▶ skip (throw, masuk hitungan "gagal")
  Payment::create(note default "Pembayaran massal")
  Invoice update → invoice_status jadi lunas (remaining selalu jadi 0)
        │
        ▼
Response JSON: {paid: N, failed: M} — invoice yang gagal TIDAK menghentikan
                                        proses invoice lain (per-invoice try/catch)
```

## 4b. Batch Bayar Kolektor (`POST /collector-batch/{collector}`)

Dipakai dari tab "Worklist & Bayar" di `/collectors/{collector}` (satu kolektor,
banyak pelanggan/invoice sekaligus, nominal BEBAS per baris — bukan cuma lunas
penuh seperti §4). `CollectorBatchController::store()`.

```
Admin buka /collectors/{collector}, tab Worklist & Bayar
  → centang invoice + isi nominal per baris (boleh partial) → submit sekali
        │
        ▼
idempotency_key sudah pernah diproses? ──ya──▶ return sukses, TIDAK diproses ulang
        │ belum
        ▼
Fase validasi cepat (tanpa lock) — semua baris dicek dulu, kalau ADA yang
  gagal, SELURUH batch ditolak dengan daftar alasan (tak ada yang tersimpan)
        │ semua valid
        ▼
DB transaction (all-or-nothing untuk SELURUH batch):
  PaymentBatch::create(idempotency_key, submitted_by, collector_id)
  loop tiap baris:
    lock Invoice → re-validasi amount ≤ remaining_amount (otoritatif, bukan
      cuma optimasi UX seperti fase cepat di atas)
    Payment::create(payment_batch_id=batch.id, collected_by=collector.id,
                     collected_date=tanggal setor)
    Invoice::recalculateFromPayments()
        │
        ▼
Response JSON: {paymentCount, results} — SATU transaksi utuh, gagal satu
                                          baris = batal semua (beda dari §4
                                          yang per-invoice independen)
```

## 5. Audit Trail

```
Payment::created/updated/deleted (model event, app/Models/Payment.php)
        │
        ▼
writeAuditLog() → AuditLog::create(module=Pembayaran, action, old_values, new_values)
        │
        action ditentukan:
        - create  → status baru dibuat
        - update  → field berubah (kecuali updated_at doang)
        - cancel  → khusus kalau payment_status berubah jadi 'ditolak'
        - delete  → record dihapus
```

Invoice juga punya audit (`RecordsAuditLogs` trait, module `Tagihan`) tapi cuma event `updated` & `deleted` — insert Invoice gak diaudit di level ini (creation context sudah jelas dari jalur A/B/C di atas).
