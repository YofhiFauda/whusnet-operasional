# Database Schema — Customer Verifikasi & Onboarding Lifecycle

## Entity Relationship

```
customers ──1:1──▶ customer_addresses
    │      ──1:1──▶ customer_services ──belongsTo──▶ internet_packages
    │      ──1:1──▶ customer_technical_details
    │      ──1:1──▶ customer_devices (legacy, subset technical_details)
    │      ──1:N──▶ customer_surveys ──belongsTo──▶ users (technician_id, surveyor_2_id, surveyor_3_id)
    │      ──1:N──▶ customer_installations ──belongsTo──▶ users (technician_id)
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
| `service_status` | string, default `calon_pelanggan` | `aktif` di-set saat Verifikasi Admin approve |
| `billing_status` | string, default `pending` | `active` di-set saat aktivasi |
| `profile`, `contract_type` | string | |
| `activation_time`, `activated_by_name`, `activated_by_user_id` | | Dicatat di `finalVerify()` |

## Tabel `customer_surveys` (1:N)

Migrasi: `2026_06_13_104704_create` + `add_multi_surveyor_house_photo`.

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
| `required_tools`, `cable_estimation_meter`, `nearest_odp` | | |
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
