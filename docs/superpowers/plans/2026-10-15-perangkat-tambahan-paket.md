# Perangkat Tambahan per Paket — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Izinkan paket internet (mis. Bisnis) kasih benefit perangkat tambahan (AP, STB, dll) ke pelanggan, dibatasi jumlahnya per paket, tanpa mengubah device utama (PPPOE/ODP/VLAN tetap 1 row seperti sekarang).

**Architecture:** Tabel baru `customer_additional_devices` (field minim, tanpa PPPOE/ODP/VLAN/WiFi) + kolom `max_additional_devices` di `internet_packages`. Input lewat Tab Perangkat di Detail Pelanggan (modal kecil, reuse permission `customers.detail.devices.create|update`). Guard limit ditegakkan di dua titik: controller store (limit paket sekarang) dan `CustomerPackageService::change()` (ganti paket ke limit lebih kecil ditolak kalau device tambahan masih kelebihan).

**Tech Stack:** Laravel 13, PHPUnit 12, Blade + Alpine.js (modal pattern existing di tab Perangkat).

**Spec:** `docs/superpowers/specs/2026-10-15-perangkat-tambahan-paket-design.md`

## Global Constraints

- `max_additional_devices` default `0` — paket existing tidak berubah perilaku tanpa admin eksplisit mengisi.
- Tabel baru TIDAK punya kolom PPPOE/ODP/VLAN/WiFi/signal — field jaringan cuma di device utama (`customer_devices`).
- Jangan ubah `Customer::customerDevice()` (tetap `hasOne`) atau `CustomerDeviceProfileService` — blast radius ke 43 titik existing harus nol.
- Device tambahan TIDAK mempengaruhi `recalculateCompleteness()` / status "Siap Billing" — murni informasional.
- Device tambahan TIDAK terhubung Inventori Gudang / `DeviceRetrievalLog` / laporan Task Teknisi di v1 (lihat spec §6).
- Pakai permission existing `customers.detail.devices.create`, `customers.detail.devices.update` — jangan buat kode permission baru.
- `vendor/bin/pint --dirty --format agent` sebelum setiap commit yang nyentuh file PHP.

## Review Focus

- Downgrade/ganti paket ke paket dengan `max_additional_devices` lebih kecil saat device tambahan pelanggan masih lebih banyak dari limit baru → harus ditolak dengan pesan jelas, bukan lolos diam-diam (spec §8.2). Test di Task 3.
- `max_additional_devices` default `0` (paket belum pernah diisi admin) → percobaan tambah device tambahan pertama kali ditolak dengan pesan limit, bukan 500/exception tak tertangani. Test di Task 5.
- Boundary pas limit: device tambahan ke-N (N == limit) diterima, device ke-(N+1) ditolak — bukan off-by-one. Test di Task 5.
- User tanpa permission `customers.detail.devices.create`/`update` → 403 saat store maupun destroy device tambahan (konsisten pola `CustomerDeviceTest::test_finance_cannot_fill_customer_device`). Test di Task 5.
- Tambah/hapus device tambahan TIDAK mengubah `customer->data_completeness_status` — regression guard eksplisit karena ini keputusan desain yang mudah diabaikan diam-diam. Test di Task 5.

---

## Task 1: Migration — kolom `max_additional_devices` di `internet_packages`

**Files:**
- Create: `database/migrations/2026_10_15_100000_add_max_additional_devices_to_internet_packages_table.php`
- Modify: `app/Models/InternetPackage.php:11-37` (tambah `max_additional_devices` ke `#[Fillable]` array dan ke `casts()`)
- Test: `tests/Feature/Master/InternetPackageCrudTest.php` (cek apakah file ini sudah ada; kalau belum, cari file test CRUD paket existing yang setara dan tambah test di situ)

**Interfaces:**
- Produces: `internet_packages.max_additional_devices` (unsignedInteger, default 0, not nullable). `InternetPackage::$max_additional_devices` (int, cast `integer`).

- [ ] **Step 1: Cari file test CRUD Master Paket Internet existing**

Jalankan `grep -rl "master.paket" tests/Feature` buat nemu file test yang nge-cover `InternetPackageController::store()`/`update()`. Task ini numpang di file itu — jangan bikin file test baru kalau sudah ada yang pas.

- [ ] **Step 2: Tulis migration**

```php
Schema::table('internet_packages', function (Blueprint $table) {
    $table->unsignedInteger('max_additional_devices')->default(0)->after('max_users');
});
```

- [ ] **Step 3: Jalankan migration di DB test, pastikan tidak error**

Run: `php artisan migrate --env=testing` (atau cukup lanjut ke step test — `RefreshDatabase` otomatis jalanin migration)

- [ ] **Step 4: Tambah `max_additional_devices` ke `#[Fillable]` dan `casts()` di `InternetPackage.php`**

Tambah `'max_additional_devices'` ke array `#[Fillable([...])]`, dan `'max_additional_devices' => 'integer'` ke `protected function casts(): array`.

- [ ] **Step 5: Write failing test — paket baru default limit 0**

```php
public function test_new_package_defaults_to_zero_additional_devices(): void
{
    $package = InternetPackage::factory()->create();

    $this->assertSame(0, $package->max_additional_devices);
}
```

(Pakai factory existing `InternetPackage::factory()` kalau sudah ada; kalau belum ada factory, buat via `InternetPackage::create([...])` dengan field wajib minimal seperti pola test lain di file yang sama.)

- [ ] **Step 6: Run test, verify fails**

Run: `php artisan test --compact --filter=test_new_package_defaults_to_zero_additional_devices`
Expected: FAIL (kolom belum ada / cast belum ada)

- [ ] **Step 7: Run test, verify passes**

Run: `php artisan test --compact --filter=test_new_package_defaults_to_zero_additional_devices`
Expected: PASS

- [ ] **Step 8: `vendor/bin/pint --dirty --format agent`, lalu commit**

```bash
git add database/migrations/2026_10_15_100000_add_max_additional_devices_to_internet_packages_table.php app/Models/InternetPackage.php tests/Feature/Master/InternetPackageCrudTest.php
git commit -m "feat: tambah kolom max_additional_devices ke internet_packages"
```

---

## Task 2: Migration + Model `CustomerAdditionalDevice`

**Files:**
- Create: `database/migrations/2026_10_15_100100_create_customer_additional_devices_table.php`
- Create: `app/Models/CustomerAdditionalDevice.php`
- Modify: `app/Models/Customer.php` (tambah relasi `additionalDevices()`, dekat `customerDevice()` di baris ~574)
- Test: `tests/Feature/CustomerAdditionalDeviceTest.php` (file baru, test awal cuma soal model/relasi — CRUD lengkap di Task 5)

**Interfaces:**
- Produces: tabel `customer_additional_devices` (id, customer_id FK cascadeOnDelete, device_type string, brand/model/serial_number/mac_address/technical_note nullable string/text, timestamps). Model `CustomerAdditionalDevice` dengan `#[Fillable(['customer_id','device_type','brand','model','serial_number','mac_address','technical_note'])]`, trait `RecordsAuditLogs` (`$auditModule = 'Data Teknis'`, `$auditEvents = ['created','updated','deleted']`), relasi `customer(): BelongsTo`. `Customer::additionalDevices(): HasMany`.

- [ ] **Step 1: Write failing test — relasi Customer ke device tambahan**

```php
public function test_customer_can_have_multiple_additional_devices(): void
{
    $pop = $this->createPop('ADD1');
    $customer = $this->createCustomer($pop, 'TEST-ADD-001');

    $customer->additionalDevices()->create(['device_type' => 'AP', 'brand' => 'TP-Link']);
    $customer->additionalDevices()->create(['device_type' => 'STB']);

    $this->assertCount(2, $customer->additionalDevices);
}
```

(Pakai helper `createPop`/`createCustomer` dengan pola persis sama seperti `tests/Feature/CustomerDeviceTest.php:262-286` — salin dua method private itu ke file test baru ini.)

- [ ] **Step 2: Run test, verify fails**

Run: `php artisan test --compact --filter=test_customer_can_have_multiple_additional_devices`
Expected: FAIL (class/table belum ada)

- [ ] **Step 3: Tulis migration**

```php
Schema::create('customer_additional_devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->string('device_type');
    $table->string('brand')->nullable();
    $table->string('model')->nullable();
    $table->string('serial_number')->nullable();
    $table->string('mac_address')->nullable();
    $table->text('technical_note')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Buat model `CustomerAdditionalDevice`**

Ikuti persis struktur `app/Models/CustomerDevice.php:1-66` (attribute `#[Fillable]`, trait `RecordsAuditLogs`, `protected string $auditModule = 'Data Teknis';`, `protected array $auditEvents = ['created', 'updated', 'deleted'];`, relasi `customer(): BelongsTo`). **Jangan** salin `boot()`/`recalculateCompleteness()` — device tambahan sengaja tidak mempengaruhi completeness (Global Constraints).

- [ ] **Step 5: Tambah relasi di `Customer.php`**

```php
/**
 * @return HasMany<CustomerAdditionalDevice, $this>
 */
public function additionalDevices(): HasMany
{
    return $this->hasMany(CustomerAdditionalDevice::class);
}
```

Taruh persis setelah `customerDevice()` (baris ~574-578).

- [ ] **Step 6: Run test, verify passes**

Run: `php artisan test --compact --filter=test_customer_can_have_multiple_additional_devices`
Expected: PASS

- [ ] **Step 7: `vendor/bin/pint --dirty --format agent`, lalu commit**

```bash
git add database/migrations/2026_10_15_100100_create_customer_additional_devices_table.php app/Models/CustomerAdditionalDevice.php app/Models/Customer.php tests/Feature/CustomerAdditionalDeviceTest.php
git commit -m "feat: tambah tabel & model CustomerAdditionalDevice"
```

---

## Task 3: Guard ganti paket — tolak kalau device tambahan kelebihan limit baru

**Files:**
- Modify: `app/Services/CustomerPackageService.php:55-109` (method `change()`)
- Test: `tests/Feature/CustomerPackageChangeTest.php` (cek nama file test existing untuk `CustomerPackageService::change()` dulu — kemungkinan sudah ada file test upgrade/downgrade dari ADHOC-68; tambah test di situ kalau ketemu)

**Interfaces:**
- Consumes: `Customer::additionalDevices()` (Task 2), `InternetPackage::max_additional_devices` (Task 1).
- Produces: `CustomerPackageService::change()` melempar `RuntimeException` sebelum mutasi apa pun kalau `$customer->additionalDevices()->count() > $newPackage->max_additional_devices`.

- [ ] **Step 1: Cari file test existing untuk `CustomerPackageService::change()`**

`grep -rl "CustomerPackageService" tests/Feature` — pakai file itu, jangan duplikasi setup upgrade/downgrade yang sudah ada.

- [ ] **Step 2: Write failing test — downgrade ditolak kalau device tambahan kelebihan**

```php
public function test_package_change_rejected_when_additional_devices_exceed_new_limit(): void
{
    // paket lama max_additional_devices = 2, pelanggan punya 2 device tambahan
    // paket baru max_additional_devices = 1 → change() harus RuntimeException
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('perangkat tambahan');

    app(CustomerPackageService::class)->change($customer, $newPackage);
}
```

Susun `$customer` (punya `customerService` aktif + 2 baris `additionalDevices`) dan `$newPackage` (`max_additional_devices = 1`) sesuai pola setup yang dipakai test existing di file yang ditemukan Step 1 (factory/seed paket + customer service aktif).

- [ ] **Step 3: Run test, verify fails**

Run: `php artisan test --compact --filter=test_package_change_rejected_when_additional_devices_exceed_new_limit`
Expected: FAIL (belum ada guard, `change()` lanjut tanpa exception)

- [ ] **Step 4: Tambah guard di `CustomerPackageService::change()`**

Tambah assertion baru sebelum baris `$isUpgrade = ...` (baris 67), pola sama seperti `assertNoOutstandingPreviousPeriod()`:

```php
private function assertAdditionalDevicesWithinLimit(Customer $customer, InternetPackage $newPackage): void
{
    $count = $customer->additionalDevices()->count();

    if ($count > $newPackage->max_additional_devices) {
        throw new RuntimeException(
            "Ganti paket diblok — pelanggan punya {$count} perangkat tambahan, ".
            "melebihi batas {$newPackage->max_additional_devices} di paket \"{$newPackage->name}\". ".
            'Hapus perangkat tambahan dulu sebelum ganti paket.'
        );
    }
}
```

Panggil `$this->assertAdditionalDevicesWithinLimit($customer, $newPackage);` di awal `change()`, sebelum `$isUpgrade = ...` — berlaku untuk upgrade MAUPUN downgrade (bukan cuma di dalam blok `if ($isUpgrade)`, karena downgrade paket adalah kasus utama yang punya limit lebih kecil).

- [ ] **Step 5: Run test, verify passes**

Run: `php artisan test --compact --filter=test_package_change_rejected_when_additional_devices_exceed_new_limit`
Expected: PASS

- [ ] **Step 6: Jalankan ulang test file `CustomerPackageService` lengkap (regresi)**

Run: `php artisan test --compact tests/Feature/<file-yang-ditemukan-step-1>.php`
Expected: semua PASS (guard baru tidak mengganggu test ganti paket yang sudah ada)

- [ ] **Step 7: `vendor/bin/pint --dirty --format agent`, lalu commit**

```bash
git add app/Services/CustomerPackageService.php tests/Feature/<file-yang-ditemukan-step-1>.php
git commit -m "fix: tolak ganti paket kalau perangkat tambahan kelebihan limit paket baru"
```

---

## Task 4: Master Paket Internet — form isi `max_additional_devices`

**Files:**
- Modify: `app/Http/Controllers/Master/InternetPackageController.php:127-175` (`validatePackage()`)
- Modify: `resources/views/master/paket/create.blade.php` (tambah field, dekat field `max_users`)
- Modify: `resources/views/master/paket/edit.blade.php` (tambah field yang sama)
- Test: cari file test CRUD Master Paket Internet yang ditemukan di Task 1 Step 1, tambah test di situ

**Interfaces:**
- Consumes: `InternetPackage::max_additional_devices` (Task 1).
- Produces: `InternetPackageController::store()`/`update()` menerima & menyimpan `max_additional_devices` dari form.

- [ ] **Step 1: Write failing test — simpan paket dengan `max_additional_devices`**

```php
public function test_package_can_be_created_with_max_additional_devices(): void
{
    $response = $this->actingAs($admin)->post(route('master.paket.store'), [
        // ...field wajib existing seperti test store lain di file ini...
        'max_additional_devices' => 3,
    ]);

    $response->assertRedirect(route('master.paket.index'));
    $this->assertDatabaseHas('internet_packages', [
        'package_code' => '...',
        'max_additional_devices' => 3,
    ]);
}
```

Salin field wajib lain (`package_code`, `category`, dll) dari test store existing di file yang sama supaya validasi lolos.

- [ ] **Step 2: Run test, verify fails**

Run: `php artisan test --compact --filter=test_package_can_be_created_with_max_additional_devices`
Expected: FAIL (field belum divalidasi/disimpan)

- [ ] **Step 3: Tambah rule validasi di `validatePackage()`**

Tambah ke array rules (sebelum `'is_active' => ...`):

```php
'max_additional_devices' => 'nullable|integer|min:0|max:20',
```

Setelah `$validated = $request->validate([...]);`, tambah default seperti pola `ppn`/`discount_default`:

```php
$validated['max_additional_devices'] = $validated['max_additional_devices'] ?? 0;
```

- [ ] **Step 4: Run test, verify passes**

Run: `php artisan test --compact --filter=test_package_can_be_created_with_max_additional_devices`
Expected: PASS

- [ ] **Step 5: Tambah input field di `create.blade.php` dan `edit.blade.php`**

Cari blok input `max_users` di kedua file, duplikasi strukturnya (label, `<input type="number" name="max_additional_devices" min="0" value="{{ old('max_additional_devices', $paket->max_additional_devices ?? 0) }}">`) tepat di sebelahnya. Label: "Maks. Perangkat Tambahan". Ikuti class Tailwind yang sama persis dengan input `max_users` di file itu (jangan desain ulang).

- [ ] **Step 6: `vendor/bin/pint --dirty --format agent`, lalu commit**

```bash
git add app/Http/Controllers/Master/InternetPackageController.php resources/views/master/paket/create.blade.php resources/views/master/paket/edit.blade.php tests/Feature/Master/InternetPackageCrudTest.php
git commit -m "feat: admin bisa atur batas perangkat tambahan per paket di Master Paket Internet"
```

---

## Task 5: CRUD Device Tambahan — Controller, Routes, View Tab Perangkat

**Files:**
- Create: `app/Http/Controllers/CustomerAdditionalDeviceController.php`
- Modify: `routes/web.php` (tambah 2 route, dekat blok `customers.device.store` baris ~1148)
- Modify: `resources/views/customers/tabs/_device.blade.php` (tambah section baru "3. Perangkat Tambahan" + tombol buka modal)
- Create: `resources/views/customers/partials/_additional_device_modal.blade.php`
- Test: `tests/Feature/CustomerAdditionalDeviceTest.php` (lanjutan dari Task 2)

**Interfaces:**
- Consumes: `Customer::additionalDevices()` (Task 2), permission string `customers.detail.devices.create`, `customers.detail.devices.update` (existing).
- Produces: route `customers.additional-device.store` (POST `/customers/{customer}/additional-device`), route `customers.additional-device.destroy` (DELETE `/customers/{customer}/additional-device/{additionalDevice}`).

- [ ] **Step 1: Write failing test — tambah device tambahan sampai limit, lalu ditolak di atas limit**

```php
public function test_additional_device_rejected_when_limit_reached(): void
{
    $pop = $this->createPop('ADD2');
    $technician = $this->createUserWithRole('Teknisi'); // + attach customers.detail.devices.create/update, pola CustomerDeviceTest
    $customer = $this->createCustomer($pop, 'TEST-ADD-002');
    $package = InternetPackage::factory()->create(['max_additional_devices' => 1]);
    // hubungkan $customer ke $package lewat customer_services aktif, pola setup existing

    $first = $this->actingAs($technician)->post(route('customers.additional-device.store', $customer), [
        'device_type' => 'AP', 'brand' => 'TP-Link',
    ]);
    $first->assertStatus(302);
    $this->assertDatabaseCount('customer_additional_devices', 1);

    $second = $this->actingAs($technician)->post(route('customers.additional-device.store', $customer), [
        'device_type' => 'AP',
    ]);
    $second->assertSessionHasErrors();
    $this->assertDatabaseCount('customer_additional_devices', 1);
}

public function test_additional_device_rejected_when_package_limit_is_zero(): void
{
    // paket tanpa max_additional_devices diisi (default 0) → store ditolak
}

public function test_additional_device_create_requires_permission(): void
{
    // user tanpa customers.detail.devices.create/update → 403, pola
    // CustomerDeviceTest::test_finance_cannot_fill_customer_device
}

public function test_additional_device_can_be_deleted(): void
{
    // DELETE customers.additional-device.destroy → device terhapus,
    // $customer->data_completeness_status TIDAK berubah (Review Focus)
}

public function test_additional_device_delete_requires_permission(): void
{
    // user tanpa permission → 403, device tidak terhapus
}
```

- [ ] **Step 2: Run tests, verify semuanya fail**

Run: `php artisan test --compact tests/Feature/CustomerAdditionalDeviceTest.php`
Expected: FAIL (controller/route belum ada)

- [ ] **Step 3: Buat `CustomerAdditionalDeviceController`**

```php
class CustomerAdditionalDeviceController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse { ... }
    public function destroy(Customer $customer, CustomerAdditionalDevice $additionalDevice): RedirectResponse { ... }
}
```

`store()`: validasi `device_type` (`required|string|max:50`), `brand`/`model`/`serial_number` (`nullable|string|max:100`), `mac_address` (`nullable|string|max:17|regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/` — regex sama persis dengan `CustomerDeviceController.php:23`), `technical_note` (`nullable|string`). Sebelum create, hitung limit:

```php
$package = $customer->customerService?->internetPackage;
$limit = $package?->max_additional_devices ?? 0;

if ($customer->additionalDevices()->count() >= $limit) {
    return back()->withErrors(['device_type' => "Sudah mencapai batas {$limit} perangkat tambahan untuk paket pelanggan ini."]);
}
```

Lalu `$customer->additionalDevices()->create([...$validated])`, `return redirect()->back()->with('success', 'Perangkat tambahan berhasil ditambahkan.');` — pola PRG sama seperti `CustomerDeviceController::store()`.

`destroy()`: pastikan `$additionalDevice->customer_id === $customer->id` (`abort_unless(..., 404)`), lalu `$additionalDevice->delete()`, redirect back dengan flash success. Permission dicek lewat middleware route (Step 4), bukan manual di controller — konsisten pola `CustomerDeviceController`.

- [ ] **Step 4: Tambah routes di `routes/web.php`**

Taruh persis setelah blok `customers.device.store` (baris ~1148-1150):

```php
Route::middleware('permission:customers.detail.devices.create|customers.detail.devices.update')->group(function () {
    Route::post('/customers/{customer}/additional-device', [CustomerAdditionalDeviceController::class, 'store'])->name('customers.additional-device.store');
    Route::delete('/customers/{customer}/additional-device/{additionalDevice}', [CustomerAdditionalDeviceController::class, 'destroy'])->name('customers.additional-device.destroy');
});
```

Tambah `use App\Http\Controllers\CustomerAdditionalDeviceController;` di bagian use statement (dekat `CustomerDeviceController`, baris ~20).

- [ ] **Step 5: Run tests, verify semua passes**

Run: `php artisan test --compact tests/Feature/CustomerAdditionalDeviceTest.php`
Expected: PASS semua

- [ ] **Step 6: Tambah UI — section list + modal di tab Perangkat**

Di `resources/views/customers/tabs/_device.blade.php`, setelah section "2. INFRASTRUKTUR OLT NODE..." (sebelum penutup `@endif` blok `$hasAnyDeviceData`, dekat baris 141-180), tambah section baru independen dari `$hasAnyDeviceData` (device tambahan bisa ada walau device utama belum terisi):

```blade
<!-- 3. PERANGKAT TAMBAHAN (AP, STB, dll — benefit paket) -->
<div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
    <div class="px-5 py-3 ... flex items-center justify-between">
        <span>3. PERANGKAT TAMBAHAN</span>
        @can('customers.detail.devices.update')
            @php
                $additionalLimit = $customer->customerService?->internetPackage?->max_additional_devices ?? 0;
                $additionalCount = $customer->additionalDevices->count();
            @endphp
            <button type="button" @if($additionalCount >= $additionalLimit) disabled @endif onclick="openAdditionalDeviceModal()">
                Tambah Perangkat ({{ $additionalCount }}/{{ $additionalLimit }})
            </button>
        @endcan
    </div>
    <div class="p-5">
        @forelse($customer->additionalDevices as $additional)
            <div class="flex justify-between items-center ...">
                <span>{{ $additional->device_type }} — {{ $additional->brand }} {{ $additional->model }} ({{ $additional->serial_number ?: 'tanpa SN' }})</span>
                @can('customers.detail.devices.update')
                    <form method="POST" action="{{ route('customers.additional-device.destroy', [$customer, $additional]) }}">
                        @csrf @method('DELETE')
                        <button type="submit">Hapus</button>
                    </form>
                @endcan
            </div>
        @empty
            <p>Belum ada perangkat tambahan.</p>
        @endforelse
    </div>
</div>
```

Styling Tailwind: ikuti class yang sudah dipakai section 1 & 2 di file ini (warna border/background boleh beda, mis. `emerald` buat beda dari `sky`/`indigo` yang sudah dipakai). Load `$customer->load('additionalDevices')` di titik yang sama dengan `customerDevice` di-eager-load (`CustomerController.php:791` dan `CustomerFieldworkController` kalau tab ini juga dirender di sana — cek & tambah di keduanya).

- [ ] **Step 7: Buat modal `_additional_device_modal.blade.php`**

Form kecil (device_type select: AP/STB/Extender/Lainnya, brand, model, serial_number, mac_address, technical_note) yang POST ke `customers.additional-device.store`. Ikuti struktur modal device utama yang sudah ada di tab ini (cari fungsi `openDeviceModal()` di file Blade terkait, biasanya `resources/views/customers/show.blade.php` atau partial modal device) — modal baru `openAdditionalDeviceModal()` meniru pola Alpine yang sama (`x-data`, `x-show`, `x-on:click.outside`).

- [ ] **Step 8: Manual check — jalankan `npm run build`, buka tab Perangkat pelanggan di browser, pastikan modal muncul & submit berhasil**

Ini perubahan Blade/Alpine — WAJIB `npm run build` dulu (lihat CLAUDE.md), lalu verifikasi manual di browser karena test feature tidak mengecek interaksi Alpine.

- [ ] **Step 9: `vendor/bin/pint --dirty --format agent`, lalu commit**

```bash
git add app/Http/Controllers/CustomerAdditionalDeviceController.php routes/web.php resources/views/customers/tabs/_device.blade.php resources/views/customers/partials/_additional_device_modal.blade.php tests/Feature/CustomerAdditionalDeviceTest.php
git commit -m "feat: CRUD perangkat tambahan pelanggan di tab Perangkat"
```

---

## Task 6: Update `docs/TASKS.md`

**Files:**
- Modify: `docs/TASKS.md`

**Interfaces:** none (dokumentasi saja)

- [ ] **Step 1: Pindahkan task "Perangkat Tambahan per Paket" ke bagian Done, sesuai format section "Status Project Saat Ini" yang sudah ada di file itu**
- [ ] **Step 2: Commit**

```bash
git add docs/TASKS.md
git commit -m "docs: update status task perangkat tambahan per paket"
```
