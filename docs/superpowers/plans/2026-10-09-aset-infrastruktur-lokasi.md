# Aset Infrastruktur per Lokasi (Pusat/Cabang/Mini POP/ODP) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lacak aset infrastruktur jaringan (OLT/switch/ODP box/UPS/splitter-ODP)
yang terpasang netap di Pusat/Cabang/Mini POP/ODP, dipasang/diganti lewat
laporan task O-REQ/INFR REQ, dan bisa diaudit dua arah: per-SN (sudah ada,
Lacak Barang) dan per-lokasi (baru, halaman Aset per Lokasi).

**Architecture:** Tambah node master `odps` (anak Mini POP). Perluas ledger
Gudang yang ada (`inventory_transactions`, tipe baru `deploy`/`retrieve_infra`)
alih-alih bikin ledger baru. SERIALIZED pakai ulang `inventory_serials`
(status baru `DEPLOYED` + kolom `current_odp_id`). QUANTITY **TIDAK** numpang
`inventory_balances` (kolom itu sengaja dibatasi Pusat/Cabang, `Pop::WAREHOUSE_TYPES`)
— proyeksi saldo-nya ditaruh di tabel baru `infrastructure_balances`. Jalur
masuk teknisi reuse `TaskMaintenanceController` yang sudah menangani O-REQ/INFR
REQ (seksi form baru, bukan controller baru).

**Tech Stack:** Laravel 13, PHPUnit 12, Blade + Alpine.js (Vite), sqlite `:memory:` test.

**Spec:** `docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md`

## Global Constraints

- Semua nomor dokumen (ledger reference) lewat pola `generateReferenceNumber()`
  yang sudah ada di `InventoryService` — jangan `count()+1`.
- `Mini POP` (`pops.type=mini_pop`) **tidak boleh** pernah masuk
  `inventory_balances.pop_id`/`inventory_transfers` — invariant existing,
  jangan dilonggarkan.
- Ledger (`inventory_transactions`) append-only — jangan update/delete baris
  lama, `InventoryTransactionObserver` yang sudah ada otomatis menolaknya.
- Guard `OwnershipMode::INFRASTRUCTURE_ASSET` ditegakkan di Service
  (`InventoryService`), satu-satunya pintu transisi ke `SerialStatus::DEPLOYED`
  — sama prinsip `installSerial()` existing.
- Semua permission baru lewat `config/rbac.php` (`allowed_actions`) +
  `PermissionGeneratorService::generate()` — jangan hardcode string permission.
- Semua query/scope POP baru pakai `EffectiveAccessService`/`AuthorizesWarehousePop`
  yang sudah ada — jangan query `getAllowedPopIds()` inline di tempat baru.
- `vendor/bin/pint --dirty --format agent` setelah tiap task yang menyentuh PHP.

## Review Focus

- **SN company_asset biasa (OTDR/laptop) jangan bisa di-deploy** — guard
  `OwnershipMode::INFRASTRUCTURE_ASSET` harus menolak `COMPANY_ASSET` dan
  `INSTALLABLE` juga, bukan cuma menolak satu dari dua.
- **Kirim `target_mini_pop_id` DAN `target_odp_id` sekaligus (dua lokasi
  bersamaan)** — harus ditolak validasi, bukan diam-diam pakai salah satu.
- **ODP yang dipilih bukan anak dari Mini POP task ini** (ID ODP valid secara
  global tapi beda Cabang/Mini POP) — harus ditolak, bukan nyangkut di cabang
  lain diam-diam.
- **`deployQuantity()` custody tim tidak cukup** — harus melempar
  `InsufficientCustodyException` yang sudah ada, bukan silently partial-deploy.
- **SN yang sedang `DEPLOYED` di lokasi lain diadjust ke LOST/DAMAGED/SCRAPPED/QUARANTINE**
  — `current_pop_id`/`current_odp_id` harus ikut terkosongkan, bukan nyangkut
  nunjuk ke lokasi yang sudah tidak relevan (item Review Focus utama Task 7).

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `database/migrations/2026_10_09_100000_create_odps_table.php` | Tabel master ODP |
| `database/migrations/2026_10_09_100001_add_current_odp_id_to_inventory_serials_table.php` | Kolom lokasi ODP di SN |
| `database/migrations/2026_10_09_100002_add_odp_columns_to_inventory_transactions_table.php` | Kolom ledger `to_odp_id`/`from_odp_id` |
| `database/migrations/2026_10_09_100003_create_infrastructure_balances_table.php` | Proyeksi saldo QUANTITY yang di-deploy |
| `app/Enums/OwnershipMode.php` | + case `INFRASTRUCTURE_ASSET` |
| `app/Enums/SerialStatus.php` | + case `DEPLOYED` |
| `app/Enums/InventoryTransactionType.php` | + case `DEPLOY`, `RETRIEVE_INFRA` |
| `app/Models/Odp.php` | Model baru, relasi ke Mini POP + breadcrumb |
| `app/Models/InfrastructureBalance.php` | Model baru, proyeksi saldo lokasi infra |
| `app/Models/InventorySerial.php` | + relasi `currentOdp()` |
| `app/Models/InventoryTransaction.php` | + relasi `toOdp()`/`fromOdp()` |
| `app/Http/Controllers/Warehouse/Concerns/AuthorizesWarehousePop.php` | + `assertOdpInScope()` |
| `app/Services/InventoryService.php` | + `deploySerial()`, `deployQuantity()`, `retrieveSerialFromLocation()` |
| `app/Services/InventoryAdjustmentService.php` | `adjustSerialStatus()` — kosongkan `current_pop_id`/`current_odp_id` saat SN `DEPLOYED` diadjust |
| `app/Http/Controllers/Master/OdpController.php` | CRUD Master ODP |
| `resources/views/master/odp/{index,create,edit}.blade.php` | View Master ODP |
| `app/Http/Controllers/Warehouse/WarehouseLocationAssetController.php` | Halaman Aset per Lokasi |
| `resources/views/warehouse/location_assets/index.blade.php` | View Aset per Lokasi |
| `app/Http/Controllers/TaskMaintenanceController.php` | + seksi "Pasang/Ganti Infrastruktur" di `report()`/`store()` |
| `resources/views/tasks/maintenance-report.blade.php` | + seksi form baru (kondisional `task_type ∈ {OREQ, INFR}`) |
| `database/seeders/OdpFeatureSeeder.php` | Feature `master_odp` |
| `database/seeders/WarehouseLocationAssetFeatureSeeder.php` | Feature `warehouse_location_asset` |
| `config/rbac.php` | `allowed_actions['master_odp']`, `allowed_actions['warehouse_location_asset']` |
| `routes/web.php` | Routes Master ODP + Aset per Lokasi |

---

### Task 1: Enum baru — `OwnershipMode`, `SerialStatus`, `InventoryTransactionType`

**Files:**
- Modify: `app/Enums/OwnershipMode.php`
- Modify: `app/Enums/SerialStatus.php`
- Modify: `app/Enums/InventoryTransactionType.php`
- Test: `tests/Unit/InfrastructureAssetEnumTest.php`

**Interfaces:**
- Produces: `OwnershipMode::INFRASTRUCTURE_ASSET`, `SerialStatus::DEPLOYED`,
  `InventoryTransactionType::DEPLOY`, `InventoryTransactionType::RETRIEVE_INFRA`
  — dipakai semua task berikutnya.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function semua_case_enum_punya_label_dan_badge_tanpa_error(): void
{
    foreach (OwnershipMode::cases() as $case) {
        $this->assertIsString($case->label());
    }
    foreach (SerialStatus::cases() as $case) {
        $this->assertIsString($case->label());
        $this->assertIsString($case->badgeVariant());
    }
    foreach (InventoryTransactionType::cases() as $case) {
        $this->assertIsString($case->label());
    }

    $this->assertSame('infrastructure_asset', OwnershipMode::INFRASTRUCTURE_ASSET->value);
    $this->assertSame('deployed', SerialStatus::DEPLOYED->value);
    $this->assertSame('deploy', InventoryTransactionType::DEPLOY->value);
    $this->assertSame('retrieve_infra', InventoryTransactionType::RETRIEVE_INFRA->value);
}
```

- [ ] **Step 2: Run test, verify fails** (cases/enum tidak ada)

Run: `php artisan test --compact tests/Unit/InfrastructureAssetEnumTest.php`
Expected: FAIL — case `INFRASTRUCTURE_ASSET`/`DEPLOYED`/`DEPLOY`/`RETRIEVE_INFRA` tidak ada.

- [ ] **Step 3: Implement**

`OwnershipMode::INFRASTRUCTURE_ASSET = 'infrastructure_asset'` + tambah di
`label()`: `'Aset Infrastruktur (Terpasang Netap)'`.

`SerialStatus::DEPLOYED = 'deployed'` + `label()`: `'Terpasang di Infrastruktur'`
+ `badgeVariant()`: masukkan ke grup `success` bersama `AVAILABLE`/`INSTALLED`.

`InventoryTransactionType::DEPLOY = 'deploy'` + `RETRIEVE_INFRA = 'retrieve_infra'`
+ `label()`: `'Terpasang di Infrastruktur'` / `'Dicabut dari Infrastruktur'`.
Docblock tiap case ikuti gaya existing (jelaskan kombinasi kolom, lihat spec §3.6).

- [ ] **Step 4: Run test, verify passes**

Run: `php artisan test --compact tests/Unit/InfrastructureAssetEnumTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Enums/OwnershipMode.php app/Enums/SerialStatus.php app/Enums/InventoryTransactionType.php tests/Unit/InfrastructureAssetEnumTest.php
git commit -m "feat: tambah enum OwnershipMode::INFRASTRUCTURE_ASSET, SerialStatus::DEPLOYED, InventoryTransactionType::DEPLOY/RETRIEVE_INFRA"
```

---

### Task 2: Master ODP — migration, model, CRUD

**Files:**
- Create: `database/migrations/2026_10_09_100000_create_odps_table.php`
- Create: `app/Models/Odp.php`
- Create: `app/Http/Controllers/Master/OdpController.php`
- Create: `resources/views/master/odp/index.blade.php`
- Create: `resources/views/master/odp/create.blade.php`
- Create: `resources/views/master/odp/edit.blade.php`
- Create: `database/seeders/OdpFeatureSeeder.php`
- Modify: `config/rbac.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Master/OdpCrudTest.php`

**Interfaces:**
- Produces: `App\Models\Odp` — columns `pop_id` (FK Mini POP), `code`, `name`,
  `capacity_port` (nullable int), `latitude`/`longitude` (nullable decimal),
  `status` (`active`/`inactive`), `notes`. Relasi `miniPop(): BelongsTo` (ke
  `Pop`, FK `pop_id`). Method `breadcrumbLabel(): string` → `"{Cabang} → {Mini POP} → {ODP}"`
  (null-safe kalau relasi belum ter-load/hilang).
- Routes: `master.odp.index`, `.create`, `.store`, `.edit`, `.update`, `.destroy`.
- Permission: `master_odp.view|create|update|delete`.

- [ ] **Step 1: Write migration**

```php
Schema::create('odps', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();
    $table->string('code', 30)->unique();
    $table->string('name', 150);
    $table->unsignedInteger('capacity_port')->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->string('status', 20)->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index('pop_id');
});
```

`pop_id` wajib Mini POP — ditegakkan Service/Controller (`Rule::exists('pops','id')->where('type','mini_pop')`),
bukan DB constraint (sama pola `distributions.pop_id`).

- [ ] **Step 2: Run migration**

Run: `php artisan migrate --no-interaction`
Expected: tabel `odps` terbuat, no error.

- [ ] **Step 3: Write failing feature test** (`tests/Feature/Master/OdpCrudTest.php`)

```php
#[Test]
public function admin_bisa_crud_odp_di_bawah_mini_pop(): void
{
    $this->loginAsAdmin();
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);

    $response = $this->post(route('master.odp.store'), [
        'pop_id' => $miniPop->id,
        'code' => 'ODP-A1',
        'name' => 'ODP Jalan Mawar',
    ]);

    $response->assertRedirect(route('master.odp.index'));
    $this->assertDatabaseHas('odps', ['code' => 'ODP-A1', 'pop_id' => $miniPop->id]);
}

#[Test]
public function tolak_odp_dengan_pop_id_bukan_mini_pop(): void
{
    $this->loginAsAdmin();
    $cabang = Pop::factory()->create(['type' => 'cabang']);

    $response = $this->post(route('master.odp.store'), [
        'pop_id' => $cabang->id,
        'code' => 'ODP-B1',
        'name' => 'ODP Salah Level',
    ]);

    $response->assertSessionHasErrors('pop_id');
}
```

- [ ] **Step 4: Run tests, verify fail** (route/controller belum ada)

Run: `php artisan test --compact tests/Feature/Master/OdpCrudTest.php`
Expected: FAIL — route `master.odp.store` tidak ditemukan.

- [ ] **Step 5: Implement model, controller, views, routes, seeder, rbac config**

`Odp` model — fillable `['pop_id','code','name','capacity_port','latitude','longitude','status','notes']`,
`casts(): ['latitude'=>'decimal:7','longitude'=>'decimal:7']`, relasi
`miniPop(): BelongsTo(Pop::class, 'pop_id')`. `breadcrumbLabel()` naik
`miniPop->parent` (Cabang).

`OdpController` — pola identik `DistributionController` (lihat
`app/Http/Controllers/Master/DistributionController.php`): `index()` (filter
search+pop_id, scope `Pop::forUser()` buat dropdown filter), `create()`,
`store()` (validasi `pop_id` exists+type=mini_pop, `code` unique, `name`),
`edit()`/`update()`/`destroy()` dengan guard scope POP per-record (pola
`authorizeDistributionPopScope()`, scope dicek dari `miniPop->parent_id`
karena `Odp` sendiri bukan baris `pops`).

Views `master/odp/{index,create,edit}.blade.php` — pola sama
`master/distribusi/*.blade.php`, dropdown `pop_id` di-filter
`Pop::where('type','mini_pop')` (bukan semua level).

`config/rbac.php` — tambah entri:
```php
'master_odp' => [
    ActionCode::VIEW->value,
    ActionCode::CREATE->value,
    ActionCode::UPDATE->value,
    ActionCode::DELETE->value,
],
```

`OdpFeatureSeeder` — pola sama `ItemFeatureSeeder`: `Feature::updateOrCreate(['code'=>'master_odp'], [...])` lalu `PermissionGeneratorService::generate()`.

`routes/web.php` — tambah di bawah blok Distribusi (sekitar baris 645),
pola persis blok POP/Distribusi (static-first, permission per action).

- [ ] **Step 6: Run tests, verify pass**

Run: `php artisan test --compact tests/Feature/Master/OdpCrudTest.php`
Expected: PASS

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan db:seed --class=OdpFeatureSeeder
git add database/migrations/2026_10_09_100000_create_odps_table.php app/Models/Odp.php app/Http/Controllers/Master/OdpController.php resources/views/master/odp database/seeders/OdpFeatureSeeder.php config/rbac.php routes/web.php tests/Feature/Master/OdpCrudTest.php
git commit -m "feat: tambah master ODP (anak Mini POP) dengan CRUD + permission master_odp"
```

---

### Task 3: Kolom lokasi ODP di ledger & SN + model `InfrastructureBalance`

**Files:**
- Create: `database/migrations/2026_10_09_100001_add_current_odp_id_to_inventory_serials_table.php`
- Create: `database/migrations/2026_10_09_100002_add_odp_columns_to_inventory_transactions_table.php`
- Create: `database/migrations/2026_10_09_100003_create_infrastructure_balances_table.php`
- Create: `app/Models/InfrastructureBalance.php`
- Modify: `app/Models/InventorySerial.php`
- Modify: `app/Models/InventoryTransaction.php`
- Test: `tests/Unit/InfrastructureBalanceModelTest.php`

**Interfaces:**
- Produces: `InventorySerial::currentOdp(): BelongsTo`; `InventoryTransaction::toOdp()`/`fromOdp(): BelongsTo`;
  `InfrastructureBalance` model — fillable `['item_id','lot_no','pop_id','odp_id','qty']`,
  relasi `item()`, `pop()`, `odp()`.

- [ ] **Step 1: Write migrations**

```php
// add_current_odp_id_to_inventory_serials_table
Schema::table('inventory_serials', function (Blueprint $table) {
    $table->foreignId('current_odp_id')->nullable()->after('current_pop_id')
        ->constrained('odps')->nullOnDelete();
    $table->index('current_odp_id');
});

// add_odp_columns_to_inventory_transactions_table
Schema::table('inventory_transactions', function (Blueprint $table) {
    $table->foreignId('to_odp_id')->nullable()->after('to_pop_id')
        ->constrained('odps')->nullOnDelete();
    $table->foreignId('from_odp_id')->nullable()->after('from_pop_id')
        ->constrained('odps')->nullOnDelete();
});

// create_infrastructure_balances_table
Schema::create('infrastructure_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('item_id')->constrained()->restrictOnDelete();
    $table->string('lot_no', 50)->default('');
    $table->foreignId('pop_id')->nullable()->constrained('pops')->restrictOnDelete();
    $table->foreignId('odp_id')->nullable()->constrained('odps')->restrictOnDelete();
    $table->decimal('qty', 12, 2)->default(0);
    $table->timestamps();
    $table->index(['item_id', 'pop_id']);
    $table->index(['item_id', 'odp_id']);
});
```

**Tidak ada unique index di `infrastructure_balances`** — `pop_id`/`odp_id`
nullable-exclusive, dan unique index standar memperlakukan NULL sebagai
"selalu beda" (tidak mencegah duplikat baris `pop_id` sama + `odp_id` NULL).
Keunikan "satu baris per lokasi+item+lot" ditegakkan Service
(`lockForUpdate()` + `firstOrCreate` di Task 5) — pola yang sama dengan
`inventory_serials` ("cuma SATU kolom relevan tergantung status, ditegakkan
Service, bukan DB constraint").

- [ ] **Step 2: Run migrations**

Run: `php artisan migrate --no-interaction`
Expected: 3 migration baru jalan tanpa error.

- [ ] **Step 3: Write failing test**

```php
#[Test]
public function infrastructure_balance_bisa_dibuat_untuk_lokasi_odp(): void
{
    $item = Item::factory()->create();
    $odp = Odp::factory()->create();

    $balance = InfrastructureBalance::create([
        'item_id' => $item->id,
        'odp_id' => $odp->id,
        'qty' => 3,
    ]);

    $this->assertSame($odp->id, $balance->odp->id);
}

#[Test]
public function inventory_serial_bisa_simpan_current_odp_id(): void
{
    $serial = InventorySerial::factory()->create(['current_odp_id' => Odp::factory()->create()->id]);

    $this->assertInstanceOf(Odp::class, $serial->currentOdp);
}
```

(Butuh `OdpFactory` — buat `database/factories/OdpFactory.php` minimal
`code`/`name`/`pop_id` fakeable, dan pastikan `InventorySerialFactory` yang
sudah ada tidak perlu diubah karena kolom baru nullable.)

- [ ] **Step 4: Run test, verify fails**

Run: `php artisan test --compact tests/Unit/InfrastructureBalanceModelTest.php`
Expected: FAIL — `InfrastructureBalance`/`Odp::currentOdp` tidak ada.

- [ ] **Step 5: Implement models**

`InfrastructureBalance` — mirror struktur `InventoryBalance` (lihat
`app/Models/InventoryBalance.php`), cast `qty => decimal:2`, relasi `item()`,
`pop()`, `odp()`.

`InventorySerial::currentOdp(): BelongsTo` → `belongsTo(Odp::class, 'current_odp_id')`,
tambahkan `current_odp_id` ke `#[Fillable]`.

`InventoryTransaction::toOdp()`/`fromOdp(): BelongsTo` → `belongsTo(Odp::class, 'to_odp_id'/'from_odp_id')`,
tambahkan kedua kolom ke fillable kalau model ini pakai atribut `#[Fillable]`
yang sama (cek kelas, ikuti pola yang sudah ada di file itu).

`database/factories/OdpFactory.php` — minimal, `pop_id` default ke Mini POP
hasil `Pop::factory()->create(['type'=>'mini_pop'])`.

- [ ] **Step 6: Run test, verify passes**

Run: `php artisan test --compact tests/Unit/InfrastructureBalanceModelTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_09_100001_add_current_odp_id_to_inventory_serials_table.php database/migrations/2026_10_09_100002_add_odp_columns_to_inventory_transactions_table.php database/migrations/2026_10_09_100003_create_infrastructure_balances_table.php app/Models/InfrastructureBalance.php app/Models/InventorySerial.php app/Models/InventoryTransaction.php database/factories/OdpFactory.php tests/Unit/InfrastructureBalanceModelTest.php
git commit -m "feat: kolom lokasi ODP di ledger/SN + tabel proyeksi infrastructure_balances"
```

---

### Task 4: POP-scope untuk ODP (`AuthorizesWarehousePop::assertOdpInScope()`)

**Files:**
- Modify: `app/Http/Controllers/Warehouse/Concerns/AuthorizesWarehousePop.php`
- Test: `tests/Feature/Warehouse/AuthorizesOdpScopeTest.php`

**Interfaces:**
- Consumes: `Odp::miniPop` (Task 2), `EffectiveAccessService::hasAllPopAccess()`/`getAllowedPopIds()` (sudah ada).
- Produces: `assertOdpInScope(Odp $odp, User $user, EffectiveAccessService $access): void`
  (throws 403 `HttpException` kalau Mini POP induk ODP di luar scope) — dipakai
  Task 6 (controller Aset per Lokasi).

- [ ] **Step 1: Write failing test**

Buat controller test-double sederhana atau test lewat route nyata di Task 6
— **tunda assertion method-level ini sampai Task 6** kalau lebih mudah diuji
lewat HTTP test end-to-end. Kalau diuji langsung: buat trait consumer kosong
di test (`class TestController { use AuthorizesWarehousePop; public function
check(...) { $this->assertOdpInScope(...); } }`) dan assert `HttpException`
dilempar untuk user di luar scope, lolos untuk user `all_pop`.

```php
#[Test]
public function menolak_user_scope_cabang_lain_mengakses_odp_cabang_lain(): void
{
    $cabangA = Pop::factory()->create(['type' => 'cabang']);
    $cabangB = Pop::factory()->create(['type' => 'cabang']);
    $miniPopB = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangB->id]);
    $odpB = Odp::factory()->create(['pop_id' => $miniPopB->id]);

    $user = User::factory()->create();
    // ... assign scope selected_pop ke $cabangA saja (pola UserRoleScope yang sudah ada)

    $this->expectException(HttpException::class);
    (new class {
        use AuthorizesWarehousePop;

        public function check(Odp $odp, User $user, EffectiveAccessService $access): void
        {
            $this->assertOdpInScope($odp, $user, $access);
        }
    })->check($odpB, $user, app(EffectiveAccessService::class));
}
```

- [ ] **Step 2: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/Warehouse/AuthorizesOdpScopeTest.php`
Expected: FAIL — method `assertOdpInScope` tidak ada.

- [ ] **Step 3: Implement `assertOdpInScope()`**

```php
protected function assertOdpInScope(Odp $odp, User $user, EffectiveAccessService $access): void
{
    $odp->loadMissing('miniPop');
    $this->assertPopInScope($odp->miniPop, $user, $access);
}
```

Reuse `assertPopInScope()` yang sudah ada — Mini POP sudah masuk hasil
`getAllowedPopIds()` lewat `resolvePopTree()` existing, tidak perlu logic baru
di `EffectiveAccessService`.

- [ ] **Step 4: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/Warehouse/AuthorizesOdpScopeTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Warehouse/Concerns/AuthorizesWarehousePop.php tests/Feature/Warehouse/AuthorizesOdpScopeTest.php
git commit -m "feat: assertOdpInScope() — scope ODP ikut Mini POP induknya"
```

---

### Task 5: `InventoryService` — `deploySerial()`, `deployQuantity()`, `retrieveSerialFromLocation()`

**Files:**
- Modify: `app/Services/InventoryService.php`
- Test: `tests/Feature/Warehouse/InventoryServiceDeployTest.php`

**Interfaces:**
- Consumes: `InfrastructureBalance` (Task 3), `TechnicianCustody` (sudah ada),
  `InventoryTransactionType::DEPLOY`/`RETRIEVE_INFRA` (Task 1).
- Produces:
  - `deploySerial(InventorySerial $serial, ?Pop $pop, ?Odp $odp, FopTask $fopTask, iterable $technicians, User $actor): InventoryTransaction`
  - `deployQuantity(iterable $technicians, Item $item, float $qtyUsed, ?Pop $pop, ?Odp $odp, FopTask $fopTask, User $actor): array` (return shape sama `consumeFromCustody()`: `list<array{custody_id:int, lot_no:?string, qty:float}>`)
  - `retrieveSerialFromLocation(InventorySerial $serial, FopTask $fopTask, User $actor): InventoryTransaction`

  Dipakai Task 6 (`TaskMaintenanceController`).

- [ ] **Step 1: Write failing tests**

```php
#[Test]
public function deploy_serial_memindahkan_sn_dari_custody_ke_lokasi_mini_pop(): void
{
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
    $item = Item::factory()->create(['tracking_type' => TrackingType::SERIALIZED, 'ownership_mode' => OwnershipMode::INFRASTRUCTURE_ASSET]);
    $technician = User::factory()->create();
    $serial = InventorySerial::factory()->create([
        'item_id' => $item->id,
        'status' => SerialStatus::ISSUED,
        'current_technician_id' => $technician->id,
    ]);
    $fopTask = FopTask::factory()->create();

    $tx = app(InventoryService::class)->deploySerial($serial, $miniPop, null, $fopTask, [$technician], $technician);

    $serial->refresh();
    $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
    $this->assertSame($miniPop->id, $serial->current_pop_id);
    $this->assertNull($serial->current_technician_id);
    $this->assertSame(InventoryTransactionType::DEPLOY, $tx->type);
}

#[Test]
public function deploy_serial_menolak_item_bukan_infrastructure_asset(): void
{
    $item = Item::factory()->create(['ownership_mode' => OwnershipMode::INSTALLABLE]);
    $technician = User::factory()->create();
    $serial = InventorySerial::factory()->create(['item_id' => $item->id, 'status' => SerialStatus::ISSUED, 'current_technician_id' => $technician->id]);

    $this->expectException(InvalidArgumentException::class);
    app(InventoryService::class)->deploySerial($serial, Pop::factory()->create(['type' => 'cabang']), null, FopTask::factory()->create(), [$technician], $technician);
}

#[Test]
public function deploy_quantity_menambah_infrastructure_balance_dan_mengurangi_custody(): void
{
    $odp = Odp::factory()->create();
    $item = Item::factory()->create(['tracking_type' => TrackingType::QUANTITY, 'ownership_mode' => OwnershipMode::INFRASTRUCTURE_ASSET]);
    $technician = User::factory()->create();
    TechnicianCustody::factory()->create(['technician_id' => $technician->id, 'item_id' => $item->id, 'qty_remaining' => 10]);

    app(InventoryService::class)->deployQuantity([$technician], $item, 4, null, $odp, FopTask::factory()->create(), $technician);

    $this->assertDatabaseHas('infrastructure_balances', ['item_id' => $item->id, 'odp_id' => $odp->id, 'qty' => 4]);
    $this->assertDatabaseHas('technician_custody', ['technician_id' => $technician->id, 'item_id' => $item->id, 'qty_remaining' => 6]);
}

#[Test]
public function deploy_quantity_menolak_kalau_custody_tim_kurang(): void
{
    $odp = Odp::factory()->create();
    $item = Item::factory()->create(['tracking_type' => TrackingType::QUANTITY, 'ownership_mode' => OwnershipMode::INFRASTRUCTURE_ASSET]);
    $technician = User::factory()->create();
    TechnicianCustody::factory()->create(['technician_id' => $technician->id, 'item_id' => $item->id, 'qty_remaining' => 2]);

    $this->expectException(InsufficientCustodyException::class);
    app(InventoryService::class)->deployQuantity([$technician], $item, 5, null, $odp, FopTask::factory()->create(), $technician);

    $this->assertDatabaseMissing('infrastructure_balances', ['item_id' => $item->id, 'odp_id' => $odp->id]);
}

#[Test]
public function retrieve_serial_from_location_mengembalikan_sn_ke_custody_aktor(): void
{
    $odp = Odp::factory()->create();
    $item = Item::factory()->create(['ownership_mode' => OwnershipMode::INFRASTRUCTURE_ASSET]);
    $actor = User::factory()->create();
    $serial = InventorySerial::factory()->create(['item_id' => $item->id, 'status' => SerialStatus::DEPLOYED, 'current_odp_id' => $odp->id]);

    $tx = app(InventoryService::class)->retrieveSerialFromLocation($serial, FopTask::factory()->create(), $actor);

    $serial->refresh();
    $this->assertSame(SerialStatus::ISSUED, $serial->status);
    $this->assertSame($actor->id, $serial->current_technician_id);
    $this->assertNull($serial->current_odp_id);
    $this->assertSame(InventoryTransactionType::RETRIEVE_INFRA, $tx->type);
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/Warehouse/InventoryServiceDeployTest.php`
Expected: FAIL — method tidak ada.

- [ ] **Step 3: Implement**

`deploySerial()` — pola re-fetch+`lockForUpdate()` sama `installSerial()`:
validasi `status===ISSUED`, `current_technician_id` ada di `$technicians`,
`item->ownership_mode===OwnershipMode::INFRASTRUCTURE_ASSET` (lempar
`InvalidArgumentException` kalau bukan — pesan sebut nama item). Validasi
**tepat satu** dari `$pop`/`$odp` non-null, lempar `InvalidArgumentException`
kalau dua-duanya null ATAU dua-duanya terisi. Update SN: `status=DEPLOYED`,
`current_pop_id=$pop?->id`, `current_odp_id=$odp?->id`, `current_technician_id=null`.
Buat `InventoryTransaction` tipe `DEPLOY`, `from_technician_id` = custody lama,
`to_pop_id`/`to_odp_id` sesuai target, `fop_task_id`, `created_by=$actor->id`,
reference pakai `generateReferenceNumber('DPL')`.

`deployQuantity()` — FIFO lock custody sama persis `consumeFromCustody()`
(copy pola lock+urutan `issued_at`), tapi per baris custody yang kepotong:
(a) tulis `InventoryTransaction` tipe `DEPLOY` (`from_technician_id`,
`to_pop_id`/`to_odp_id`, qty diambil, `unit_price_snapshot` disalin dari
custody); (b) `InfrastructureBalance::lockForUpdate()->firstOrCreate(['item_id'=>...,'pop_id'=>...,'odp_id'=>...,'lot_no'=>$lot->lot_no ?? ''])`
lalu `increment('qty', $take)` di dalam transaction yang sama. Validasi
exactly-one `$pop`/`$odp` sama seperti `deploySerial()`. Lempar
`InsufficientCustodyException` kalau custody kurang (reuse exception yang
sudah ada).

`retrieveSerialFromLocation()` — re-fetch+lock, validasi `status===DEPLOYED`.
Simpan `$fromPopId`/`$fromOdpId` dari nilai SEBELUM dikosongkan. Update SN:
`status=ISSUED`, `current_technician_id=$actor->id`, `current_pop_id=null`,
`current_odp_id=null`. Buat `InventoryTransaction` tipe `RETRIEVE_INFRA`,
`from_pop_id`/`from_odp_id` = nilai yang disimpan, `to_technician_id=$actor->id`.

- [ ] **Step 4: Run tests, verify pass**

Run: `php artisan test --compact tests/Feature/Warehouse/InventoryServiceDeployTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/InventoryService.php tests/Feature/Warehouse/InventoryServiceDeployTest.php
git commit -m "feat: InventoryService::deploySerial/deployQuantity/retrieveSerialFromLocation"
```

---

### Task 6: `InventoryAdjustmentService::adjustSerialStatus()` — kosongkan lokasi infra

**Files:**
- Modify: `app/Services/InventoryAdjustmentService.php`
- Test: `tests/Feature/Warehouse/AdjustDeployedSerialTest.php`

**Interfaces:**
- Consumes: `SerialStatus::DEPLOYED` (Task 1).
- Produces: tidak ada interface baru — perilaku `adjustSerialStatus()` existing
  diperluas, signature tidak berubah.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function adjust_sn_deployed_ke_rusak_mengosongkan_lokasi_infra(): void
{
    $odp = Odp::factory()->create();
    $serial = InventorySerial::factory()->create(['status' => SerialStatus::DEPLOYED, 'current_odp_id' => $odp->id]);
    $actor = User::factory()->create();

    app(InventoryAdjustmentService::class)->adjustSerialStatus($serial, SerialStatus::DAMAGED, 'damaged', 'rusak kena petir', $actor, evidenceFilePath: 'evidence.jpg');

    $serial->refresh();
    $this->assertNull($serial->current_odp_id);
    $this->assertNull($serial->current_pop_id);
}
```

(Sesuaikan signature `adjustSerialStatus()` dengan yang sebenarnya di
`InventoryAdjustmentService` — baca method existing sebelum menulis test ini,
param di atas cuma ilustrasi urutan argumen umum.)

- [ ] **Step 2: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/Warehouse/AdjustDeployedSerialTest.php`
Expected: FAIL — `current_odp_id` masih terisi setelah adjust.

- [ ] **Step 3: Implement**

Di blok `adjustSerialStatus()` yang sudah mengosongkan `customer_id`/`fop_task_id`/`installed_at`
saat SN `INSTALLED` diadjust (lihat Gudang business-logic §7/§12) — tambah
cabang: kalau `$serial->status` SEBELUM diubah adalah `DEPLOYED`, kosongkan
juga `current_pop_id` dan `current_odp_id` (selain `customer_id` dkk yang
memang tidak relevan untuk kasus ini). `current_technician_id` TETAP
dibiarkan sebagai jejak forensik (konsisten §12 Gudang, bukan dikosongkan).

- [ ] **Step 4: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/Warehouse/AdjustDeployedSerialTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/InventoryAdjustmentService.php tests/Feature/Warehouse/AdjustDeployedSerialTest.php
git commit -m "fix: adjustSerialStatus() kosongkan current_pop_id/current_odp_id untuk SN DEPLOYED"
```

---

### Task 7: Seksi "Pasang/Ganti Infrastruktur" di Laporan O-REQ/INFR REQ

**Files:**
- Modify: `app/Http/Controllers/TaskMaintenanceController.php`
- Modify: `resources/views/tasks/maintenance-report.blade.php`
- Test: `tests/Feature/TaskInfrastructureDeployReportTest.php`

**Interfaces:**
- Consumes: `InventoryService::deploySerial()`/`deployQuantity()`/`retrieveSerialFromLocation()` (Task 5),
  `Odp` (Task 2), `OwnershipMode::INFRASTRUCTURE_ASSET` (Task 1).
- Produces: tidak ada interface baru untuk task lain — ini titik akhir alur user-facing.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function teknisi_bisa_deploy_sn_infrastruktur_lewat_laporan_infr_req(): void
{
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
    $technician = $this->loginAsTechnicianInPop($cabang); // sesuaikan helper login teknisi yang sudah ada di TestCase
    $task = Task::factory()->create(['task_type' => TaskType::INFR, 'pop_id' => $cabang->id]);
    TaskTeamMember::factory()->create(['task_id' => $task->id, 'user_id' => $technician->id]);
    $item = Item::factory()->create(['tracking_type' => TrackingType::SERIALIZED, 'ownership_mode' => OwnershipMode::INFRASTRUCTURE_ASSET]);
    $serial = InventorySerial::factory()->create(['item_id' => $item->id, 'status' => SerialStatus::ISSUED, 'current_technician_id' => $technician->id]);

    $response = $this->post(route('tasks.maintenance.store', $task), [
        'kendala_teknis' => 'Pasang OLT baru',
        'infra_target_mini_pop_id' => $miniPop->id,
        'infra_selected_serial_id' => $serial->id,
    ]);

    $response->assertRedirect(route('tasks.show', $task));
    $serial->refresh();
    $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
    $this->assertSame($miniPop->id, $serial->current_pop_id);
}

#[Test]
public function tolak_kirim_target_mini_pop_dan_odp_bersamaan(): void
{
    // ... setup sama di atas, tambah 'infra_target_odp_id' => $odp->id bersamaan 'infra_target_mini_pop_id'
    $response->assertSessionHasErrors('infra_target_mini_pop_id');
}

#[Test]
public function tolak_odp_yang_bukan_anak_cabang_task_ini(): void
{
    $cabangTask = Pop::factory()->create(['type' => 'cabang']);
    $cabangLain = Pop::factory()->create(['type' => 'cabang']);
    $miniPopLain = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabangLain->id]);
    $odpLain = Odp::factory()->create(['pop_id' => $miniPopLain->id]);

    $technician = $this->loginAsTechnicianInPop($cabangTask);
    $task = Task::factory()->create(['task_type' => TaskType::OREQ, 'pop_id' => $cabangTask->id]);
    TaskTeamMember::factory()->create(['task_id' => $task->id, 'user_id' => $technician->id]);

    $response = $this->post(route('tasks.maintenance.store', $task), [
        'kendala_teknis' => 'Pasang splitter',
        'infra_target_odp_id' => $odpLain->id,
        'infra_quantity_item_id' => Item::factory()->create(['tracking_type' => TrackingType::QUANTITY, 'ownership_mode' => OwnershipMode::INFRASTRUCTURE_ASSET])->id,
        'infra_quantity_qty' => 1,
    ]);

    $response->assertSessionHasErrors('infra_target_odp_id');
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskInfrastructureDeployReportTest.php`
Expected: FAIL — field `infra_*` belum diproses, SN tidak berubah status.

- [ ] **Step 3: Implement controller**

Di `report()`: tambah `$eligibleInfraSerials = $this->eligibleInfrastructureSerialsForTeam($task)`
(method baru, copy `eligibleSerialsForTeam()` tapi filter
`ownership_mode=OwnershipMode::INFRASTRUCTURE_ASSET`), `$eligibleInfraCustody`
(copy `eligiblePassiveCustodyForTeam()` tapi filter item `ownership_mode=INFRASTRUCTURE_ASSET`
bukan `effective_equipment_class=PASIF`), dan dropdown lokasi: Mini POP
(`Pop::where('type','mini_pop')->where('parent_id', $task->pop_id)`) + ODP
(`Odp::whereIn('pop_id', $miniPopIdsTadi)`), dioper ke view. **Seksi ini
cuma tampil untuk `$task->task_type ∈ [TaskType::OREQ, TaskType::INFR]`.**

Di `store()`, tambah rules (semua `nullable`, seksi opsional sama pola
Material Terpakai):

```php
'infra_target_mini_pop_id' => ['nullable', 'integer', Rule::exists('pops', 'id')->where('type', 'mini_pop')->where('parent_id', $task->pop_id)],
'infra_target_odp_id' => ['nullable', 'integer', Rule::exists('odps', 'id')],
'infra_selected_serial_id' => ['nullable', 'integer', Rule::in($eligibleInfraSerialIds)],
'infra_retrieve_old_serial_id' => ['nullable', 'integer'],
'infra_quantity_item_id' => ['nullable', 'integer', 'exists:items,id'],
'infra_quantity_qty' => ['nullable', 'numeric', 'min:0.01', 'required_with:infra_quantity_item_id'],
```

Plus validasi custom (closure/`Validator::after`): **tolak kalau
`infra_target_mini_pop_id` dan `infra_target_odp_id` dua-duanya terisi**, dan
**tolak kalau salah satu field `infra_*` lain terisi tapi target lokasi
kosong** (harus pilih lokasi dulu). Kalau ODP dipilih, validasi
`Odp::find($id)->miniPop->parent_id === $task->pop_id` (ODP harus anak Cabang
task ini) — lempar error kalau tidak (Review Focus item ke-3).

Di blok transaction (dalam `store()`, setelah `$fopTask` resolve, sejajar blok
`selected_inventory_serial_id`/`selected_inventory_roll_id` yang sudah ada):

```php
if (!empty($validated['infra_target_mini_pop_id']) || !empty($validated['infra_target_odp_id'])) {
    $pop = !empty($validated['infra_target_mini_pop_id']) ? Pop::find($validated['infra_target_mini_pop_id']) : null;
    $odp = !empty($validated['infra_target_odp_id']) ? Odp::find($validated['infra_target_odp_id']) : null;

    if (!empty($validated['infra_retrieve_old_serial_id'])) {
        $oldSerial = InventorySerial::findOrFail($validated['infra_retrieve_old_serial_id']);
        app(InventoryService::class)->retrieveSerialFromLocation($oldSerial, $fopTask, auth()->user());
    }

    if (!empty($validated['infra_selected_serial_id'])) {
        $serial = InventorySerial::findOrFail($validated['infra_selected_serial_id']);
        app(InventoryService::class)->deploySerial($serial, $pop, $odp, $fopTask, $teamTechnicians, auth()->user());
    }

    if (!empty($validated['infra_quantity_item_id'])) {
        $item = Item::findOrFail($validated['infra_quantity_item_id']);
        app(InventoryService::class)->deployQuantity($teamTechnicians, $item, (float) $validated['infra_quantity_qty'], $pop, $odp, $fopTask, auth()->user());
    }
}
```

- [ ] **Step 4: Implement view**

Di `resources/views/tasks/maintenance-report.blade.php`, tambah seksi baru
(Alpine `x-show="taskType === 'O-REQ' || taskType === 'INFR REQ'"`, pola sama
seksi Material Terpakai yang sudah kondisional) — dropdown berjenjang Cabang
(fixed dari task) → Mini POP → ODP (JS filter opsi `<select>` ODP berdasar
Mini POP terpilih, pola sama dropdown berjenjang modal "Atur Mini POP &
Distribusi"), dropdown SN dari `$eligibleInfraSerials`, dropdown qty item dari
`$eligibleInfraCustody`, dan dropdown opsional "SN lama yang dicabut" (daftar
SN `status=DEPLOYED` di lokasi yang sama, query ringan di controller atau lazy
lewat endpoint AJAX kalau datanya besar — pilih yang lebih sederhana sesuai
pola endpoint `availableStock()` yang sudah ada di modul Gudang).

- [ ] **Step 5: Run tests, verify pass**

Run: `php artisan test --compact tests/Feature/TaskInfrastructureDeployReportTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/TaskMaintenanceController.php resources/views/tasks/maintenance-report.blade.php tests/Feature/TaskInfrastructureDeployReportTest.php
git commit -m "feat: seksi Pasang/Ganti Infrastruktur di Laporan O-REQ/INFR REQ"
```

---

### Task 8: Halaman "Aset per Lokasi" (lokasi-centric)

**Files:**
- Create: `app/Http/Controllers/Warehouse/WarehouseLocationAssetController.php`
- Create: `resources/views/warehouse/location_assets/index.blade.php`
- Create: `database/seeders/WarehouseLocationAssetFeatureSeeder.php`
- Modify: `config/rbac.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Warehouse/LocationAssetPageTest.php`

**Interfaces:**
- Consumes: `AuthorizesWarehousePop::assertOdpInScope()`/`assertPopInScope()` (Task 4),
  `InventorySerial` (`status=DEPLOYED`), `InfrastructureBalance` (Task 3/5).
- Produces: route `warehouse.location-assets.index`, permission `warehouse_location_asset.view`.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function menampilkan_aset_deployed_di_mini_pop_dan_rollup_odp_anaknya(): void
{
    $this->loginAsAdmin();
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $cabang->id]);
    $odp = Odp::factory()->create(['pop_id' => $miniPop->id]);
    $serialDiMiniPop = InventorySerial::factory()->create(['status' => SerialStatus::DEPLOYED, 'current_pop_id' => $miniPop->id]);
    $serialDiOdp = InventorySerial::factory()->create(['status' => SerialStatus::DEPLOYED, 'current_odp_id' => $odp->id]);

    $response = $this->get(route('warehouse.location-assets.index', ['mini_pop_id' => $miniPop->id]));

    $response->assertOk();
    $response->assertSee($serialDiMiniPop->serial_number);
    $response->assertSee($serialDiOdp->serial_number);
}

#[Test]
public function filter_odp_spesifik_tidak_ikut_rollup_mini_pop(): void
{
    // setup 2 ODP beda di bawah Mini POP yang sama, assert cuma SN ODP yang dipilih muncul.
}
```

- [ ] **Step 2: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/Warehouse/LocationAssetPageTest.php`
Expected: FAIL — route tidak ada.

- [ ] **Step 3: Implement**

`WarehouseLocationAssetController::index(Request $request)` — query param
`cabang_id`/`mini_pop_id`/`odp_id` (berjenjang, makin spesifik makin
menyempit). Resolusi:
- `odp_id` terisi → `InventorySerial::where('current_odp_id', $odpId)`,
  `InfrastructureBalance::where('odp_id', $odpId)`, scope via
  `assertOdpInScope()`.
- `mini_pop_id` terisi (tanpa `odp_id`) → SN `current_pop_id=$miniPopId`
  **UNION** SN yang `current_odp_id` ada di `Odp::where('pop_id',$miniPopId)->pluck('id')`
  (rollup), sama untuk `InfrastructureBalance`. Scope via `assertPopInScope()`.
- Breadcrumb: Cabang → Mini POP → ODP, dirangkai dari relasi `parent`/`miniPop`.

View — tabel SN (serial_number, nama item, `deployed_at` dari
`InventoryTransaction::where('serial_id',$id)->where('type','deploy')->latest()->value('created_at')`,
link ke `warehouse.traceability.index` dengan query SN) + tabel saldo
QUANTITY dari `InfrastructureBalance`. Dropdown Cabang/Mini POP/ODP
berjenjang, scope `Pop::forUser()`.

`config/rbac.php` — `'warehouse_location_asset' => [ActionCode::VIEW->value]`.
`WarehouseLocationAssetFeatureSeeder` — pola `ItemFeatureSeeder`.
Route di `routes/web.php`, grup `permission:warehouse_location_asset.view`,
dekat blok `warehouse.traceability.index`.

- [ ] **Step 4: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/Warehouse/LocationAssetPageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan db:seed --class=WarehouseLocationAssetFeatureSeeder
git add app/Http/Controllers/Warehouse/WarehouseLocationAssetController.php resources/views/warehouse/location_assets database/seeders/WarehouseLocationAssetFeatureSeeder.php config/rbac.php routes/web.php tests/Feature/Warehouse/LocationAssetPageTest.php
git commit -m "feat: halaman Aset per Lokasi (lokasi-centric) — audit infrastruktur per Mini POP/ODP"
```

---

## Urutan Eksekusi

Task 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 (linear — tiap task konsumsi hasil task
sebelumnya; tidak ada task yang bisa dikerjakan paralel tanpa konflik migrasi/model).
