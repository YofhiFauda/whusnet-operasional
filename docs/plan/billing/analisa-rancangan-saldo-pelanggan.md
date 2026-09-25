# Analisa & Rancangan: Saldo Pelanggan — Bayar di Muka + Auto-Pakai ke Tagihan Bulanan

**Status:** ✅ **Selesai diimplementasi 2026-09-24.** Analisa selesai 2026-09-21. Dicatat sebagai ADHOC-92 di `docs/TASKS.md`.

**Sumber ide awal:** permintaan user 2026-09-21 (studi kasus pelanggan paket 150k, prorate 100k, bayar aktivasi + 3 bulan di muka = 550k sekali bayar di admin). Dokumen ini = gap analysis terhadap kode nyata + rancangan implementasi + keputusan hasil diskusi.

**Terkait:**
- [`analisa-skema-alokasi-pembayaran-dan-saldo.md`](analisa-skema-alokasi-pembayaran-dan-saldo.md) (ADHOC-84) — modal konfirmasi overpay & peringatan piutang lama. §7 dokumen itu menyebut "auto-pakai saldo di generator" sebagai di luar scope; dokumen ini yang mengisinya.
- `upgrade-downgrade/analisa-upgrade-downgrade-paket.md` §2.3 (ADHOC-68) — butuh jalur `credit()` tanpa `Payment`; belum dirancang di sini (lihat §5).
- ADHOC-38 (`docs/TASKS.md`) — ledger saldo yang sudah ada.

> ADHOC-90 (hapus buku piutang `tak_tertagih`) sudah di-commit sebelum implementasi dokumen ini dimulai — tidak ada konflik.

---

## 1. Ringkasan Putusan

Kasus user: paket 150k, prorate aktivasi 100k, pelanggan bayar sekaligus 550k.

- Tagihan AWAL (prorate) tetap **100k**.
- Sisa **450k** masuk **Saldo Pelanggan**.
- Saldo **otomatis** dipakai membayar tagihan BULANAN begitu terbit. Admin tidak input manual tiap bulan, tidak lupa.
- Saldo < tagihan (mis. 50k vs 150k): tetap dipakai, tagihan jadi `sebagian`, tercatat **cicilan**.

**Ini gap-fill, bukan bangun dari nol.** Separuh fitur sudah ada sejak ADHOC-38 (commit `fcac4c1`): ledger saldo, overpay otomatis jadi credit, pemakaian saldo manual. Yang belum ada adalah **auto-pakai** saat tagihan bulanan terbit, plus perapian di sekelilingnya (sumber saldo, method SALDO, laporan kas, reject, RBAC, UI). Detail di §2.

---

## 2. Gap Terhadap Kode Nyata

### 2.1 Kondisi kode saat ini

| Sudah ada | Rujukan |
|---|---|
| Ledger `customer_balance_mutations` (append-only secara konvensi): kolom `type` credit/debit, `amount` selalu positif, `payment_id` nullable, `pop_id`, `created_by`, `note` | migration `2026_08_18_100002` |
| Saldo **diturunkan**, bukan disimpan: SUM(credit) − SUM(debit). Tidak ada `customers.balance` | `CustomerBalanceService.php:30` |
| `credit()`, `debit()` (dengan `lockedBalance()` + `lockForUpdate`), `reverseCreditForPayment()` (idempoten, sengaja boleh bikin saldo negatif) | `CustomerBalanceService.php:74,99,59,135` |
| Overpay otomatis jadi credit. **Input 550k di AWAL 100k sudah menghasilkan credit 450k** | `PaymentService.php:86-101,116-126` |
| Pakai saldo manual lewat `use_balance_amount` | `PaymentService.php:63-79` |
| Baca saldo: portal `GET /me/balance`, `customer_balance` di invoice show, quick-hub pelanggan, form bayar | `PortalBalanceController`, `InvoiceController.php:168`, `CustomerController.php:1518` |
| Cicilan = beberapa payment; "Cicilan Ke-N" dihitung `Payment::installmentContext()` | `Payment.php:226` |
| Guard dobel: `payments.idempotency_key` unique, `PaymentObserver::rejectBurstDuplicate` (300 dtk), `InvoiceObserver` 1 tagihan langganan/periode | `PaymentObserver.php:44`, `InvoiceObserver.php:64-86` |
| Generator BULANAN terjadwal tgl 1 pukul 01:00, `issue_date` tgl 1, `due_date` tgl 10 | `routes/console.php:18`, `GenerateMonthlyInvoicesCommand.php:70-156` |

### 2.2 Yang belum ada (gap)

| # | Gap | Rujukan |
|---|---|---|
| G1 | **Generator tidak menyentuh saldo sama sekali.** Saldo hanya terpakai kalau admin/kolektor ingat mencentang `use_balance_amount` saat bayar. Ini inti permintaan user | `GenerateMonthlyInvoicesCommand.php:117-162` |
| G2 | Tidak ada penanda **sumber** credit. Saldo hasil bayar di muka bercampur dengan kelebihan bayar biasa | `customer_balance_mutations` tanpa kolom `source` |
| G3 | Tidak ada `PaymentMethod::SALDO`. Pemakaian saldo hanya "companion field" di payment berbagai method | `app/Enums/PaymentMethod.php:18-22` |
| G4 | `payments.amount` **sudah mencakup bagian yang dibayar dari saldo** (`PaymentService.php:86-101`). Laporan kas menjumlah `amount` per method → saldo terhitung sebagai uang fisik yang harus disetor | `AdminCashBalanceService.php:78,96`, `CollectorBalanceService.php:55` |
| G5 | **Reject payment yang memakai saldo tidak mengembalikan saldo** — `reject()` hanya memanggil `reverseCreditForPayment` (sisi credit) | `PaymentController.php:516` |
| G6 | `amount` divalidasi `min:1` → pembayaran saldo-murni lewat form mustahil (tidak relevan bagi auto-pay, tapi perlu diketahui) | `PaymentController.php:354` |
| G7 | Tidak ada halaman/kartu riwayat Saldo di Detail Pelanggan; tooltip badge "Lebih Bayar" masih bilang *"bukan saldo, tidak otomatis dipakai"* — basi dan akan bertentangan dengan auto-pay | `customers/show.blade.php:60-67` |
| G8 | `CustomerBalanceMutation` tidak memakai `HasPopScope` dan tidak ada observer append-only (beda dari `InventoryTransactionObserver`) | `AppServiceProvider.php:123` (pola pembanding) |
| G9 | Tidak ada RBAC feature `customer_balance` | `config/rbac.php`, `FeatureSeeder.php` |
| G10 | Tidak ada unique DB pada ledger `(payment_id, type)` → idempotensi hanya di kode | migration ledger |
| G11 | **Dokumentasi basi** yang bilang lebih bayar "bukan saldo kredit": `docs/billing-pembayaran/README.md:19`, `database-schema.md:103`, `user-flow.md:22-29`. Bertentangan dengan kode sejak ADHOC-38 | (dikoreksi bersama dokumen ini, lihat §7) |
| G12 | `credit()` mewajibkan `Payment $sourcePayment` → tidak ada jalur credit tanpa payment (dibutuhkan ADHOC-68) | `CustomerBalanceService.php:74` |

---

## 3. Keputusan

> **KEPUTUSAN 2026-09-21 (user) — waktu auto-pay:** saldo dipakai **begitu tagihan bulanan terbit**. Contoh user: periode September, tanggal 1 tagihan muncul → langsung bisa dibayar dari saldo. Jadi hook di `GenerateMonthlyInvoicesCommand`, dalam transaksi yang sama dengan pembuatan invoice (bukan job terpisah, bukan lazy saat dibuka).

> **KEPUTUSAN 2026-09-21 (user) — bentuk catatan:** `Payment` baru dengan `PaymentMethod::SALDO`, satu payment per tagihan. Pemakaian sebagian tampil sebagai "Cicilan Ke-N" lewat `installmentContext()` yang sudah ada. Alternatif "tanpa Payment, hanya ledger" ditolak: terlalu invasif ke `recalculateFromPayments()` dan riwayat cicilan.

> **KEPUTUSAN 2026-09-21 (user) — input awal:** admin mengisi **satu nominal** (550k) di tagihan AWAL. Sistem menutup 100k, kelebihan 450k otomatis jadi credit dengan sumber `bayar_di_muka` (mekanisme overpay yang sudah ada). Ada modal konfirmasi sebelum submit. Alternatif field "Titip Saldo" terpisah ditolak.

> **KEPUTUSAN 2026-09-21 (user) — saldo kurang:** tetap dipakai semua → tagihan `sebagian`, sisa ditagih normal, tercatat cicilan. Tidak menunggu saldo cukup.

> **KEPUTUSAN 2026-09-21 (user) — urutan:** **FIFO, tagihan terlama dulu.** Bila pelanggan punya BULANAN lama yang belum lunas, saldo dipakai ke `billing_period` terlama, lalu yang lebih baru. Dikecualikan: `lunas`, `batal`, `tak_tertagih`.

> **KEPUTUSAN 2026-09-21 (user) — kolom `payments.balance_used_amount`:** disetujui (§4.1).

### 3.1 Asumsi rancangan (bukan keputusan user; ubah bila salah)

1. **Hanya tagihan `BULANAN`** yang di-auto-pay. `AWAL`, `REAKTIVASI`, `INSIDENTAL` tetap manual.
2. **Saldo ≤ 0 dilewati.** `PaymentObserver::creating` menolak nominal ≤ 0, jadi memang tidak boleh dibuat payment.
3. **Kasus pinggiran di luar studi kasus user:** saldo yang masuk *setelah* sebuah tagihan bulanan sudah terbit (mis. pelanggan titip saldo di tengah bulan padahal tagihan bulan itu belum lunas). Pada studi kasus utama ini **tidak terjadi**: saldo dari pembayaran aktivasi selalu masuk sebelum tagihan bulanan pertama, karena generator melewati bulan aktivasi (`GenerateMonthlyInvoicesCommand.php:80`) — tagihan bulanan pertama terbit tanggal 1 bulan berikutnya. Untuk kasus pinggiran ini disediakan command manual `billing:apply-balance` (§4.3); tidak dibuat trigger otomatis supaya tidak mengubah tagihan yang sedang ditagih kolektor. Bila nanti ingin otomatis penuh, cukup memanggil `applyToOpenInvoices()` setelah credit masuk — tidak butuh perubahan skema.

---

## 4. Rancangan Implementasi

### 4.1 Skema DB (hanya kolom tambahan — tanpa tabel baru)

| Perubahan | Alasan |
|---|---|
| `customer_balance_mutations.source` string nullable, enum baru `BalanceMutationSource`: `bayar_di_muka`, `kelebihan_bayar`, `pakai_otomatis`, `pakai_manual`, `pembatalan`, `backfill` | G2. Baris lama di-backfill dari konteks (`payment_id` + `type`) |
| Unique `(payment_id, type, source)` di ledger | G10. Perlu `source` karena `reverseCreditForPayment` menulis debit dengan `payment_id` yang sama dengan credit asalnya — tanpa `source`, unique `(payment_id, type)` bertabrakan |
| `payments.balance_used_amount` decimal(12,2) default 0 | G4. Definisi **kas fisik** = `amount − balance_used_amount + overpay_amount`. Backfill dari debit ledger ber-`payment_id`. Untuk method SALDO, `balance_used_amount = amount` |
| `PaymentMethod::SALDO` | G3. Kolom method sudah string(50), bukan perubahan skema |

Tidak ada `customers.balance` — saldo tetap diturunkan dari ledger. Tidak ada tabel baru (aturan repo: kolom cukup → jangan tabel).

> **Alternatif `balance_used_amount`:** tanpa kolom, kas fisik dihitung dari join ledger tiap laporan. Ditolak karena laporan kas sudah memakai SUM langsung pada `payments`. Kolom disetujui user 2026-09-21.

### 4.2 UI

- **Form bayar tagihan AWAL** (`payments/create` + `payments/partials/quick-payment-modal.blade.php`): bila nominal > sisa tagihan, tampil modal konfirmasi `<x-ui.modal>` (bukan `confirm()`): *"Rp450.000 akan masuk Saldo Pelanggan → cukup ±3× tagihan bulanan (Rp150.000)."* Sumber otomatis `bayar_di_muka` bila invoice AWAL, selain itu `kelebihan_bayar`.
- **Detail Pelanggan** (`customers/show`): kartu/tab **Saldo** — saldo sekarang, perkiraan "cukup N bulan" (saldo ÷ `total_monthly_bill`), riwayat ledger (tanggal, sumber, +/−, invoice/payment terkait, oleh). Perbaiki tooltip basi "Lebih Bayar" (`:60-67`).
- **Detail Tagihan** (`invoices/show`): payment method SALDO diberi label "Dibayar dari Saldo" — bukan uang diterima.
- **Kwitansi AWAL:** tampilkan "Titip saldo: Rp450.000" + perkiraan bulan tercakup.
- Target aksi POST dirender server-side (`route()`), redirect pola PRG ke `*.show` (`docs/PRG_REDIRECT_CONVENTION.md`).

### 4.3 Service

- **`CustomerBalanceService::applyToOpenInvoices(Customer, ?Invoice = null)`** — `DB::transaction` + `lockedBalance()`. Untuk tiap BULANAN terbuka (FIFO): `pakai = min(saldo, sisa)`; buat `Payment` (method SALDO, `idempotency_key = "auto-saldo:{invoice_id}:{urutan}"`, `payment_date` = hari proses, dibuat oleh sistem, nomor dari `payment_number_sequences`); debit ledger (`pakai_otomatis`, `payment_id`); lalu `Invoice::recalculateFromPayments()`.
- **Hook:** `GenerateMonthlyInvoicesCommand.php` setelah `rebuildFor` (`:162`), dalam transaksi yang sama. Kegagalan auto-pay **tidak boleh membatalkan penerbitan tagihan** → tangkap per pelanggan, log, lanjut.
- **Command catch-up:** `billing:apply-balance {--customer=} {--period=} {--dry-run}` — default dry-run. Untuk saldo yang masuk setelah tagihan terbit (asumsi 3.1 poin 3) dan untuk audit sebelum go-live (§4.5). Dicatat di `docs/RUNBOOK_COMMANDS.md` §D.
- **Tutup gap reject (G5):** `PaymentController::reject` — bila `balance_used_amount > 0`, kembalikan debit (`source=pembatalan`). Bila payment **sumber credit** ditolak dan credit sudah terpakai → saldo boleh negatif (perilaku ADHOC-38 dipertahankan); auto-pay melewati saldo ≤ 0; kartu Saldo menampilkan peringatan.
- **Laporan kas (G4):** `AdminCashBalanceService` & `CollectorBalanceService` memakai kas fisik `amount − balance_used_amount + overpay_amount` dan mengecualikan method SALDO. Laporan pendapatan/tagihan (`PaymentReportController`, `InvoiceReportController`) tetap mengakui SALDO sebagai pelunasan tagihan, dengan kolom/filter method.
- **Hardening (G8):** observer append-only untuk `CustomerBalanceMutation` (pola `InventoryTransactionObserver`: tolak `updating`/`deleting`); `HasPopScope` atau filter `pop_id` eksplisit di setiap daftar/laporan saldo.
- **RBAC (G9):** feature baru `customer_balance` dengan action `view` saja di `FeatureSeeder`, `config/rbac.php` (+ label di `permission_name_overrides`), `RolePermissionSeeder`. Admin dan pop_admin (dalam scope) dapat `view`; owner via `*`. Sales & teknisi tidak dapat (aturan: sales tak boleh akses keuangan). `EffectiveAccessService::clearCache()` setelah seed. Penyesuaian saldo manual **di luar scope**.

### 4.4 Test yang wajib ada (PHPUnit, nama menurut gejala)

- `CustomerBalanceAutoApplyOnMonthlyInvoiceTest` — kasus 550k: 3 bulan lunas berurutan, bulan ke-4 `belum_dibayar`.
- `CustomerBalanceAutoApplyPartialBecomesInstallmentTest` — saldo 50k vs 150k → `sebagian`, "Cicilan Ke-1", sisa 100k.
- `CustomerBalanceAutoApplyIdempotentTest` — generator/command dijalankan dua kali tidak menggandakan payment/debit.
- `CustomerBalanceAutoApplyFifoOldestInvoiceFirstTest`, `...SkipsBatalAndTakTertagihTest`, `...SkipsZeroOrNegativeBalanceTest`, `...OnlyBulananTypeTest`.
- `AdvancePaymentOverpayCreditsBalanceWithSourceTest` — 550k di AWAL 100k → credit 450k sumber `bayar_di_muka`.
- `PaymentRejectRefundsBalanceUsedTest` — reject payment SALDO/campuran mengembalikan saldo.
- `CashReportsExcludeSaldoPaymentsTest` — kas admin/kolektor tidak menghitung SALDO sebagai uang fisik; overpay terhitung sebagai kas masuk.
- `CustomerBalanceMutationAppendOnlyTest`, `CustomerBalancePopScopeTest`, `CustomerBalanceRbacTest`.
- `ApplyBalanceCommandDryRunTest`.

### 4.5 Migrasi data lama & rollout — **risiko utama**

Begitu auto-pay aktif, **credit lama** (dari ADHOC-38, atau dari `payments:backfill-customer-balance` bila sudah dijalankan) akan langsung terpakai di generator tgl 1 berikutnya. Pelanggan/kasir bisa kaget karena tagihan tiba-tiba lunas tanpa uang masuk.

Mitigasi:
1. Jalankan `billing:apply-balance --dry-run` **sebelum** tgl 1 pertama setelah deploy; tinjau daftar pelanggan terdampak.
2. Backfill `source` dan `balance_used_amount` **sebelum** go-live (kalau tidak, laporan kas keliru).
3. `payments:backfill-customer-balance` (belum dijalankan ke produksi, ADHOC-38) hanya dijalankan sadar, setelah dry-run.
4. Tidak memakai feature flag (aturan repo) — kendali lewat urutan deploy + dry-run.

---

## 5. Dampak ke Modul Lain

| Modul | Dampak |
|---|---|
| ADHOC-84 (alokasi & konfirmasi overpay) | Modal konfirmasi overpay (§4.2) beririsan. Dokumen ini mengambil modal konfirmasi; dropdown "Alokasi" dan FIFO kolektor tetap di ADHOC-84 |
| ADHOC-68 (upgrade/downgrade) | Butuh credit tanpa `Payment` (G12). Jalur itu **belum dirancang di sini** — jangan didesain ganda; catat ketergantungan |
| ADHOC-69 / 87 (putus langganan, waiver periode) | Auto-pay wajib menghormati `batal`. Generator `--period` bisa menerbitkan ulang invoice `batal` — pastikan invoice pengganti tidak memakai saldo tanpa disengaja |
| ADHOC-89 (piutang mulai bulan berganti) | Tagihan yang lunas dari saldo tidak menjadi piutang; `sebagian` tetap piutang untuk sisanya |
| ADHOC-90 (tak_tertagih) | Auto-pay melewati `tak_tertagih`; kerjakan setelah ADHOC-90 di-commit |
| Kolektor | `CollectorPaymentService` menolak nominal > sisa (`:113,:193`) dan tak menyentuh saldo. Cukup keterangan worklist "sisa setelah saldo". Teks `collector-worklist/index.blade.php:265` ("kelebihan dikembalikan tunai") sudah usang |
| Portal pelanggan | `/me/balance` tetap; riwayat saldo di portal di luar scope |
| `InitialInvoiceService` | Tak berubah. Catatan: `due_date` AWAL = tanggal terbit (`:149`) sedangkan README billing menyebut "digeser ke tgl 10 bulan berikutnya" — ketidakcocokan yang sudah ada, tidak diselesaikan di sini |

---

## 6. Contoh Angka

**Kasus utama** (paket 150k, prorate 100k, bayar 550k di hari aktivasi; tanpa PPN agar sederhana):

| Tanggal | Kejadian | Saldo |
|---|---|---|
| Aktivasi | Bayar 550k di AWAL 100k → AWAL `lunas`, credit 450k (`bayar_di_muka`) | 450k |
| 1 Sep | BULANAN 150k terbit → payment SALDO 150k → `lunas` | 300k |
| 1 Okt | BULANAN 150k → auto → `lunas` | 150k |
| 1 Nov | BULANAN 150k → auto → `lunas` | 0 |
| 1 Des | Saldo 0 → tagihan `belum_dibayar` normal | 0 |

**Saldo kurang** (saldo 50k, tagihan 150k): payment SALDO 50k (Cicilan Ke-1) → `sebagian`, sisa 100k, saldo 0. Sisa 100k dibayar admin/kolektor sebagai Cicilan Ke-2.

**Kas fisik kasus utama:** hari aktivasi kas masuk = `amount 100k + overpay_amount 450k` = 550k. Payment SALDO bulanan menghasilkan kas 0 (`amount − balance_used_amount = 0`). Tanpa koreksi G4, tiap payment SALDO 150k akan tercatat sebagai uang fisik yang belum disetor.

---

## 7. Dokumentasi yang Ikut Dikoreksi

- `docs/billing-pembayaran/README.md` (L19), `database-schema.md` (L103), `user-flow.md` (L22-29, 2a): "lebih bayar bukan saldo kredit" → kelebihan bayar **masuk ledger Saldo Pelanggan** sejak ADHOC-38; auto-pakai ke tagihan bulanan = rancangan ADHOC-92, belum diimplementasi.
- `docs/customer-lifecycle/business-logic.md` §7 (Approve → Aktivasi): catatan rencana bayar di muka.
- `docs/TASKS.md`: baris ADHOC-92.

---

## 8. Pertanyaan Terbuka

Tidak ada yang menghalangi implementasi. FIFO dan kolom `balance_used_amount` sudah disetujui (§3, §4.1). Kasus pinggiran §3.1 poin 3 tidak mempengaruhi studi kasus utama.

---

## 9. Checklist Eksekusi

- [x] ADHOC-90 sudah di-commit (hindari konflik file)
- [x] Migration: `source`, unique ledger, `balance_used_amount` + backfill
- [x] `PaymentMethod::SALDO`, enum `BalanceMutationSource`
- [x] `CustomerBalanceService::applyToOpenInvoices()` + hook generator + command `billing:apply-balance`
- [x] Perbaikan reject, laporan kas, observer append-only, POP scope ledger
- [x] RBAC `customer_balance` + seed + `clearCache`
- [x] UI: kartu Saldo (Detail Pelanggan), label SALDO (Detail Tagihan/Riwayat Pembayaran), kwitansi "Titip Saldo" — modal konfirmasi overpay reuse ADHOC-84, tidak dibuat ulang
- [x] Test §4.4 hijau (`php artisan test --compact --filter=CustomerBalance`, 6 file baru + `AdvancePaymentOverpayCreditsBalanceWithSourceTest`/`PaymentRejectRefundsBalanceUsedTest`/`CashReportsExcludeSaldoPaymentsTest`/`ApplyBalanceCommandDryRunTest`)
- [x] `vendor/bin/pint`
- [x] Update `docs/billing-pembayaran/*`, pindah ADHOC-92 ke Done di `docs/TASKS.md`
- [ ] **Dry-run `billing:apply-balance` di server produksi sebelum tgl 1 pertama setelah deploy** — belum dieksekusi, ini murni operasional deploy (bukan kode), lihat §4.5

---

## 10. Di Luar Scope

Refund saldo tunai / nasib saldo saat putus langganan; penyesuaian saldo manual oleh admin; auto-pakai untuk tipe non-BULANAN; auto-pakai otomatis saat credit masuk setelah tagihan terbit; notifikasi saldo menipis (WhatsApp/in-app); riwayat saldo di portal pelanggan; jalur credit tanpa `Payment` (ADHOC-68).
