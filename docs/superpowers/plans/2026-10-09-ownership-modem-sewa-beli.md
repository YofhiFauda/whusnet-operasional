# Ownership Modem (Sewa / Beli Sendiri / Beli dari Gudang) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sistem bisa bedain modem Sewa / Beli Sendiri / Beli dari Gudang di level behavior (bukan cuma label), sehingga DEAC gak narik modem yang udah terjual, dan penjualan modem dari gudang tercatat & tertagih.

**Architecture:** Dua kolom baru (`inventory_serials.ownership`, `customer_devices.acquisition_source`) + tabel baru `inventory_serial_sales`. Satu titik tulis (`InventoryService`) menegakkan konsistensi. Form existing (Pemasangan, Maintenance/C-REQ) di-gate dengan pilihan ownership sebelum manggil service. DEAC & billing baca flag ini, gak nebak.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, Blade+Alpine, sqlite test.

**Spec:** `docs/plan/task-teknisi/rancangan-ownership-modem-sewa-beli.md`

## Global Constraints

- `OwnershipMode` enum (installable/company_asset) TIDAK disentuh/ditumpangi — axis berbeda, sudah ditegaskan 3x di dokumen existing.
- Skema `Invoice`/`ManualInvoiceCategory` TIDAK diubah — breakdown jual modem di C-REQ tetap 1 nominal akhir ke `ManualCategoryInvoiceService::issue()`.
- Permission baru pakai konvensi codebase: sub-feature `warehouse_report.sold_to_customer` (parent ROOT `warehouse_report` yang sudah ada, SINGULAR — bukan `warehouse_reports` seperti draft awal spec).
- Semua query laporan baru wajib lewat `EffectiveAccessService::getAllowedPopIds()`/`hasAllPopAccess()` — tidak boleh bocor lintas cabang.
- `inventory_serials.ownership` default `company`, `customer_devices.acquisition_source` default `sewa` — data lama dibiarkan, tidak ada backfill.
- FK ke tabel keuangan/inventory baru pakai `restrictOnDelete()` konsisten aturan proyek.
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum tiap commit yang menyentuh PHP.

## Review Focus

- **Beli Sendiri dipilih tapi brand/model/serial manual kosong** — form harus reject, bukan nyimpen `customer_devices` kosong tanpa identitas device.
- **Beli dari Gudang dipilih tapi Harga Jual Modem 0/negatif/kosong** — validasi numeric `min:0.01`, jangan sampai `inventory_serial_sales.price=0` lolos (beda dari guard `PaymentObserver` tapi prinsipnya sama: nominal jual gak boleh nol).
- **TAMBAH_MODEM (`returnExistingSerial=false`, pelanggan dapat modem kedua) + Beli dari Gudang** — `customer_devices` (hasOne) gak ketimpa dari device utama, tapi `inventory_serials.ownership=customer` & `inventory_serial_sales` WAJIB tetap tercatat di SN kedua itu (DEAC baca `inventory_serials` langsung, bukan `customer_devices`).
- **DEAC pelanggan campuran** (sebagian SN company, sebagian customer) — daftar wajib tarik cuma yang `company`, bukan banner "gak perlu ditarik" nongol padahal masih ada SN company yang harus ditarik.
- **PSB breakdown invoice — invariant subtotal** — `InitialInvoiceService::calculate()` wajib ikut masukkan `modem_sale_amount` ke subtotal sebelum `InvoiceItemBuilder::rebuildFor()` dipanggil, kalau lupa → `assertMatchesSubtotal()` throw `InvalidArgumentException` dan PSB gagal total.

---

## File Structure

**Baru:**
- `app/Enums/DeviceOwnership.php` — `COMPANY`/`CUSTOMER`, dipakai `inventory_serials.ownership`.
- `app/Enums/DeviceAcquisitionSource.php` — `SEWA`/`BELI_SENDIRI`/`BELI_GUDANG`, dipakai `customer_devices.acquisition_source` + label/badge.
- `app/Models/InventorySerialSale.php` — model tabel `inventory_serial_sales`.
- `database/migrations/2026_10_09_090000_add_ownership_to_inventory_serials_table.php`
- `database/migrations/2026_10_09_090100_add_acquisition_source_to_customer_devices_table.php`
- `database/migrations/2026_10_09_090200_create_inventory_serial_sales_table.php`
- `database/migrations/2026_10_09_090300_add_jual_modem_revenue_subcategory.php` — insert subcategory baru (non-system) di bawah `RevenueCategory::CODE_JASA_PERBAIKAN`.
- `resources/views/components/warehouse/acquisition-badge.blade.php` — badge Sewa/Beli Sendiri/Beli dari Gudang, pola `origin-badge.blade.php`.
- `resources/views/warehouse/reports/sold-to-customer.blade.php` — Laporan Barang Terjual ke Pelanggan.
- Test: satu file per task sesuai daftar di §9 spec.

**Modifikasi:**
- `app/Services/InventoryService.php` — `installSerial()` tambah param ownership; method baru `recordCustomerOwnedDevice()`, `recordSerialSale()`.
- `app/Models/InventorySerial.php` — fillable+cast `ownership`.
- `app/Models/CustomerDevice.php` — fillable+cast `acquisition_source`.
- `app/Services/InitialInvoiceService.php` — `calculate()`/`issue()`/`lineSpecs()` terima `modemSaleAmount` opsional.
- `app/Http/Controllers/TaskMaintenanceController.php` — radio 4 opsi di `store()`.
- `resources/views/tasks/maintenance-report.blade.php` — UI radio + field kondisional.
- `app/Http/Controllers/CustomerInstallationController.php` — gate `contract_type` di `storePemasangan()`/`storeSpeedtest()`.
- `resources/views/installations/report.blade.php` — UI radio 2 opsi (kondisional `contract_type=beli`).
- `app/Http/Controllers/TaskCreqBillingController.php` — breakdown Harga Modem+Jasa di `approve()`.
- `resources/views/tasks/creq-billing/show.blade.php` — field breakdown kondisional.
- `app/Http/Controllers/TaskDeviceRetrievalController.php` — filter `installedSerials` + banner skip.
- `resources/views/tasks/device-retrieval-report.blade.php` — banner UI.
- `resources/views/customers/show.blade.php`, `resources/views/customers/partials/_list_table.blade.php` — pasang badge.
- `database/seeders/WarehouseFeatureSeeder.php`, `config/rbac.php`, `database/seeders/RolePermissionSeeder.php` — permission baru.
- `routes/web.php` — route laporan baru.
- `app/Http/Controllers/Warehouse/WarehouseReportController.php` — method `soldToCustomer()`.

---

## Task 1: Enum, migrasi kolom, model `InventorySerialSale`

**Files:**
- Create: `app/Enums/DeviceOwnership.php`
- Create: `app/Enums/DeviceAcquisitionSource.php`
- Create: `app/Models/InventorySerialSale.php`
- Create: `database/migrations/2026_10_09_090000_add_ownership_to_inventory_serials_table.php`
- Create: `database/migrations/2026_10_09_090100_add_acquisition_source_to_customer_devices_table.php`
- Create: `database/migrations/2026_10_09_090200_create_inventory_serial_sales_table.php`
- Modify: `app/Models/InventorySerial.php` (fillable array + `casts()`)
- Modify: `app/Models/CustomerDevice.php` (fillable array + `casts()`)
- Test: `tests/Unit/DeviceOwnershipEnumTest.php`

**Interfaces:**
- Produces: `DeviceOwnership::COMPANY|CUSTOMER` (string enum, values `'company'`/`'customer'`).
- Produces: `DeviceAcquisitionSource::SEWA|BELI_SENDIRI|BELI_GUDANG` (string enum, values `'sewa'`/`'beli_sendiri'`/`'beli_gudang'`), methods `label(): string`, `badgeVariant(): string` (returns `'info'`/`'warning'`/`'success'` per spec §7).
- Produces: `InventorySerialSale` model, fillable `['inventory_serial_id', 'customer_id', 'fop_task_id', 'price', 'created_by']`, casts `price` → `decimal:2`, relations `inventorySerial(): BelongsTo`, `customer(): BelongsTo`, `fopTask(): BelongsTo`, `createdBy(): BelongsTo` (`User::class`, `created_by`).
- Produces: `InventorySerial::$fillable` includes `'ownership'`, cast `'ownership' => DeviceOwnership::class`.
- Produces: `CustomerDevice::$fillable` includes `'acquisition_source'`, cast `'acquisition_source' => DeviceAcquisitionSource::class`.

- [ ] **Step 1: Write failing test for enum labels/badges**

```php
#[Test]
public function label_dan_badge_variant_sesuai_spec(): void
{
    $this->assertSame('Sewa', DeviceAcquisitionSource::SEWA->label());
    $this->assertSame('Beli Sendiri', DeviceAcquisitionSource::BELI_SENDIRI->label());
    $this->assertSame('Beli dari Gudang', DeviceAcquisitionSource::BELI_GUDANG->label());
    $this->assertSame('info', DeviceAcquisitionSource::SEWA->badgeVariant());
    $this->assertSame('warning', DeviceAcquisitionSource::BELI_SENDIRI->badgeVariant());
    $this->assertSame('success', DeviceAcquisitionSource::BELI_GUDANG->badgeVariant());
}
```

- [ ] **Step 2: Run test, verify fails** (class not found)

Run: `php artisan test --compact tests/Unit/DeviceOwnershipEnumTest.php`

- [ ] **Step 3: Implement both enums** per Interfaces block above.

- [ ] **Step 4: Write migrations** — `ownership` string(20) default `'company'` not null (after `condition_checked_by`); `acquisition_source` string(20) default `'sewa'` not null (after `device_retrieved_at`); `inventory_serial_sales` table exactly per spec §2.3 (`inventory_serial_id`, `customer_id`, `fop_task_id` nullable, `price` decimal(12,2), `created_by`, timestamps, all FK `restrictOnDelete()`).

- [ ] **Step 5: Implement `InventorySerialSale` model + modify `InventorySerial`/`CustomerDevice`** per Interfaces block.

- [ ] **Step 6: Run migrations in test DB + run test, verify passes**

Run: `php artisan test --compact tests/Unit/DeviceOwnershipEnumTest.php`
Expected: PASS

- [ ] **Step 7: `vendor/bin/pint --dirty --format agent`, then commit**

```bash
git add app/Enums/DeviceOwnership.php app/Enums/DeviceAcquisitionSource.php app/Models/InventorySerialSale.php app/Models/InventorySerial.php app/Models/CustomerDevice.php database/migrations/2026_10_09_09* tests/Unit/DeviceOwnershipEnumTest.php
git commit -m "feat: tambah kolom ownership modem & tabel inventory_serial_sales"
```

---

## Task 2: `InventoryService` — tulis ownership, device manual, sale record

**Files:**
- Modify: `app/Services/InventoryService.php` (`installSerial()` signature; dua method baru)
- Test: `tests/Feature/InventoryServiceOwnershipTest.php`

**Interfaces:**
- Consumes: `DeviceOwnership`, `DeviceAcquisitionSource`, `InventorySerialSale` dari Task 1.
- Produces:
  ```php
  public function installSerial(
      InventorySerial $serial,
      Customer $customer,
      FopTask $fopTask,
      iterable $technicians,
      User $actor,
      bool $returnExistingSerial = true,
      DeviceOwnership $ownership = DeviceOwnership::COMPANY,
  ): InventoryTransaction
  ```
  (param baru di akhir, default `COMPANY` — semua call site existing tetap valid tanpa ubahan.)
  ```php
  public function recordCustomerOwnedDevice(Customer $customer, array $deviceAttributes, User $actor): CustomerDevice
  ```
  (`$deviceAttributes` wajib berisi `brand`, `model`, `serial_number`; set `device_type='ONT'`, `acquisition_source=DeviceAcquisitionSource::BELI_SENDIRI`, `device_retrieved_at=null`, lewat `updateOrCreate(['customer_id' => $customer->id], ...)` — pola sama blok existing di `installSerial()`.)
  ```php
  public function recordSerialSale(InventorySerial $serial, Customer $customer, ?FopTask $fopTask, float $price, User $actor): InventorySerialSale
  ```
  (create `InventorySerialSale`; `$price` harus > 0, kalau tidak lempar `InvalidArgumentException` — bukan `ValidationException`, sesuai pola guard existing di method ini yang nganggap ini bug pemanggil bukan input form salah.)

- [ ] **Step 1: Write failing test — `installSerial()` menulis ownership & acquisition_source**

```php
#[Test]
public function install_serial_dengan_ownership_customer_menulis_flag_di_sn_dan_customer_device(): void
{
    // Arrange: Item INSTALLABLE, InventorySerial status ISSUED current_technician_id=$technician->id
    $service = app(InventoryService::class);
    $service->installSerial($serial, $customer, $fopTask, [$technician], $actor, true, DeviceOwnership::CUSTOMER);

    $this->assertSame(DeviceOwnership::CUSTOMER, $serial->refresh()->ownership);
    $this->assertSame(DeviceAcquisitionSource::BELI_GUDANG, $customer->customerDevice->refresh()->acquisition_source);
}

#[Test]
public function install_serial_default_ownership_company_menulis_acquisition_source_sewa(): void
{
    // panggil installSerial() TANPA param ownership — harus default COMPANY/sewa
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/InventoryServiceOwnershipTest.php`

- [ ] **Step 3: Implement.** Di blok `$serial->update([...])` (baris ~256-262 existing) tambah `'ownership' => $ownership->value`. Di blok `if ($returnExistingSerial)` (baris ~267-293 existing) tambah `'acquisition_source' => $ownership === DeviceOwnership::CUSTOMER ? DeviceAcquisitionSource::BELI_GUDANG->value : DeviceAcquisitionSource::SEWA->value` ke array `updateOrCreate` yang sudah ada.

- [ ] **Step 4: Write failing test — `recordCustomerOwnedDevice()` & `recordSerialSale()`**

```php
#[Test]
public function record_customer_owned_device_simpan_tanpa_sentuh_inventory_serials(): void
{
    $device = app(InventoryService::class)->recordCustomerOwnedDevice($customer, [
        'brand' => 'TP-Link', 'model' => 'Archer C6', 'serial_number' => 'MANUAL-001',
    ], $actor);

    $this->assertSame(DeviceAcquisitionSource::BELI_SENDIRI, $device->acquisition_source);
    $this->assertDatabaseCount('inventory_serials', 0);
}

#[Test]
public function record_serial_sale_tolak_harga_nol(): void
{
    $this->expectException(\InvalidArgumentException::class);
    app(InventoryService::class)->recordSerialSale($serial, $customer, null, 0.0, $actor);
}
```

- [ ] **Step 5: Run tests, verify fail, then implement both methods** per Interfaces block.

- [ ] **Step 6: Run full test file, verify all pass**

Run: `php artisan test --compact tests/Feature/InventoryServiceOwnershipTest.php`

- [ ] **Step 7: Pint + commit**

```bash
git add app/Services/InventoryService.php tests/Feature/InventoryServiceOwnershipTest.php
git commit -m "feat: InventoryService catat ownership modem, device beli sendiri, dan sale record"
```

---

## Task 3: Radio ownership di Ganti/Tambah Modem (MTN & C-REQ)

**Files:**
- Modify: `app/Http/Controllers/TaskMaintenanceController.php` (`store()`, sekitar baris 240-320 untuk validasi, 483-487 untuk eksekusi)
- Modify: `resources/views/tasks/maintenance-report.blade.php` (section 219-257)
- Test: `tests/Feature/TaskMaintenanceOwnershipRadioTest.php`

**Interfaces:**
- Consumes: `InventoryService::installSerial()`, `recordCustomerOwnedDevice()`, `recordSerialSale()` (Task 2); `CReqCategory::requiresModem()`/`addsModemWithoutReturningExisting()` (existing, tidak diubah).
- Produces: request field `modem_ownership_choice` (`tidak_ganti`|`sewa_gudang`|`beli_sendiri`|`beli_gudang`), `manual_device_brand`, `manual_device_model`, `manual_device_serial_number`, `modem_sale_price` — dipakai task lain yang ikut ubah form serupa (Task 4 pakai field manual sama, bukan field `modem_ownership_choice` yang beda nama, lihat Task 4).

- [ ] **Step 1: Write failing test — radio wajib begitu SN dipilih**

```php
#[Test]
public function pilih_sn_tanpa_modem_ownership_choice_ditolak(): void
{
    // POST store() dengan selected_inventory_serial_id terisi, modem_ownership_choice KOSONG
    $response->assertSessionHasErrors('modem_ownership_choice');
}

#[Test]
public function beli_sendiri_wajib_isi_brand_model_serial_manual(): void
{
    // modem_ownership_choice=beli_sendiri, manual_device_brand kosong → error
}

#[Test]
public function beli_dari_gudang_installserial_dengan_ownership_customer_dan_sale_tercatat(): void
{
    // modem_ownership_choice=beli_gudang, modem_sale_price=150000
    // assert InventorySerial::ownership === CUSTOMER, InventorySerialSale dibuat dgn price 150000
}

#[Test]
public function sewa_gudang_flow_sama_seperti_sekarang(): void
{
    // modem_ownership_choice=sewa_gudang → ownership company, acquisition_source sewa, perilaku installSerial tidak berubah dari existing test TaskMaintenanceModemInstallTest
}

#[Test]
public function tambah_modem_beli_dari_gudang_tetap_catat_ownership_di_sn_walau_customer_devices_tidak_disentuh(): void
{
    // CReqCategory::TAMBAH_MODEM (returnExistingSerial=false), modem_ownership_choice=beli_gudang
    // customer sudah punya SN pertama (ownership=company, acquisition_source=sewa) sebelumnya
    // assert SN KEDUA ownership=customer & InventorySerialSale tercatat untuk SN kedua itu
    // assert customer_devices TIDAK berubah (masih reflect device pertama) — limitasi hasOne, bukan bug
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskMaintenanceOwnershipRadioTest.php`

- [ ] **Step 3: Implement validasi di `store()`.** Tambah ke `$rules` (dekat baris 240):
  ```php
  'modem_ownership_choice' => ['required_with:selected_inventory_serial_id', Rule::in(['tidak_ganti', 'sewa_gudang', 'beli_sendiri', 'beli_gudang'])],
  'manual_device_brand' => ['required_if:modem_ownership_choice,beli_sendiri', 'nullable', 'string', 'max:100'],
  'manual_device_model' => ['required_if:modem_ownership_choice,beli_sendiri', 'nullable', 'string', 'max:100'],
  'manual_device_serial_number' => ['required_if:modem_ownership_choice,beli_sendiri', 'nullable', 'string', 'max:100'],
  'modem_sale_price' => ['required_if:modem_ownership_choice,beli_gudang', 'nullable', 'numeric', 'min:0.01'],
  ```
  Catatan: `beli_sendiri` TIDAK butuh `selected_inventory_serial_id` — longgarkan rule `selected_inventory_serial_id` existing jadi `nullable` kalau `modem_ownership_choice=beli_sendiri` (override `Rule::in($eligibleSerialIds)` cuma berlaku kalau field itu diisi).

- [ ] **Step 4: Implement eksekusi di blok sekitar baris 483-487.** Branch by `$validated['modem_ownership_choice'] ?? 'tidak_ganti'`:
  - `tidak_ganti` → skip semua (seperti existing).
  - `sewa_gudang` → `installSerial(..., ownership: DeviceOwnership::COMPANY)` (perilaku sama existing).
  - `beli_gudang` → `installSerial(..., ownership: DeviceOwnership::CUSTOMER)`, lalu `recordSerialSale($serial, $task->customer, $fopTask, (float) $validated['modem_sale_price'], auth()->user())`.
  - `beli_sendiri` → `recordCustomerOwnedDevice($task->customer, ['brand' => ..., 'model' => ..., 'serial_number' => ...], auth()->user())` — TIDAK memanggil `installSerial()`.

- [ ] **Step 5: Implement UI di blade.** Tambah radio group 4 opsi di atas combobox existing (baris ~219-257); `x-data`/Alpine toggle nampilin: combobox SN (kalau sewa_gudang/beli_gudang), 3 input manual (kalau beli_sendiri), 1 input harga (kalau beli_gudang). Pakai pola Alpine show/hide yang sudah umum dipakai file blade lain di repo (cek `x-show` existing di file yang sama atau file sejenis sebelum menulis).

- [ ] **Step 6: Run tests, verify pass**

Run: `php artisan test --compact tests/Feature/TaskMaintenanceOwnershipRadioTest.php tests/Feature/TaskMaintenanceModemInstallTest.php`
Expected: PASS semua (termasuk regresi test lama)

- [ ] **Step 7: Pint + commit**

```bash
git add app/Http/Controllers/TaskMaintenanceController.php resources/views/tasks/maintenance-report.blade.php tests/Feature/TaskMaintenanceOwnershipRadioTest.php
git commit -m "feat: radio ownership modem di laporan Maintenance/C-REQ"
```

---

## Task 4: Gate `contract_type` di Laporan Pemasangan (PSB)

**Files:**
- Modify: `app/Http/Controllers/CustomerInstallationController.php` (`storePemasangan()` ~986-1283, `storeSpeedtest()` ~1354-1541)
- Modify: `resources/views/installations/report.blade.php`
- Test: `tests/Feature/CustomerInstallationOwnershipTest.php`

**Interfaces:**
- Consumes: `InventoryService::installSerial()`, `recordCustomerOwnedDevice()`, `recordSerialSale()` (Task 2).
- Produces: request field `modem_acquisition_choice` (`beli_sendiri`|`beli_gudang`, cuma relevan & required kalau `customer.customerService.contract_type === 'beli'`), reuse `manual_device_brand`/`manual_device_model`/`manual_device_serial_number`/`modem_sale_price` dari Task 3 (nama field sama, biar konsisten).

- [ ] **Step 1: Write failing test — contract_type=sewa tidak berubah**

```php
#[Test]
public function contract_type_sewa_flow_installasi_tidak_berubah(): void
{
    // regresi: pemasangan normal, assert ownership=company, acquisition_source=sewa, invoice AWAL tidak ada baris Jual Modem
}

#[Test]
public function contract_type_beli_wajib_pilih_modem_acquisition_choice(): void
{
    // POST storePemasangan tanpa modem_acquisition_choice saat contract_type=beli → error
}

#[Test]
public function contract_type_beli_beli_sendiri_tidak_panggil_installserial(): void
{
    // assert tidak ada InventorySerial baru ter-install, customer_devices acquisition_source=beli_sendiri
}

#[Test]
public function contract_type_beli_beli_dari_gudang_installserial_ownership_customer_dan_invoice_dapat_baris_jual_modem(): void
{
    // storeSpeedtest() complete → assert InventorySerialSale tercatat, Invoice AWAL punya InvoiceItem subcategory Jual Modem sebesar modem_sale_price
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/CustomerInstallationOwnershipTest.php`

- [ ] **Step 3: Implement di `storePemasangan()` (~1057).** Tambah validasi: `modem_acquisition_choice` wajib HANYA kalau `$customer->customerService->contract_type === 'beli'` (`Rule::requiredIf(...)`); longgarkan `selected_inventory_serial_id` jadi tidak-wajib kalau pilihan `beli_sendiri`. Simpan pilihan + field manual sebagai draft pointer di `customer_installations` (kolom baru — lihat Step 3b) seperti pola `selected_inventory_serial_id` existing (baris 1178).

  **Step 3b (bagian migrasi tambahan untuk task ini):** Tambah ke Task 1 migration list — kolom `modem_acquisition_choice`, `manual_device_brand`, `manual_device_model`, `manual_device_serial_number`, `modem_sale_price` nullable di `customer_installations` (draft fields, pola sama `selected_inventory_serial_id` existing). *(Catatan implementer: migrasi ini idealnya digabung ke Task 1 kalau Task 1 belum di-commit; kalau sudah, buat migration tambahan baru di task ini dengan timestamp setelah Task 1.)*

- [ ] **Step 4: Implement di `storeSpeedtest()` (~1497-1500) branch eksekusi**, sama persis pola Task 3 Step 4 tapi baca dari `$installation->modem_acquisition_choice` dkk, dan untuk `beli_gudang` TERUSKAN `modem_sale_price` ke `InitialInvoiceService` (lihat Task 5 — simpan di variabel lokal, dilempar ke pemanggil `InitialInvoiceService::issue()` yang terjadi di titik lain setelah speedtest, cari call site existing sebelum implementasi).

- [ ] **Step 5: Implement UI di `installations/report.blade.php`** — radio 2 opsi muncul HANYA kalau `$customer->customerService->contract_type === 'beli'` (blade `@if`), pakai komponen input sama seperti Task 3 Step 5.

- [ ] **Step 6: Run tests, verify pass**

Run: `php artisan test --compact tests/Feature/CustomerInstallationOwnershipTest.php tests/Feature/InstallationSerialDropdownAuthorityTest.php`
Expected: PASS semua (termasuk regresi)

- [ ] **Step 7: Pint + commit**

```bash
git add app/Http/Controllers/CustomerInstallationController.php resources/views/installations/report.blade.php database/migrations/2026_10_09_09*customer_installations* tests/Feature/CustomerInstallationOwnershipTest.php
git commit -m "feat: gate contract_type sewa/beli di Laporan Pemasangan"
```

---

## Task 5: Baris "Jual Modem" di Tagihan AWAL (`InitialInvoiceService`)

**Files:**
- Modify: `database/migrations/2026_10_09_090300_add_jual_modem_revenue_subcategory.php` (buat di Task 1, dipakai di sini)
- Modify: `app/Services/InitialInvoiceService.php` (`calculate()` ~67-128, `issue()` ~147-188, `lineSpecs()` ~254-288)
- Test: `tests/Unit/InitialInvoiceModemSaleLineTest.php`

**Interfaces:**
- Consumes: hasil `modem_sale_price` dari Task 4 (dilempar sebagai parameter baru, bukan dibaca ulang dari DB di service ini — service tetap tidak tahu soal `CustomerInstallation`).
- Produces:
  ```php
  public function calculate(CustomerServiceModel $service, array $fees, float $modemSaleAmount = 0.0): array
  public function issue(Customer $customer, ..., float $modemSaleAmount = 0.0): Invoice
  ```
  (cek signature exact existing `calculate()`/`issue()` sebelum ubah — tambahkan param baru di AKHIR daftar parameter dengan default 0.0, supaya call site lama tetap valid.)

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function modem_sale_amount_masuk_subtotal_dan_jadi_baris_jual_modem(): void
{
    $invoice = app(InitialInvoiceService::class)->issue($customer, ..., modemSaleAmount: 150000.0);
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'subcategory_name_snapshot' => 'Jual Modem',
        'amount' => 150000.0,
    ]);
    // invariant: SUM(invoice_items.amount) == invoice.subtotal (ditegakkan InvoiceItemBuilder sendiri — test ini cuma pastikan gak throw)
}

#[Test]
public function modem_sale_amount_default_nol_tidak_menambah_baris(): void
{
    // panggil issue() tanpa modemSaleAmount — invoice_items TIDAK punya baris Jual Modem
}
```

- [ ] **Step 2: Run test, verify fail**

Run: `php artisan test --compact tests/Unit/InitialInvoiceModemSaleLineTest.php`

- [ ] **Step 3: Migrasi seed subcategory baru.** Insert row `revenue_subcategories`: `code='jual_modem'`, `revenue_category_id` = id `RevenueCategory::CODE_JASA_PERBAIKAN`, `name='Jual Modem'`, `is_system=false` (pola sama baris `tambah_kabel` existing).

- [ ] **Step 4: Implement.** `calculate()` tambah `$modemSaleAmount` ke komponen subtotal (baca rumus existing dulu, tambahkan sebagai komponen baru paralel `extra_cable_fee` dkk). `lineSpecs()` tambah entry ke-6:
  ```php
  ['category_code' => RevenueCategory::CODE_JASA_PERBAIKAN, 'subcategory_code' => 'jual_modem', 'description' => null, 'amount' => $billing['modem_sale_amount']],
  ```
  `InvoiceItemBuilder::rebuildFor()` sudah otomatis skip baris bernominal 0 — tidak perlu guard tambahan.

- [ ] **Step 5: Run test, verify pass**

Run: `php artisan test --compact tests/Unit/InitialInvoiceModemSaleLineTest.php`

- [ ] **Step 6: Pint + commit**

```bash
git add database/migrations/2026_10_09_090300_add_jual_modem_revenue_subcategory.php app/Services/InitialInvoiceService.php tests/Unit/InitialInvoiceModemSaleLineTest.php
git commit -m "feat: baris Jual Modem di tagihan AWAL saat Beli dari Gudang"
```

---

## Task 6: Breakdown Harga Modem + Jasa di Verifikasi C-REQ

**Files:**
- Modify: `app/Http/Controllers/TaskCreqBillingController.php` (`approve()` ~111-187)
- Modify: `resources/views/tasks/creq-billing/show.blade.php` (~653-670)
- Test: `tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php`

**Interfaces:**
- Consumes: `InventorySerialSale` (Task 1/2/3) — dicari via `$task->fopTask` relasi ke `inventory_serial_sales.fop_task_id` (atau relasi yang lebih langsung kalau `Task`↔`FopTask`↔sale sudah ada; cek relasi existing sebelum implement, tambah scope/relasi kecil kalau belum ada).

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function approve_dengan_sale_row_sum_harga_modem_dan_jasa_jadi_satu_invoice(): void
{
    // task punya InventorySerialSale price=150000 dari laporan Beli dari Gudang
    // POST approve() dengan modem_price=150000, service_fee=50000 (amount TIDAK dikirim)
    // assert Invoice::total_amount === 200000, description mengandung 'Jasa: Rp50.000' dan 'Jual Modem: Rp150.000'
    // assert InventorySerialSale::price di-update jadi 150000 (walau sama, pastikan kode nulis balik)
}

#[Test]
public function approve_tanpa_sale_row_flow_lama_tidak_berubah(): void
{
    // task TANPA InventorySerialSale → field amount biasa tetap jalan seperti TaskCreqBillingReportTest existing
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php`

- [ ] **Step 3: Implement.** Di awal `approve()`, cek `$sale = InventorySerialSale::where('fop_task_id', $task->fop_task_id)->first();` (sesuaikan nama kolom join yang benar setelah baca relasi `Task`/`FopTask` existing). Kalau `$sale` ada: validasi `modem_price`/`service_fee` (`required|numeric|min:0`) bukan `amount`; `$amount = $modem_price + $service_fee`; `$description = "Jasa: Rp{$service_fee} + Jual Modem: Rp{$modem_price}"` (pakai helper format rupiah existing kalau ada, cek `FormatHelper`); update `$sale->update(['price' => $modem_price])` DI DALAM transaction yang sama dengan `ManualCategoryInvoiceService::issue()`. Kalau `$sale` tidak ada: validasi & flow existing APA ADANYA (`amount` field biasa), tidak ada baris kode baru yang jalan.

- [ ] **Step 4: Implement UI kondisional di blade** — kalau `$task` punya sale row, tampilkan 2 input (Harga Modem prefilled, Biaya Jasa) sebagai pengganti input `amount` tunggal (sembunyikan/hilangkan field `amount` dari DOM kalau kondisi ini aktif, biar gak ada 2 sumber nilai).

- [ ] **Step 5: Run tests, verify pass + regresi**

Run: `php artisan test --compact tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php tests/Feature/TaskCreqBillingReportTest.php`
Expected: PASS semua

- [ ] **Step 6: Pint + commit**

```bash
git add app/Http/Controllers/TaskCreqBillingController.php resources/views/tasks/creq-billing/show.blade.php tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php
git commit -m "feat: breakdown Harga Modem + Jasa di Verifikasi C-REQ"
```

---

## Task 7: DEAC skip SN milik pelanggan

**Files:**
- Modify: `app/Http/Controllers/TaskDeviceRetrievalController.php` (`report()` ~38-63, `store()` ~65-149)
- Modify: `resources/views/tasks/device-retrieval-report.blade.php`
- Test: `tests/Feature/DeviceRetrievalSkipsCustomerOwnedSerialTest.php`

**Interfaces:**
- Consumes: `DeviceOwnership` (Task 1).

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function sn_ownership_customer_tidak_muncul_di_installed_serials(): void
{
    // 1 SN ownership=customer, 1 SN ownership=company untuk pelanggan yang sama
    $response = $this->get(route('tasks.device-retrieval.report', $task));
    $response->assertViewHas('installedSerials', fn ($serials) => $serials->pluck('id')->doesntContain($customerOwnedSerial->id) && $serials->pluck('id')->contains($companyOwnedSerial->id));
}

#[Test]
public function semua_sn_customer_owned_tampilkan_banner_dan_submit_otomatis_diambil_tanpa_serial(): void
{
    // semua SN pelanggan ownership=customer → installedSerials kosong
    $response->assertSee('tidak perlu ditarik');
    // POST store() dengan outcome=diambil, serials=[] → TIDAK gagal validasi (guard dilonggarkan khusus kondisi ini)
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/DeviceRetrievalSkipsCustomerOwnedSerialTest.php`

- [ ] **Step 3: Implement di `report()` (~38-63).** Tambah `->where('ownership', DeviceOwnership::COMPANY->value)` ke query `installedSerials`. Hitung `$allCustomerOwned = $task->customer->inventorySerials()->where('status', SerialStatus::INSTALLED)->exists() && $installedSerials->isEmpty();` (sesuaikan nama relasi existing), kirim ke view sebagai `$showOwnershipSkipBanner`.

- [ ] **Step 4: Implement di `store()` (~83-98).** Longgarkan rule `serials` — ganti `required_if:outcome,diambil` jadi custom: tetap required_if KECUALI `$allCustomerOwned` true (pola `Rule::requiredIf(fn () => ... && ! $allCustomerOwned)`, hitung `$allCustomerOwned` ulang di awal `store()` sama seperti `report()`). Kalau `$allCustomerOwned` dan `outcome=diambil` dan `serials` kosong, set `notes` auto `'Modem milik pelanggan, tidak perlu ditarik'` sebelum create `TaskDeviceRetrieval` (jangan andalkan input user untuk notes di kondisi ini).

- [ ] **Step 5: Implement banner di blade** — `@if($showOwnershipSkipBanner)` tampilkan info box "Modem pelanggan ini milik sendiri/sudah dibeli — tidak perlu ditarik" + submit button konfirmasi tanpa form SN.

- [ ] **Step 6: Run tests + regresi**

Run: `php artisan test --compact tests/Feature/DeviceRetrievalSkipsCustomerOwnedSerialTest.php tests/Feature/DeviceRetrievalDeacToWarehouseTest.php tests/Feature/DeviceRetrievalHistoryAndWalkInTest.php`
Expected: PASS semua

- [ ] **Step 7: Pint + commit**

```bash
git add app/Http/Controllers/TaskDeviceRetrievalController.php resources/views/tasks/device-retrieval-report.blade.php tests/Feature/DeviceRetrievalSkipsCustomerOwnedSerialTest.php
git commit -m "feat: DEAC skip modem yang sudah milik pelanggan"
```

---

## Task 8: Badge label di Detail Pelanggan & List Pelanggan

**Files:**
- Create: `resources/views/components/warehouse/acquisition-badge.blade.php`
- Modify: `resources/views/customers/show.blade.php`
- Modify: `resources/views/customers/partials/_list_table.blade.php` (~183, ~320)
- Test: `tests/Feature/CustomerDetailShowsAcquisitionSourceBadgeTest.php`

**Interfaces:**
- Consumes: `DeviceAcquisitionSource` (Task 1), `CustomerDevice::$acquisition_source`.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function detail_pelanggan_tampilkan_badge_beli_dari_gudang(): void
{
    // customerDevice->acquisition_source = BELI_GUDANG
    $response = $this->get(route('customers.show', $customer));
    $response->assertSee('Beli dari Gudang');
}

#[Test]
public function list_pelanggan_tampilkan_badge_sesuai_acquisition_source(): void
{
    $response = $this->get(route('customers.index'));
    $response->assertSee('Beli Sendiri');
}
```

- [ ] **Step 2: Run tests, verify fail**

- [ ] **Step 3: Implement komponen** — `@props(['device' => null])`, `@if($device?->acquisition_source)` render `<x-ui.badge :variant="$device->acquisition_source->badgeVariant()">{{ $device->acquisition_source->label() }}</x-ui.badge>`, pola identik `origin-badge.blade.php` (Task 1 research item #1).

- [ ] **Step 4: Pasang komponen** di `customers.show` (dekat info device existing) dan `_list_table.blade.php` (baris 183/320, dekat kolom `contract_type` existing — SANDINGKAN, jangan hapus kolom lama di task ini kecuali diminta eksplisit).

- [ ] **Step 5: Run tests, verify pass**

Run: `php artisan test --compact tests/Feature/CustomerDetailShowsAcquisitionSourceBadgeTest.php tests/Feature/CustomerListTest.php`

- [ ] **Step 6: Pint + commit**

```bash
git add resources/views/components/warehouse/acquisition-badge.blade.php resources/views/customers/show.blade.php resources/views/customers/partials/_list_table.blade.php tests/Feature/CustomerDetailShowsAcquisitionSourceBadgeTest.php
git commit -m "feat: badge Sewa/Beli Sendiri/Beli dari Gudang di Detail & List Pelanggan"
```

---

## Task 9: Laporan Barang Terjual ke Pelanggan

**Files:**
- Modify: `database/seeders/WarehouseFeatureSeeder.php` (tambah sub-feature `warehouse_report.sold_to_customer`)
- Modify: `config/rbac.php` (action + label untuk sub-feature baru, ~dekat baris 619-621)
- Modify: `database/seeders/RolePermissionSeeder.php` (grant ke role yang akses `warehouse_report.view` sekarang)
- Modify: `routes/web.php` (~941-946, group permission baru)
- Modify: `app/Http/Controllers/Warehouse/WarehouseReportController.php` (method `soldToCustomer()`)
- Create: `resources/views/warehouse/reports/sold-to-customer.blade.php`
- Test: `tests/Feature/WarehouseSoldToCustomerReportScopedByPopTest.php`

**Interfaces:**
- Consumes: `InventorySerialSale` (Task 1), `EffectiveAccessService::getAllowedPopIds()`/`hasAllPopAccess()` (existing).
- Produces: route `warehouse.reports.sold-to-customer` (GET), permission `warehouse_report.sold_to_customer.view`.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function laporan_sold_to_customer_terpotong_pop_scope(): void
{
    // user scope selected_pop = POP A, sale ada di POP A dan POP B
    $response = $this->actingAs($user)->get(route('warehouse.reports.sold-to-customer'));
    $response->assertSee($saleDiPopA->customer->name);
    $response->assertDontSee($saleDiPopB->customer->name);
}

#[Test]
public function tanpa_permission_sold_to_customer_ditolak(): void
{
    $response = $this->actingAs($userTanpaPermission)->get(route('warehouse.reports.sold-to-customer'));
    $response->assertForbidden();
}
```

- [ ] **Step 2: Run test, verify fail** (route not found)

- [ ] **Step 3: Seed permission.** Tambah ke `WarehouseFeatureSeeder.php`: sub-feature `['parent' => 'warehouse_report', 'code' => 'warehouse_report.sold_to_customer', 'name' => 'Laporan Barang Terjual ke Pelanggan', 'sort_order' => <next>]`, pola identik contoh `tickets.selesai` (research item #9). `config/rbac.php` tambah `'warehouse_report.sold_to_customer' => [ActionCode::VIEW->value]` + label `'warehouse_report.sold_to_customer.view' => 'Lihat Laporan Barang Terjual ke Pelanggan'`. `RolePermissionSeeder.php` grant permission string ini ke role yang sudah punya `warehouse_report.view` (cek daftar role existing sebelum nambah baris).

- [ ] **Step 4: Implement route** — group baru di `routes/web.php` dekat baris 941-946: `Route::middleware('permission:warehouse_report.sold_to_customer.view')->group(fn () => Route::get('/warehouse/reports/sold-to-customer', [WarehouseReportController::class, 'soldToCustomer'])->name('warehouse.reports.sold-to-customer'));`

- [ ] **Step 5: Implement `soldToCustomer()`** — query `InventorySerialSale::with(['inventorySerial.item', 'customer', 'fopTask'])`, filter POP pelanggan pakai `EffectiveAccessService::getAllowedPopIds()`/`hasAllPopAccess()` (pola sama laporan gudang lain — baca implementasi `WarehouseReportController::index()` existing sebelum menulis query baru), kembalikan view dengan kolom sesuai spec §8 (tanggal, SN, nama barang, pelanggan, cabang, harga, sumber PSB/C-REQ-MTN dari `fop_task_id` null-check, `created_by`).

- [ ] **Step 6: Implement view** `sold-to-customer.blade.php` — tabel sederhana, pola sama halaman laporan gudang lain yang sudah ada (ikuti struktur `resources/views/warehouse/reports/index.blade.php` existing).

- [ ] **Step 7: Run migrations/seeders di test env, run tests, verify pass**

Run: `php artisan test --compact tests/Feature/WarehouseSoldToCustomerReportScopedByPopTest.php`

- [ ] **Step 8: Pint + commit**

```bash
git add database/seeders/WarehouseFeatureSeeder.php config/rbac.php database/seeders/RolePermissionSeeder.php routes/web.php app/Http/Controllers/Warehouse/WarehouseReportController.php resources/views/warehouse/reports/sold-to-customer.blade.php tests/Feature/WarehouseSoldToCustomerReportScopedByPopTest.php
git commit -m "feat: Laporan Barang Terjual ke Pelanggan dengan POP scope"
```

---

## Task 10: Regresi akhir & dokumentasi

**Files:**
- Modify: `docs/TASKS.md` (pindah task ke Done)
- Modify: `docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md` (tandai Gap 1 status selesai, link ke spec+plan ini)

- [ ] **Step 1: Jalankan seluruh test file yang disentuh task 1-9 sekaligus (bukan full suite)**

Run: `php artisan test --compact tests/Unit/DeviceOwnershipEnumTest.php tests/Feature/InventoryServiceOwnershipTest.php tests/Feature/TaskMaintenanceOwnershipRadioTest.php tests/Feature/TaskMaintenanceModemInstallTest.php tests/Feature/CustomerInstallationOwnershipTest.php tests/Feature/InstallationSerialDropdownAuthorityTest.php tests/Unit/InitialInvoiceModemSaleLineTest.php tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php tests/Feature/TaskCreqBillingReportTest.php tests/Feature/DeviceRetrievalSkipsCustomerOwnedSerialTest.php tests/Feature/DeviceRetrievalDeacToWarehouseTest.php tests/Feature/DeviceRetrievalHistoryAndWalkInTest.php tests/Feature/CustomerDetailShowsAcquisitionSourceBadgeTest.php tests/Feature/CustomerListTest.php tests/Feature/WarehouseSoldToCustomerReportScopedByPopTest.php`

Expected: PASS semua.

- [ ] **Step 2: Update `docs/TASKS.md`** — tandai fitur ini selesai, task berikutnya (kalau ada) ke In Progress.

- [ ] **Step 3: Update dokumen analisa Gap 1** — tambah catatan status "SELESAI, lihat spec+plan [tanggal]" di bagian atas, jangan hapus isi Q&A lama (jejak keputusan).

- [ ] **Step 4: Commit**

```bash
git add docs/TASKS.md docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md
git commit -m "docs: tandai Gap 1 ownership modem selesai"
```

- [ ] **Step 5: Beri tahu user bahwa full suite (`composer test` tanpa filter) perlu dijalankan user sendiri sesuai aturan proyek — JANGAN jalankan dari sini.**
