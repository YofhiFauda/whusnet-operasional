# Pohon Infrastruktur Jaringan + Aset Gudang per Lokasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pohon node infrastruktur jaringan generik (`network_nodes`, tipe
dinamis lewat lookup admin) dengan kapasitas/port, dipadukan dengan aset
Gudang (OLT/switch/ODP box/splitter/kabel) yang dipasang/diganti lewat
laporan task O-REQ/INFR REQ, dan diaudit dua arah (SN→lokasi, lokasi→isi).

**Architecture:** Satu tabel pohon self-referencing `network_nodes`
(`parent_id` + anchor `root_pop_id` ke Cabang/Mini POP existing), tipe node
lewat master lookup `network_node_types` (bukan enum — admin bisa nambah
tipe tanpa deploy). Kapasitas lewat `capacity` (ringkasan) atau
`network_node_ports` (port bernomor). Aset Gudang nempel lewat SATU kolom
baru per tabel (`current_network_node_id`, `to/from_network_node_id`,
`network_node_id`) — bukan pasangan kolom per level. **Rekonsiliasi**:
kerja uncommitted task lain (`odps`/`odp_ports`/`Network\OdpController`/
`FopUnifiedDashboardService::getOdpSummary()`) direshape jadi instance
pertama pohon ini, tidak dibuang.

**Tech Stack:** Laravel 13, PHPUnit 12, Blade + Alpine.js (Vite), sqlite `:memory:` test.

**Spec:** `docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` (v2)

## Global Constraints

- Semua nomor dokumen ledger lewat `generateReferenceNumber()` yang sudah
  ada — jangan `count()+1`.
- `inventory_balances`/`inventory_transfers` tetap cuma pusat/cabang
  (`Pop::WAREHOUSE_TYPES`) — tidak disentuh, tidak dilonggarkan.
- Ledger (`inventory_transactions`) append-only.
- Guard `OwnershipMode::INFRASTRUCTURE_ASSET` ditegakkan di `InventoryService`
  — satu-satunya pintu transisi ke `SerialStatus::DEPLOYED`.
- Permission baru lewat `config/rbac.php` + `PermissionGeneratorService::generate()`.
- Scope POP baru pakai `EffectiveAccessService`/`AuthorizesWarehousePop` yang
  sudah ada — jangan query `getAllowedPopIds()` inline baru.
- `vendor/bin/pint --dirty --format agent` tiap task yang menyentuh PHP.
- **Sebelum Task 1**: `git status`/`git diff` ulang working tree — plan ini
  diasumsikan dieksekusi di atas state yang sudah ada `odps`/`odp_ports`/
  `Network\OdpController`/`FopUnifiedDashboardService::getOdpSummary()`/
  `OdpNetworkManagementTest` (uncommitted, per analisa 2026-10-09). Kalau
  state itu sudah berubah (sudah di-commit, atau sudah dihapus), **berhenti
  dan konfirmasi ke user** sebelum lanjut Task 6 (rekonsiliasi) — jangan
  asumsikan.

## Review Focus

- **SN company_asset/installable biasa jangan bisa di-deploy** — guard
  `OwnershipMode::INFRASTRUCTURE_ASSET` harus menolak dua mode lain.
- **`network_node_id` yang dikirim bukan milik scope POP aktor** — harus
  ditolak lewat `assertNetworkNodeInScope()`, bukan nyangkut ke cabang lain.
- **`deployQuantity()`/`deployRoll()` custody tim tidak cukup** — harus
  melempar exception yang sudah ada, bukan silently partial-deploy.
- **SN `DEPLOYED` diadjust ke LOST/DAMAGED/SCRAPPED/QUARANTINE** —
  `current_network_node_id` harus ikut terkosongkan.
- **Data `odps`/`odp_ports` existing (hasil task lain) hilang/rusak** saat
  reshape ke `network_nodes`/`network_node_ports` — migration data Task 6
  wajib idempotent & reversible, dites dengan data existing di tempat.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `app/Enums/OwnershipMode.php` | + case `INFRASTRUCTURE_ASSET` |
| `app/Enums/SerialStatus.php` | + case `DEPLOYED` |
| `app/Enums/InventoryTransactionType.php` | + case `DEPLOY`, `RETRIEVE_INFRA` |
| `database/seeders/ItemSeeder.php` | Koreksi `tracking_type` splitter/ODP (serialized) & kabel (roll) |
| `database/migrations/2026_10_12_1100*_create_network_node_types_table.php` | Master lookup tipe node |
| `app/Models/NetworkNodeType.php` | Model lookup |
| `app/Http/Controllers/Master/NetworkNodeTypeController.php` | CRUD lookup |
| `database/seeders/NetworkNodeTypeSeeder.php` | 7 tipe awal |
| `database/migrations/2026_10_12_1101*_create_network_nodes_table.php` | Pohon node |
| `app/Models/NetworkNode.php` | Model pohon, breadcrumb, scope |
| `database/migrations/2026_10_12_1102*_create_network_node_ports_table.php` | Port generik |
| `app/Models/NetworkNodePort.php` | Model port |
| `app/Http/Controllers/Network/NetworkNodeController.php` | CRUD pohon + port (generalisasi `Network\OdpController`) |
| `resources/views/network/nodes/index.blade.php` | View pohon + kapasitas + aset (generalisasi `network/odps/index.blade.php`) |
| `database/migrations/2026_10_12_1103*_reshape_odps_into_network_nodes.php` | Migrasi data `odps`→`network_nodes`, `odp_ports`→`network_node_ports`, drop tabel lama |
| `app/Services/FopUnifiedDashboardService.php` | `getOdpSummary()` baca `network_nodes`/`network_node_ports` |
| `tests/Feature/OdpNetworkManagementTest.php` | Disesuaikan ke route/model baru |
| `database/migrations/2026_10_12_1104*_add_current_network_node_id_to_inventory_serials_table.php` | Kolom lokasi SN |
| `database/migrations/2026_10_12_1105*_add_network_node_columns_to_inventory_transactions_table.php` | Kolom ledger |
| `database/migrations/2026_10_12_1106*_create_infrastructure_balances_table.php` | Proyeksi QUANTITY/ROLL yang di-deploy |
| `app/Models/InfrastructureBalance.php` | Model proyeksi |
| `app/Models/InventorySerial.php` | + relasi `currentNetworkNode()` |
| `app/Models/InventoryTransaction.php` | + relasi `toNetworkNode()`/`fromNetworkNode()` |
| `app/Http/Controllers/Warehouse/Concerns/AuthorizesWarehousePop.php` | + `assertNetworkNodeInScope()` |
| `app/Services/InventoryService.php` | + `deploySerial()`, `deployQuantity()`, `deployRoll()`, `retrieveSerialFromLocation()` |
| `app/Services/InventoryAdjustmentService.php` | `adjustSerialStatus()` kosongkan `current_network_node_id` |
| `app/Http/Controllers/TaskMaintenanceController.php` | + seksi "Pasang/Ganti Infrastruktur", tree picker node |
| `resources/views/tasks/maintenance-report.blade.php` | + seksi form baru |
| `config/rbac.php` | `master_network_node_types.*`, `network_nodes.*` (rename `network_odps`) |
| `routes/web.php` | Routes lookup + node + port (ganti routes `network.odps.*`) |

---

### Task 1: Enum baru — `OwnershipMode`, `SerialStatus`, `InventoryTransactionType`

**Files:**
- Modify: `app/Enums/OwnershipMode.php`, `app/Enums/SerialStatus.php`, `app/Enums/InventoryTransactionType.php`
- Test: `tests/Unit/InfrastructureAssetEnumTest.php`

**Interfaces:**
- Produces: `OwnershipMode::INFRASTRUCTURE_ASSET`, `SerialStatus::DEPLOYED`,
  `InventoryTransactionType::DEPLOY`, `InventoryTransactionType::RETRIEVE_INFRA`.

- [ ] **Step 1: Write failing test** — iterasi `cases()` tiap enum, assert
  `label()`/`badgeVariant()` tidak lempar error, assert value baru sesuai
  (`infrastructure_asset`, `deployed`, `deploy`, `retrieve_infra`).
- [ ] **Step 2: Run, verify fails** — `php artisan test --compact tests/Unit/InfrastructureAssetEnumTest.php`
- [ ] **Step 3: Implement** — tambah case + `label()` di tiap enum
  (`OwnershipMode`: `'Aset Infrastruktur (Terpasang Netap)'`; `SerialStatus`:
  `'Terpasang di Infrastruktur'`, masuk grup `badgeVariant()` `success`;
  `InventoryTransactionType`: `'Terpasang di Infrastruktur'`/`'Dicabut dari Infrastruktur'`).
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit** — `feat: tambah enum OwnershipMode::INFRASTRUCTURE_ASSET, SerialStatus::DEPLOYED, InventoryTransactionType::DEPLOY/RETRIEVE_INFRA`

---

### Task 2: Koreksi `ItemSeeder` — Splitter/ODP jadi SERIALIZED, Kabel jadi ROLL

Kategori `splitter_odp` (SPL-1X2…SPL-1X32, ODP-8, ODP-16, ODC-24) dan
`kabel_dropcore` (DC-1C, DC-2C, KBL-FEEDER-12C) di `ItemSeeder.php` tidak
mengisi `tracking_type` override → default `'quantity'` (`ItemSeeder.php:165`).
Seharusnya `splitter_odp`/ODP box = `serialized`+`auto_generate_serial=true`
(sudah terdokumentasi `docs/warehouse/business-logic.md` §1), `kabel_dropcore`
= `roll`. Task 9 (`deploySerial()`/`deployRoll()`) butuh data ini benar.

**Files:**
- Modify: `database/seeders/ItemSeeder.php`
- Test: `tests/Unit/ItemSeederTrackingTypeTest.php`

- [ ] **Step 1: Write failing test** — seed `ItemSeeder`, assert
  `Item::where('code','SPL-1X8')->first()->tracking_type === TrackingType::SERIALIZED`
  dan `auto_generate_serial === true`; assert `Item::where('code','ODP-8')`
  sama; assert `Item::where('code','DC-1C')->tracking_type === TrackingType::ROLL`.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement** — tambah `'tracking_type' => 'serialized', 'auto_generate_serial' => true`
  ke tiap baris kategori `splitter_odp`; `'tracking_type' => 'roll'` ke
  tiap baris `kabel_dropcore`. **Data existing yang sudah ter-seed salah**
  (dev/staging) — `updateOrCreate` yang sudah ada otomatis perbaiki ulang
  begitu `db:seed --class=ItemSeeder` dijalankan ulang; kalau sudah ada
  `inventory_balances`/`inventory_serials` ter-isi dengan `tracking_type`
  lama untuk kode-kode ini, itu migrasi data terpisah, di luar scope —
  berhenti & konfirmasi kalau ditemukan saat eksekusi.
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit** — `fix: ItemSeeder — splitter/ODP jadi SERIALIZED auto-generate, kabel dropcore jadi ROLL`

---

### Task 3: Master `network_node_types`

**Files:**
- Create: `database/migrations/2026_10_12_110000_create_network_node_types_table.php`
- Create: `app/Models/NetworkNodeType.php`
- Create: `app/Http/Controllers/Master/NetworkNodeTypeController.php`
- Create: `resources/views/master/network-node-types/{index,create,edit}.blade.php`
- Create: `database/seeders/NetworkNodeTypeSeeder.php`
- Modify: `config/rbac.php`, `routes/web.php`
- Test: `tests/Feature/Master/NetworkNodeTypeCrudTest.php`

**Interfaces:**
- Produces: `NetworkNodeType` model (`code`, `name`, `is_active`, `sort_order`),
  routes `master.network-node-types.{index,create,store,edit,update,destroy}`,
  permission `master_network_node_types.{view,create,update,delete}`.
  Dipakai Task 4 (`network_nodes.node_type_id`).

- [ ] **Step 1: Write migration**

```php
Schema::create('network_node_types', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('name', 100);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 2: Run migration** — `php artisan migrate --no-interaction`
- [ ] **Step 3: Write failing test** — pola sama `tests/Feature/Master/ItemCategoryCrudTest.php`
  kalau ada, atau minimal: admin bisa `store()` tipe baru (`code='repeater'`),
  `code` unique ditolak kalau dobel, `destroy()` ditolak kalau
  `network_nodes.node_type_id` masih mereferensikannya (butuh Task 4 selesai
  dulu untuk test guard-hapus ini — kalau Task 4 belum ada, test guard-hapus
  ditulis di Task 4 sebagai regression, bukan di sini).
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement** — model fillable
  `['code','name','is_active','sort_order']`; controller pola
  `TicketIssueCategoryController` (CRUD polos, search+filter status);
  views pola `master/ticket-issue-categories/*`; `config/rbac.php` tambah
  `'master_network_node_types' => [VIEW,CREATE,UPDATE,DELETE]`;
  `NetworkNodeTypeSeeder` — `updateOrCreate` by `code` untuk 7 tipe:
  `core_router`, `backbone`, `aggregation_switch`, `olt`, `pon_port`, `odc`,
  `odp` (nama label Indonesia tiap satu), lalu `PermissionGeneratorService::generate()`;
  routes pola blok Master lain (static-first).
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit + seed** — `vendor/bin/pint --dirty --format agent`,
  `php artisan db:seed --class=NetworkNodeTypeSeeder`,
  `feat: master network_node_types — tipe node infrastruktur dikelola admin`

---

### Task 4: Pohon `network_nodes`

**Files:**
- Create: `database/migrations/2026_10_12_110100_create_network_nodes_table.php`
- Create: `app/Models/NetworkNode.php`
- Test: `tests/Unit/NetworkNodeModelTest.php`

**Interfaces:**
- Consumes: `NetworkNodeType` (Task 3), `Pop` (sudah ada).
- Produces: `NetworkNode` — fillable `['parent_id','root_pop_id','node_type_id','code','name','capacity','address','village','district','city','latitude','longitude','status','notes']`.
  Relasi: `parent(): BelongsTo(NetworkNode::class)`, `children(): HasMany(NetworkNode::class,'parent_id')`,
  `rootPop(): BelongsTo(Pop::class,'root_pop_id')`, `nodeType(): BelongsTo(NetworkNodeType::class)`.
  Method `breadcrumbLabel(): string` — gabung `rootPop->parent?->name` (Cabang,
  kalau `rootPop` adalah Mini POP) → `rootPop->name` → naik `parent` chain
  `network_nodes` sampai node ini, dipisah `" → "`.
  Dipakai Task 5/6/8/9/12.

- [ ] **Step 1: Write migration**

```php
Schema::create('network_nodes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('network_nodes')->restrictOnDelete();
    $table->foreignId('root_pop_id')->constrained('pops')->restrictOnDelete();
    $table->foreignId('node_type_id')->constrained('network_node_types')->restrictOnDelete();
    $table->string('code', 50)->unique();
    $table->string('name', 150);
    $table->integer('capacity')->nullable();
    $table->text('address')->nullable();
    $table->string('village', 100)->nullable();
    $table->string('district', 100)->nullable();
    $table->string('city', 100)->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->string('status', 20)->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index(['parent_id', 'root_pop_id', 'node_type_id']);
});
```

- [ ] **Step 2: Run migration**
- [ ] **Step 3: Write failing test** — `breadcrumbLabel()` 3 skenario: node
  root langsung di bawah Cabang (`root_pop_id`=Cabang, `parent_id=null`) →
  `"Cabang Jetis → <name>"`; node root di bawah Mini POP → `"Cabang Jetis →
  Mini POP C1 → <name>"`; node bertingkat (`parent_id` terisi 2 level) →
  rangkaian lengkap. Assert `children()` mengembalikan anak langsung saja
  (bukan rekursif) untuk node dengan 2 anak + 1 cucu.
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement model** — relasi di atas, `breadcrumbLabel()`
  null-safe (`rootPop` wajib ada, `rootPop->parent` opsional).
  `database/factories/NetworkNodeFactory.php` minimal (`root_pop_id`
  default `Pop::factory()->create(['type'=>'cabang'])`, `node_type_id`
  default `NetworkNodeType::factory()->create()`).
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit** — `feat: pohon network_nodes self-referencing, anchor ke root_pop_id`

---

### Task 5: Port generik `network_node_ports`

**Files:**
- Create: `database/migrations/2026_10_12_110200_create_network_node_ports_table.php`
- Create: `app/Models/NetworkNodePort.php`
- Test: `tests/Unit/NetworkNodePortModelTest.php`

**Interfaces:**
- Consumes: `NetworkNode` (Task 4).
- Produces: `NetworkNodePort` — fillable `['network_node_id','number','is_used']`,
  relasi `node(): BelongsTo(NetworkNode::class,'network_node_id')`.
  `NetworkNode::ports(): HasMany(NetworkNodePort::class)->orderBy('number')`.
  Dipakai Task 6 (reshape data) & Task 12 (tampilan kapasitas).

- [ ] **Step 1: Write migration** — identik bentuk `odp_ports` existing,
  ganti FK:

```php
Schema::create('network_node_ports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('network_node_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('number');
    $table->boolean('is_used')->default(false);
    $table->timestamps();
    $table->unique(['network_node_id', 'number']);
});
```

- [ ] **Step 2: Run migration**
- [ ] **Step 3: Write failing test** — buat `NetworkNode`, buat 3
  `NetworkNodePort` lewat relasi `ports()->create()`, assert
  `$node->ports()->where('is_used', true)->count()` dan urut `number` ASC.
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement model**
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit** — `feat: network_node_ports — port generik per node`

---

### Task 6: Rekonsiliasi — reshape `odps`/`odp_ports` jadi `network_nodes`/`network_node_ports`

**Prasyarat:** cek `git status`/isi tabel `odps` sebelum mulai (lihat
Global Constraints). Task ini mengasumsikan `odps`, `odp_ports`,
`App\Http\Controllers\Network\OdpController`, routes `network.odps.*`,
`FopUnifiedDashboardService::getOdpSummary()`, dan
`tests/Feature/OdpNetworkManagementTest.php` **sudah ada** (hasil task
lain, uncommitted per analisa 2026-10-09).

**Files:**
- Create: `database/migrations/2026_10_12_110300_reshape_odps_into_network_nodes.php`
- Modify: `app/Http/Controllers/Network/OdpController.php` → rename jadi `NetworkNodeController.php`
- Modify: `resources/views/network/odps/index.blade.php` → pindah `resources/views/network/nodes/index.blade.php`
- Modify: `app/Services/FopUnifiedDashboardService.php` (`getOdpSummary()`)
- Modify: `routes/web.php` (ganti `network.odps.*`/`network.odp-ports.*` jadi `network.nodes.*`/`network.node-ports.*`)
- Modify: `config/rbac.php` (rename `network_odps` jadi `network_nodes`)
- Modify: `tests/Feature/OdpNetworkManagementTest.php`
- Delete: `app/Models/Odp.php`, `app/Models/OdpPort.php`,
  `database/migrations/2026_10_12_101914_create_odps_table.php`,
  `database/seeders/OdpFeatureSeeder.php` (fungsinya diambil alih `NetworkNodeTypeSeeder` Task 3)

**Interfaces:**
- Consumes: `NetworkNode`/`NetworkNodePort`/`NetworkNodeType` (Task 3-5).
- Produces: route `network.nodes.index`/`.store`/`.ports.add`,
  `network.node-ports.update` (rename dari `network.odps.*`/`network.odp-ports.*`).
  Dipakai Task 12.

- [ ] **Step 1: Write failing test** — adaptasi 5 test
  `OdpNetworkManagementTest` existing ke API baru: ganti `Odp::create([...])`
  jadi `NetworkNode::create(['root_pop_id'=>...,'node_type_id'=>$odpType->id,...])`,
  ganti route `network.odps.store`/`network.odps.index`/`network.odp-ports.update`
  jadi `network.nodes.store`/`network.nodes.index`/`network.node-ports.update`,
  tambah 1 test baru: migration data — seed 1 baris `odps`+2 `odp_ports`
  (kalau tabel lama masih ada di DB test) lalu assert setelah migration
  jalan, baris yang sama muncul di `network_nodes`/`network_node_ports`
  dengan `node_type.code='odp'`.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Write migration data (`up()`)**

```php
// Hanya jalan kalau tabel lama masih ada (migration ini bisa dieksekusi
// di environment yang BELUM pernah punya odps/odp_ports sama sekali —
// misal fresh install setelah plan ini — jadi guard Schema::hasTable()).
if (Schema::hasTable('odps')) {
    $odpTypeId = DB::table('network_node_types')->where('code', 'odp')->value('id');

    DB::table('odps')->orderBy('id')->each(function ($odp) use ($odpTypeId) {
        $nodeId = DB::table('network_nodes')->insertGetId([
            'parent_id' => null,
            'root_pop_id' => $odp->pop_id,
            'node_type_id' => $odpTypeId,
            'code' => $odp->code,
            'name' => $odp->name,
            'status' => 'active',
            'created_at' => $odp->created_at,
            'updated_at' => $odp->updated_at,
        ]);

        DB::table('odp_ports')->where('odp_id', $odp->id)->each(function ($port) use ($nodeId) {
            DB::table('network_node_ports')->insert([
                'network_node_id' => $nodeId,
                'number' => $port->number,
                'is_used' => $port->is_used,
                'created_at' => $port->created_at,
                'updated_at' => $port->updated_at,
            ]);
        });
    });

    Schema::dropIfExists('odp_ports');
    Schema::dropIfExists('odps');
}
```

(`each()` butuh `DB::table(...)->orderBy('id')->cursor()->each(...)` kalau
`each()` bukan method langsung di query builder versi yang terpasang —
cek API Laravel 13 aktual saat implementasi, ganti ke `->cursor()->each()`
kalau perlu.)

- [ ] **Step 4: Rename controller & routes** — `OdpController` →
  `NetworkNodeController`: `index()` query `NetworkNode::query()->with('nodeType','ports')`
  (filter opsional `node_type_id` lewat query param, default tanpa filter =
  semua node), `store()` validasi `node_type_id` + `root_pop_id` (scope POP
  sama persis `OdpController::store()` existing, cuma field berubah nama),
  `addPorts()`/`updatePort()` **logic tidak berubah** cuma rename
  `Odp`→`NetworkNode`, `OdpPort`→`NetworkNodePort`, `odp_id`→`network_node_id`.
  View `network/nodes/index.blade.php` — copy `network/odps/index.blade.php`,
  tambah kolom "Tipe" (dari `node_type.name`) dan breadcrumb
  (`$node->breadcrumbLabel()`) yang sebelumnya tidak ada (ODP lama cuma 1
  level, tidak butuh breadcrumb).
- [ ] **Step 5: Update `FopUnifiedDashboardService::getOdpSummary()`** —
  ganti query `Odp::with('ports')` jadi
  `NetworkNode::whereHas('nodeType', fn ($q) => $q->where('code', 'odp'))->with('ports')`,
  output shape (`red_zone_count`, `red_zone[].used/.capacity`) **tidak
  berubah** — konsumen (`OdpNetworkManagementTest`, dashboard view) tidak
  perlu tau internal berubah.
- [ ] **Step 6: Update `config/rbac.php`/`routes/web.php`** — rename
  `network_odps` → `network_nodes` di `allowed_actions`, route
  `network.odps.*`/`network.odp-ports.*` → `network.nodes.*`/`network.node-ports.*`.
- [ ] **Step 7: Delete file lama** — `app/Models/Odp.php`, `app/Models/OdpPort.php`,
  migration `create_odps_table` lama, `OdpFeatureSeeder.php`.
- [ ] **Step 8: Run migration + test, verify pass** —
  `php artisan migrate --no-interaction`,
  `php artisan test --compact tests/Feature/OdpNetworkManagementTest.php`
- [ ] **Step 9: Commit** — `vendor/bin/pint --dirty --format agent`,
  `refactor: reshape odps/odp_ports jadi network_nodes/network_node_ports, generalisasi NetworkNodeController`

---

### Task 7: Kolom lokasi di ledger & SN + model `InfrastructureBalance`

**Files:**
- Create: `database/migrations/2026_10_12_110400_add_current_network_node_id_to_inventory_serials_table.php`
- Create: `database/migrations/2026_10_12_110500_add_network_node_columns_to_inventory_transactions_table.php`
- Create: `database/migrations/2026_10_12_110600_create_infrastructure_balances_table.php`
- Create: `app/Models/InfrastructureBalance.php`
- Modify: `app/Models/InventorySerial.php`, `app/Models/InventoryTransaction.php`
- Test: `tests/Unit/InfrastructureBalanceModelTest.php`

**Interfaces:**
- Consumes: `NetworkNode` (Task 4).
- Produces: `InventorySerial::currentNetworkNode(): BelongsTo`;
  `InventoryTransaction::toNetworkNode()`/`fromNetworkNode(): BelongsTo`;
  `InfrastructureBalance` — fillable `['item_id','lot_no','network_node_id','qty']`,
  relasi `item()`, `networkNode()`. Dipakai Task 9/12.

- [ ] **Step 1: Write migrations**

```php
// add_current_network_node_id_to_inventory_serials_table
Schema::table('inventory_serials', function (Blueprint $table) {
    $table->foreignId('current_network_node_id')->nullable()->after('current_pop_id')
        ->constrained('network_nodes')->nullOnDelete();
    $table->index('current_network_node_id');
});

// add_network_node_columns_to_inventory_transactions_table
Schema::table('inventory_transactions', function (Blueprint $table) {
    $table->foreignId('to_network_node_id')->nullable()->after('to_pop_id')
        ->constrained('network_nodes')->nullOnDelete();
    $table->foreignId('from_network_node_id')->nullable()->after('from_pop_id')
        ->constrained('network_nodes')->nullOnDelete();
});

// create_infrastructure_balances_table
Schema::create('infrastructure_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('item_id')->constrained()->restrictOnDelete();
    $table->string('lot_no', 50)->default('');
    $table->foreignId('network_node_id')->constrained()->restrictOnDelete();
    $table->decimal('qty', 12, 2)->default(0);
    $table->timestamps();
    $table->index(['item_id', 'network_node_id']);
});
```

- [ ] **Step 2: Run migrations**
- [ ] **Step 3: Write failing test** — `InfrastructureBalance::create([...])`
  dengan `network_node_id` terisi, assert relasi `networkNode` resolve;
  `InventorySerial::factory()->create(['current_network_node_id'=>...])`,
  assert `currentNetworkNode` resolve.
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement** — `InfrastructureBalance` mirror struktur
  `InventoryBalance`, cast `qty => decimal:2`. Tambah
  `current_network_node_id` ke `#[Fillable]` `InventorySerial`, tambah
  relasi `currentNetworkNode()`/`toNetworkNode()`/`fromNetworkNode()`.
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit** — `feat: kolom lokasi network_node di ledger/SN + tabel infrastructure_balances`

---

### Task 8: Scope — `AuthorizesWarehousePop::assertNetworkNodeInScope()`

**Files:**
- Modify: `app/Http/Controllers/Warehouse/Concerns/AuthorizesWarehousePop.php`
- Test: `tests/Feature/Warehouse/AuthorizesNetworkNodeScopeTest.php`

**Interfaces:**
- Consumes: `NetworkNode::root_pop_id` (Task 4).
- Produces: `assertNetworkNodeInScope(NetworkNode $node, User $user, EffectiveAccessService $access): void`
  (403 kalau `root_pop_id` di luar scope). Dipakai Task 11/12.

- [ ] **Step 1: Write failing test** — user scope Cabang A, `NetworkNode`
  dengan `root_pop_id`=Cabang B → assert `HttpException` 403.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement**

```php
protected function assertNetworkNodeInScope(NetworkNode $node, User $user, EffectiveAccessService $access): void
{
    $this->assertPopIdInScope($node->root_pop_id, $user, $access);
}
```

Reuse `assertPopIdInScope()` yang sudah ada — `root_pop_id` cuma 1 lookup,
tidak perlu manjat `parent_id` chain `network_nodes` untuk scope (beda dari
breadcrumb tampilan yang perlu manjat penuh).

- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit** — `feat: assertNetworkNodeInScope() — scope ikut root_pop_id`

---

### Task 9: `InventoryService` — `deploySerial()`, `deployQuantity()`, `deployRoll()`, `retrieveSerialFromLocation()`

**Files:**
- Modify: `app/Services/InventoryService.php`
- Test: `tests/Feature/Warehouse/InventoryServiceDeployTest.php`

**Interfaces:**
- Consumes: `InfrastructureBalance`/`NetworkNode` (Task 4/7), `TechnicianCustody`/`InventoryRoll` (sudah ada).
- Produces:
  - `deploySerial(InventorySerial $serial, NetworkNode $node, FopTask $fopTask, iterable $technicians, User $actor): InventoryTransaction`
  - `deployQuantity(iterable $technicians, Item $item, float $qtyUsed, NetworkNode $node, FopTask $fopTask, User $actor): array` (shape sama `consumeFromCustody()` — QUANTITY saja)
  - `deployRoll(InventoryRoll $roll, float $metersUsed, NetworkNode $node, iterable $technicians, FopTask $fopTask, User $actor): InventoryTransaction` (ROLL, bukan lewat `TechnicianCustody`)
  - `retrieveSerialFromLocation(InventorySerial $serial, FopTask $fopTask, User $actor): InventoryTransaction`

  Dipakai Task 11. **Beda dari draf v1** — parameter lokasi sekarang SATU
  `NetworkNode $node` (dulu sepasang `?Pop $pop, ?Odp $odp` exclusive).

- [ ] **Step 1: Write failing tests** — 6 skenario:
  1. `deploySerial()` pindah SN dari `ISSUED`+custody jadi `DEPLOYED` +
     `current_network_node_id`=node, `current_technician_id=null`, ledger
     `DEPLOY`.
  2. `deploySerial()` tolak `InvalidArgumentException` kalau
     `item->ownership_mode !== INFRASTRUCTURE_ASSET`.
  3. `deployQuantity()` nambah `infrastructure_balances` + kurangi custody.
  4. `deployQuantity()` lempar `InsufficientCustodyException` kalau custody
     kurang, `infrastructure_balances` tidak kebentuk.
  5. `deployRoll()` kurangi `length_remaining` roll + nambah
     `infrastructure_balances` dengan `lot_no=roll_code`, roll TETAP di
     custody teknisi (`current_technician_id` tidak berubah), `status`
     jadi `IN_USE`/`DEPLETED` tergantung sisa.
  6. `retrieveSerialFromLocation()` balik SN `DEPLOYED`→`ISSUED`,
     `current_technician_id=$actor`, `current_network_node_id=null`,
     ledger `RETRIEVE_INFRA`.
- [ ] **Step 2: Run, verify fail**
- [ ] **Step 3: Implement**
  - `deploySerial()` — pola re-fetch+`lockForUpdate()` sama `installSerial()`:
    validasi `status===ISSUED`, `current_technician_id` ada di
    `$technicians`, `ownership_mode===INFRASTRUCTURE_ASSET`. Update
    `status=DEPLOYED`, `current_network_node_id=$node->id`,
    `current_technician_id=null`. Ledger `DEPLOY`,
    `to_network_node_id=$node->id`, reference `generateReferenceNumber('DPL')`.
  - `deployQuantity()` — FIFO lock custody sama `consumeFromCustody()`,
    per baris kepotong: ledger `DEPLOY` (`to_network_node_id`, qty,
    `unit_price_snapshot` dari custody) + `InfrastructureBalance::lockForUpdate()->firstOrCreate(['item_id'=>...,'network_node_id'=>$node->id,'lot_no'=>''])->increment('qty',$take)`.
    Lempar `InsufficientCustodyException` reuse kalau kurang.
  - `deployRoll()` — pola `consumeFromRoll()`: validasi `status ∈ {ISSUED,IN_USE}`,
    custody tim, `$metersUsed <= length_remaining`,
    `ownership_mode===INFRASTRUCTURE_ASSET`. Kurangi `length_remaining`,
    `status = sisa<=0 ? DEPLETED : IN_USE` (roll TIDAK pindah custody).
    Ledger `DEPLOY`, `qty=$metersUsed`. `InfrastructureBalance::lockForUpdate()->firstOrCreate(['item_id'=>$roll->item_id,'network_node_id'=>$node->id,'lot_no'=>$roll->roll_code])->increment('qty',$metersUsed)`.
  - `retrieveSerialFromLocation()` — re-fetch+lock, validasi
    `status===DEPLOYED`. Simpan `$fromNodeId` sebelum dikosongkan. Update
    `status=ISSUED`, `current_technician_id=$actor->id`,
    `current_network_node_id=null`. Ledger `RETRIEVE_INFRA`,
    `from_network_node_id=$fromNodeId`, `to_technician_id=$actor->id`.
- [ ] **Step 4: Run, verify pass**
- [ ] **Step 5: Commit** — `feat: InventoryService::deploySerial/deployQuantity/deployRoll/retrieveSerialFromLocation`

---

### Task 10: `InventoryAdjustmentService::adjustSerialStatus()` — kosongkan lokasi infra

**Files:**
- Modify: `app/Services/InventoryAdjustmentService.php`
- Test: `tests/Feature/Warehouse/AdjustDeployedSerialTest.php`

- [ ] **Step 1: Write failing test** — SN `status=DEPLOYED` +
  `current_network_node_id` terisi, adjust ke `DAMAGED`, assert
  `current_network_node_id` jadi `null` setelahnya.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement** — di blok yang sudah mengosongkan
  `customer_id`/`fop_task_id`/`installed_at` untuk SN `INSTALLED` (Gudang
  business-logic §7/§12), tambah cabang: kalau status SEBELUM diubah
  adalah `DEPLOYED`, kosongkan juga `current_network_node_id`.
  `current_technician_id` tetap dibiarkan (jejak forensik).
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit** — `fix: adjustSerialStatus() kosongkan current_network_node_id untuk SN DEPLOYED`

---

### Task 11: Seksi "Pasang/Ganti Infrastruktur" di Laporan O-REQ/INFR REQ

**Files:**
- Modify: `app/Http/Controllers/TaskMaintenanceController.php`
- Modify: `resources/views/tasks/maintenance-report.blade.php`
- Test: `tests/Feature/TaskInfrastructureDeployReportTest.php`

**Interfaces:**
- Consumes: `InventoryService::deploySerial/deployQuantity/deployRoll/retrieveSerialFromLocation()` (Task 9),
  `NetworkNode`/`assertNetworkNodeInScope()` (Task 4/8).

- [ ] **Step 1: Write failing tests** — 4 skenario:
  1. Teknisi submit laporan INFR REQ dengan `infra_target_network_node_id`
     + `infra_selected_serial_id` → SN jadi `DEPLOYED` di node itu.
  2. `infra_target_network_node_id` di luar scope cabang task → `assertSessionHasErrors`.
  3. O-REQ dengan `infra_selected_roll_id`+`infra_roll_meters_used` →
     `infrastructure_balances` bertambah.
  4. `infra_retrieve_old_serial_id` + `infra_selected_serial_id` bersamaan
     (ganti aset) → SN lama `ISSUED` balik custody, SN baru `DEPLOYED`.
- [ ] **Step 2: Run, verify fail**
- [ ] **Step 3: Implement controller** — di `report()`: tambah
  `$eligibleInfraSerials`/`$eligibleInfraCustody`/`$eligibleInfraRolls`
  (copy 3 method `eligible*ForTeam()` existing, filter tambahan
  `ownership_mode=INFRASTRUCTURE_ASSET`), `$nodeOptions` = `NetworkNode::where('root_pop_id', $task->pop_id)->orWhereHas('parent', ...)->get()`
  (atau endpoint AJAX terpisah kalau datanya besar — pilih yang lebih
  simpel saat implementasi, pola `availableStock()` existing). Seksi
  tampil kondisional `task_type ∈ {OREQ, INFR}`.

  Di `store()`, rules (`nullable`, opsional sama pola Material Terpakai):
  `infra_target_network_node_id` (`exists:network_nodes,id`),
  `infra_selected_serial_id`/`infra_retrieve_old_serial_id` (`Rule::in($eligibleInfraSerialIds)`),
  `infra_quantity_item_id`+`infra_quantity_qty`,
  `infra_selected_roll_id`+`infra_roll_meters_used`. Validasi custom: kalau
  ada field `infra_*` lain terisi, `infra_target_network_node_id` wajib
  terisi; node yang dipilih wajib lolos `assertNetworkNodeInScope()`
  (lempar lewat `ValidationException` kalau gagal, bukan `abort()` 403 —
  biar muncul di `assertSessionHasErrors`).

  Di blok transaction `store()` (sejajar blok `selected_inventory_serial_id`
  existing): resolve `$node = NetworkNode::find($validated['infra_target_network_node_id'])`,
  lalu panggil `retrieveSerialFromLocation()` (kalau `infra_retrieve_old_serial_id`
  terisi), `deploySerial()` (kalau `infra_selected_serial_id`),
  `deployQuantity()` (kalau `infra_quantity_item_id`), `deployRoll()` (kalau
  `infra_selected_roll_id`).
- [ ] **Step 4: Implement view** — seksi baru (Alpine `x-show`, pola sama
  Material Terpakai existing): tree picker node (dropdown berjenjang,
  mulai dari Cabang task → turun `parent_id` lewat endpoint/partial yang
  sudah dioper dari controller), dropdown SN/qty/roll dari 3 variabel
  eligible, dropdown opsional "SN lama yang dicabut".
- [ ] **Step 5: Run, verify pass**
- [ ] **Step 6: Commit** — `vendor/bin/pint --dirty --format agent`, `npm run build`,
  `feat: seksi Pasang/Ganti Infrastruktur (SN + qty + roll) di Laporan O-REQ/INFR REQ`

---

### Task 12: Halaman "Aset & Kapasitas per Lokasi" — gabung ke `NetworkNodeController`

**Beda dari v1** — bukan halaman baru berdiri sendiri, melainkan
memperluas `NetworkNodeController::index()`/view (Task 6) supaya tiap node
juga menampilkan aset Gudang yang nempel, tidak cuma kapasitas port.

**Files:**
- Modify: `app/Http/Controllers/Network/NetworkNodeController.php`
- Modify: `resources/views/network/nodes/index.blade.php`
- Test: `tests/Feature/Warehouse/NetworkNodeAssetViewTest.php`

**Interfaces:**
- Consumes: `InventorySerial`(`status=DEPLOYED`)/`InfrastructureBalance` (Task 7),
  `assertNetworkNodeInScope()` (Task 8).

- [ ] **Step 1: Write failing test** — node dengan 1 SN `DEPLOYED` +
  1 baris `infrastructure_balances`, assert `index()` (atau endpoint
  detail baru `network.nodes.show` kalau drill-down per-node butuh halaman
  sendiri — putuskan saat implementasi mana yang lebih pas dengan pola
  pagination list yang sudah ada) menampilkan SN + qty tadi bersebelahan
  dengan data port.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement** — tambah eager-load
  `NetworkNode::with('deployedSerials', 'infrastructureBalances')` (2
  relasi baru: `NetworkNode::deployedSerials(): HasMany(InventorySerial::class,'current_network_node_id')`,
  `NetworkNode::infrastructureBalances(): HasMany(InfrastructureBalance::class)`)
  di query `index()`. Scope tetap ikut `root_pop_id` (Task 8) di titik
  detail/mutasi. View tambah kolom/sub-tabel: SN (link ke
  `warehouse.traceability.index`) + saldo `infrastructure_balances`.
- [ ] **Step 4: Run, verify pass**
- [ ] **Step 5: Commit** — `feat: halaman Network Node tampilkan aset Gudang terpasang, bukan cuma kapasitas port`

---

## Urutan Eksekusi

Task 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12 (linear). Task 6
(rekonsiliasi) **wajib** jalan setelah Task 3-5 (butuh `network_node_types`/
`network_nodes`/`network_node_ports` sudah ada sebagai target reshape) dan
sebelum Task 11-12 (yang butuh `NetworkNodeController`/routes final).
