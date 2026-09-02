# Metode Pembayaran + Saldo Pelanggan pada Modal Bayar Invoice

## Context

Modal Bayar Invoice (`quick-payment-modal.blade.php`) saat ini cuma punya dropdown metode generik (`cash/transfer/qris/lainnya`) tanpa field pendukung, dan tidak ada konsep saldo pelanggan. User minta:

1. Cash → masuk Saldo Admin
2. Transfer → muncul field Nama Bank + No. Rekening
3. Kolektor → muncul dropdown pilih kolektor, uangnya masuk Saldo Kolektor
4. Pelanggan lebih bayar → dapat Saldo Pelanggan yang bisa dipakai di pembayaran berikutnya
5. Ringkasan Tagihan tambah baris Metode Bayar + Saldo Pelanggan
6. Setelah bayar → animasi sukses → dialog dengan tombol Cetak Struk

**Konflik dokumen yang di-override secara eksplisit oleh user:** `docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md` §D-5/§E2.6 dulu memutuskan saldo/kredit pelanggan DILUAR SCOPE. User sudah dikonfirmasi (AskUserQuestion) ingin fitur itu dihidupkan beneran — bukan cuma catatan info seperti `overpay_amount` sekarang. Dokumen itu perlu ditandai "di-override 2026-08-18", bukan dihapus (jaga jejak keputusan).

Keputusan teknis lain yang sudah dikonfirmasi user:
- Tambah `kolektor` ke daftar `payment_method` (bukan field terpisah). Saldo kolektor tetap **derived** lewat `CollectorBalanceService` yang sudah ada — cukup isi `collected_by`.
- Tambah kolom `bank_name`, `account_number` ke tabel `payments` (bukan cuma masuk `note`).

**Verifikasi:** Cash → Saldo Admin sudah otomatis benar tanpa perubahan — `AdminCashBalanceService::unsettledManualPaymentsQuery()` (app/Services/AdminCashBalanceService.php:71-80) sudah filter `payment_method='cash' AND collected_by IS NULL`. Selama payment metode kolektor mengisi `collected_by`, dan metode lain membiarkannya null, angka Saldo Admin & Saldo Kolektor otomatis benar tanpa sentuh service itu.

## Pola yang wajib diikuti (dari codebase, bukan asumsi)

Proyek ini SENGAJA menghindari kolom saldo yang di-`increment()`/`decrement()` langsung — `CollectorBalanceService` dan `AdminCashBalanceService` sama-sama derived query dari tabel transaksi, dengan alasan eksplisit di komentar: *"Kolom seperti itu berhenti benar begitu satu payment di-reject, dan angka uang yang bohong tak punya alarm."* Saldo Pelanggan mengikuti pola sama: **ledger append-only**, bukan kolom `customers.balance` yang di-mutate.

## Perubahan

### 1. Enum baru
- `app/Enums/PaymentMethod.php` — `CASH|TRANSFER|QRIS|KOLEKTOR|LAINNYA`, method `label()`, `requiresBankDetails()` (transfer), `requiresCollector()` (kolektor). Ganti literal array `['cash','transfer','qris','lainnya']` di `PaymentController::index()` (~baris 42) dan validasi `store()` (~baris 292) supaya pakai enum ini — cukup nambah nilai, tidak mengubah nilai lama.
- `app/Enums/CustomerBalanceMutationType.php` — `CREDIT|DEBIT`.

### 2. Migration
- `add_bank_details_to_payments_table.php` — tambah `bank_name` (string 100, nullable), `account_number` (string 50, nullable) ke `payments`. `collected_by`/`collected_date` sudah ada, tinggal dipakai untuk jalur Kolektor.
- `create_customer_balance_mutations_table.php`:
  ```
  id, customer_id (FK cascade), type (credit|debit),
  amount (decimal 12,2, selalu positif — arah dari `type`),
  payment_id (FK payments, nullable, null-on-delete),
  pop_id (FK pops), created_by (FK users, nullable), note, timestamps
  index(customer_id, created_at)
  ```
  `payment_id` dobel peran: kredit → payment sumber overpay; debit → payment yang memakai saldo. Nullable untuk baris backfill manual (§6) yang tak berasal dari satu payment.

Tambah `bank_name`, `account_number` ke `Payment::$fillable` & payload audit log di `app/Models/Payment.php`.

### 3. Model baru
- `app/Models/CustomerBalanceMutation.php` — relasi `customer()`, `payment()`, `creator()`.
- `Customer.php` — tambah relasi `balanceMutations(): HasMany`.

### 4. Service baru
- `app/Services/CustomerBalanceService.php` (pola dokumentasi & derived-query sama seperti `CollectorBalanceService`):
  - `balance(Customer $customer): float` — `SUM(credit) - SUM(debit)` via `Money`.
  - `credit(Customer $customer, float $amount, Payment $sourcePayment, ?string $note)` — dipanggil saat overpay terjadi.
  - `debit(Customer $customer, float $amount, Payment $consumingPayment, ?string $note)` — dipanggil saat saldo dipakai bayar. **Wajib** `lockForUpdate()` pada agregasi balance di dalam transaction yang sama dengan lock invoice, supaya dua pembayaran simultan tidak sama-sama lolos memakai saldo yang sama. Lempar exception kalau `amount > balance`.
  - `reverseCreditForPayment(Payment $payment)` — dipanggil saat payment sumber overpay di-reject, supaya kredit yang sudah tergenerate ikut batal. Idempotent.

- `app/Services/PaymentService.php` — extract logic dari `PaymentController::recordPayment()` (private method saat ini, app/Http/Controllers/PaymentController.php:408-454) supaya business logic pindah dari Controller ke Service sesuai aturan proyek. Method `record(Invoice $invoice, array $validated, ?string $proofPath): Payment`, isinya `DB::transaction` yang sudah ada (lock invoice) ditambah:
  1. Kalau `use_balance_amount > 0`: cek saldo cukup (lock), catat debit **setelah** `Payment` tercipta (butuh `payment_id`).
  2. `amount` payment tetap berarti TOTAL yang menutup tagihan (tunai/transfer/kolektor + saldo digabung) — konsisten dengan makna kolom sekarang; saldo yang dipakai tercatat terpisah di ledger, bukan mengubah arti `amount`.
  3. Validasi kondisional per metode: isi `bank_name`/`account_number` hanya kalau `transfer`, isi `collected_by` hanya kalau `kolektor`.
  4. Overpay baru (`overpayAmount > 0`) → `CustomerBalanceService::credit()`.
  `PaymentController::store()` delegasi ke `app(PaymentService::class)->record(...)`, tetap pegang validasi HTTP + response JSON (pola existing, JSON bukan PRG — modal ini submit via fetch, ikuti konvensi yang sudah ada, tidak diubah).

Validasi tambahan di `PaymentController::store()`:
```php
'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
'bank_name' => ['required_if:payment_method,transfer', 'nullable', 'string', 'max:100'],
'account_number' => ['required_if:payment_method,transfer', 'nullable', 'string', 'max:50'],
'collected_by' => ['required_if:payment_method,kolektor', 'nullable', 'exists:users,id'],
'use_balance_amount' => ['nullable', 'numeric', 'min:0'],
```
Tambah validasi: `collected_by` yang dipilih wajib `hasRole('kolektor')` (abort kalau bukan). `use_balance_amount` dicek ulang di service dengan lock (request-level cuma UX cepat).

Response JSON `store()` tambah `'payment' => ['id' => $payment->id, ...]` (dibutuhkan tombol Cetak Struk di dialog sukses — saat ini cuma `payment_number`+`amount`).

### 5. Endpoint data untuk modal
`InvoiceController::show()` JSON payload (yang sudah dipanggil modal untuk data invoice) tambah:
- `customer_balance` — hasil `CustomerBalanceService::balance($invoice->customer)`.
- `available_collectors` — daftar user role kolektor yang relevan dengan POP invoice, `[{id, name}]`. **Cek dulu** relasi kolektor↔POP yang benar di `User.php` (kemungkinan `users.pops` many-to-many, bukan kolom `pop_id` langsung) sebelum menulis query final.

### 6. View `resources/views/payments/partials/quick-payment-modal.blade.php`
- Dropdown metode tambah opsi `kolektor`.
- Field kondisional baru (ikuti pola JS vanilla `classList`/`hidden` yang sudah dipakai file ini, bukan paksa Alpine baru): blok Nama Bank + No. Rekening (tampil saat `transfer`), blok dropdown Kolektor terisi dari `available_collectors` (tampil saat `kolektor`). Toggle `required` mengikuti visibilitas supaya HTML5 validation tidak memblokir field tersembunyi.
- Saldo Pelanggan: tampilkan `Saldo Pelanggan: Rp X` dari `customer_balance`, checkbox "Pakai saldo pelanggan" + input nominal (dibatasi `min(saldo, sisa_tagihan)`), kirim sebagai `use_balance_amount` terpisah dari `amount`.
- Ringkasan Tagihan: tambah baris Metode Bayar (live-update dari dropdown) dan Saldo Pelanggan.
- Payload `FormData` tambah `bank_name`, `account_number`, `collected_by`, `use_balance_amount` (hanya field relevan sesuai metode/checkbox).
- Dialog sukses (blok `window.Dialog.show()` saat ini di ~baris 534-550) tambah tombol "Cetak Struk" yang buka `/payments/{id}/kwitansi` (route `payments.receipt` sudah ada, `ReceiptPresenter` sudah handle `overpay_amount` — tidak perlu diubah) di tab baru, di samping tombol "Tutup" existing. Transisi buka/tutup Dialog yang sudah ada (`scale`/`opacity`) sudah menghasilkan efek "animasi sukses" — tidak perlu animasi tambahan.
- Reset function (`openQuickPaymentModal`) di-update: reset field baru, populate saldo pelanggan + daftar kolektor dari fetch.

### 7. Backfill data lama (mekanisme saja, TIDAK dijalankan sekarang)
- Artisan command baru `app/Console/Commands/BackfillCustomerBalanceFromOverpay.php` dengan flag `--dry-run`. Query `Payment` dengan `overpay_amount > 0`, `payment_status=valid`, belum ada mutasi ledger terkait → buat `CustomerBalanceMutation` type `credit`.
- **Tidak dijalankan terhadap data produksi dalam task ini** — trade-off (overpay lama mungkin sudah "dianggap selesai" manual di luar sistem, backfill buta bisa bikin saldo phantom) perlu direview manual oleh user/finance dulu.

### 8. Dokumentasi
`docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md` — tandai §D-5/§E2.6 di-override 2026-08-18 atas permintaan eksplisit user, jangan hapus histori lama.

## File dibuat
1. `app/Enums/PaymentMethod.php`
2. `app/Enums/CustomerBalanceMutationType.php`
3. `database/migrations/2026_08_18_XXXXXX_add_bank_details_to_payments_table.php`
4. `database/migrations/2026_08_18_XXXXXX_create_customer_balance_mutations_table.php`
5. `app/Models/CustomerBalanceMutation.php`
6. `app/Services/CustomerBalanceService.php`
7. `app/Services/PaymentService.php`
8. `app/Console/Commands/BackfillCustomerBalanceFromOverpay.php`
9. Test: `tests/Feature/PaymentMethodKolektorTest.php`, `tests/Feature/PaymentMethodTransferBankFieldsTest.php`, `tests/Feature/CustomerBalanceCreditOnOverpayTest.php`, `tests/Feature/CustomerBalanceDebitOnPaymentTest.php`, `tests/Unit/CustomerBalanceServiceTest.php`

## File diubah
1. `app/Models/Payment.php` — fillable + audit payload untuk `bank_name`/`account_number`.
2. `app/Models/Customer.php` — relasi `balanceMutations()`.
3. `app/Http/Controllers/PaymentController.php` — `store()` delegasi ke `PaymentService`, validasi baru, response JSON tambah `payment.id`; `index()` pakai enum.
4. `app/Http/Controllers/InvoiceController.php` — `show()` JSON tambah `customer_balance` + `available_collectors`.
5. `resources/views/payments/partials/quick-payment-modal.blade.php` — semua perubahan §6.
6. `docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md` — catatan override.

**Di luar scope (follow-up, bukan bagian task ini):** `resources/views/customers/partials/_quick_hub_modal.blade.php` (form "Input Pembayaran Instan" terpisah, submit ke controller sama tapi UI-nya sendiri) dan `resources/views/payments/create.blade.php` (form non-modal) — backend berlaku otomatis untuk keduanya lewat validasi bersama, tapi UI-nya tidak menampilkan opsi baru kecuali diupdate menyusul.

## Urutan pengerjaan
1. Enum + migration → `migrate` lokal.
2. Model + Service (`CustomerBalanceService`, `PaymentService`) + unit test service (logic murni, cepat diverifikasi).
3. Controller (`PaymentController::store`, `InvoiceController::show`) + feature test.
4. View modal (paling akhir, tergantung payload backend stabil).
5. `npm run build`.
6. `vendor/bin/pint`.
7. Command backfill dibuat tapi TIDAK dieksekusi ke data produksi.

## Test
| File | Skenario |
|---|---|
| `PaymentMethodKolektorTest` | `payment_method=kolektor`+`collected_by` valid → tersimpan, `CollectorBalanceService::balance()` naik; `collected_by` bukan role kolektor → ditolak. |
| `PaymentMethodTransferBankFieldsTest` | Transfer tanpa `bank_name` → 422; lengkap → tersimpan + masuk audit log. |
| `CustomerBalanceCreditOnOverpayTest` | Bayar melebihi sisa tagihan → `overpay_amount` + mutasi credit tercipta, `balance()` naik; payment di-reject → mutasi ikut dibalik. |
| `CustomerBalanceDebitOnPaymentTest` | Bayar pakai `use_balance_amount` sebagian → saldo berkurang, invoice `recalculateFromPayments()` benar; `use_balance_amount` > saldo → ditolak, tidak ada payment tercipta (rollback bersih). |
| `CustomerBalanceServiceTest` (Unit) | `balance()` = SUM(credit)-SUM(debit); `credit()`/`debit()` tulis ledger benar; `reverseCreditForPayment()` idempotent. |
| Regresi wajib lolos tanpa modifikasi assersi | Guard `PaymentObserver` nominal ≤0, `PaymentBurstDuplicateSubmitTest`, `CollectorRoleCannotCreatePaymentsTest`, `CollectorSelfPaymentTest`, `PaymentReceiptTest` — pastikan refactor `PaymentService` tidak mengubah perilaku observed. |
| POP scope | `customer_balance_mutations.pop_id` konsisten dgn invoice/payment POP, query saldo tidak bocor lintas POP. |

## Verifikasi
1. `php artisan migrate:fresh --seed` lokal — migration baru jalan bersih.
2. `php artisan test --filter=Payment` dan `--filter=CustomerBalance` — hijau semua termasuk regresi di atas.
3. `vendor/bin/pint`.
4. `npm run build`.
5. Manual: bayar Transfer (field bank wajib), bayar Kolektor (cek `collected_by` + saldo kolektor naik di halaman kas kolektor), bayar pakai saldo pelanggan sebagian (saldo berkurang, invoice update benar), overpay (dialog sukses + tombol Cetak Struk → buka struk benar), refresh → saldo pelanggan baru muncul di modal invoice berikutnya.
