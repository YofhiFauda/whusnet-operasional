# Modul Business Development (BD)

Kumpulan fitur buat tim Business Development (dulu disebut "Busdev" — istilah itu masih dipakai di **label UI**, tapi kode/permission/tabel semua pakai `business_development`/`customer_acquisitions`): monitoring pelanggan baru, dashboard omset Sales, master restriksi paket per role, master Agent/mitra, dan gate validasi "Biaya Instalasi" buat kategori paket Bisnis sebelum pelanggan resmi aktif.

Lima sub-fitur beda tujuan, satu submenu sidebar **"Business Development"**:

| Sub-fitur | Route awal | Buat apa |
|---|---|---|
| Pelanggan Aktif < 30 Hari (Customer Acquisition) | `/customer-acquisitions` | Monitoring pelanggan baru terverifikasi, direset tampilan tiap bulan |
| Menunggu Verifikasi BD | `/business-development-verifications` | **Gate**: pelanggan kategori Bisnis nyangkut di sini sebelum resmi ACTIVE, BD isi Biaya Instalasi |
| List Pelanggan Bisnis | `/business-development/business-customers` | Daftar pelanggan kategori paket Bisnis: harga paket, harga sesudah PPN, status, alat yang ditinggalkan, biaya instalasi, tanggal aktivasi. **Read-only, turunan data sistem** (tanpa tabel/input manual) |
| Dashboard Omset Sales | `/business-development/sales-omset` | Agregat komisi/omset per Sales |
| Restriksi Paket per Role | `/business-development/package-restrictions` | Batasi paket apa yang boleh dipilih role tertentu (Sales/Teknisi) saat registrasi |
| Master Agent | `/business-development/agents` | Master mitra/agen referral (bukan akun login) |
| Master Kategori Paket | `/master/package-categories` | Bukan submenu BD, tapi **di sinilah admin mengatur** kategori mana yang butuh gate BD |

## Dokumen

| Dokumen | Isi |
|---|---|
| [business-logic.md](business-logic.md) | Aturan tiap sub-fitur, terutama gate "Menunggu Verifikasi BD" + kapan Invoice Awal terbit |
| [database-schema.md](database-schema.md) | Tabel `customer_acquisitions`, `package_categories`, `restricted_packages`, `agents`, kolom baru di `customers` |
| [flowchart.md](flowchart.md) | Alur CS verifikasi → (gate BD kalau Bisnis) → Invoice Awal terbit → ACTIVE |
| [user-flow.md](user-flow.md) | Langkah CS/BD/Sales/Admin di tiap layar |

## Konsep Inti — Kategori Paket Menentukan Segalanya

**Satu kolom jadi saklar utama:** `package_categories.installation_fee_approval_role_id`.

- **NULL** (mis. kategori "Home Broadband") → pelanggan lewat jalur LAMA, gak berubah sama sekali: CS verifikasi (`finalVerify()`) → Invoice Awal langsung terbit → pelanggan langsung ACTIVE.
- **Diisi role tertentu** (mis. "Bisnis Broadband" → role `business_development`) → pelanggan lewat gate: CS verifikasi → **Invoice Awal BELUM terbit** → nyangkut status `waiting_business_development_verification` → BD isi Biaya Instalasi & tekan "Verifikasi & Aktifkan" → **di titik itu** SATU Invoice Awal terbit, sudah mencatat biaya CS + biaya BD sekaligus → ACTIVE.

Diatur admin lewat **Master Kategori Paket** (`/master/package-categories`, dropdown pilih Role) — bukan hardcode "kategori Bisnis" di kode, dan bukan dropdown permission mentah (terlalu teknis buat admin non-developer).

## File Kode Terkait

| Area | File |
|---|---|
| Master Kategori Paket | `app/Models/PackageCategory.php`, `app/Http/Controllers/Master/PackageCategoryController.php` |
| Customer Acquisition (monitoring) | `app/Models/CustomerAcquisition.php`, `app/Http/Controllers/CustomerAcquisitionController.php`, `app/Observers/CustomerObserver.php` |
| Gate "Menunggu Verifikasi BD" | `app/Http/Controllers/BusinessDevelopmentVerificationController.php`, `app/Enums/WorkflowTransition.php` (`WAITING_BUSINESS_DEVELOPMENT_VERIFICATION`) |
| Verifikasi CS (titik keputusan gate) | `app/Http/Controllers/CustomerVerificationController.php@finalVerify` |
| Invoice Awal (bisa langsung/tertunda) | `app/Services/InitialInvoiceService.php` |
| Invoice Biaya Instalasi (fallback pelanggan lama, invoice terpisah) | `app/Services/InstallationFeeInvoiceService.php` |
| View verifikasi (dipakai CS **dan** BD, satu file) | `resources/views/verifications/admin.blade.php`, `app/Services/CustomerVerificationDetailService.php` |
| Restriksi Paket (Skema 1) | `app/Models/RestrictedPackage.php`, `app/Http/Controllers/PackageRestrictionController.php`, `InternetPackage::scopeAvailableFor()` |
| Dashboard Omset Sales (Skema 2) | `app/Http/Controllers/SalesOmsetDashboardController.php` |
| List Pelanggan Bisnis | `app/Http/Controllers/BusinessDevelopment/BusinessCustomerController.php`, view `resources/views/business-development/business-customers/index.blade.php` |
| FK Sales/Agent/Referral (Skema 3) | `app/Models/Agent.php`, kolom `customers.sales_user_id`/`agent_id`/`referral_customer_id` |
| Seed data demo | `database/seeders/BusinessDevelopmentSeeder.php`, `database/seeders/BusinessCustomerSeeder.php` (List Pelanggan Bisnis, 7 pelanggan) |

## Terhubung dengan Modul Lain

- [docs/customer-lifecycle](../customer-lifecycle/README.md) — gate BD nempel di ujung tahap Verifikasi Admin (`finalVerify()`), sebelum `WorkflowTransition::ACTIVE`.
- [docs/billing-pembayaran](../billing-pembayaran/README.md) — Invoice Awal (`InitialInvoiceService`) & Invoice Biaya Instalasi (`InstallationFeeInvoiceService`) sama-sama lewat `InvoiceItemBuilder`/kategori pendapatan.
- [docs/rbac](../rbac/README.md) — role `business_development` (`RoleSeeder`), permission `business_development_verification.view`, `customer_acquisitions.*`, `agents.*`, `package_restrictions.*`, `sales_omset_dashboard.view`, `business_customers.view`.

---

**Last updated:** 2026-09-21 — tambah List Pelanggan Bisnis (read-only). Sebelumnya 2026-09-16 — 2 invoice terpisah (Invoice Awal + Biaya Instalasi) digabung jadi SATU invoice di jalur gate BD (laporan user: "kenapa muncul 2 tagihan pada 1 pelanggan"). Riwayat sebelumnya: 2026-09-14, Invoice Awal kategori Bisnis ditunda sampai BD verifikasi (sebelumnya terbit duluan di CS, cuma status pelanggan yang ketunda)
