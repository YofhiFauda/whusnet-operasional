# Perangkat Tambahan dari Gudang (ADHOC-190) Implementation Plan

> **Revisi 2026-10-20:** Task 3 tentang penolakan assign karena custody kurang sudah diganti oleh keputusan user: FOP assign dahulu, Gudang issue kemudian. Bagian Task 3 di bawah adalah riwayat implementasi awal; ikuti spesifikasi yang telah direvisi dan tes terbaru untuk perilaku aktif.
> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perangkat tambahan (AP/STB) jadi stok Gudang ber-SN — diissue ke teknisi lewat form issue yang sudah ada, dipasang lewat langkah sendiri di Laporan Pemasangan (bukan diketik ulang), dan ditarik balik otomatis lewat alur DEAC yang sudah ada — dengan kontrol kecukupan barang di titik FOP assign/jadwal task, bukan dibebankan ke teknisi di lapangan.

**Architecture:** Tandai kategori barang (`item_categories.is_customer_extra_device`) supaya AP/STB bisa dibedakan dari perangkat utama di semua titik yang sudah ada (CustomerDeviceProfileService, custody query, DEAC hint query) tanpa form Gudang baru. Tambah kolom tautan stok ke `customer_additional_devices` (v1, free-text) supaya baris baru menunjuk `InventorySerial` yang sama dipakai alur install (`InventoryService::installSerial()`, `returnExistingSerial:false` — supaya instalasi AP tidak mengembalikan ONT utama) dan alur retrieval (`InventoryReassignService::pickupSerialFromCustomer()`, sudah repeatable-SN). Kontrol kecukupan dipindah ke `TaskService::create()`/`update()` saat task PEMASANGAN pindah ke `TERJADWAL`.

**Tech Stack:** Laravel 13, PHPUnit 12, Blade (vanilla JS pola existing — lihat modal Perangkat Tambahan v1), SQLite `:memory:` test.

**Spec:** `docs/superpowers/specs/2026-10-16-perangkat-tambahan-gudang-design.md` (dan pendahulunya `docs/superpowers/specs/2026-10-15-perangkat-tambahan-paket-design.md`, `docs/superpowers/specs/2026-10-16-analisa-perangkat-tambahan-gudang-dan-laporan.md`)

## Global Constraints

- `item_categories.is_customer_extra_device` boolean, default `false`. Hanya kategori `access_point` (sudah ada, `ItemCategorySeeder::CODE_ACCESS_POINT`) dan kategori STB baru diset `true`.
- `customer_additional_devices` kolom baru: `inventory_serial_id` (FK nullable ke `inventory_serials`, `nullOnDelete`), `status` (string: `terpasang`|`ditarik`, default `terpasang`), `retrieved_at` (timestamp nullable). Baris v1 lama (free-text, `inventory_serial_id` null) TIDAK dimigrasi, tetap tampil label "tanpa stok".
- Instalasi perangkat tambahan WAJIB `InventoryService::installSerial(..., returnExistingSerial: false, ownership: DeviceOwnership::COMPANY)` — `returnExistingSerial: true` (default) akan meretur SN ONT utama pelanggan, itu bug, bukan perilaku yang diinginkan.
- Guard cabang (ADHOC-159, `issued_from_pop_id` vs `Pop::resolveCabangId($fopTask->pop_id)`) sudah built-in di `installSerial()` — jangan diduplikasi atau dilonggarkan.
- Kontrol kecukupan FOP: hanya berlaku kalau `task_type === PEMASANGAN` DAN pelanggan punya `internetPackage->max_additional_devices > 0` DAN task pindah ke status `TERJADWAL` (ada `team_member_ids` + `scheduled_at`). Tidak berlaku untuk task_type lain, tidak berlaku untuk paket limit 0.
- Jalur "lapor kekurangan": field `kekurangan_perangkat` (boolean) + `kekurangan_perangkat_alasan` (string, required kalau `kekurangan_perangkat=true`) pada submit langkah Perangkat Tambahan. Tidak membebaskan dari guard FOP — cuma jaring pengaman kondisi di luar kendali (rusak/hilang setelah issue).
- Permission: pakai yang sudah ada, `customers.detail.devices.create`/`customers.detail.devices.update`. Tidak ada permission baru.
- Semua migrasi pakai tanggal `2026_10_17_*` (hari ini) dan prefix berurutan biar order tetap benar.
- `vendor/bin/pint --dirty --format agent` sebelum setiap commit (bukan `--test`).
- **JANGAN jalankan full suite** (`composer test` tanpa filter) — jalankan hanya `php artisan test --compact tests/Feature/<FileYangRelevan>.php`.

## Review Focus

- **Instalasi AP meretur ONT utama** — kalau implementer lupa `returnExistingSerial: false` saat memanggil `installSerial()` untuk perangkat tambahan, ONT yang sudah `INSTALLED` otomatis `RETURNED` (bug serius, pelanggan kehilangan modem utama di sistem). Test: install AP lewat langkah baru, assert SN ONT utama pelanggan tetap `INSTALLED`.
- **AP tampil sebagai perangkat utama di Detail Pelanggan** — `CustomerDeviceProfileService::resolve()` mengambil `InventorySerial` terbaru `INSTALLED` tanpa peduli kategori; tanpa filter `is_customer_extra_device`, AP yang baru dipasang bisa "menimpa" tampilan ONT di tab Perangkat. Test: pasang AP setelah ONT, assert `resolve()['installedInventorySerial']` tetap ONT, bukan AP.
- **Guard FOP lolos padahal custody kurang, karena hanya menghitung total barang tambahan tanpa membedakan cabang/kategori** — custody teknisi lain yang bukan `is_customer_extra_device`, atau dari cabang lain, tidak boleh ikut dihitung. Test: custody campur (AP + barang non-AP, AP dari cabang lain) → guard tetap menghitung benar.
- **Task non-PEMASANGAN atau paket limit 0 ikut ter-block** — guard FOP yang taruh di `TaskService` dipanggil semua task_type; kalau kondisinya salah, task MTN/DEAC/survey pun ikut ditolak assign. Test: task MTN dengan tim tanpa custody AP tetap bisa `TERJADWAL`; task PEMASANGAN paket limit 0 tetap bisa `TERJADWAL` tanpa custody AP.
- **Submit langkah Perangkat Tambahan tanpa alasan saat kekurangan tetap lolos** — validasi `kekurangan_perangkat_alasan` required_if harus menolak submit kosong, bukan cuma di level UI. Test: submit dengan jumlah device < limit tanpa `kekurangan_perangkat`/alasan → ditolak; dengan alasan terisi → lolos dan `kekurangan_perangkat=true` tersimpan.

---

## Task 1: Penanda kategori + kolom tautan stok

**Files:**
- Create: `database/migrations/2026_10_17_100000_add_is_customer_extra_device_to_item_categories_table.php`
- Create: `database/migrations/2026_10_17_100100_add_inventory_serial_columns_to_customer_additional_devices_table.php`
- Modify: `app/Models/ItemCategory.php` (tambah `is_customer_extra_device` ke `#[Fillable]` dan `casts()`)
- Modify: `app/Models/CustomerAdditionalDevice.php` (tambah kolom ke `#[Fillable]`, cast `status`, relasi `inventorySerial(): BelongsTo`)
- Modify: `database/seeders/ItemCategorySeeder.php` (set `access_point` jadi `is_customer_extra_device=true`, tambah kategori baru `stb` dengan `is_customer_extra_device=true`)
- Test: `tests/Feature/ItemCategoryMasterTest.php` (tambah assertion), `tests/Feature/CustomerAdditionalDeviceTest.php` (tambah test relasi + cast status)

**Interfaces:**
- Produces: `ItemCategory::CODE_STB` (const string `'stb'`), kolom `item_categories.is_customer_extra_device` (bool), `CustomerAdditionalDevice::inventorySerial(): BelongsTo<InventorySerial>`, kolom `customer_additional_devices.inventory_serial_id|status|retrieved_at`.
- Consumes: pola migrasi existing (`add_max_additional_devices_to_internet_packages_table.php`) sebagai referensi gaya.

- [ ] **Step 1: Tulis test gagal untuk kolom baru**

```php
#[Test]
public function it_casts_is_customer_extra_device_as_boolean(): void
{
    $category = ItemCategory::factory()->create(['is_customer_extra_device' => true]);
    $this->assertTrue($category->refresh()->is_customer_extra_device);
}
```
Tambahkan di `tests/Feature/ItemCategoryMasterTest.php`. Tambah test serupa di `CustomerAdditionalDeviceTest.php` untuk `status` default `'terpasang'` dan relasi `inventorySerial()`.

- [ ] **Step 2: Jalankan test, pastikan gagal** (kolom belum ada)

Run: `php artisan test --compact tests/Feature/ItemCategoryMasterTest.php`
Expected: FAIL — kolom `is_customer_extra_device` tidak ada.

- [ ] **Step 3: Buat migrasi `item_categories`**

`add_column('is_customer_extra_device', 'boolean')->default(false)->after('equipment_class')` (sesuaikan kolom existing persisnya — cek `database/migrations` untuk urutan kolom `item_categories` sebelum nulis `after()`).

- [ ] **Step 4: Buat migrasi `customer_additional_devices`**

Tambah: `foreignId('inventory_serial_id')->nullable()->constrained('inventory_serials')->nullOnDelete()`, `string('status')->default('terpasang')`, `timestamp('retrieved_at')->nullable()`.

- [ ] **Step 5: Update model `ItemCategory`**

Tambah `'is_customer_extra_device'` ke array `#[Fillable]`, tambah `'is_customer_extra_device' => 'boolean'` ke `casts()`. Tambah const `CODE_STB = 'stb'` di samping `CODE_ACCESS_POINT` (yang sudah ada di `ItemCategorySeeder`, bukan di model — cek lokasi existing const sebelum menambah; kalau semua const kategori tersimpan di seeder, tambahkan `CODE_STB` di sana, bukan di model).

- [ ] **Step 6: Update model `CustomerAdditionalDevice`**

Tambah `'inventory_serial_id'`, `'status'`, `'retrieved_at'` ke `#[Fillable]`. Tambah method:
```php
public function inventorySerial(): BelongsTo
{
    return $this->belongsTo(InventorySerial::class);
}
```
Tambah `casts()` override: `'retrieved_at' => 'datetime'`.

- [ ] **Step 7: Update `ItemCategorySeeder`**

Set baris `access_point` existing: tambah `'is_customer_extra_device' => true`. Tambah baris baru kategori STB: `['code' => self::CODE_STB, 'name' => 'Set Top Box (STB)', 'default_unit' => 'pcs', 'sort_order' => 48, 'equipment_class' => 'aktif', 'is_customer_extra_device' => true]`.

- [ ] **Step 8: Jalankan migrasi test + test**

Run: `php artisan test --compact tests/Feature/ItemCategoryMasterTest.php tests/Feature/CustomerAdditionalDeviceTest.php`
Expected: PASS.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_17_100000_add_is_customer_extra_device_to_item_categories_table.php database/migrations/2026_10_17_100100_add_inventory_serial_columns_to_customer_additional_devices_table.php app/Models/ItemCategory.php app/Models/CustomerAdditionalDevice.php database/seeders/ItemCategorySeeder.php tests/Feature/ItemCategoryMasterTest.php tests/Feature/CustomerAdditionalDeviceTest.php
git commit -m "feat: tandai kategori perangkat tambahan + kolom tautan stok (ADHOC-190 task 1)"
```

---

## Task 2: `CustomerDeviceProfileService` — kecualikan perangkat tambahan dari resolusi perangkat utama

**Files:**
- Modify: `app/Services/CustomerDeviceProfileService.php:75-79` (query `$installedInventorySerial`)
- Test: `tests/Feature/CustomerAdditionalDeviceTest.php` (atau file baru `tests/Feature/CustomerDeviceProfileServiceExtraDeviceTest.php` kalau lebih cocok — ikuti pola penamaan regresi CLAUDE.md)

**Interfaces:**
- Consumes: `ItemCategory::is_customer_extra_device` (Task 1).
- Produces: `CustomerDeviceProfileService::resolve()` tidak berubah signature, hanya hasil `installedInventorySerial` yang difilter.

- [ ] **Step 1: Tulis test gagal**

```php
#[Test]
public function resolve_ignores_installed_serial_from_extra_device_category(): void
{
    $customer = Customer::factory()->create();
    $ontSerial = InventorySerial::factory()->create([
        'customer_id' => $customer->id,
        'status' => SerialStatus::INSTALLED->value,
        'installed_at' => now()->subHour(),
    ]);
    $apCategory = ItemCategory::where('code', ItemCategorySeeder::CODE_ACCESS_POINT)->first();
    $apItem = Item::factory()->create(['category_id' => $apCategory->id]);
    InventorySerial::factory()->create([
        'customer_id' => $customer->id,
        'item_id' => $apItem->id,
        'status' => SerialStatus::INSTALLED->value,
        'installed_at' => now(),
    ]);

    $profile = CustomerDeviceProfileService::resolve($customer);

    $this->assertSame($ontSerial->id, $profile['installedInventorySerial']?->id);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=resolve_ignores_installed_serial_from_extra_device_category`
Expected: FAIL — AP (lebih baru) yang terpilih, bukan ONT.

- [ ] **Step 3: Filter query di `resolve()`**

Ubah query `$installedInventorySerial` (baris 75-79) tambah: `->whereHas('item.category', fn ($q) => $q->where('is_customer_extra_device', false)->orWhereNull('is_customer_extra_device'))` — atau filter via `item.category.is_customer_extra_device` dengan join yang sesuai pola existing (`->with('item')` sudah ada, ubah jadi `->with('item.category')` dan filter lewat `whereHas`).

- [ ] **Step 4: Jalankan test, pastikan lolos**

Run: `php artisan test --compact --filter=resolve_ignores_installed_serial_from_extra_device_category`
Expected: PASS.

- [ ] **Step 5: Regresi cepat**

Run: `php artisan test --compact tests/Feature/CustomerAdditionalDeviceTest.php`
Expected: PASS (tidak ada perangkat utama yang kena filter keliru untuk pelanggan tanpa AP).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CustomerDeviceProfileService.php tests/Feature/CustomerAdditionalDeviceTest.php
git commit -m "fix: CustomerDeviceProfileService kecualikan kategori perangkat tambahan dari perangkat utama (ADHOC-190 task 2)"
```

---

## Task 3: Kontrol kecukupan barang di titik assign/jadwal (FOP preventif)

**Files:**
- Create: `app/Services/AdditionalDeviceCustodyService.php` (service kecil, dipakai Task 3 & Task 4)
- Modify: `app/Services/TaskService.php` (panggil guard di `create()` dan `update()`, sebelum/selama transisi ke `TERJADWAL`)
- Test: `tests/Feature/TaskAdditionalDeviceAssignGuardTest.php` (baru)

**Interfaces:**
- Produces: `AdditionalDeviceCustodyService::countAvailable(Collection $technicianIds, ?int $cabangId): int` — jumlah `InventorySerial` status `ISSUED`, `current_technician_id` ∈ `$technicianIds`, `item.category.is_customer_extra_device = true`, dan (`issued_from_pop_id` null atau `= $cabangId`).
- Produces: `AdditionalDeviceCustodyService::assertSufficientForTask(Task $task, array $technicianIds): void` — throw `\RuntimeException` dengan pesan `"Barang perangkat tambahan belum di-issue cukup — teknisi butuh {$required}, tersedia {$available}. Issue dulu dari Gudang."` kalau `countAvailable() < $required`, di mana `$required = $task->customer?->customerService?->internetPackage?->max_additional_devices ?? 0`. No-op (return langsung) kalau `$task->task_type !== TaskType::PEMASANGAN->value` atau `$required === 0`.
- Consumes: `Pop::resolveCabangId()` (sudah ada, dipakai pola yang sama di `CustomerInstallationController::eligibleSerialsForTeam()`).

- [ ] **Step 1: Tulis test gagal — guard menolak assign kalau custody kurang**

```php
#[Test]
public function scheduling_pemasangan_task_is_rejected_when_extra_device_custody_insufficient(): void
{
    // Arrange: customer paket max_additional_devices=1, teknisi tanpa custody AP
    // Act + Assert
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Barang perangkat tambahan belum di-issue cukup');

    app(TaskService::class)->create([
        'task_type' => TaskType::PEMASANGAN->value,
        'customer_id' => $customer->id,
        'pop_id' => $pop->id,
        'team_member_ids' => [$teknisi->id],
        'scheduled_at' => now()->addDay(),
    ], $actor);
}
```
Tambah test pasangan: custody cukup → `create()` berhasil, task `TERJADWAL`. Tambah test: task_type MTN dengan custody kosong → tetap berhasil (no-op). Tambah test: paket `max_additional_devices=0` → tetap berhasil.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=scheduling_pemasangan_task_is_rejected_when_extra_device_custody_insufficient`
Expected: FAIL — task saat ini lolos `TERJADWAL` tanpa guard apa pun.

- [ ] **Step 3: Implementasi `AdditionalDeviceCustodyService`**

Dua method publik sesuai Interfaces di atas. Gunakan `InventorySerial::query()->whereIn('current_technician_id', $technicianIds)->where('status', SerialStatus::ISSUED->value)->whereHas('item.category', fn ($q) => $q->where('is_customer_extra_device', true))->when(...)->count()`.

- [ ] **Step 4: Panggil guard di `TaskService::create()` dan `update()`**

Di `create()` (baris ~35-50 area), setelah `$status` dihitung (tahu kapan jadi `TERJADWAL`) dan sebelum `Task::create(...)` — hanya panggil guard kalau `$status === TaskStatus::TERJADWAL->value`. Pola sama di `update()` untuk kasus `$rescheduled`/`$teamChanged` yang membuat task (tetap) `TERJADWAL`. Lempar langsung (jangan ditangkap di service ini) — biarkan controller pemanggil (FOP assign) yang menangkap `\RuntimeException` dan `redirect()->back()->with('error', ...)`, pola yang sama dipakai `CustomerPackageService::assertAdditionalDevicesWithinLimit()` (lihat pemanggilnya di `CustomerController::update()`).

- [ ] **Step 5: Cari & perbaiki pemanggil `TaskService::create()`/`update()` untuk assign FOP**

Grep pemanggil (`FopTaskController`, atau controller assign FOP yang relevan) dan bungkus dengan `try { ... } catch (\RuntimeException $e) { return redirect()->back()->with('error', $e->getMessage()); }`, konsisten dengan idiom file itu (cek dulu apakah sudah ada catch serupa di situ).

- [ ] **Step 6: Jalankan semua test baru**

Run: `php artisan test --compact tests/Feature/TaskAdditionalDeviceAssignGuardTest.php`
Expected: PASS semua skenario (kurang/cukup/non-PEMASANGAN/limit-0).

- [ ] **Step 7: Regresi task scheduling existing**

Run: `php artisan test --compact --filter=TaskService` (atau file test `TaskService` yang sudah ada — cari nama filenya dulu lewat `git ls-files | grep -i TaskServiceTest`)
Expected: PASS, tidak ada task lama yang ikut terblokir.

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/AdditionalDeviceCustodyService.php app/Services/TaskService.php tests/Feature/TaskAdditionalDeviceAssignGuardTest.php
git commit -m "feat: guard FOP — assign/jadwal Task Pemasangan tolak kalau custody perangkat tambahan kurang (ADHOC-190 task 3)"
```

---

## Task 4: Langkah "Perangkat Tambahan" di Laporan Pemasangan

**Files:**
- Create: `app/Http/Controllers/CustomerInstallationAdditionalDeviceController.php`
- Create: `resources/views/installations/partials/_additional_devices_step.blade.php`
- Modify: `resources/views/installations/report.blade.php` (include partial baru sebagai langkah sendiri, terpisah dari Material Terpakai — lihat §2/§4.2 spec)
- Modify: `routes/web.php` (route baru, dekat route `customers.installation.*` existing, baris ~1116-1129)
- Modify: `app/Http/Controllers/CustomerInstallationController.php` (tambah private method `eligibleExtraDeviceSerialsForTeam()`, mirror `eligibleSerialsForTeam()` tapi filter `is_customer_extra_device=true`; panggil dari `report()` dan expose ke view)
- Test: `tests/Feature/InstallationAdditionalDeviceStepTest.php` (baru)

**Interfaces:**
- Consumes: `AdditionalDeviceCustodyService` (Task 3, untuk hitung jumlah wajib), `InventoryService::installSerial()` (existing, panggil dengan `returnExistingSerial: false`), `CustomerAdditionalDevice` model (Task 1).
- Produces: route `customers.installation.additional-devices.store` (POST `/customers/{customer}/installation/additional-devices`), method `CustomerInstallationAdditionalDeviceController::store(Request, Customer): RedirectResponse`.

- [ ] **Step 1: Tulis test gagal — submit kurang dari limit tanpa alasan ditolak**

```php
#[Test]
public function submit_rejected_when_decisions_incomplete_and_no_shortage_reason(): void
{
    // Arrange: customer paket max_additional_devices=2, teknisi punya 2 SN AP di custody (ISSUED)
    // Act: POST dengan cuma 1 baris keputusan ('terpasang'), tanpa kekurangan_perangkat
    // Assert: redirect back dengan error, tidak ada baris customer_additional_devices terbuat
}

#[Test]
public function submit_allowed_with_shortage_reason_when_fewer_devices_decided_than_required(): void
{
    // Act: POST 1 dari 2 wajib, kekurangan_perangkat=true, alasan terisi
    // Assert: redirect sukses, customer_additional_devices punya 1 baris, flag kekurangan tercatat (lokasi penyimpanan flag: lihat Step 3)
}

#[Test]
public function submit_installs_selected_serial_without_retiring_primary_ont(): void
{
    // Arrange: ONT sudah INSTALLED ke customer. AP di custody ISSUED.
    // Act: POST decision AP = 'terpasang'
    // Assert: AP serial INSTALLED, customer_additional_devices baris baru dengan inventory_serial_id = AP, ONT serial TETAP INSTALLED (bukan RETURNED)
}

#[Test]
public function device_marked_not_installed_requires_final_status(): void
{
    // Act: POST decision AP = 'kembali_gudang' (bukan 'terpasang')
    // Assert: sukses, customer_additional_devices TIDAK dibuat untuk SN itu (atau dibuat dgn status sesuai — tentukan di Step 3), InventorySerial TETAP ISSUED (bukan diinstall)
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact tests/Feature/InstallationAdditionalDeviceStepTest.php`
Expected: FAIL — route/controller belum ada.

- [ ] **Step 3: Tentukan & implementasikan kontrak request/response**

Request fields: `decisions` (array, satu per SN custody yang relevan): `decisions.*.inventory_serial_id` (required, `Rule::in($eligibleExtraDeviceSerialIds)`), `decisions.*.outcome` (required, `Rule::in(['terpasang','kembali_gudang','rusak','hilang'])`). Plus `kekurangan_perangkat` (boolean, nullable) dan `kekurangan_perangkat_alasan` (`required_if:kekurangan_perangkat,1`, string, max:500).

Validasi tambahan di controller (bukan Rule bawaan, karena butuh hitung agregat): jumlah baris `decisions` dengan `outcome` TERISI (apa pun nilainya — bukan cuma 'terpasang') harus `>= max_additional_devices` KECUALI `kekurangan_perangkat=true` dengan alasan terisi. Kalau kurang dan `kekurangan_perangkat` tidak dikirim/alasan kosong → `ValidationException::withMessages(['decisions' => '...'])`.

Untuk setiap decision:
- `outcome === 'terpasang'`: panggil `InventoryService::installSerial($serial, $customer, $installFopTask, $teamTechnicians, auth()->user(), returnExistingSerial: false, ownership: DeviceOwnership::COMPANY)`, lalu `CustomerAdditionalDevice::create(['customer_id' => ..., 'inventory_serial_id' => $serial->id, 'device_type' => $serial->item->category->name, 'brand' => ..., 'serial_number' => $serial->serial_number, 'status' => 'terpasang'])`.
- `outcome !== 'terpasang'`: TIDAK memanggil `installSerial()`. SN tetap `ISSUED` (balik custody/rusak/hilang ditangani proses Gudang existing di luar scope ini — lihat §5 spec "Tidak termasuk"). Simpan jejak keputusan ini minimal di `installation_note`/log audit sederhana (keputusan implementer: field teks gabungan di `CustomerInstallation` atau catatan `technical_note` — PILIH salah satu dan dokumentasikan di PR, tidak perlu tabel baru untuk v1 langkah ini karena di luar scope §5).

Simpan `kekurangan_perangkat`/`kekurangan_perangkat_alasan` di kolom `installations.installation_note` (append teks) ATAU buat 2 kolom baru `customer_installations.extra_device_shortage`/`extra_device_shortage_reason` — PILIH kolom baru (lebih mudah di-query FOP/Gudang untuk follow-up, sesuai §4.2 spec "ditandai untuk ditindaklanjuti"). Tambah migrasi kolom ini ke tabel instalasi yang relevan (cek nama tabel sebenarnya — kemungkinan `customer_installations`, verifikasi via `database-schema` sebelum menulis migrasi).

- [ ] **Step 4: Buat migrasi kolom shortage**

`database/migrations/2026_10_17_100200_add_extra_device_shortage_to_installations_table.php` — tambah `boolean('extra_device_shortage')->default(false)` dan `string('extra_device_shortage_reason')->nullable()` ke tabel instalasi yang benar (cek nama via `database-schema` tool atau grep `$customer->installations()`).

- [ ] **Step 5: Implementasikan `CustomerInstallationAdditionalDeviceController::store()`**

Guard sama pola `CustomerInstallationController::storePemasangan()`: permission `customers.detail.devices.update`, status pelanggan `installation_in_progress`/`revision_installation`, keanggotaan tim task PEMASANGAN (`abort_unless` pola yang sama, baris ~1044-1049 di `CustomerInstallationController`). Pakai `DB::transaction()` membungkus semua `installSerial()` + `CustomerAdditionalDevice::create()` + update kolom shortage.

- [ ] **Step 6: Tambah `eligibleExtraDeviceSerialsForTeam()` di `CustomerInstallationController`**

Mirror `eligibleSerialsForTeam()` (baris 224-249) tapi tambah `->whereHas('item.category', fn ($q) => $q->where('is_customer_extra_device', true))`. Panggil dari `report()`, kirim ke view sebagai variable baru `eligibleExtraDeviceSerials`, dan hitung `requiredExtraDevices = $customer->customerService?->internetPackage?->max_additional_devices ?? 0` untuk dikirim ke partial.

- [ ] **Step 7: Buat partial view langkah baru**

`_additional_devices_step.blade.php`: tampil HANYA kalau `$requiredExtraDevices > 0` (spec §2: opsional/tidak tampil kalau limit 0). Daftar `eligibleExtraDeviceSerials` dengan radio/select outcome per baris, checkbox "Laporkan kekurangan" + textarea alasan (muncul kalau dicentang — vanilla JS toggle, ikuti pola modal v1). Include di `installations/report.blade.php` sebagai section terpisah (bukan di dalam blok Material Terpakai) — cari section Material Terpakai di file itu (grep `MaterialKind::TERPAKAI` atau label "Material Terpakai") dan taruh section baru SEBELUM atau SESUDAHNYA sebagai saudara, bukan nested.

- [ ] **Step 8: Tambah route**

```php
Route::post('/customers/{customer}/installation/additional-devices', [CustomerInstallationAdditionalDeviceController::class, 'store'])->name('customers.installation.additional-devices.store');
```
Taruh dekat blok route `customers.installation.*` existing (routes/web.php ~1116-1129), di dalam middleware group permission yang sama dengan route `customers.installation.pemasangan`.

- [ ] **Step 9: Jalankan test, pastikan lolos**

Run: `php artisan test --compact tests/Feature/InstallationAdditionalDeviceStepTest.php`
Expected: PASS semua 4 skenario.

- [ ] **Step 10: Regresi**

Run: `php artisan test --compact tests/Feature/CustomerAdditionalDeviceTest.php tests/Feature/GantiPaketBulanAktivasiTidakMenghapusBarisJualModemTest.php`
Expected: PASS.

- [ ] **Step 11: `npm run build`** (Blade baru + JS toggle)

Run: `npm run build` (atau `docker compose run --rm assets` kalau host Windows/UNC bermasalah — lihat CLAUDE.md).

- [ ] **Step 12: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CustomerInstallationAdditionalDeviceController.php app/Http/Controllers/CustomerInstallationController.php resources/views/installations/partials/_additional_devices_step.blade.php resources/views/installations/report.blade.php routes/web.php database/migrations/2026_10_17_100200_add_extra_device_shortage_to_installations_table.php tests/Feature/InstallationAdditionalDeviceStepTest.php
git commit -m "feat: langkah Perangkat Tambahan di Laporan Pemasangan, jalur kekurangan + alasan (ADHOC-190 task 4)"
```

---

## Task 5: Perluas DEAC — tarik semua perangkat tambahan terpasang

**Files:**
- Modify: `app/Http/Controllers/TaskDeviceRetrievalController.php` (query `$installedSerials` di `report()` sudah generic — tambah sinkronisasi status di `store()`)
- Test: `tests/Feature/DeviceRetrievalExtraDeviceTest.php` (baru)

**Interfaces:**
- Consumes: `InventoryReassignService::pickupSerialFromCustomer()` (existing, tidak diubah signature), `CustomerAdditionalDevice` (Task 1).

- [ ] **Step 1: Tulis test gagal**

```php
#[Test]
public function retrieving_extra_device_serial_marks_additional_device_row_ditarik(): void
{
    // Arrange: customer_additional_devices row status='terpasang', inventory_serial_id = AP serial INSTALLED company
    // Act: submit TaskDeviceRetrievalController::store() dengan serials berisi SN AP tsb, outcome DIAMBIL
    // Assert: customer_additional_devices row refresh → status='ditarik', retrieved_at terisi; InventorySerial AP → RETURNED
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact tests/Feature/DeviceRetrievalExtraDeviceTest.php`
Expected: FAIL — baris `customer_additional_devices` tidak ikut berubah (controller belum menyentuhnya).

- [ ] **Step 3: Tambah sinkronisasi di `store()`**

Di `TaskDeviceRetrievalController::store()`, dalam `foreach ($rows as $row)` (baris ~142-146) setelah `$inventory->pickupSerialFromCustomer(...)` berhasil mengembalikan `InventorySerial $serial`, cek `CustomerAdditionalDevice::where('inventory_serial_id', $serial->id)->where('status', 'terpasang')->update(['status' => 'ditarik', 'retrieved_at' => now()])`. Tidak perlu exception kalau tidak ada baris cocok (SN itu mungkin perangkat utama, bukan tambahan).

- [ ] **Step 4: Jalankan test, pastikan lolos**

Run: `php artisan test --compact tests/Feature/DeviceRetrievalExtraDeviceTest.php`
Expected: PASS.

- [ ] **Step 5: Regresi DEAC existing**

Run: `php artisan test --compact tests/Feature/DeviceRetrievalDeacToWarehouseTest.php tests/Feature/DeviceRetrievalHistoryAndWalkInTest.php tests/Feature/DeviceRetrievalSkipsCustomerOwnedSerialTest.php`
Expected: PASS, tidak ada regresi di alur modem utama.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/TaskDeviceRetrievalController.php tests/Feature/DeviceRetrievalExtraDeviceTest.php
git commit -m "feat: DEAC tandai customer_additional_devices ditarik saat SN perangkat tambahan diambil (ADHOC-190 task 5)"
```

---

## Task 6: Tampilan Detail Pelanggan — label sumber & status

**Files:**
- Modify: `resources/views/customers/partials/_additional_device_modal.blade.php` atau `resources/views/customers/tabs/_device.blade.php` (bagian "3. PERANGKAT TAMBAHAN" dari v1 — tambah kolom status + label sumber)
- Modify: `app/Http/Controllers/CustomerController.php` (pastikan `additionalDevices` eager-load `inventorySerial` juga — cek `edit()`/`show()` yang sudah eager-load `additionalDevices`)
- Test: `tests/Feature/CustomerAdditionalDeviceTest.php` (tambah assertion tampilan)

**Interfaces:**
- Consumes: `CustomerAdditionalDevice::status`, `CustomerAdditionalDevice::inventory_serial_id` (Task 1).

- [ ] **Step 1: Tulis test gagal**

```php
#[Test]
public function additional_device_from_warehouse_shows_source_label_and_status(): void
{
    // Arrange: 1 baris v1 (inventory_serial_id null), 1 baris dari Gudang (inventory_serial_id terisi, status ditarik)
    // Act: GET customers.show
    // Assert: response berisi "tanpa stok" untuk baris pertama, "Gudang" + "Ditarik" untuk baris kedua
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=additional_device_from_warehouse_shows_source_label_and_status`
Expected: FAIL — label belum ada di view.

- [ ] **Step 3: Eager-load `inventorySerial`**

Di `CustomerController::edit()`/`show()`, ubah `->with(['additionalDevices', ...])` jadi `->with(['additionalDevices.inventorySerial', ...])`.

- [ ] **Step 4: Tambah badge di partial**

Di baris tampilan tiap `CustomerAdditionalDevice` (bagian "3. PERANGKAT TAMBAHAN"): kalau `$device->inventory_serial_id` null → badge abu-abu "Tanpa Stok"; kalau terisi → badge "Gudang" + badge status (`Terpasang`/`Ditarik` sesuai `$device->status`).

- [ ] **Step 5: Jalankan test, pastikan lolos**

Run: `php artisan test --compact --filter=additional_device_from_warehouse_shows_source_label_and_status`
Expected: PASS.

- [ ] **Step 6: `npm run build`**

Run: `npm run build` (atau `docker compose run --rm assets`).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CustomerController.php resources/views/customers/tabs/_device.blade.php tests/Feature/CustomerAdditionalDeviceTest.php
git commit -m "feat: label sumber Gudang + status di tampilan Perangkat Tambahan Detail Pelanggan (ADHOC-190 task 6)"
```

---

## Task 7: Regresi penuh scope + update `docs/TASKS.md`

**Files:**
- Modify: `docs/TASKS.md` (ADHOC-190 → Done, dengan ringkasan file yang disentuh)

**Interfaces:** (tidak ada — task penutup)

- [ ] **Step 1: Jalankan semua test yang disebut §7 spec**

Run: `php artisan test --compact tests/Feature/ItemCategoryMasterTest.php tests/Feature/CustomerAdditionalDeviceTest.php tests/Feature/TaskAdditionalDeviceAssignGuardTest.php tests/Feature/InstallationAdditionalDeviceStepTest.php tests/Feature/DeviceRetrievalExtraDeviceTest.php tests/Feature/DeviceRetrievalDeacToWarehouseTest.php tests/Feature/DeviceRetrievalHistoryAndWalkInTest.php tests/Feature/DeviceRetrievalSkipsCustomerOwnedSerialTest.php tests/Feature/GantiPaketBulanAktivasiTidakMenghapusBarisJualModemTest.php tests/Feature/CustomerEditTest.php tests/Feature/VerificationInlineEditTest.php`
Expected: PASS semua.

- [ ] **Step 2: Update `docs/TASKS.md`**

Ubah baris ADHOC-190 dari "Direncanakan" ke "Done (2026-10-17)" — cantumkan link plan ini.

- [ ] **Step 3: Commit**

```bash
git add docs/TASKS.md
git commit -m "docs: tandai ADHOC-190 selesai"
```

---

## Self-Review Notes

- **Spec coverage**: §2 kontrol kecukupan → Task 3; §3.1/3.2 data model → Task 1; §4.1 issue → TIDAK ADA task kode (spec sendiri bilang "tidak ada logika baru", cukup kategori baru dari Task 1 otomatis ikut form issue existing karena issue memilih dari `Item` manapun yang `tracking_type=SERIALIZED` — tidak difilter kategori); §4.2 laporan → Task 4; §4.3 DEAC → Task 5; §4.4 tampilan → Task 2 (filter) + Task 6 (label); §5 scope luar → sengaja tidak ada task; §6/§7 → tercermin di seluruh task + Task 7.
- **Risiko terbesar yang implementer harus sadar**: Task 4 Step 3 punya SATU keputusan desain yang spec tidak detilkan persis (lokasi penyimpanan jejak device yang "tidak terpasang" — rusak/hilang/kembali gudang) — diserahkan ke implementer dengan catatan eksplisit supaya tidak membuat tabel baru di luar scope §5. Kalau reviewer task 4 merasa ini kurang, eskalasi ke user sebelum lanjut Task 5.
- **Task 4 adalah task terbesar dan paling berisiko** (menyentuh controller 1700+ baris yang sudah sangat padat) — pertimbangkan dispatch sebagai task tersendiri dengan reviewer yang membaca ulang seluruh `CustomerInstallationController` sebelum menyetujui, bukan cuma diff.
