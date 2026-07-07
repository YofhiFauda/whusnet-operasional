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

```
Admin buka form bayar (GET /invoices/{invoice}/payments/create)
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
validasi: amount ≤ remaining_amount, payment_method in (cash/transfer/qris/lainnya),
          proof_file opsional (jpg/jpeg/png/pdf, max 2MB)
        │
        ▼
upload proof_file (kalau ada) → FileUploadService::uploadPaymentProof()
        │
        ▼
DB transaction:
  lock Invoice (lockForUpdate)
  re-cek amount ≤ remaining_amount (race condition guard)
  Payment::create(status=valid, payment_number=PAY-YYYYMM-NNNN)
  Invoice update: paid_amount += amount, remaining_amount, invoice_status
        │
        ▼
PaymentObserver::creating() — tolak kalau amount ≤ 0 (lapis kedua, independen dari validasi form)
        │
        ▼
Response: redirect ke invoices.show + flash success, atau JSON (kalau AJAX)
```

## 4. Bayar Massal (`POST /invoices/bulk-pay`)

```
Admin pilih banyak invoice (checkbox) di /invoices → isi tanggal+metode bayar → submit
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
