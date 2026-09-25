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
- `payments.bank_account_id` → `bank_accounts.id`, `nullOnDelete` (ADHOC-95). Master rekening tak punya aksi hapus (cuma nonaktif); `payments.bank_name`/`account_number` = SNAPSHOT dari master saat dicatat, jadi riwayat tetap utuh walau rekening diedit. `payments.sender_name` (nullable, ≤150) = nama pengirim faktual, cuma diisi untuk Transfer/Kolektor.
- `bank_accounts` (GLOBAL, tanpa `pop_id`): `bank_name`, `account_number` (angka saja, unique per `bank_name`), `account_holder_name`, `label` nullable, `is_active`.
- `invoices.created_by`, `payments.received_by/collected_by/rejected_by`, `customers.collector_id` → `nullOnDelete` (user dihapus, riwayat tetap ada, kolom jadi null).
- `payment_batches.submitted_by` → cascade delete. `payment_batches.collector_id` → `nullOnDelete`.

## Tabel `invoices`

Migrasi sumber: `2026_06_12_132728_create`, `2026_06_15_000002_add_legacy_ids`, `2026_06_16_103000_add_extended_attributes`, `2026_06_17_150000_add_other_fee`, `2026_07_02_133000_add_invoice_type`, `2026_07_04_091633_remove_default_from_invoice_type`, `2026_09_23_085400_add_manual_invoice_columns` (ADHOC-70).

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `invoice_number` | string(50), unique | | Format `INV-{periode}-{urutan}` |
| `old_invoice_id` / `old_cost_id` / `old_request_id` | string(50) | ✔ | ID referensi data legacy (migrasi dari sistem lama) |
| `invoice_type` | string(30), **NOT NULL, no default** | | Enum `App\Enums\InvoiceType`: `awal`, `bulanan`, `reaktivasi`, `insidental`, `manual` (ADHOC-70, Tagihan Manual) — wajib diisi eksplisit tiap insert (default DB sengaja dihapus, lihat catatan di bawah) |
| `manual_category` | string(30) | ✔ | Enum `App\Enums\ManualInvoiceCategory`: `perbaikan`, `lainnya`, `pindah_lokasi` — cuma diisi untuk `invoice_type=manual` (ADHOC-70), NULL untuk jenis lain |
| `manual_subtype_name` | string(150) | ✔ | Nama sub bebas untuk `manual_category=lainnya` (mis. "Over Kabel") — NULL untuk kategori lain |
| `description` | text | ✔ | Deskripsi tagihan diketik manual — cuma dipakai `invoice_type=manual` |
| `customer_id` | FK → `customers.id`, cascade delete | | |
| `pop_id` | FK → `pops.id`, cascade delete | | |
| `customer_service_id` | FK → `customer_services.id`, cascade delete | | |
| `internet_package_id` | FK → `internet_packages.id`, cascade delete | | |
| `billing_period` | string(50) | | Format `Y-m`, e.g. `2026-07` |
| `issue_date` | date | | Tanggal terbit |
| `due_date` | date | | Tanggal jatuh tempo — **hanya label UI/formalitas** (bulanan: tanggal 10 periode). Bukan penentu terlambat/piutang; lihat catatan di bawah |
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
| `invoice_status` | string(50), default `belum_dibayar` | | `belum_dibayar`, `sebagian`, `lunas`, `batal`, `tak_tertagih` (hapus buku piutang, ADHOC-90) |
| `written_off_at` | timestamp | ✔ | Kapan piutang dihapus buku. Dasar kolom "Piutang tak Tertagih" laporan bulanan |
| `written_off_by` | FK → `users.id`, null on delete | ✔ | |
| `written_off_amount` | decimal(15,2) | ✔ | Snapshot `remaining_amount` saat dihapus buku (`remaining_amount` sendiri tidak diubah) |
| `write_off_reason` | string(500) | ✔ | Alasan wajib |
| `created_by` | FK → `users.id`, null on delete | ✔ | Null kalau dibuat via command (`billing:generate-monthly-invoices`) |
| `created_at` / `updated_at` | timestamp | | |

**Catatan piutang (2026-09-21):** `due_date` (tanggal 10) tidak dipakai menentukan "terlambat". Batas riil pembayaran = akhir bulan `billing_period`; kas admin ditutup selalu tanggal 1, jadi tagihan `belum_dibayar`/`sebagian` dengan `billing_period` < bulan berjalan = **piutang**. Satu-satunya definisi: `Invoice::scopePiutang()` / `Invoice::isPiutang()`. Jangan menulis `where('due_date', '<', now())` untuk menandai tunggakan. Aturan lengkap: `docs/BUSINESS_RULES.md` §7 poin 4.

**Catatan `invoice_type`:** kolom ini sempat punya default DB `'bulanan'` — dihapus lewat migrasi `remove_default_from_invoice_type_column` karena default diam-diam itu yang bikin invoice salah tag pas migrasi data legacy. Sekarang: `InvoiceObserver::creating()` juga menolak insert kalau `invoice_type` kosong (lapis aplikasi, redundant dengan constraint DB).

**Catatan unique index (2026-07-21):** migrasi `add_invoice_period_unique_index_to_invoices` SENGAJA kosong (no-op) — unique index `(customer_id, invoice_type, billing_period)` gagal dipasang karena invoice `batal` tetap menempati slot periode (MySQL tak punya partial index) dan memblokir tagihan pengganti yang sah. Anti-dobel invoice tetap murni layer aplikasi (`InvoiceObserver` + `CustomerController::storeManualInvoice`), dipantau `billing:audit-duplicate-invoices`. Jangan coba pasang index ini lagi tanpa membaca catatan lengkap di file migrasinya.

## Tabel `customer_termination_reasons` (ADHOC-69)

Master alasan Putus Langganan — dropdown di form putus, bisa difilter/sort di List Putus (`/customers/terminated`).

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | bigint PK | | |
| `name` | string(150), unique | | "Pindah", "Kompetitor", "Meninggal", dst — CRUD bebas via `/master/termination-reasons` |
| `default_penalty_amount` | decimal(12,2), default 0 | | Prefill/titik awal nominal denda untuk pelanggan masa langganan ≤1 tahun — TIDAK pernah dipakai otomatis, admin wajib konfirmasi/ubah di form |
| `is_active` | boolean, default true | | Nonaktif = hilang dari dropdown form putus baru, data pelanggan lama tetap utuh |
| `created_at` / `updated_at` | timestamp | | |

**Hapus permanen** (bukan soft-delete) diblok kalau masih dipakai ≥1 pelanggan — dicek di `Master\CustomerTerminationReasonController::destroy()` sebelum DELETE (pesan jelas), dijaga ulang di level DB oleh `customers.termination_reason_id` FK `restrictOnDelete()`.

**Kolom baru di `customers`** (migrasi `add_termination_fields_to_customers_table`):

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `termination_reason_id` | FK → `customer_termination_reasons.id`, `restrictOnDelete()` | ✔ | Sumber utama Alasan Putus di List Putus (menggantikan baca `AuditLog.new_values.reason` di memori). Data lama (putus sebelum fitur ini) dibiarkan NULL, tidak di-backfill |
| `termination_note` | text | ✔ | Catatan tambahan bebas, pelengkap alasan master |

**Denda Putus Langganan → invoice `manual`:** `CustomerTerminationService::terminate()` cuma menerbitkan invoice kalau masa langganan pelanggan (`customer_services.activation_date` s.d. tanggal submit) ≤1 tahun (inklusif; `activation_date` NULL diperlakukan ≤1 tahun) **dan** `penalty_amount` submit >0. Invoice-nya: `invoice_type=manual`, `manual_category=lainnya`, `manual_subtype_name='Denda Putus Langganan'` (konstanta `CustomerTerminationService::PENALTY_SUBTYPE_NAME`, bukan diketik ulang), `invoice_status=belum_dibayar`, **tanpa Payment**. Masa >1 tahun → `penalty_amount` klien diabaikan sepenuhnya di server (guard anti tamper), tidak ada invoice yang terbit sama sekali. **Tanpa prorate** — invoice Bulanan periode berjalan & tunggakan lama tidak disentuh. Rancangan lengkap: `docs/plan/billing/analisa-rancangan-putus-langganan.md`.

## Tabel `period_closings` (ADHOC-90)

Snapshot beku Laporan Bulanan Admin Collector. Tanpa baris = periode masih terbuka (laporan dihitung live).

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | bigint PK | | |
| `period` | char(7) | | `YYYY-MM` |
| `pop_id` | FK → `pops.id` | | POP pusat/cabang (mini-POP sudah dilipat ke induk) |
| `figures` | json | | 4 blok angka (`tagihan`, `piutang_lalu`, `pelanggan`, `uang_diterima`) |
| `closed_by` | FK → `users.id`, null on delete | ✔ | |
| `closed_at` | timestamp | | |
| `created_at` / `updated_at` | timestamp | | |

Unique `(period, pop_id)`. Buka ulang = hapus baris (alasan + angka yang dibuang tercatat di `audit_logs`).

## Tabel `payments`

Migrasi sumber: `2026_06_13_000001_create`, `2026_06_15_000002_add_legacy_ids`, `2026_07_04_091731_add_duplicate_guard_indexes` (dicabut lagi, lihat di bawah), `2026_08_03_090927_drop_invoice_date_amount_unique_index`, `2026_08_03_090929_add_payment_batch_id`, `2026_08_03_090931_add_reject_columns`, `2026_08_03_120002_add_collector_columns`, `2026_08_03_130001_change_status_default_to_valid`, `2026_08_03_140001_add_overpay_amount`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `payment_number` | string(50), unique | | Format `PAY-{periode}-{urutan}`, digenerate `Payment::generatePaymentNumber()` via `payment_number_sequences` (lihat di bawah) |
| `idempotency_key` | string(191), **unique** | ✔ | Penahan submit dobel jalur Tagihan (migrasi `2026_08_10_154725`). Nullable: seluruh pembayaran lama — hasil migrasi legacy maupun batch kolektor — tidak punya kunci ini dan tak boleh dipaksa punya; unique memperlakukan NULL sebagai berbeda satu sama lain sehingga baris lama tidak saling bertabrakan. Jalur batch kolektor tetap memakai `payment_batches.idempotency_key` (satu kunci per sesi submit, bukan per pembayaran) |
| `old_payment_id` / `old_transaction_id` / `old_request_id` | string(50) | ✔ | Referensi data legacy |
| `billing_period` | string(50) | ✔ | Legacy — periode bayar dari data lama |
| `received_by_old` / `deposited_by_old` | string(50) | ✔ | Nama penerima/penyetor dari data legacy (sebelum ada FK `users`) |
| `invoice_id` | FK → `invoices.id`, cascade delete | | |
| `payment_batch_id` | FK → `payment_batches.id`, null on delete | ✔ | Terisi HANYA untuk payment yang lahir dari batch kolektor. Jalur single-payment (bayar langsung dari halaman Tagihan) tetap null |
| `customer_id` | FK → `customers.id`, cascade delete | | |
| `pop_id` | FK → `pops.id`, cascade delete | | |
| `payment_date` | date | | Tanggal posting/validasi kantor |
| `collected_date` | date | ✔ | Tanggal uang diterima DI LAPANGAN — beda dari `payment_date`, mencegah pendapatan lintas-bulan salah potong saat kolektor telat setor |
| `payment_method` | string(50) | | `cash`, `transfer`, `kolektor`, `lainnya`, `saldo` (`PaymentMethod` enum). `qris` DIHAPUS (2026-09-22, tak pernah dipakai operasional) — jangan hidupkan lagi. `lainnya` wajib mengisi `note` (keterangan metode apa persisnya, mis. "OVO") — `PaymentMethod::requiresDescription()`, divalidasi `required_if:payment_method,lainnya`. `saldo` (ADHOC-92) BUKAN pilihan dropdown — dibuat sistem saat auto-pakai Saldo Pelanggan (`CustomerBalanceService::applyToOpenInvoices()`), satu payment per invoice |
| `amount` | decimal(12,2) | | Bagian yang DITERAPKAN ke tagihan — wajib > 0 (`PaymentObserver::creating()`), tak pernah melebihi `remaining_amount` invoice saat insert. **TOTAL uang diterima dari pelanggan boleh lebih besar** — sisanya otomatis dipisah ke `overpay_amount`, bukan masuk ke `amount` (§ lihat catatan lebih bayar di bawah) |
| `balance_used_amount` | decimal(12,2), default 0 | | ADHOC-92 (G4) — porsi `amount` yang dibayar dari Saldo Pelanggan, BUKAN uang fisik. Untuk method `saldo`, sama dengan `amount`. Dikeluarkan dari perhitungan kas fisik — tanpa ini, saldo yang dipakai terhitung sebagai uang yang harus disetor |

**Kas fisik satu payment = `Payment::physicalAmount()`** (`amount − balance_used_amount + overpay_amount`, ADHOC-92 G4 + koreksi 2026-09-24): dipakai `AdminCashBalanceService`, `CollectorBalanceService`, `CollectorDeposit::computedAmount()`, `CashDepositService` — SATU rumus, jangan hitung ulang `amount`/`overpay_amount` sendiri-sendiri di tempat baru. Overpay (`overpay_amount`) WAJIB ikut dihitung sebagai uang fisik: kolomnya terpisah dari `amount` tapi uangnya sama-sama tunai/transfer yang benar-benar diterima (mis. pelanggan bayar 3 bulan sekaligus lewat satu invoice BULANAN — sisa dua bulan tercatat `overpay_amount`, tetap uang di tangan admin/kolektor, tetap wajib disetor).
| `overpay_amount` | decimal(12,2) | ✔ | Kelebihan uang fisik yang diserahkan pelanggan di atas sisa tagihan. Sejak ADHOC-38 kelebihan ini **dicatat juga sebagai credit** di ledger `customer_balance_mutations` (saldo pelanggan diturunkan dari SUM(credit) − SUM(debit), tanpa kolom `customers.balance`); kolom ini tetap sebagai jejak per payment. Auto-pakai ke tagihan bulanan **belum ada** (rancangan ADHOC-92: kolom `payments.balance_used_amount`, `PaymentMethod::SALDO`, `customer_balance_mutations.source` — belum dimigrasi). `PaymentController::store()` yang menghitung otomatis (`total_received - min(total_received, remaining)`), admin tak perlu hitung manual |
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

## Tabel `customer_balance_mutations` (ADHOC-38, diperluas ADHOC-92)

Ledger append-only Saldo Pelanggan — ditegakkan `CustomerBalanceMutationObserver` (blok `update`/`delete` lewat jalur Eloquent). Saldo pelanggan **DITURUNKAN**, bukan disimpan (`SUM(credit) − SUM(debit)`), lihat `CustomerBalanceService::balance()`.

| Kolom | Tipe | Nullable | Keterangan |
|---|---|---|---|
| `id` | bigint PK | | |
| `customer_id` | FK → `customers.id`, cascade delete | | |
| `type` | string(20) | | `credit`/`debit` (`CustomerBalanceMutationType`). `amount` selalu positif, arah ditentukan kolom ini |
| `source` | string(30) | ✔ | ADHOC-92 (G2) — `BalanceMutationSource`: `bayar_di_muka` (overpay invoice AWAL), `kelebihan_bayar` (overpay lainnya), `pakai_otomatis` (auto-pay ADHOC-92), `pakai_manual` (`use_balance_amount`), `pembatalan` (reversal payment ditolak), `backfill` (migrasi data lama) |
| `amount` | decimal(12,2) | | Selalu positif |
| `payment_id` | FK → `payments.id`, null on delete | ✔ | Peran ganda tergantung `type`: kredit → payment SUMBER overpay; debit → payment yang MEMAKAI saldo. Null untuk kredit non-payment (`creditWithoutPayment()`, dipakai ADHOC-68 downgrade paket) |
| `pop_id` | FK → `pops.id`, cascade delete | | |
| `created_by` | FK → `users.id`, null on delete | ✔ | Null untuk baris yang dibuat sistem (auto-pay, tanpa user login) |
| `note` | text | ✔ | |
| `created_at` / `updated_at` | timestamp | | |

Unique `(payment_id, type, source)` — bukan `(payment_id, type)`: satu payment bisa punya dua baris ber-`type` sama (mis. kredit overpay ASLI + kredit REFUND saldo yang dibalik saat payment itu ditolak), `source` yang membedakannya.

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
