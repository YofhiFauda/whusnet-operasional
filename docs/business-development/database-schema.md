# Database Schema — Modul Business Development

## `package_categories`

Master Kategori Paket Internet (menggantikan `InternetPackage::CATEGORIES` hardcode).

| Kolom | Tipe | Ket |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(100) unique | Dipakai APA ADANYA sebagai nilai `internet_packages.category` (string, **bukan FK**) |
| `is_active` | boolean, default true | Nonaktif ≠ hapus — paket lama yang masih pakai nama ini tetap terbaca |
| `sort_order` | unsigned int, default 0 | Urutan dropdown |
| `installation_fee_approval_role_id` | FK `roles`, nullable | **Saklar gate** — lihat [business-logic.md §2](business-logic.md#2-master-kategori-paket--saklar-gate). NULL = kategori ini gak pernah kena gate BD |
| `created_at`/`updated_at` | timestamps | |

4 kategori bawaan ditanam migrasi: Paket Home Broadband, Paket Bisnis Broadband, Paket Bisnis UKM, Paket Bisnis Dedicated. `destroy()` hard-delete cuma kalau `internet_packages.category` gak ada yang merujuk nama ini.

## `customer_acquisitions`

Satu baris = satu pelanggan yang pertama kali ACTIVE. Dibuat otomatis `CustomerObserver`.

| Kolom | Tipe | Ket |
|---|---|---|
| `id` | bigint PK | |
| `customer_id` | FK `customers`, **unique**, cascade delete | Satu baris seumur hidup per pelanggan (`firstOrCreate`) |
| `periode` | string(7), index | `YYYY-MM` bulan verifikasi — dasar "reset tanggal 1" |
| `verified_at` | datetime | |
| `installation_fee` | decimal(?,2), nullable | Diisi BD saat `verify()` (kategori butuh gate) atau lewat fallback `updateInstallationFee()` |
| `installation_fee_invoice_id` | FK `invoices`, nullable, `nullOnDelete` | Invoice Biaya Instalasi (INSIDENTAL) — begitu terisi, baris **terkunci** dari edit lanjutan |
| `created_at`/`updated_at` | timestamps | |

Accessor (dihitung live, bukan kolom): `harga_dikurangi_ppn` (Biaya Langganan × 89%, monitoring biaya net Busdev), `omset_sales` (Biaya Langganan × 11%, komisi Sales — metrik terpisah, jangan disatukan).

## `restricted_packages` (Skema 1)

| Kolom | Tipe | Ket |
|---|---|---|
| `id` | bigint PK | |
| `package_id` | FK `internet_packages`, **unique**, cascade delete | Daftar GLOBAL — satu daftar dipakai semua role `is_package_restricted` |
| `created_at`/`updated_at` | timestamps | |

## `agents` (Skema 3)

Master mitra/agen referral — **bukan** akun login.

| Kolom | Tipe | Ket |
|---|---|---|
| `id` | bigint PK | |
| `code` | string(30), unique | |
| `name` | string(150) | |
| `phone` | string(20), nullable | |
| `is_active` | boolean, default true | |
| `created_at`/`updated_at` | timestamps | |

## Kolom Tambahan di `customers`

| Kolom | Tipe | Ket |
|---|---|---|
| `sales_user_id` | FK `users`, nullable, `nullOnDelete` | Sales yang mendaftarkan — dipaksa `= auth()->id()` di server buat Sales login |
| `agent_id` | FK `agents`, nullable, `nullOnDelete` | Diisi hanya oleh actor ber-permission `agents.view` |
| `referral_customer_id` | FK `customers` (self), nullable, `nullOnDelete` | Ganti `referral_customer_code` varchar bebas |
| `sales_code`/`agent_code`/`referral_customer_code` | varchar, tetap ada | **Tidak dihapus** — fallback tampilan data legacy pra-Skema 3 |
| `pending_initial_invoice` | JSON, nullable | **Snapshot Invoice Awal yang ditunda** (kategori butuh gate) — `{billing: <hasil InitialInvoiceService::calculate()>, issue_date}`. Diisi `finalVerify()` (CS), dibaca & ditimpa `null` oleh `BusinessDevelopmentVerificationController::verify()` (BD) begitu invoice sungguhan terbit. Lihat [business-logic.md §3.1](business-logic.md#31-kapan-invoice-awal-terbit--titik-paling-penting). Di-cast `array` di `Customer::casts()`, terdaftar di atribut `#[Fillable]` model (Laravel 13 — **bukan** `$fillable` klasik) |

## Kolom Tambahan di `roles`

| Kolom | Tipe | Ket |
|---|---|---|
| `is_package_restricted` | boolean, default false | Role ini kena Skema 1 (dropdown paket dibatasi `restricted_packages`) DAN otomatis jadi "role penjual" yang muncul di filter Role Penginput/Dashboard Omset — satu sumber kebenaran dipakai dua fungsi |

## Kolom Tambahan di `customers.status`

`WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION` (value `waiting_business_development_verification`, 42 karakter) — nilai enum baru buat gate BD. `customers.status` sempat SQLSTATE 1406 (`varchar(30)` terlalu sempit) sampai dilebarkan ke `varchar(60)` (migration `widen_status_column_on_customers_table`, 2026-09-14).

## Diagram Relasi (ringkas)

```
customers ──1:1──> customer_acquisitions ──belongsTo──> invoices (installation_fee_invoice_id)
customers ──belongsTo──> internet_packages (via customer_services) ──.category (string)──> package_categories.name
package_categories ──belongsTo──> roles (installation_fee_approval_role_id)
customers ──belongsTo──> users (sales_user_id), agents (agent_id), customers self (referral_customer_id)
customers.pending_initial_invoice (JSON snapshot) ──[BD verify()]──> invoices (invoice_type=awal, BARU)
```

---

Lihat juga [docs/database-schema.md](../database-schema.md) (skema global) dan [docs/DATABASE_RULES.md](../DATABASE_RULES.md).
