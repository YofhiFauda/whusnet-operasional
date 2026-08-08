# Database Schema — Customer Verifikasi & Onboarding Lifecycle

## Entity Relationship

```
customers ──1:1──▶ customer_addresses
    │      ──1:1──▶ customer_services ──belongsTo──▶ internet_packages
    │      ──1:1──▶ customer_technical_details
    │      ──1:1──▶ customer_devices (legacy, subset technical_details)
    │      ──1:N──▶ customer_surveys ──belongsTo──▶ users (technician_id, surveyor_2_id, surveyor_3_id)
    │      ──1:N──▶ customer_installations ──belongsTo──▶ users (technician_id)
    │      ──1:N──▶ task_materials ──belongsTo──▶ fop_tasks, items (estimasi & terpakai)
    │      ──1:N──▶ customer_documents ──belongsTo──▶ users (uploaded_by)
    │      ──1:N──▶ customer_status_logs ──belongsTo──▶ users (changed_by)
    │      ──1:N──▶ tasks (Task eksekusi teknisi)
    │      ──1:N──▶ fop_tasks (tiket FOP, lihat docs/fop-task)
    │      ──1:N──▶ invoices, payments (lihat docs/billing-pembayaran)
    └──belongsTo──▶ pops, cities, districts, villages, internet_packages
```

## Tabel `customers`

Migrasi awal: `2026_06_09_035326_create`. Banyak kolom ditambah belakangan lewat migrasi terpisah (legacy id, extended attributes — lihat [docs/billing-pembayaran/database-schema.md](../billing-pembayaran/database-schema.md) untuk pola serupa di invoice).

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_code` | string(30), unique | Nomor pelanggan, generate dari `Pop` sequence |
| `cid` | string | **Baru diisi saat Aktivasi** (`Pop::generateComplexCid()`) — kosong sebelum status `active` |
| `full_name`, `identity_number`, `gender`, `customer_type`, `company_name`, `npwp` | string | Identitas |
| `email`, `phone`, `primary_phone`, `alternative_phone` | string | Kontak |
| `registration_date` | date | |
| `status` | string(50), default `registered` | State machine — lihat `App\Enums\WorkflowTransition` |
| `customer_status` | string | Label operasional Bahasa Indonesia (mapping terpisah dari `status`, e.g. `waiting_survey`→`survey`) |
| `data_completeness_status` | string | e.g. `siap_billing` — dipakai guard di beberapa alur (invoice manual) |
| `pop_id` | FK → `pops.id` (WAJIB row `type=cabang`) | Diisi saat registrasi, gak pernah berubah level |
| `distribution_id` | FK → `distributions.id`, nullable | Di-assign pasca pemasangan lewat modal, lihat [docs/master/pop/bug.md](../master/pop/bug.md) |
| `mini_pop_id` | FK → `pops.id` (row `type=mini_pop`), nullable | Migrasi `2026_07_07_154528_add_mini_pop_id`. Di-assign bareng `distribution_id` lewat modal yang sama, dipakai resolve segmen Mini POP di CID |
| `city_id`, `district_id`, `village_id` | FK, nullOnDelete | Region |
| `address`, `latitude`, `longitude` | text/decimal | Alamat ringkas (duplikat sebagian dengan `customer_addresses`) |
| `internet_package_id` | FK | Paket dipilih saat registrasi |
| `contract_period_months`, `discount_amount`, `tax_percent` | | Dipakai hitung `customer_services` awal |
| `sales_code`, `agent_code`, `referral_customer_code` | string | Atribusi sales |
| `ont_sn`, `ip_address`, `odp_code`, `olt_code`, `vlan_id` | string | Data teknis ringkas di level customer (duplikat sebagian dengan `customer_technical_details`) |
| `foto_ktp`, `foto_rumah`, `foto_kontrak` | string | Path foto (audit-hidden — gak muncul di payload AuditLog) |
| `created_by`, `updated_by` | FK users | |
| `old_customer_id` | string, nullable | Referensi `IDPENGGUNA` legacy — cuma terisi buat hasil `app:import-legacy-sql`. **Load-bearing buat business rule** (baru 2026-07-20): jadi salah satu syarat gate tombol "Aktivasi Manual" (lihat [business-logic.md §7](business-logic.md#aktivasi-manual--jalur-khusus-data-migrasi-customercontrolleractivate-2026-07-20)) — kalau kosong, customer dianggap BUKAN hasil migrasi, gak boleh lewat jalur aktivasi manual. |

## Tabel `customer_addresses` (1:1)

Migrasi: `2026_06_11_130000_create` (dengan backfill data dari `customers`).

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK, cascade delete | |
| `full_address`, `province`, `city`, `district`, `village` | string/text | Snapshot teks (independen dari perubahan master wilayah) |
| `city_id`, `district_id`, `village_id` | FK, nullOnDelete | |
| `latitude`, `longitude` | decimal(10,7) | |
| `house_photo`, `ktp_photo`, `contract_photo` | string | |

## Tabel `customer_services` (1:1)

Migrasi: `2026_06_11_140000_create` + beberapa alter (`add_activated_by_user_id`, `add_other_fee`, `add_contract_type`). Backfill otomatis dari `customers`+`internet_packages` saat migrasi awal jalan.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK, cascade delete | |
| `internet_package_id` | FK, nullOnDelete | |
| `package_name_snapshot`, `download_speed_snapshot`, `upload_speed_snapshot` | string | Snapshot paket saat itu (gak berubah walau master paket diedit belakangan) |
| `monthly_price`, `discount`, `ppn`, `other_fee`, `total_monthly_bill` | decimal | |
| `activation_date`, `due_date` | date | |
| `billing_cycle` | string, default `monthly` | |
| `service_status` | string, default `calon_pelanggan` | `aktif` di-set saat Verifikasi Admin approve. Buat pelanggan migrasi, di-set langsung dari `mapLegacyServiceStatus()` saat import (lihat [business-logic.md §9](business-logic.md#9-migrasi-data-legacy-customercontrollerconfirmimport--migratelegacydatacommand-2026-07-20)) |
| `billing_status` | string, default `pending` | `active` di-set saat aktivasi |
| `profile`, `contract_type` | string | `contract_type` (`sewa`/`beli`, lowercase) hasil import diambil dari kolom legacy `STATUSALAT` (fixed 2026-07-20 — sebelumnya salah baca dari `STATUSLANGGANAN` yang kosong di semua data legacy) |
| `activation_time`, `activated_by_name`, `activated_by_user_id` | | Dicatat di `finalVerify()` |
| `old_request_id`, `old_cost_id` | string | Referensi ID legacy |
| `request_status`, `installation_status` | string, nullable | **Raw** status legacy (`STATUS`/`STATUSPASANG` — teks asli, BEDA dari `service_status` yang udah di-mapping ke vocab baru). `request_status` dipakai sebagai salah satu gate "Aktivasi Manual" — harus persis `'ACTIVE'` (huruf besar, nilai legacy asli) |
| `reason` | text, nullable | `ALASAN` legacy — sumber alasan buat AuditLog sintetis `rejected`/`terminated` (lihat bagian Audit di bawah) |

## Tabel `customer_surveys` (1:N)

Migrasi: `2026_06_13_104704_create` + `add_multi_surveyor_house_photo` + `2026_07_31_000001_add_requested_installation_date`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK, cascade delete | |
| `survey_status` | string, default `pending` | `pending`/`completed`/`failed` |
| `survey_date`, `start_time`, `end_time`, `started_at`, `completed_at`, `duration_minutes` | date/time | Timer mulai/selesai survey |
| `technician_id` | FK users, nullOnDelete | Yang submit laporan |
| `surveyor_2_id`, `surveyor_3_id` | FK users | Anggota tim survey lain (maks 3 tercatat) |
| `surveyors` | string | Teks ringkas "Petugas Survey N - Nama" |
| `fop_id` | | FOP/pembuat task terkait |
| `required_tools` | text | **Alat khusus / kendala peralatan** (tangga, bor) — catatan bebas, BUKAN material habis pakai. Material terstruktur ada di `task_materials` |
| `cable_estimation_meter`, `nearest_odp` | | `cable_estimation_meter` otomatis diturunkan jadi satu baris `task_materials` bertipe `kabel_dropcore` |
| `requested_installation_date` | date, nullable | Tanggal pemasangan yang diminta pelanggan. **Satu-satunya sumber kebenaran** — `fop_tasks.client_request_date` kategori PSB cuma turunannya. Kosong = "secepatnya" |
| `survey_photo`, `house_photo` | string | Wajib diisi saat submit |
| `survey_note` | text | Gabungan tingkat kesulitan + catatan bebas |

## Tabel `customer_installations` (1:N)

Migrasi: `2026_06_13_110000_create` + `add_multi_technician`, `add_contract_and_signature_photo`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK, cascade delete | |
| `installation_status` | string, default `scheduled` | `scheduled`/`in_progress`/`completed`/`failed` |
| `scheduled_date`, `scheduled_time`, `started_at`, `start_time`, `finished_date`, `end_time`, `completed_at` | date/time | |
| `technician_id`, `fop_id` | FK users | |
| `installation_photo`, `contract_photo`, `signature_photo` | string | Wajib saat status `completed` |
| `installation_note` | text | Diprepend catatan revisi kalau ada |

## Tabel `task_materials` (material per task)

Migrasi: `2026_07_31_000003_create_task_materials_table`. Menampung **dua fase** pencatatan material dalam satu tabel.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `fop_task_id` | FK `fop_tasks`, cascade delete | Anchor. Sengaja ke FopTask (bukan `customer_installations`) — FopTask satu-satunya entitas yang dimiliki SEMUA jenis pekerjaan |
| `customer_id` | FK, nullOnDelete | Jalan pintas baca lintas task: estimasi menempel di task SURVEY, realisasi di task PSB |
| `kind` | string(20) | `estimasi` (dari Laporan Survey) / `terpakai` (dari Laporan Pemasangan) — `App\Enums\MaterialKind` |
| `item_id` | FK `items`, nullable, nullOnDelete | Null **hanya** untuk barang "lainnya" di luar master |
| `item_type` | string(50) | Snapshot — `App\Enums\MaterialType` |
| `item_name` | string(150) | Snapshot nama; laporan lama tidak berubah kalau master di-rename |
| `qty` | decimal(10,2) | Decimal, bukan integer — kabel dihitung meter |
| `unit` | string(20) | `meter`/`pcs`/`roll`/`set`. Untuk barang master, **selalu** diambil dari master |
| `unit_price_snapshot` | decimal(12,2), nullable | Kosong sampai modul Inventory ada |
| `note` | string(255), nullable | |
| `recorded_by` | FK users, nullOnDelete | |

Index: `(fop_task_id, kind)`.

> Tabel ini **adalah** `fop_task_materials` yang direncanakan [docs/post-mvp/inventory-fop.md](../post-mvp/inventory-fop.md), dibangun lebih awal dengan bentuk final. Inventory nanti **menambah** (kolom stok/harga di `items`, tabel pergerakan stok, dashboard biaya) — bukan mengubah bentuk tabel ini atau UI-nya.

## Tabel `items` (master barang/material)

Migrasi: `2026_07_31_000002_create_items_table`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `code` | string(30), unique | mis. `DC-1C`, `SPL-1X8` |
| `name` | string(150) | |
| `type` | string(50) | `App\Enums\MaterialType` |
| `unit` | string(20) | Satuan resmi barang |
| `is_active` | boolean, default true | Barang tak terpakai **dinonaktifkan**, bukan dihapus — baris `task_materials` lama menunjuk ke sini |

**Sengaja tidak ada:** stok, harga, lokasi gudang, minimum stock. Itu wilayah modul Inventory. Tabel ini cuma menjawab "barang apa saja yang boleh dicatat" supaya penamaan seragam sejak baris pertama.

## Tabel `customer_technical_details` (1:1)

Migrasi: `2026_06_15_000001_create` + `add_cid_olt_slot`, `add_olt_vlan_fields`, `add_structured_passive_device_fields`. **Sumber data teknis utama** — lebih lengkap dari `customer_devices`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK, cascade delete | |
| `old_report_id`, `old_customer_id`, `old_request_id` | string | Referensi data legacy |
| `connection_type`, `ssid`, `ip_address`, `antenna_mac`, `router_mac`, `router_or_ont_serial` | string | |
| `odp_number`, `odp_port`, `olt_port` (+ `olt_number`, `olt_slot`, `vlan` dari alter migration) | string | Jalur fiber |
| `test_upload`, `test_download` | string | Hasil speedtest |
| `wireless_signal`, `fiber_signal`, `actual_attenuation`, `initial_attenuation` (alter) | string | |
| `speed_conformity_percent` (alter) | decimal | Dihitung otomatis: `test_download / paket.download_speed_mbps * 100` |
| `speedtest_photo`, `form_photo`, `signed_form_photo`, `router_photo`, `cable_photo` | string | |
| `location_source`, `note` | | |

## Tabel `customer_devices` (1:1, legacy)

Migrasi: `2026_06_13_120000_create`. Overlap kolom dengan `customer_technical_details` (device_type, brand, model, serial_number, mac_address, PPPoE, WiFi, IP) — dipertahankan untuk backward compatibility (`CustomerInstallationController` nulis ke keduanya sekaligus saat submit laporan).

- `device_retrieved_at` (nullable timestamp, migrasi `2026_07_18_163955_add_device_retrieved_at_to_customer_devices_table`) — **baru**. Dicek buat kolom "Status Alat" di List Putus Langganan (null = "Belum di Ambil", terisi = "Sudah di Ambil"). Diset oleh `CustomerController::retrieveDevice()` ("Ambil Alat"). Sebelum kolom ini ada, gak ada cara sistem tahu status pengambilan alat pelanggan yang putus langganan — lihat [business-logic.md §8](business-logic.md#8-terminasi-layanan-customerterminationcontroller).

## Tabel `customer_documents` (1:N)

Migrasi: `2026_06_13_130000_create`.

| Kolom | Tipe |
|-------|------|
| `id` | bigint PK |
| `customer_id` | FK, cascade delete |
| `document_type` | string |
| `file_path` | string |
| `uploaded_by` | FK users, nullOnDelete |

## Tabel `customer_status_logs` (1:N, immutable)

Migrasi: `2026_06_27_104414_create`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `customer_id` | FK, cascade delete | |
| `from_status`, `to_status` | string | VARCHAR murni (bukan FK/enum) — fleksibel untuk histori lama |
| `changed_by` | FK users, nullOnDelete | Null kalau transisi dari scheduler/CLI |
| `note` | text | |
| `created_at` | timestamp, useCurrent | **Tidak ada `updated_at`** — model set `const UPDATED_AT = null`, log ini didesain immutable |

**Catatan:** tabel ini cuma keisi lewat `CustomerWorkflowService::transition()` — terminasi (`CustomerTerminationController`) **tidak** menulis ke sini (lihat [business-logic.md §8](business-logic.md#8-terminasi-layanan-customerterminationcontroller)).

## Model Relations (ringkas)

```php
// Customer
pop(): BelongsTo(Pop::class)                    // selalu row Cabang
miniPop(): BelongsTo(Pop::class, 'mini_pop_id')  // row Mini POP, assignment pasca pemasangan
distribution(): BelongsTo(Distribution::class)
customerAddress(): HasOne(CustomerAddress::class)
customerService(): HasOne(CustomerService::class)
surveys(): HasMany(CustomerSurvey::class)
installations(): HasMany(CustomerInstallation::class)
latestSurvey(): HasOne(CustomerSurvey::class)->latestOfMany()
latestInstallation(): HasOne(CustomerInstallation::class)->latestOfMany()
tasks(): HasMany(Task::class)
fopTasks(): HasMany(FopTask::class)
invoices(): HasMany(Invoice::class)
payments(): HasMany(Payment::class)

// CustomerSurvey / CustomerInstallation
customer(): BelongsTo(Customer::class)
technician(): BelongsTo(User::class)
```

## Audit

- `Customer` — trait `RecordsAuditLogs`, module `Data Pelanggan`; `foto_ktp`/`foto_rumah`/`foto_kontrak` di-hide dari payload (`$auditHidden`).
- Tiap transisi status — `CustomerStatusLog` (khusus histori status) + `AuditLog` module `Customer Workflow` (`CustomerWorkflowService`) atau module `Data Pelanggan`/`customers` (aksi manual di controller seperti `activate_from_verification`, `terminate`).
- **List Pelanggan Gagal & List Putus Langganan (2026-07-20) baca langsung dari `AuditLog`, bukan kolom dedicated** — `CustomerController::index()` query `AuditLog` per customer buat ambil alasan+tanggal: module `Customer Workflow` action `status_transition` dengan `new_values->status='rejected'` (Pelanggan Gagal), atau module `customers` action `terminate` (Putus Langganan). Dua action baru ditambahkan ke module yang sama: `status_restore` (Kembalikan dari Gagal, `CustomerController::restoreFromFailed()`) dan `reactivate` (Langganan Lagi, `CustomerController::reactivate()`) — dua-duanya nyimpen `old_values`/`new_values.status` tapi **tidak** tercatat di `customer_status_logs` (bypass state machine, lihat [business-logic.md §7](business-logic.md#reject-reject) & [§8](business-logic.md#list-putus-langganan--ambil-alat--langganan-lagi-2026-07-20)).
- **Kedua list di atas diurut DESC berdasarkan tanggal dari `AuditLog` (2026-07-20)** — subquery `AuditLog::selectRaw('MAX(created_at)')` di-`orderBy()` SEBELUM `paginate()` (bukan `customer_code`), karena tanggal itu bukan kolom asli di `customers`, harus dihitung on-the-fly.
- **AuditLog sintetis buat pelanggan hasil migrasi (2026-07-20)** — `confirmImport()` set status pelanggan pakai `Customer::updateQuietly()`, yang gak nulis `AuditLog` sama sekali. Biar List Pelanggan Gagal/Putus Langganan gak kosong buat pelanggan migrasi yang `rejected`/`terminated`, `confirmImport()` sekarang bikin `AuditLog` manual dengan format yang sama persis kayak transisi asli (module+action sama), pakai `reason` (`ALASAN` legacy) dan tanggal dari `status_changed_at` (`TGLSELESAI` legacy, fallback `updated_at` baris legacy). `old_values.status` di-default (`registered`/`active`) karena data legacy gak selalu jelas tahap persis sebelumnya — lihat [business-logic.md §9](business-logic.md#9-migrasi-data-legacy-customercontrollerconfirmimport--migratelegacydatacommand-2026-07-20).
