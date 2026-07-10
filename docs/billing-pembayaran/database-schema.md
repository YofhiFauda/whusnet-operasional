# Database Schema — Modul Billing & Pembayaran

## Entity Relationship

```
customers ──┐
            │
customer_services ──┐
            │        │
internet_packages ───┼──▶ invoices ──1:N──▶ payments ◀── users (received_by)
            │        │        ▲
pops ───────┴────────┘        │
            │                 │
users (created_by) ───────────┘
```

- `invoices.customer_id/pop_id/customer_service_id/internet_package_id` → cascade delete dari parent (hapus customer/POP/service/package ikut hapus invoice terkait).
- `payments.invoice_id/customer_id/pop_id` → cascade delete dari parent.
- `invoices.created_by`, `payments.received_by` → `nullOnDelete` (user dihapus, riwayat tetap ada, kolom jadi null).

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
| `paid_amount` | decimal(12,2), default 0 | | Akumulasi dari `payments.amount` |
| `remaining_amount` | decimal(12,2) | | `total_amount - paid_amount`, floor 0 |
| `invoice_status` | string(50), default `belum_dibayar` | | `belum_dibayar`, `sebagian`, `lunas`, `batal` |
| `created_by` | FK → `users.id`, null on delete | ✔ | Null kalau dibuat via command (`billing:generate-monthly-invoices`) |
| `created_at` / `updated_at` | timestamp | | |

**Catatan `invoice_type`:** kolom ini sempat punya default DB `'bulanan'` — dihapus lewat migrasi `remove_default_from_invoice_type_column` karena default diam-diam itu yang bikin invoice salah tag pas migrasi data legacy. Sekarang: `InvoiceObserver::creating()` juga menolak insert kalau `invoice_type` kosong (lapis aplikasi, redundant dengan constraint DB).

## Tabel `payments`

Migrasi sumber: `2026_06_13_000001_create`, `2026_06_15_000002_add_legacy_ids`, `2026_07_04_091731_add_duplicate_guard_indexes`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `payment_number` | string(50), unique | | Format `PAY-{periode}-{urutan}` |
| `old_payment_id` / `old_transaction_id` / `old_request_id` | string(50) | ✔ | Referensi data legacy |
| `billing_period` | string(50) | ✔ | Legacy — periode bayar dari data lama |
| `received_by_old` / `deposited_by_old` | string(50) | ✔ | Nama penerima/penyetor dari data legacy (sebelum ada FK `users`) |
| `invoice_id` | FK → `invoices.id`, cascade delete | | |
| `customer_id` | FK → `customers.id`, cascade delete | | |
| `pop_id` | FK → `pops.id`, cascade delete | | |
| `payment_date` | date | | |
| `payment_method` | string(50) | | `cash`, `transfer`, `qris`, `lainnya` |
| `amount` | decimal(12,2) | | Wajib > 0 (`PaymentObserver::creating()`) |
| `received_by` | FK → `users.id`, null on delete | ✔ | |
| `proof_file` | string | ✔ | Path bukti transfer/foto |
| `payment_status` | string(50), default `pending` | | `pending`, `valid`, `ditolak` |
| `note` | text | ✔ | |
| `created_at` / `updated_at` | timestamp | | |

**Unique index `payments_invoice_date_amount_unique`** (`invoice_id`, `payment_date`, `amount`) — guard anti-duplikat DB-level, dari insiden migrasi data lama (retry submit bikin payment dobel persis).

## Model relations (ringkas)

```php
// Invoice
customer(): BelongsTo(Customer::class)
pop(): BelongsTo(Pop::class)
customerService(): BelongsTo(CustomerService::class)
internetPackage(): BelongsTo(InternetPackage::class)
creator(): BelongsTo(User::class, 'created_by')
payments(): HasMany(Payment::class)

// Payment
invoice(): BelongsTo(Invoice::class)
customer(): BelongsTo(Customer::class)
pop(): BelongsTo(Pop::class)
receiver(): BelongsTo(User::class, 'received_by')
auditLogs(): MorphMany(AuditLog::class, 'auditable')
```

## Audit Log

- `Invoice` — trait `RecordsAuditLogs`, module `Tagihan`, event `updated` & `deleted` saja (bukan `created` — invoice creation context sudah jelas dari jalur pembuatannya, lihat [flowchart.md](flowchart.md)).
- `Payment` — model event manual (`booted()` di `app/Models/Payment.php`), module `Pembayaran`, event `created`/`updated`/`deleted`. Action `update` otomatis jadi `cancel` kalau perubahan mengubah `payment_status` jadi `ditolak`.

## Traits Bersama

- `HasPopScope` — dipakai `Invoice` & `Payment`, nyediain `applyUserScope()` query scope buat filter data sesuai POP yang di-assign ke user (kecuali owner/full-access).
