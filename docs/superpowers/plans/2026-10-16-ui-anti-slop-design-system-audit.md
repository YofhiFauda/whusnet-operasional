# WHUSNET Operasional UI Anti-Slop & Design System Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghilangkan seluruh anti-pattern "AI Slop" (card melayang tebal, glow blur, shadow berat, padding boros `p-8`, radius acak `rounded-2xl`/`3xl`) dan menyelaraskan seluruh antarmuka (termasuk Laporan Lapangan PSB/Maintenance/Survey, Verifikasi Admin, Laporan Keuangan, dan Modul Pergudangan WMS) ke standar [Design.md](file:///home/yopi/whusnet/whusnet-operasional/design-system/whusnet-operasional/Design.md).

**Architecture:** Menggunakan arsitektur layout 3-layer dan 3-tipe template WHUSNET:
- Type A (List / Register / Laporan): Layer 1 Naked Page Header -> Layer 2 Flat Summary Strip (hairline dividers) -> Layer 3 Single Panel Card (Control bar flat + data table).
- Type B (Entity Detail / Verifikasi / Form): Layer 1 Naked Page Header + Action Toolbar -> Layer 2 Hairline KPI Metric Strip -> Layer 3 Single Panel (Card Budget = 1) dengan sub-section internal.
- Type C (Aggregate / Dashboard / Gudang WMS): Layer 1 Naked Page Header -> Layer 2 Contextual Filter Bar telanjang -> Layer 3 Clean Metric/Status Grid (maksimal `shadow-2xs`, `rounded-lg`).

**Tech Stack:** Laravel 13, Blade Components, Tailwind CSS v4, Alpine.js.

**Spec:** [design-system/whusnet-operasional/Design.md](file:///home/yopi/whusnet/whusnet-operasional/design-system/whusnet-operasional/Design.md)

## Global Constraints
- Dilarang menggunakan shadow tebal: Tidak boleh ada `shadow-xl`, `shadow-2xl`, `shadow-lg`, atau `shadow-md`. Hanya boleh `shadow-2xs` atau `shadow-xs`.
- Dilarang menggunakan elemen glow / blur hiasan AI: Tidak boleh ada `blur-2xl`, `blur-3xl`, gradient latar kartu gelap (`from-slate-900 to-slate-900`).
- Card Budget Rule: Halaman Type B (detail & verifikasi) hanya boleh memiliki maksimal 1 kartu panel putih (Card Budget = 1). Page Header dan KPI tidak boleh dibungkus card tersendiri.
- Radius Consistency: Gunakan token standar `rounded-lg` (8px). Tidak boleh menggunakan `rounded-2xl` atau `rounded-3xl` pada kartu, toolbar, dan form container.
- Padding Budget: Maksimal padding container adalah `p-5` atau `p-6` (16px - 24px). Tidak boleh ada padding boros `p-8`, `p-10`, `p-16`.
- Warehouse WMS Principles: Menggunakan tabel inventori berdensitas tinggi, indikator status/lot yang presisi, tab navigasi rapi (`rounded-lg`), dan filter bar flat.

## Review Focus
- Tampilan responsif di mobile (`sm:`, `md:`, `lg:`): Ringkasan strip metrik tidak boleh overflow dan harus flex-wrap rapi.
- Fungsionalitas form filter Alpine / Livewire / GET parameter: Pemindahan form dari dalam card ke naked control bar tidak boleh memutus atribut `name`, `x-model`, atau action submit.
- Wizard & Stepper di Form Laporan Lapangan (PSB & Survey): Tidak boleh merusak submit payload, validasi foto, dan navigasi step.
- Dark mode compatibility: Semua background dan border harus memakai token CSS/Tailwind (`bg-surface`, `border-border`, `text-foreground`).
- Tab state & collapsible sections di detail halaman: Navigasi tab tidak boleh rusak saat kartu-kartu disatukan ke dalam 1 card container.

---

### Task 1: Basmi AI Slop & Gradient Glow pada Modul Tugas Lapangan & Teknisi

**Files:**
- Modify: `resources/views/tasks/own.blade.php`
- Modify: `resources/views/tasks/partials/own-card.blade.php`
- Modify: `resources/views/technician-payments/index.blade.php`
- Test: `tests/Feature/Task/OwnTaskTest.php`

**Interfaces:**
- Consumes: Blade variables `$tasks`, `$technicianPayments`, `$summary`
- Produces: UI bersih tanpa hero dark gradient, tanpa blob blur glow, radius `rounded-lg`.

- [ ] **Step 1: Inspect existing routes & tests**
  Jalankan: `php artisan test --filter=Task`
- [ ] **Step 2: Refactor `tasks/own.blade.php` & `own-card.blade.php`**
  - Hapus dark bento hero `bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900` dan elemen `blur-3xl`.
  - Ubah header menjadi **Naked Header** (Layer 1) dengan judul dan breadcrumb langsung di atas canvas.
  - Ubah counter metrik menjadi **Flat Summary Strip** (`bg-surface border border-border rounded-lg divide-x divide-border`).
  - Ganti `rounded-3xl` dan `rounded-2xl` menjadi `rounded-lg`.
  - Ganti empty state `py-16` menjadi padding efisien `py-8`.
- [ ] **Step 3: Refactor `technician-payments/index.blade.php`**
  - Hapus hero banner gelap ber-blob `w-40 h-40 bg-amber-500/15 blur-2xl`.
  - Ubah menjadi struktur Type A standar WHUSNET: Naked Header -> Flat Summary Strip -> Single Data Panel.
  - Turunkan radius semua card dari `rounded-2xl` menjadi `rounded-lg`, hapus padding lebay `p-8`.
- [ ] **Step 4: Verify formatting**
  Jalankan: `vendor/bin/pint --dirty --format agent`

---

### Task 2: Standardisasi Laporan Lapangan (PSB / Instalasi, Survey & Maintenance Tiket)

**Files:**
- Modify: `resources/views/installations/report.blade.php` (Laporan PSB)
- Modify: `resources/views/surveys/report.blade.php` (Laporan Survey)
- Modify: `resources/views/surveys/queue.blade.php` (Antrean Survey)
- Modify: `resources/views/tickets/create.blade.php` (Worksheet Helpdesk & Tiket Gangguan)
- Modify: `resources/views/tickets/show.blade.php` (Detail Tiket)
- Test: `tests/Feature/Installation/*` & `tests/Feature/Survey/*`

**Interfaces:**
- Consumes: Form wizard step state, image uploads, GPS coordinates
- Produces: Wizard form rapi dengan progress bar solid (tanpa gradient rainbow), step button `rounded-lg`, single panel layout.

- [ ] **Step 1: Check existing installation & survey tests**
  Jalankan: `php artisan test --filter=Installation` dan `php artisan test --filter=Survey`
- [ ] **Step 2: Refactor `installations/report.blade.php` (Laporan PSB)**
  - Ganti wrapper progress bar `rounded-2xl p-4 sm:p-5 shadow-sm` menjadi `rounded-lg border border-border p-4 shadow-2xs`.
  - Hapus bar gradien `bg-gradient-to-r from-sky-500 via-blue-500 to-emerald-500`, ganti dengan warna solid Sky Blue (`bg-sky-600 dark:bg-sky-500`).
  - Ubah tombol stepper mobile dan desktop dari `rounded-2xl`/`rounded-xl` menjadi `rounded-lg`.
  - Normalkan semua section form panel ke `rounded-lg` dengan padding `p-5`.
- [ ] **Step 3: Refactor `surveys/report.blade.php` & `surveys/queue.blade.php` (Laporan Survey)**
  - Samakan struktur stepper progress dengan PSB (solid sky, `rounded-lg`, `shadow-2xs`).
  - Ubah list antrean survey ke Type A: Naked Header -> Flat Summary Strip -> Single Table Panel.
- [ ] **Step 4: Refactor `tickets/create.blade.php` & `tickets/show.blade.php` (Helpdesk & Tiket)**
  - Hapus toast floating shadow berlebihan `shadow-2xl` menjadi `shadow-md rounded-lg`.
  - Normalkan sidebar tiket dan drawer dari `rounded-2xl` menjadi `rounded-lg`.
  - Hapus shadow warna (`shadow-sky-500/20`) pada button aksi.
- [ ] **Step 5: Run tests**
  Jalankan: `php artisan test --filter=Installation` dan `php artisan test --filter=Survey`
  Expected: PASS

---

### Task 3: Refactor Verifikasi Admin & Antrean Workflow (`verifications/admin.blade.php` & `queue.blade.php`)

**Files:**
- Modify: `resources/views/verifications/admin.blade.php`
- Modify: `resources/views/verifications/queue.blade.php`
- Modify: `resources/views/verifications/partials/*`
- Test: `tests/Feature/Verification/*`

**Interfaces:**
- Consumes: Customer model data, survey/installation validation state, device assignments
- Produces: Type B murni: Naked Header (status badge + return button) -> Hairline KPI Metric Strip -> Single Panel Verifikasi dengan internal tabs.

- [ ] **Step 1: Check existing verification tests**
  Jalankan: `php artisan test --filter=Verification`
- [ ] **Step 2: Refactor Header `verifications/admin.blade.php`**
  - Keluarkan Page Header dari dalam card pembungkus `bg-surface border border-border rounded-xl shadow-sm p-6 mb-6`.
  - Buat Naked Header langsung di canvas atas: Nama pelanggan, ID Pelanggan, badge status workflow, tombol aksi kembali.
- [ ] **Step 3: Konsolidasi Multi-Card Verifikasi ke Single Panel (Card Budget = 1)**
  - Gabungkan tab navigasi tahapan (Survey, Pemasangan, Pengujian, Verifikasi Akhir) dan isi form ke dalam 1 card container utama `rounded-lg border border-border shadow-2xs`.
  - Normalkan seluruh sub-card informasi teknis (ODP, OLT, Modem SN) dari `rounded-xl`/`rounded-2xl` ke `rounded-lg`.
- [ ] **Step 4: Refactor `verifications/queue.blade.php` ke Type A murni**
  - Naked Header -> Summary Strip antrean verifikasi -> Single Table Panel.
- [ ] **Step 5: Run tests**
  Jalankan: `php artisan test --filter=Verification`
  Expected: PASS

---

### Task 4: Standardisasi Laporan Bisnis & Keuangan (`resources/views/reports/*`)

**Files:**
- Modify: `resources/views/reports/invoices/index.blade.php`
- Modify: `resources/views/reports/payments/index.blade.php`
- Modify: `resources/views/reports/customers/index.blade.php`
- Modify: `resources/views/reports/collector-monthly/index.blade.php`
- Test: `tests/Feature/Report/*`

**Interfaces:**
- Consumes: Financial report aggregates, date range filters, export actions
- Produces: Type A List/Report standar WHUSNET: Naked Header -> Flat Summary Strip -> Single Table Panel.

- [ ] **Step 1: Check existing report tests**
  Jalankan: `php artisan test --filter=Report`
- [ ] **Step 2: Refactor `reports/invoices/index.blade.php` & `reports/payments/index.blade.php`**
  - Ubah 3 card KPI mengambang (`grid grid-cols-1 md:grid-cols-3` dengan icon rounded-full) menjadi **1 Flat Summary Strip** (`divide-x divide-border rounded-lg border border-border bg-surface`) persis seperti acuan `roles/index.blade.php`.
  - Lepas form filter tanggal/cabang dari card tebal `p-6` menjadi flat control toolbar.
  - Standarkan tabel laporan ke dalam panel `rounded-lg shadow-2xs`.
- [ ] **Step 3: Refactor `reports/customers/index.blade.php` & `reports/collector-monthly/index.blade.php`**
  - Hilangkan multi-card KPI terpisah, integrasikan ke dalam Summary Strip horizontal.
  - Normalkan padding dan radius ke 8px `rounded-lg`.
- [ ] **Step 4: Run tests**
  Jalankan: `php artisan test --filter=Report`
  Expected: PASS

---

### Task 5: Rombak Modul Gudang & Logistik Menjadi Standar WMS Profesional

**Files:**
- Modify: `resources/views/components/warehouse/header.blade.php`
- Modify: `resources/views/warehouse/index.blade.php`
- Modify: `resources/views/warehouse/stock/index.blade.php`
- Modify: `resources/views/warehouse/returns/from-customer.blade.php`
- Modify: `resources/views/warehouse/adjustments/create.blade.php`
- Modify: `resources/views/warehouse/custody/index.blade.php`
- Test: `tests/Feature/Warehouse/*`

**Interfaces:**
- Consumes: Warehouse inventory items, stock lots, technician custody, movements
- Produces: Antarmuka WMS bersih, densitas tinggi, tab header rapi tanpa tab bertumpuk acak, filter flat, tanpa padding raksasa `p-8`.

- [ ] **Step 1: Check existing warehouse tests**
  Jalankan: `php artisan test --filter=Warehouse`
- [ ] **Step 2: Rapikan Header Terpadu Gudang `components/warehouse/header.blade.php`**
  - Pastikan tab bar navigasi memiliki padding dan gap proporsional tanpa pembatas ganda yang berantakan.
  - Standarkan radius semua tab dan tombol ke `rounded-lg` (8px).
  - Hapus shadow berlebih pada dropdown aksi (`shadow-xl`/`shadow-2xl` -> `shadow-md rounded-lg`).
- [ ] **Step 3: Refactor Dashboard Gudang `warehouse/index.blade.php`**
  - Rampingkan card "ZONA 0 : PERLU TINDAKAN" agar tidak memakan ruang vertikal berlebih.
  - Pastikan seluruh indikator alert stok kritis menggunakan hairlines bersih `rounded-lg`.
- [ ] **Step 4: Refactor Kelola Stok `warehouse/stock/index.blade.php`**
  - Buka pembungkus card filter `rounded-2xl p-4 sm:p-5 shadow-xs` menjadi flat filter bar terpadu.
  - Ubah toggle mode per lot/ringkasan ke `rounded-lg`.
- [ ] **Step 5: Basmi AI Slop di Form Retur & Penyesuaian**
  - Hapus padding boros `p-6 sm:p-8` di `warehouse/returns/from-customer.blade.php` dan `adjustments/create.blade.php`, normalkan ke `p-5`.
  - Ganti empty state `p-8 rounded-2xl` menjadi `p-4 rounded-lg`.
- [ ] **Step 6: Run tests**
  Jalankan: `php artisan test --filter=Warehouse`
  Expected: PASS

---

### Task 6: Refactor Detail Tagihan (`invoices/show.blade.php`) & Profil User

**Files:**
- Modify: `resources/views/invoices/show.blade.php`
- Modify: `resources/views/profile/show.blade.php`
- Modify: `resources/views/auth/login.blade.php`
- Test: `tests/Feature/Billing/InvoiceDetailTest.php`

**Interfaces:**
- Consumes: Invoice model, user profile form
- Produces: Detail tagihan Type B (Card Budget = 1), profil form clean tanpa glow banner, login card rapi.

- [ ] **Step 1: Refactor `invoices/show.blade.php` ke Single Panel**
  - Keluarkan Page Header dari dalam card `rounded-2xl` ke Naked Header.
  - Satukan 4 KPI floating cards menjadi satu baris hairline metric strip di Layer 2.
  - Satukan tab invoice, rincian, dan riwayat pembayaran ke dalam 1 card panel utama (`rounded-lg border border-border shadow-2xs p-5`).
- [ ] **Step 2: Refactor `profile/show.blade.php`**
  - Hapus hero banner gradien `bg-gradient-to-r` dan elemen `blur-2xl`.
  - Ubah avatar menjadi badge standar dengan border subtle (hapus `ring-4 shadow-lg`).
  - Ubah form card dari `rounded-2xl p-6 lg:p-8` menjadi `rounded-lg border border-border p-5`.
  - Ubah card login di `auth/login.blade.php` dari `p-8 shadow-large` menjadi `p-6 rounded-lg border shadow-2xs`.
- [ ] **Step 3: Run billing & auth tests**
  Jalankan: `php artisan test --filter=Invoice`

---

### Task 7: Standardisasi Modul List Pelanggan, Billing, & Transaksi Kasir

**Files:**
- Modify: `resources/views/customers/index.blade.php`
- Modify: `resources/views/customers/partials/_list_stats.blade.php`
- Modify: `resources/views/customers/partials/_list_filters.blade.php`
- Modify: `resources/views/invoices/index.blade.php`
- Modify: `resources/views/payments/index.blade.php`
- Test: `tests/Feature/Customer/*` & `tests/Feature/Billing/*` & `tests/Feature/Payment/*`

**Interfaces:**
- Consumes: Customers, invoices, payments queries
- Produces: Type A List murni (Layer 1: Naked Header, Layer 2: Flat Summary Strip, Layer 3: Single Table Card).

- [ ] **Step 1: Refactor Pelanggan `customers/index.blade.php` & partials**
  - Ubah `_list_stats.blade.php` dari 4 card ber-`rounded-2xl` menjadi 1 Flat Summary Strip.
  - Lepas card pembungkus filter di `_list_filters.blade.php` menjadi flat control toolbar.
  - Normalkan radius tabel ke `rounded-lg`.
- [ ] **Step 2: Refactor Invoices Index `invoices/index.blade.php`**
  - Satukan stat terpisah ke dalam 1 Flat Summary Strip.
  - Buka filter card `p-6 mb-6` menjadi flat filter bar.
- [ ] **Step 3: Refactor Payments Index `payments/index.blade.php`**
  - Satukan 4 metric cards ke Flat Summary Strip.
  - Buka filter bar dari card terpisah `p-4 sm:p-5 shadow-xs`.
- [ ] **Step 4: Run tests**
  Jalankan: `php artisan test --filter=Customer` dan `php artisan test --filter=Invoice`

---

### Task 8: Normalisasi Master Data, Pengaturan & Audit Logs (Users, POP, Paket, Audit Logs)

**Files:**
- Modify: `resources/views/users/index.blade.php`
- Modify: `resources/views/master/pop/index.blade.php`
- Modify: `resources/views/master/paket/index.blade.php`
- Modify: `resources/views/audit-logs/index.blade.php`
- Test: `tests/Feature/User/*` & `tests/Feature/Master/*`

**Interfaces:**
- Consumes: User management, master data POP/Paket, Audit logs
- Produces: UI konsisten dengan Naked Page Header, tanpa card pembungkus header, radius seragam 8px.

- [ ] **Step 1: Refactor `users/index.blade.php`**
  - Lepaskan Page Header dari dalam card `p-5 shadow-sm mb-6` menjadi Naked Header.
  - Ubah sub-boxes KPI menjadi Flat Summary Strip.
- [ ] **Step 2: Refactor `master/pop/index.blade.php` & `master/paket/index.blade.php`**
  - Buka pembungkus card header `p-6 mb-6 shadow-sm` menjadi Naked Header standar.
  - Ubah filter card menjadi inline/flat filter bar.
  - Hilangkan efek `hover:shadow-md` pada card paket, standarkan radius ke `rounded-lg`.
- [ ] **Step 3: Refactor `audit-logs/index.blade.php`**
  - Lepas filter card `p-6 mb-6` menjadi flat filter bar.
- [ ] **Step 4: Run master data tests & pint**
  Jalankan: `php artisan test --filter=User` dan `vendor/bin/pint --dirty --format agent`

---

### Task 9: Final Polish Dashboard & Verifikasi Regresi Menyeluruh

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/noc/dashboard.blade.php`
- Modify: `resources/views/collector-worksheet/index.blade.php`
- Modify: `resources/views/collector-worklist/index.blade.php`
- Test: Seluruh unit/feature test suite aplikasi

**Interfaces:**
- Consumes: Metrics dashboard dan data sheet kolektor
- Produces: Dashboard rapih (Type C) dan worksheet tanpa radius acak `rounded-2xl` atau floating cards berlebih.

- [ ] **Step 1: Refactor Dashboard & NOC Dashboard**
  - Ubah semua radius widget dashboard dari `rounded-2xl` menjadi `rounded-lg`.
  - Lepaskan filter bar dari card pembungkus tebal.
  - Pastikan semua shadow maksimal `shadow-2xs`.
- [ ] **Step 2: Refactor Collector Worksheet & Worklist**
  - Satukan floating cards stat menjadi Summary Strip.
  - Ganti alert `rounded-2xl` menjadi alert standar `rounded-lg border`.
- [ ] **Step 3: Run Full Test Suite & Linter**
  Jalankan:
  1. `php artisan test --compact`
  2. `vendor/bin/pint --dirty --format agent`
- [ ] **Step 4: Grep Audit Final**
  Verifikasi tidak ada sisa `shadow-xl`, `shadow-2xl`, `blur-3xl`, `p-8` di seluruh template view:
  `grep -rn "shadow-xl" resources/views/`
  `grep -rn "blur-3xl" resources/views/`
