# Fitur Data Pelanggan

Fitur Data Pelanggan adalah modul utama untuk mengelola calon pelanggan dan pelanggan ISP. Modul ini mencakup pendaftaran pelanggan, antrean survey, verifikasi lapangan, pemasangan, hingga aktivasi dan import batch.

> **Usulan terbuka:** [analisa-identitas-pelanggan-person-uuid.md](analisa-identitas-pelanggan-person-uuid.md) — REQ ID bukan identitas orang (39 `customer_code` kembar lintas POP, 25 orang punya >1 RQ). Usulan layer `persons` + UUIDv7. Belum diimplementasi.

## File Terkait

| Bagian | File |
| --- | --- |
| Controller | `app/Http/Controllers/CustomerController.php`<br>`app/Http/Controllers/CustomerTerminatedController.php`<br>`app/Http/Controllers/CustomerFailedController.php`<br>`app/Http/Controllers/CustomerRegistrationController.php`<br>`app/Http/Controllers/CustomerSurveyController.php`<br>`app/Http/Controllers/CustomerVerificationController.php`<br>`app/Http/Controllers/CustomerInstallationController.php` |
| Trait daftar | `app/Http/Controllers/Concerns/RendersCustomerList.php` |
| Service | `app/Services/CustomerWorkflowService.php` |
| Model | `app/Models/Customer.php`<br>`app/Models/CustomerSurvey.php`<br>`app/Models/CustomerInstallation.php`<br>`app/Models/CustomerTechnicalDetail.php` |
| Daftar | `resources/views/customers/index.blade.php` (List Pelanggan)<br>`resources/views/customers/terminated.blade.php` (Pelanggan Putus)<br>`resources/views/customers/failed.blade.php` (Pelanggan Gagal)<br>`resources/views/customers/partials/_list_*.blade.php` (bagian bersama) |
| Antrean | `resources/views/surveys/queue.blade.php`<br>`resources/views/verifications/queue.blade.php` |
| Route | `routes/web.php` |

## Fungsi Utama

1. Menampilkan daftar pelanggan dengan pencarian dan filter.
2. Menambahkan pelanggan baru melalui multi-step form registrasi.
3. Mengelola antrean survey dan verifikasi secara real-time.
4. Memfasilitasi workflow status (survey, acc, pemasangan, verifikasi).
5. Menyimpan data teknis (perangkat, OLT, VLAN, speedtest) secara terstruktur.
6. Mengimport banyak pelanggan sekaligus.

## Relasi Model

| Relasi | Keterangan |
| --- | --- |
| `Customer belongsTo City/District/Village` | Wilayah lokasi pelanggan. |
| `Customer belongsTo InternetPackage` | Paket layanan yang dipilih. |
| `Customer belongsTo SubscriptionStatus` | Status workflow. |
| `Customer hasMany CustomerSurvey` | Histori / data survey pelanggan. |
| `Customer hasMany CustomerInstallation` | Histori / data pemasangan pelanggan. |
| `Customer hasOne CustomerTechnicalDetail` | Data perangkat (ONT, Router), FOP, OLT, VLAN. |

## Status Workflow Pelanggan

Urutan status pelanggan dalam alur Onboarding:

| Urutan | Code | Nama | Terminal | Keterangan |
| --- | --- | --- | --- | --- |
| 1 | `waiting_survey` | Waiting Survey | Tidak | Menunggu tim survey |
| 2 | `survey_in_progress` | Survey In Progress | Tidak | Sedang disurvey (Live Countdown) |
| 3 | `surveyed` | Surveyed | Tidak | Selesai survey, menunggu ACC |
| 4 | `waiting_installation`| Waiting Installation | Tidak | Menunggu jadwal pasang |
| 5 | `installation_in_progress` | Installation In Progress | Tidak | Sedang dipasang (Live Countdown) |
| 6 | `verification_admin` | Verification Admin | Tidak | Review perangkat & speedtest |
| 7 | `installed` | Installed | Tidak | Pemasangan selesai & valid |
| 8 | `active` | Active | Tidak | Siap ditagih (Layanan aktif) |

## Tab Detail Pelanggan

Halaman detail menyusun informasi pelanggan dalam beberapa area operasional:
1. Ringkasan dan identitas.
2. Timeline workflow terperinci.
3. Survey (termasuk foto rumah, petugas).
4. FOP & Perangkat (ONT, OLT, Speedtest).
5. Pemasangan (teknisi).
6. Aktivasi & Billing.

## Perubahan Terbaru (2026-07-21)

Rangkuman lengkap + test regresi: `docs/ANALISA_BUG_LIST_PELANGGAN_DAN_MIGRASI_REQ_ID.md`.

### 1. Visibilitas daftar per grup status (`Concerns\RendersCustomerList`)

Daftar default hanya menampilkan `active`+`suspended`. Status workflow lain terlihat lewat
tab `?status_group=...` (`survey`, `verification`) atau — untuk `failed`/`terminated` — lewat
halaman & route sendiri (lihat §4). Peta grup **wajib menutup seluruh pipeline** — kalau ada status
yang tidak terpetakan, pelanggan di status itu lenyap dari semua daftar (mirip aturan "tiap
`TaskStatus` wajib punya `TicketBucket`"). Peta sekarang:

| `status_group` | Status yang tercakup |
| --- | --- |
| `survey` | `waiting_survey`, `survey_in_progress`, `surveyed`, `waiting_acc` |
| `verification` | `waiting_installation`, `installation_in_progress`, `installed`, `verification_admin`, `revision_installation` |
| `failed` | `rejected` (+ legacy `failed`/`gagal`) |
| `terminated` | `terminated` (+ legacy `putus`) |
| _(default)_ | `active`, `suspended` |

> **Aturan:** menambah state baru di `WorkflowTransition` → wajib update `match()` di
> `RendersCustomerList::renderCustomerList()`.

### 2. Filter mempertahankan konteks tab (`customers/partials/_list_filters.blade.php`)

Form filter (`GET`, action `url()->current()`) membawa hidden input `status_group` & `status` bila
ada nilainya. Tanpa ini, "Cari" dari grup `survey`/`verification` kehilangan konteks dan jatuh ke
daftar default. Halaman Putus/Gagal sudah aman lewat route sendiri, tapi hidden input tetap ada
karena partial-nya dipakai bertiga. Regresi: `CustomerListFilterKeepsStatusGroupTest`.

### 3. Redirect setelah registrasi → halaman Detail

`store()` mengarahkan ke `customers.show` (bukan `customers.index`) — konsisten dengan update/ticket/
task, dan mendaratkan user di record baru untuk lanjut workflow. Import massal tetap ke daftar
(list-oriented). Pola & visualisasi PRG lengkap: **[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

## Perubahan Terbaru (2026-08-12)

### 4. Pelanggan Gagal & Pelanggan Putus: route, permission, controller, DAN view sendiri

Tiga halaman daftar, tiga berkas Blade — bukan lagi satu `index.blade.php` 2.178 baris dengan
cabang `@if($statusGroup === 'failed') … @elseif('terminated')` di tengahnya.

| Halaman | Route | Permission | Controller | View |
| --- | --- | --- | --- | --- |
| List Pelanggan | `customers.index` | `customers.view` | `CustomerController::index()` | `customers/index.blade.php` |
| Pelanggan Putus | `customers.terminated` | `customers.terminated.view` | `CustomerTerminatedController` | `customers/terminated.blade.php` |
| Pelanggan Gagal | `customers.failed` | `customers.failed.view` | `CustomerFailedController` | `customers/failed.blade.php` |

Bagian yang identik di ketiganya hidup di `resources/views/customers/partials/`:
`_list_styles`, `_list_header`, `_list_stats`, `_list_filters`, `_list_pagination`,
`_list_density_script`.

Query/filter/pagination pindah dari `CustomerController` ke trait
`app/Http/Controllers/Concerns/RendersCustomerList.php`. Dua controller arsip sekarang
`extends Controller` (bukan `extends CustomerController`) — berhenti mewarisi method tulis
pelanggan yang bukan urusannya. Rincian signature: `docs/rbac/customer-permission-hierarchy.md`.

**Aturan:** jangan gabungkan lagi ketiga view. Grup `failed`/`terminated` dikunci dari controller
(argumen `$forcedStatusGroup`), jadi `$statusGroup` tidak boleh jadi cabang tampilan di Blade —
itu bikin dua sumber kebenaran. Penjaga: `CustomerListSeparateViewsTest`.

Ikut diperbaiki: form pemilih jumlah baris di footer pagination dulu `action="/customers"` hardcode,
sehingga mengubah "Baris" dari halaman Putus/Gagal melempar user balik ke List Pelanggan. Sekarang
`url()->current()`.

