# Database Schema — Modul Billing & Pembayaran

## Entity Relationship

```
customers ──┐
            │
customer_services ──┐
            │        │
internet_packages ───┼──▶ invoices ──1:N──▶ payments ◀── users (received_by, collected_by, rejected_by)
            │        │        ▲                  │
pops ───────┴────────┘        │                  ├──▶ payment_batches ◀── users (submitted_by, collector_id)
            │                 │                  │
users (created_by) ───────────┘                  └──▶ payment_number_sequences (counter, bukan FK)

customers.collector_id ──▶ users (role kolektor)
```

- `invoices.customer_id/pop_id/customer_service_id/internet_package_id` → cascade delete dari parent (hapus customer/POP/service/package ikut hapus invoice terkait).
- `payments.invoice_id/customer_id/pop_id` → cascade delete dari parent.
- `payments.payment_batch_id` → `nullOnDelete` (batch dihapus, payment tetap ada, kolom jadi null).
- `invoices.created_by`, `payments.received_by/collected_by/rejected_by`, `customers.collector_id` → `nullOnDelete` (user dihapus, riwayat tetap ada, kolom jadi null).
- `payment_batches.submitted_by` → cascade delete. `payment_batches.collector_id` → `nullOnDelete`.

## Tabel `invoices`

Migrasi sumber: `2026_06_12_132728_create`, `2026_06_15_000002_add_legacy_ids`, `2026_06_16_103000_add_extended_attributes`, `2026_06_17_150000_add_other_fee`, `2026_07_02_133000_add_invoice_type`, `2026_07_04_091633_remove_default_from_invoice_type`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `invoice_number` | string(50), unique | | Format `INV-{periode}-{urutan}` |
| `old_invoice_id` / `old_cost_id` / `old_request_id` | string(50) | ✔ | ID referensi data legacy (migrasi dari sistem lama) |
| `invoice_type` | string(30), **NOT NULL, no default** | | Enum `App\Enums\InvoiceType`: `awal`, `bulanan`, `reaktivasi` — wajib diisi eksplisit tiap insert (default DB sengaja dihapus, lihat catatan di bawah) |
| `customer_id` | FK → `customers.id`, cascade delete | | |
| `pop_id` | FK → `pops.id`, cascade delete | | |
| `customer_service_id` | FK → `customer_services.id`, cascade delete | | |
| `internet_package_id` | FK → `internet_packages.id`, cascade delete | | |
| `billing_period` | string(50) | | Format `Y-m`, e.g. `2026-07` |
| `issue_date` | date | | Tanggal terbit |
| `due_date` | date | | Tanggal jatuh tempo |
| `subtotal` | decimal(12,2) | | |
| `discount` | decimal(12,2), default 0 | | |
| `ppn` | decimal(5,2), default 0 | | Persentase PPN |
| `prorate_amount` | decimal(12,2) | ✔ | Biaya prorata (khusus invoice AWAL) |
| `extra_cable_fee` | decimal(12,2) | ✔ | Biaya kabel tambahan |
| `extra_installation_fee` | decimal(12,2) | ✔ | Biaya instalasi tambahan |
| `extra_pole_fee` | decimal(12,2) | ✔ | Biaya tiang tambahan |
| `other_fee` | decimal(12,2) | ✔ | Biaya lain-lain |
| `total_amount` | decimal(12,2) | | |
| `paid_amount` | decimal(12,2), default 0 | | Akumulasi `payments.amount` VALID — dihitung `Invoice::recalculateFromPayments()`, tak pernah melebihi `total_amount` |
| `remaining_amount` | decimal(12,2) | | `total_amount - paid_amount`, floor 0 |
| `invoice_status` | string(50), default `belum_dibayar` | | `belum_dibayar`, `sebagian`, `lunas`, `batal` |
| `created_by` | FK → `users.id`, null on delete | ✔ | Null kalau dibuat via command (`billing:generate-monthly-invoices`) |
| `created_at` / `updated_at` | timestamp | | |

**Catatan `invoice_type`:** kolom ini sempat punya default DB `'bulanan'` — dihapus lewat migrasi `remove_default_from_invoice_type_column` karena default diam-diam itu yang bikin invoice salah tag pas migrasi data legacy. Sekarang: `InvoiceObserver::creating()` juga menolak insert kalau `invoice_type` kosong (lapis aplikasi, redundant dengan constraint DB).

**Catatan unique index (2026-07-21):** migrasi `add_invoice_period_unique_index_to_invoices` SENGAJA kosong (no-op) — unique index `(customer_id, invoice_type, billing_period)` gagal dipasang karena invoice `batal` tetap menempati slot periode (MySQL tak punya partial index) dan memblokir tagihan pengganti yang sah. Anti-dobel invoice tetap murni layer aplikasi (`InvoiceObserver` + `CustomerController::storeManualInvoice`), dipantau `billing:audit-duplicate-invoices`. Jangan coba pasang index ini lagi tanpa membaca catatan lengkap di file migrasinya.

## Tabel `payments`

Migrasi sumber: `2026_06_13_000001_create`, `2026_06_15_000002_add_legacy_ids`, `2026_07_04_091731_add_duplicate_guard_indexes` (dicabut lagi, lihat di bawah), `2026_08_03_090927_drop_invoice_date_amount_unique_index`, `2026_08_03_090929_add_payment_batch_id`, `2026_08_03_090931_add_reject_columns`, `2026_08_03_120002_add_collector_columns`, `2026_08_03_130001_change_status_default_to_valid`, `2026_08_03_140001_add_overpay_amount`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `payment_number` | string(50), unique | | Format `PAY-{periode}-{urutan}`, digenerate `Payment::generatePaymentNumber()` via `payment_number_sequences` (lihat di bawah) |
| `old_payment_id` / `old_transaction_id` / `old_request_id` | string(50) | ✔ | Referensi data legacy |
| `billing_period` | string(50) | ✔ | Legacy — periode bayar dari data lama |
| `received_by_old` / `deposited_by_old` | string(50) | ✔ | Nama penerima/penyetor dari data legacy (sebelum ada FK `users`) |
| `invoice_id` | FK → `invoices.id`, cascade delete | | |
| `payment_batch_id` | FK → `payment_batches.id`, null on delete | ✔ | Terisi HANYA untuk payment yang lahir dari batch kolektor. Jalur single-payment (bayar langsung dari halaman Tagihan) tetap null |
| `customer_id` | FK → `customers.id`, cascade delete | | |
| `pop_id` | FK → `pops.id`, cascade delete | | |
| `payment_date` | date | | Tanggal posting/validasi kantor |
| `collected_date` | date | ✔ | Tanggal uang diterima DI LAPANGAN — beda dari `payment_date`, mencegah pendapatan lintas-bulan salah potong saat kolektor telat setor |
| `payment_method` | string(50) | | `cash`, `transfer`, `qris`, `lainnya` |
| `amount` | decimal(12,2) | | Bagian yang DITERAPKAN ke tagihan — wajib > 0 (`PaymentObserver::creating()`), tak pernah melebihi `remaining_amount` invoice saat insert. **TOTAL uang diterima dari pelanggan boleh lebih besar** — sisanya otomatis dipisah ke `overpay_amount`, bukan masuk ke `amount` (§ lihat catatan lebih bayar di bawah) |
| `overpay_amount` | decimal(12,2) | ✔ | Kelebihan uang fisik yang diserahkan pelanggan di atas sisa tagihan. **Catatan informatif, BUKAN saldo kredit** — tak punya sisi debit, tak pernah dipakai otomatis untuk tagihan berikutnya. `PaymentController::store()` yang menghitung otomatis (`total_received - min(total_received, remaining)`), admin tak perlu hitung manual |
| `received_by` | FK → `users.id`, null on delete | ✔ | Kasir/admin yang mem-validasi pembayaran di sistem |
| `collected_by` | FK → `users.id`, null on delete | ✔ | Kolektor yang FAKTANYA menagih — snapshot BEKU, TIDAK disalin otomatis dari `customers.collector_id` (kalau disalin buta, laporan kolektor mencatat uang yang tak pernah dia tagih). Null untuk jalur non-kolektor |
| `proof_file` | string | ✔ | Path bukti transfer/foto |
| `payment_status` | string(50), **default `valid`** | | `valid`, `ditolak` — **`pending` DIHAPUS dari enum (2026-08-03)**, sistem ini tak punya alur verifikasi bertahap, semua jalur insert baru langsung `valid` |
| `reject_reason` | text | ✔ | Alasan wajib diisi saat reject (`ReasonValidationRule::required(1000)`) |
| `rejected_at` | timestamp | ✔ | |
| `rejected_by` | FK → `users.id`, null on delete | ✔ | |
| `note` | text | ✔ | |
| `created_at` / `updated_at` | timestamp | | |

**Riwayat unique index anti-duplikat (PENTING, jangan pasang ulang tanpa baca ini):** `payments_invoice_date_amount_unique` (`invoice_id`, `payment_date`, `amount`) pernah jadi satu-satunya guard dobel-submit di DB, tapi ikut menolak **cicilan sah** (nominal sama, invoice sama, tanggal sama — mis. dua sesi setoran kolektor di hari yang sama) dan menghalangi koreksi "void lalu input ulang nominal & tanggal sama". **Di-drop 2026-08-03**, diganti dua guard aplikasi:
1. `PaymentObserver::rejectBurstDuplicate()` — tolak insert identik (customer+invoice+amount+date) dalam jendela 300 detik, jalur single-payment.
2. `payment_batches.idempotency_key` — dedup per sesi submit batch, jalur kolektor.

Index biasa `payments_invoice_id_idx` (`invoice_id`) dipasang dulu sebelum unique index lama dicabut — itu satu-satunya index yang menaungi FK `payments_invoice_id_foreign`, MySQL/InnoDB menolak drop tanpa penggantinya.

## Tabel `payment_batches`

Migrasi sumber: `2026_08_03_090928_create_payment_batches_table`.

Wadah RINGAN untuk satu sesi submit batch pembayaran kolektor — cuma untuk dedup + pengelompokan. **SENGAJA TANPA** `declared_total`/`recorded_total`/`variance`/status selisih — itu bagian dari fitur Setoran Kolektor (rekonsiliasi kas) yang di-**drop** dari scope (lihat §B-11 di `docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md`). Kalau fitur itu diaktifkan lagi nanti, tabel ini yang diperluas — jangan bikin tabel baru lagi.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `idempotency_key` | string, unique | | Digenerate klien sekali per sesi submit batch. Submit ulang dengan key sama = ditolak/diabaikan |
| `submitted_by` | FK → `users.id`, cascade delete | | Admin yang input batch |
| `collector_id` | FK → `users.id`, null on delete | ✔ | Kolektor asal batch (kalau ada) |
| `submitted_at` | timestamp | | |
| `created_at` / `updated_at` | timestamp | | |

## Tabel `payment_number_sequences`

Migrasi sumber: `2026_08_03_090930_create_payment_number_sequences_table`.

Pengganti MAX+1 di `Payment::generatePaymentNumber()` (dulu `orderBy('payment_number','desc')->lockForUpdate()->first()` — phantom read kalau periode masih kosong dan dua request pertama bulan itu jalan bersamaan). Pola sama `PopSequence`: kunci baris counter yang SELALU ada, baru increment. `current_number` unsigned tanpa batas atas — lebar digit format `PAY-{periode}-%0Nd` naik otomatis kalau lewat 9999.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `period_code` | string(6), unique | | Format `YYYYMM` |
| `current_number` | unsigned int, default 0 | | |
| `created_at` / `updated_at` | timestamp | | |

## Kolom kolektor di `customers`

Migrasi sumber: `2026_08_03_120001_add_collector_id_to_customers_table`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `collector_id` | FK → `users.id`, null on delete | ✔ | Rute permanen: pelanggan ini rutin ditagih kolektor siapa. Reassignable, TIDAK terkunci mati. Tiga guard di layer aplikasi (bukan di migration): (1) target wajib ber-role `kolektor`, (2) POP pelanggan wajib masuk scope kolektor, (3) kolektor yang masih memegang pelanggan tak boleh dinonaktifkan — `CollectorController` & `UserController::update()` |

## Model relations (ringkas)

```php
// Invoice
customer(): BelongsTo(Customer::class)
pop(): BelongsTo(Pop::class)
customerService(): BelongsTo(CustomerService::class)
internetPackage(): BelongsTo(InternetPackage::class)
creator(): BelongsTo(User::class, 'created_by')
payments(): HasMany(Payment::class)
recalculateFromPayments(): void  // hitung ulang paid_amount/remaining_amount/invoice_status dari SUM(payments.amount WHERE status=valid)

// Payment
invoice(): BelongsTo(Invoice::class)
paymentBatch(): BelongsTo(PaymentBatch::class)
customer(): BelongsTo(Customer::class)
pop(): BelongsTo(Pop::class)
receiver(): BelongsTo(User::class, 'received_by')
collector(): BelongsTo(User::class, 'collected_by')
rejecter(): BelongsTo(User::class, 'rejected_by')
auditLogs(): MorphMany(AuditLog::class, 'auditable')
installmentContext(): ?array  // ['number' => int, 'settles' => bool] — posisi "Cicilan Ke-N" payment ini di invoice-nya + apakah dia yang melunasi. null kalau payment bukan VALID atau tak ada invoice_id. Satu sumber kebenaran dipakai payments/show, payments/receipt, invoices/show

// Customer
collector(): BelongsTo(User::class, 'collector_id')

// User
assignedCustomers(): HasMany(Customer::class, 'collector_id')
```

## Audit Log

- `Invoice` — trait `RecordsAuditLogs`, module `Tagihan`, event `updated` & `deleted` saja (bukan `created` — invoice creation context sudah jelas dari jalur pembuatannya, lihat [flowchart.md](flowchart.md)).
- `Payment` — model event manual (`booted()` di `app/Models/Payment.php`), module `Pembayaran`, event `created`/`updated`/`deleted`. Action `update` otomatis jadi `cancel` kalau perubahan mengubah `payment_status` jadi `ditolak` — jalur resmi ini ditulis `PaymentController::reject()` (route `POST /payments/{payment}/reject`, permission `payments.reject`), yang membungkus perubahan status DAN `Invoice::recalculateFromPayments()` dalam satu `DB::transaction`.

## Traits Bersama

- `HasPopScope` — dipakai `Invoice` & `Payment`, nyediain `applyUserScope()` query scope buat filter data sesuai POP yang di-assign ke user (kecuali owner/full-access).
