# Pohon Infrastruktur Jaringan + Aset Gudang per Lokasi Implementation Plan (v3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pohon node infrastruktur jaringan generik (`network_nodes`, tipe
dinamis lewat lookup admin) dengan kapasitas/port, dipadukan dengan aset
Gudang (OLT chassis tidak termasuk, lihat Open Item spec; ODC/ODP/splitter/kabel)
yang dipasang/diganti lewat laporan task O-REQ/INFR REQ, dan diaudit dua arah
(SN→lokasi, lokasi→isi). Node bisa dipindah (`move()`) tanpa merusak riwayat.

**Architecture:** Satu tabel pohon self-referencing `network_nodes`. Setiap
node tinggal di tepat satu tempat: di dalam node lain (`parent_id`) ATAU
langsung di sebuah POP (`anchor_pop_id`, hanya node puncak). Cabang tidak
disimpan per node, diturunkan dengan naik rantai `parent_id`. OLT = Mini POP
(tidak ada tipe `olt`). Tipe node lewat master lookup `network_node_types`
(dengan flag perilaku, bukan hardcode kode). Aset Gudang nempel lewat satu
kolom (`current_network_node_id`, `to/from_network_node_id`, `network_node_id`).
Ledger menyimpan snapshot cabang saat kejadian (`to_pop_id`/`from_pop_id`).
**Rekonsiliasi**: kerja uncommitted `odps`/`odp_ports`/`Network\OdpController`/
`FopUnifiedDashboardService::getOdpSummary()` direshape jadi instance pertama
pohon ini, tidak dibuang.

**Tech Stack:** Laravel 13, PHPUnit 12, Blade + Alpine.js (Vite), sqlite `:memory:` test.

**Spec:** `docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` (v3)

**Status implementasi (2026-10-13):** Task 1-13 telah diimplementasikan di worktree network-nodes-infra. Rekonsiliasi grant, pembuatan dan pemindahan node bertingkat, guard scope pindah, tree browser, dan tree picker dilengkapi dalam ADHOC-166. Checklist langkah di bawah adalah catatan rencana historis, bukan status eksekusi terkini.

## Global Constraints

- Semua nomor dokumen ledger lewat `generateReferenceNumber()` yang sudah
  ada, jangan `count()+1`.
- `inventory_balances`/`inventory_transfers` tetap cuma pusat/cabang
  (`Pop::WAREHOUSE_TYPES`), tidak disentuh.
- Ledger (`inventory_transactions`) append-only. Snapshot `to_pop_id`/`from_pop_id`
  wajib diisi saat `DEPLOY`/`RETRIEVE_INFRA`.
- Guard `OwnershipMode::INFRASTRUCTURE_ASSET` ditegakkan di `InventoryService`,
  satu-satunya pintu transisi ke `SerialStatus::DEPLOYED`.
- Permission baru lewat `config/rbac.php` + `PermissionGeneratorService::generate()`.
- Scope POP pakai `EffectiveAccessService`/`AuthorizesWarehousePop`. Jangan
  query `getAllowedPopIds()` inline baru. Cabang sebuah node selalu lewat
  `NetworkNode::resolvePop()` atau `scopeInPops()`, jangan naik-turun parent
  manual di controller.
- `vendor/bin/pint --dirty --format agent` tiap task yang menyentuh PHP.
- **Sebelum Task 1**: `git status`/`git diff` ulang working tree. Plan ini
  diasumsikan dieksekusi di atas state yang sudah ada `odps`/`odp_ports`/
  `Network\OdpController`/`FopUnifiedDashboardService::getOdpSummary()`/
  `OdpNetworkManagementTest` (uncommitted). Kalau state itu sudah berubah,
  **berhenti dan konfirmasi ke user** sebelum Task 7 (rekonsiliasi).
- Jangan jalankan full suite (CLAUDE.md). Jalankan file test terdampak saja.

## Review Focus

- **SN company_asset/installable biasa jangan bisa di-deploy**: guard
  `OwnershipMode::INFRASTRUCTURE_ASSET` harus menolak dua mode lain.
- **Aturan XOR**: tiap node tepat satu dari `parent_id` atau `anchor_pop_id`.
  `move()` yang menghasilkan dua-duanya terisi atau dua-duanya kosong harus ditolak.
- **Siklus**: `move()` ke turunan dirinya sendiri harus ditolak.
- **`network_node_id` yang dikirim bukan milik scope POP aktor**: ditolak lewat
  `assertNetworkNodeInScope()`, dicek dari cabang hasil `resolvePop()`.
- **`move()` lintas cabang**: aktor harus punya scope di cabang asal DAN tujuan.
- **Snapshot ledger**: `to_pop_id`/`from_pop_id` terisi cabang saat kejadian.
  Setelah node dipindah, laporan transaksi lama harus tetap menunjuk cabang lama.
- **`deployQuantity()`/`deployRoll()` custody tim tidak cukup**: harus
  melempar exception yang sudah ada, bukan silently partial-deploy.
- **SN `DEPLOYED` diadjust ke LOST/DAMAGED/SCRAPPED/QUARANTINE**:
  `current_network_node_id` harus ikut terkosongkan.
- **Data `odps`/`odp_ports` existing hilang/rusak** saat reshape: migration data
  Task 7 wajib idempotent dan dites dengan data existing.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `app/Enums/OwnershipMode.php` | + case `INFRASTRUCTURE_ASSET` |
| `app/Enums/SerialStatus.php` | + case `DEPLOYED` |
| `app/Enums/InventoryTransactionType.php` | + case `DEPLOY`, `RETRIEVE_INFRA` |
| `database/seeders/ItemSeeder.php` | Koreksi `tracking_type` splitter/ODP (serialized) & kabel (roll) |
| `database/migrations/2026_10_12_1100*_create_network_node_types_table.php` | Master lookup tipe node + flag perilaku |
| `app/Models/NetworkNodeType.php` | Model lookup |
| `app/Http/Controllers/Master/NetworkNodeTypeController.php` | CRUD lookup |
| `database/seeders/NetworkNodeTypeSeeder.php` | 6 tipe awal + flag |
| `database/migrations/2026_10_12_1101*_create_network_nodes_table.php` | Pohon node dengan `anchor_pop_id` |
| `app/Models/NetworkNode.php` | Model pohon, `resolvePop()`, `scopeInPops()`, `breadcrumbLabel()` |
| `database/factories/NetworkNodeFactory.php` | Factory node |
| `database/migrations/2026_10_12_1102*_create_network_node_ports_table.php` | Port generik |
| `app/Models/NetworkNodePort.php` | Model port |
| `app/Services/NetworkNodeService.php` | `move()`, validasi XOR & siklus, catat audit |
| `app/Http/Controllers/Network/NetworkNodeController.php` | CRUD pohon + port (generalisasi `Network\OdpController`) |
| `resources/views/network/nodes/index.blade.php` | View pohon + kapasitas + aset (generalisasi `network/odps/index.blade.php`) |
| `database/migrations/2026_10_12_1103*_reshape_odps_into_network_nodes.php` | Migrasi data `odps`→`network_nodes`, `odp_ports`→`network_node_ports`, drop tabel lama |
| `app/Services/FopUnifiedDashboardService.php` | `getOdpSummary()` pakai flag tipe, bukan kode `odp` |
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
| `routes/web.php` | Routes lookup + node + port + move |

---

### Task 1: Enum baru: `OwnershipMode`, `SerialStatus`, `InventoryTransactionType`

**Files:**
- Modify: `app/Enums/OwnershipMode.php`, `app/Enums/SerialStatus.php`, `app/Enums/InventoryTransactionType.php`
- Test: `tests/Unit/InfrastructureAssetEnumTest.php`

**Interfaces:**
- Produces: `OwnershipMode::INFRASTRUCTURE_ASSET`, `SerialStatus::DEPLOYED`,
  `InventoryTransactionType::DEPLOY`, `InventoryTransactionType::RETRIEVE_INFRA`.

- [ ] **Step 1: Write failing test**: iterasi `cases()` tiap enum, assert
  `label()`/`badgeVariant()` tidak lempar error, assert value baru sesuai
  (`infrastructure_asset`, `deployed`, `deploy`, `retrieve_infra`).
- [ ] **Step 2: Run, verify fails**: `php artisan test --compact tests/Unit/InfrastructureAssetEnumTest.php`
- [ ] **Step 3: Implement**: tambah case + `label()` di tiap enum
  (`OwnershipMode`: `'Aset Infrastruktur (Terpasang Netap)'`; `SerialStatus`:
  `'Terpasang di Infrastruktur'`, masuk grup `badgeVariant()` `success`;
  `InventoryTransactionType`: `'Terpasang di Infrastruktur'`/`'Dicabut dari Infrastruktur'`).
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit**: `feat: tambah enum OwnershipMode::INFRASTRUCTURE_ASSET, SerialStatus::DEPLOYED, InventoryTransactionType::DEPLOY/RETRIEVE_INFRA`

---

### Task 2: Koreksi `ItemSeeder`: Splitter/ODP jadi SERIALIZED, Kabel jadi ROLL

Kategori `splitter_odp` (SPL-1X2…SPL-1X32, ODP-8, ODP-16, ODC-24) dan
`kabel_dropcore` (DC-1C, DC-2C, KBL-FEEDER-12C) di `ItemSeeder.php` tidak
mengisi `tracking_type` override, default `'quantity'` (`ItemSeeder.php:165`).
Seharusnya `splitter_odp`/ODP box = `serialized`+`auto_generate_serial=true`,
`kabel_dropcore` = `roll`. Deploy (Task 10) butuh data ini benar.

**Files:**
- Modify: `database/seeders/ItemSeeder.php`
- Test: `tests/Unit/ItemSeederTrackingTypeTest.php`

- [ ] **Step 1: Write failing test**: seed `ItemSeeder`, assert
  `Item::where('code','SPL-1X8')` `tracking_type === SERIALIZED` dan
  `auto_generate_serial === true`; assert `ODP-8` sama; assert
  `DC-1C` `tracking_type === ROLL`.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement**: tambah `'tracking_type' => 'serialized', 'auto_generate_serial' => true`
  ke tiap baris `splitter_odp`; `'tracking_type' => 'roll'` ke tiap baris
  `kabel_dropcore`. Data existing yang salah (dev/staging) diperbaiki ulang
  lewat `db:seed --class=ItemSeeder` (`updateOrCreate`). Kalau sudah ada
  `inventory_balances`/`inventory_serials` ter-isi dengan `tracking_type`
  lama untuk kode-kode ini, itu migrasi data terpisah, di luar scope:
  berhenti & konfirmasi kalau ditemukan saat eksekusi.
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit**: `fix: ItemSeeder, splitter/ODP jadi SERIALIZED auto-generate, kabel dropcore jadi ROLL`

---

### Task 3: Master `network_node_types` + flag perilaku

**Files:**
- Create: `database/migrations/2026_10_12_110000_create_network_node_types_table.php`
- Create: `app/Models/NetworkNodeType.php`
- Create: `app/Http/Controllers/Master/NetworkNodeTypeController.php`
- Create: `resources/views/master/network-node-types/{index,create,edit}.blade.php`
- Create: `database/seeders/NetworkNodeTypeSeeder.php`
- Modify: `config/rbac.php`, `routes/web.php`
- Test: `tests/Feature/Master/NetworkNodeTypeCrudTest.php`

**Interfaces:**
- Produces: `NetworkNodeType` model (`code`, `name`, `has_ports`,
  `monitors_capacity`, `max_ports`, `red_zone_threshold`, `is_active`, `sort_order`),
  routes `master.network-node-types.{index,create,store,edit,update,destroy}`,
  permission `master_network_node_types.{view,create,update,delete}`.
  Dipakai Task 4 (`node_type_id`), Task 7 (`getOdpSummary()`, `addPorts()`).

- [ ] **Step 1: Write migration**

```php
Schema::create('network_node_types', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('name', 100);
    $table->boolean('has_ports')->default(false);
    $table->boolean('monitors_capacity')->default(false);
    $table->unsignedSmallInteger('max_ports')->nullable();
    $table->unsignedTinyInteger('red_zone_threshold')->nullable();
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

- [ ] **Step 2: Run migration**: `php artisan migrate --no-interaction`
- [ ] **Step 3: Write failing test**: admin bisa `store()` tipe baru
  (`code='repeater'`), `code` unique ditolak kalau dobel, flag tersimpan dan
  dibaca balik. Guard hapus (tolak kalau masih dipakai `network_nodes`) ditulis
  di Task 4 sebagai regression, karena butuh tabel itu.
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement**: model fillable + cast boolean; controller pola
  `TicketIssueCategoryController`; views pola `master/ticket-issue-categories/*`;
  `config/rbac.php` tambah `'master_network_node_types' => [VIEW,CREATE,UPDATE,DELETE]`;
  `NetworkNodeTypeSeeder` `updateOrCreate` by `code` untuk 6 tipe:

  | code | name | has_ports | monitors_capacity | max_ports | red_zone_threshold |
  |---|---|---|---|---|---|
  | `core_router` | Core Router/Switch | false | false | null | null |
  | `backbone` | Backbone/Transport | false | false | null | null |
  | `aggregation_switch` | Router/Switch Agregasi POP | false | false | null | null |
  | `pon_port` | PON Port/Card | false | false | null | null |
  | `odc` | ODC | true | true | 128 | 80 |
  | `odp` | ODP | true | true | 128 | 80 |

  Lalu `PermissionGeneratorService::generate()`. Routes pola blok Master lain
  (static-first).
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit + seed**: `vendor/bin/pint --dirty --format agent`,
  `php artisan db:seed --class=NetworkNodeTypeSeeder`,
  `feat: master network_node_types, tipe node dikelola admin dengan flag perilaku`

---

### Task 4: Pohon `network_nodes` dengan `anchor_pop_id` + resolver cabang

**Files:**
- Create: `database/migrations/2026_10_12_110100_create_network_nodes_table.php`
- Create: `app/Models/NetworkNode.php`
- Create: `database/factories/NetworkNodeFactory.php`
- Test: `tests/Unit/NetworkNodeModelTest.php`

**Interfaces:**
- Consumes: `NetworkNodeType` (Task 3), `Pop` (sudah ada).
- Produces: `NetworkNode`, fillable `['parent_id','anchor_pop_id','node_type_id','code','name','capacity','address','village','district','city','latitude','longitude','status','notes']`.
  Relasi: `parent(): BelongsTo(NetworkNode::class)`, `children(): HasMany(NetworkNode::class,'parent_id')`,
  `anchorPop(): BelongsTo(Pop::class,'anchor_pop_id')`, `nodeType(): BelongsTo(NetworkNodeType::class)`.
  Method:
  - `resolvePop(): ?Pop`. Naik `parent` sampai node puncak, ambil `anchorPop`.
    Kalau anchor = Mini POP, kembalikan parent Pop-nya (cabang); kalau anchor = Cabang, kembalikan anchor itu sendiri.
  - `scopeInPops(Builder $q, array $popIds)`. Ambil node yang anchor-nya di `$popIds`, plus semua turunannya lewat recursive CTE (satu query).
  - `breadcrumbLabel(): string`. Gabung `resolvePop()->name` lalu nama tiap node sepanjang rantai, dipisah `" → "`.

  Dipakai Task 6, 7, 9, 12, 13.

- [ ] **Step 1: Write migration**

```php
Schema::create('network_nodes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('network_nodes')->restrictOnDelete();
    $table->foreignId('anchor_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
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
    $table->index(['parent_id']);
    $table->index(['anchor_pop_id']);
    $table->index(['node_type_id']);
});
```

- [ ] **Step 2: Run migration**
- [ ] **Step 3: Write failing tests**:
  1. Node puncak di bawah Cabang (`anchor_pop_id`=Cabang, `parent_id=null`):
     `resolvePop()` = Cabang, `breadcrumbLabel()` = `"Cabang Jetis → <name>"`.
  2. Node puncak di bawah Mini POP (`anchor_pop_id`=Mini POP C1): `resolvePop()`
     = Cabang induk Mini POP, breadcrumb = `"Cabang Jetis → Mini POP C1 → <name>"`.
  3. Node bertingkat 3 level (ODC → ODP → splitter): `resolvePop()` dari node
     paling bawah = cabang yang sama dengan puncak.
  4. `scopeInPops([CabangA])` mengembalikan node di bawah Cabang A dan semua
     turunannya, tidak mengembalikan node Cabang B.
  5. `children()` hanya anak langsung, bukan rekursif.
  6. Tanpa XOR (parent dan anchor sama-sama terisi, atau sama-sama kosong) ditolak
     `NetworkNodeService`. Test XOR ditulis di Task 6 (service), di sini cukup
     dicatat sebagai TODO di test file, bukan ditiru.
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement model**:
  - `resolvePop()`: loop naik `parent` sampai `anchor_pop_id !== null`, lalu ambil
    `anchorPop`. Kalau tipe anchor Mini POP (`type='mini_pop'`), kembalikan
    relasi parent-nya di `pops` (cek nama relasi di model `Pop` saat
    implementasi). Kalau tidak, kembalikan anchor itu. Pakai cache per request
    (`once()`) supaya tidak menghitung ulang di satu loop tampilan.
  - `scopeInPops()`: recursive CTE, verifikasi lewat sqlite test dan MySQL 8.
    Contoh bentuk query:

    ```sql
    WITH RECURSIVE tree AS (
      SELECT id FROM network_nodes WHERE anchor_pop_id IN (?)
      UNION ALL
      SELECT n.id FROM network_nodes n JOIN tree t ON n.parent_id = t.id
    )
    SELECT id FROM tree
    ```
  - `breadcrumbLabel()`: null-safe.
  - Factory: `root` default `anchor_pop_id` = `Pop::factory()->create(['type'=>'cabang'])->id`,
    `node_type_id` default `NetworkNodeType::factory()->create()`.
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit**: `feat: pohon network_nodes, penempatan folder-di-drive (anchor_pop_id), resolver cabang`

---

### Task 5: Port generik `network_node_ports`

**Files:**
- Create: `database/migrations/2026_10_12_110200_create_network_node_ports_table.php`
- Create: `app/Models/NetworkNodePort.php`
- Test: `tests/Unit/NetworkNodePortModelTest.php`

**Interfaces:**
- Consumes: `NetworkNode` (Task 4).
- Produces: `NetworkNodePort`, fillable `['network_node_id','number','is_used']`,
  relasi `node(): BelongsTo(NetworkNode::class,'network_node_id')`.
  `NetworkNode::ports(): HasMany(NetworkNodePort::class)->orderBy('number')`.

- [ ] **Step 1: Write migration**

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
- [ ] **Step 3: Write failing test**: buat `NetworkNode`, 3 port lewat
  `ports()->create()`, assert `where('is_used', true)->count()` dan urut `number` ASC.
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement model**
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit**: `feat: network_node_ports, port generik per node`

---

### Task 6: `NetworkNodeService::move()` + validasi XOR & siklus + audit

**Files:**
- Create: `app/Services/NetworkNodeService.php`
- Test: `tests/Feature/Network/NetworkNodeMoveTest.php`

**Interfaces:**
- Consumes: `NetworkNode` (Task 4), `Pop`.
- Produces:
  - `NetworkNodeService::move(NetworkNode $node, ?NetworkNode $newParent, ?Pop $newPop, User $actor): void`
  - `NetworkNodeService::assertPlacement(?NetworkNode $parent, ?Pop $anchor): void`
    (dipakai juga oleh create/update, supaya aturan XOR hanya ada di satu tempat).

- [ ] **Step 1: Write failing tests**:
  1. `move()` ODC-1 dari Mini POP A (anchor) ke Mini POP B (anchor): `anchor_pop_id`
     ODC-1 berubah, ODP di bawahnya tidak diubah tapi `resolvePop()` ODP ikut berubah.
  2. `move()` ODP dari ODC-1 ke ODC-2 (dalam satu OLT): hanya `parent_id` yang berubah.
  3. `move()` ke turunan dirinya sendiri → `InvalidArgumentException`, tidak ada
     baris yang berubah.
  4. `assertPlacement()` menolak `parent` dan `anchor` sama-sama terisi, dan sama-sama kosong.
  5. `move()` mencatat ke audit log: aktor, node, cabang asal, cabang tujuan.
  6. Snapshot tidak ikut berubah: `move()` tidak menyentuh baris
     `inventory_transactions` yang sudah ada.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement**:

```php
public function move(NetworkNode $node, ?NetworkNode $newParent, ?Pop $newPop, User $actor): void
{
    self::assertPlacement($newParent, $newPop);

    DB::transaction(function () use ($node, $newParent, $newPop, $actor) {
        $node = NetworkNode::lockForUpdate()->findOrFail($node->id);

        if ($newParent && $this->isSelfOrDescendant($newParent, $node)) {
            throw new InvalidArgumentException('Node tidak boleh dipindah ke dalam dirinya sendiri atau turunannya.');
        }

        $fromPopId = $node->resolvePop()?->id;

        $node->update([
            'parent_id' => $newParent?->id,
            'anchor_pop_id' => $newPop?->id,
        ]);

        $toPopId = $node->refresh()->resolvePop()?->id;

        // catat ke audit log yang sudah ada: aktor, node, $fromPopId → $toPopId
    });
}
```

  `isSelfOrDescendant()` naik dari `$newParent` sampai puncak, cek apakah
  melewati `$node->id`. Jangan pakai CTE di sini, cukup loop (kedalaman kecil).
  Catat audit lewat mekanisme yang sudah ada (cek `app/Observers/` dan
  `docs/` audit log saat implementasi). **Tabel `network_node_moves` baru
  dibuat kalau audit log existing tidak cukup**, dan itu keputusan yang harus
  dikonfirmasi dulu (Open Item spec nomor 6).
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit**: `feat: NetworkNodeService::move() dengan validasi XOR dan siklus, dicatat audit`

---

### Task 7: Rekonsiliasi: reshape `odps`/`odp_ports` jadi `network_nodes`/`network_node_ports`

**Prasyarat:** cek `git status`/isi tabel `odps` sebelum mulai (lihat
Global Constraints). Task ini mengasumsikan `odps`, `odp_ports`,
`App\Http\Controllers\Network\OdpController`, routes `network.odps.*`,
`FopUnifiedDashboardService::getOdpSummary()`, dan
`tests/Feature/OdpNetworkManagementTest.php` **sudah ada** (uncommitted).

**Files:**
- Create: `database/migrations/2026_10_12_110300_reshape_odps_into_network_nodes.php`
- Modify: `app/Http/Controllers/Network/OdpController.php` → rename jadi `NetworkNodeController.php`
- Modify: `resources/views/network/odps/index.blade.php` → pindah `resources/views/network/nodes/index.blade.php`
- Modify: `app/Services/FopUnifiedDashboardService.php` (`getOdpSummary()`)
- Modify: `routes/web.php` (`network.odps.*`/`network.odp-ports.*` → `network.nodes.*`/`network.node-ports.*`)
- Modify: `config/rbac.php` (rename `network_odps` jadi `network_nodes`)
- Modify: `tests/Feature/OdpNetworkManagementTest.php`
- Delete: `app/Models/Odp.php`, `app/Models/OdpPort.php`,
  `database/migrations/2026_10_12_101914_create_odps_table.php`,
  `database/seeders/OdpFeatureSeeder.php`

**Interfaces:**
- Consumes: `NetworkNode`/`NetworkNodePort`/`NetworkNodeType` (Task 3-5), `NetworkNodeService` (Task 6).
- Produces: route `network.nodes.index`/`.store`/`.ports.add`,
  `network.node-ports.update`. Dipakai Task 13.

- [ ] **Step 1: Write failing test**: adaptasi 5 test `OdpNetworkManagementTest`:
  `Odp::create` → `NetworkNode::create(['anchor_pop_id'=>..., 'node_type_id'=>$odpType->id,...])`;
  route ganti ke `network.nodes.*`/`network.node-ports.update`. Tambah test
  migration data: seed 1 `odps` + 2 `odp_ports`, migrasi jalan, baris muncul
  di `network_nodes` (`anchor_pop_id = odps.pop_id`, `node_type.code='odp'`,
  `parent_id=null`) dan `network_node_ports`.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Write migration data (`up()`)**: guard `Schema::hasTable('odps')`.
  Mapping: `root_pop_id` lama jadi `anchor_pop_id`, `odps.pop_id` langsung.
  Tipe `odp` dicari dari `network_node_types`. Kalau belum ada, migration
  berhenti dengan exception yang jelas (seeder Task 3 harus jalan dulu).
  Insert per baris pakai `insertGetId`, lalu port dengan `DB::table('odp_ports')`
  per `odp_id`. Pakai `->orderBy('id')->cursor()` atau `chunkById`, cek API
  Laravel 13 aktual saat implementasi. Setelah copy, `dropIfExists('odp_ports')`
  dan `dropIfExists('odps')`.
- [ ] **Step 4: Rename controller & routes**: `OdpController` → `NetworkNodeController`:
  `index()` pakai `NetworkNode::query()->with('nodeType','ports')`, filter
  opsional `node_type_id`. `store()` validasi `node_type_id` + `anchor_pop_id`
  (scope POP sama persis `OdpController::store()`), lewat
  `NetworkNodeService::assertPlacement()`. `addPorts()` menolak total port
  melebihi `nodeType->max_ports` (bukan 128 hardcode); `updatePort()` tidak
  berubah. View `network/nodes/index.blade.php`: kolom Tipe dan breadcrumb
  `breadcrumbLabel()`.
- [ ] **Step 5: Update `FopUnifiedDashboardService::getOdpSummary()`**: ganti
  `Odp::with('ports')` jadi `NetworkNode::whereHas('nodeType', fn ($q) => $q->where('has_ports', true)->where('monitors_capacity', true))->with('ports')`.
  Ambang Red Zone dari `nodeType->red_zone_threshold`. Output shape tidak
  berubah.
- [ ] **Step 6: Update `config/rbac.php`/`routes/web.php`**: rename `network_odps`
  jadi `network_nodes`, route sesuai Step 4.
- [ ] **Step 7: Delete file lama**
- [ ] **Step 8: Run migration + test, verify pass**:
  `php artisan migrate --no-interaction`, `php artisan test --compact tests/Feature/OdpNetworkManagementTest.php`
- [ ] **Step 9: Commit**: `vendor/bin/pint --dirty --format agent`,
  `refactor: reshape odps/odp_ports jadi network_nodes/network_node_ports, flag tipe menggantikan hardcode odp`

---

### Task 8: Kolom lokasi di ledger & SN + model `InfrastructureBalance`

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
  `InfrastructureBalance`, fillable `['item_id','lot_no','network_node_id','qty']`.

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
- [ ] **Step 3: Write failing test**: `InfrastructureBalance::create` dengan
  `network_node_id`, assert relasi `networkNode` resolve;
  `InventorySerial::factory()->create(['current_network_node_id'=>...])`, assert
  `currentNetworkNode` resolve.
- [ ] **Step 4: Run, verify fails**
- [ ] **Step 5: Implement**: `InfrastructureBalance` mirror `InventoryBalance`,
  cast `qty => decimal:2`. Tambah `current_network_node_id` ke fillable
  `InventorySerial`, relasi `currentNetworkNode()`/`toNetworkNode()`/`fromNetworkNode()`.
- [ ] **Step 6: Run, verify passes**
- [ ] **Step 7: Commit**: `feat: kolom lokasi network_node di ledger/SN + tabel infrastructure_balances`

---

### Task 9: Scope: `AuthorizesWarehousePop::assertNetworkNodeInScope()`

**Files:**
- Modify: `app/Http/Controllers/Warehouse/Concerns/AuthorizesWarehousePop.php`
- Test: `tests/Feature/Warehouse/AuthorizesNetworkNodeScopeTest.php`

**Interfaces:**
- Consumes: `NetworkNode::resolvePop()` (Task 4).
- Produces: `assertNetworkNodeInScope(NetworkNode $node, User $user, EffectiveAccessService $access): void`
  (403 kalau cabang hasil `resolvePop()` di luar scope). Dipakai Task 12/13.

- [ ] **Step 1: Write failing test**: user scope Cabang A, node di bawah Mini POP
  milik Cabang B → assert `HttpException` 403. Node di bawah Mini POP milik
  Cabang A → lolos.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement**:

```php
protected function assertNetworkNodeInScope(NetworkNode $node, User $user, EffectiveAccessService $access): void
{
    $pop = $node->resolvePop();
    abort_unless($pop !== null, 403);
    $this->assertPopIdInScope($pop->id, $user, $access);
}
```

  Beda dari v2 (`root_pop_id` 1 lookup): sekarang butuh naik rantai ke puncak,
  jadi resolver dipakai (cache per request dari Task 4).
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit**: `feat: assertNetworkNodeInScope() lewat resolvePop()`

---

### Task 10: `InventoryService`: `deploySerial()`, `deployQuantity()`, `deployRoll()`, `retrieveSerialFromLocation()`

**Files:**
- Modify: `app/Services/InventoryService.php`
- Test: `tests/Feature/Warehouse/InventoryServiceDeployTest.php`

**Interfaces:**
- Consumes: `InfrastructureBalance`/`NetworkNode` (Task 4/8), `TechnicianCustody`/`InventoryRoll` (sudah ada).
- Produces:
  - `deploySerial(InventorySerial $serial, NetworkNode $node, FopTask $fopTask, iterable $technicians, User $actor): InventoryTransaction`
  - `deployQuantity(iterable $technicians, Item $item, float $qtyUsed, NetworkNode $node, FopTask $fopTask, User $actor): array`
  - `deployRoll(InventoryRoll $roll, float $metersUsed, NetworkNode $node, iterable $technicians, FopTask $fopTask, User $actor): InventoryTransaction`
  - `retrieveSerialFromLocation(InventorySerial $serial, FopTask $fopTask, User $actor): InventoryTransaction`

  Setiap transaksi DEPLOY dan RETRIEVE_INFRA mengisi `to_pop_id`/`from_pop_id`
  dengan `$node->resolvePop()` pada saat itu (snapshot).

- [ ] **Step 1: Write failing tests**: 7 skenario:
  1. `deploySerial()` pindah SN dari `ISSUED`+custody jadi `DEPLOYED` +
     `current_network_node_id`=node, `current_technician_id=null`, ledger `DEPLOY`
     dengan `to_pop_id` = cabang node saat itu.
  2. `deploySerial()` tolak `InvalidArgumentException` kalau `ownership_mode !== INFRASTRUCTURE_ASSET`.
  3. `deployQuantity()` nambah `infrastructure_balances` + kurangi custody.
  4. `deployQuantity()` lempar `InsufficientCustodyException` kalau custody kurang,
     `infrastructure_balances` tidak kebentuk.
  5. `deployRoll()` kurangi `length_remaining` + nambah `infrastructure_balances`
     dengan `lot_no=roll_code`; roll TETAP di custody (`current_technician_id` tidak
     berubah); `status` jadi `IN_USE`/`DEPLOYED` sesuai sisa.
  6. `retrieveSerialFromLocation()` balik SN `DEPLOYED`→`ISSUED`,
     `current_technician_id=$actor`, `current_network_node_id=null`, ledger
     `RETRIEVE_INFRA`, `from_pop_id` = cabang saat itu.
  7. **Snapshot tahan pindah**: deploy SN ke ODC di Cabang A, lalu `move()` ODC
     ke Cabang B. Ledger lama tetap `to_pop_id = Cabang A`.
- [ ] **Step 2: Run, verify fail**
- [ ] **Step 3: Implement**
  - `deploySerial()`: pola re-fetch+`lockForUpdate()` sama `installSerial()`.
    Validasi `status===ISSUED`, `current_technician_id` ada di `$technicians`,
    `ownership_mode===INFRASTRUCTURE_ASSET`. Update `status=DEPLOYED`,
    `current_network_node_id=$node->id`, `current_technician_id=null`. Ledger
    `DEPLOY`, `to_network_node_id=$node->id`, `to_pop_id=$node->resolvePop()?->id`,
    reference `generateReferenceNumber('DPL')`.
  - `deployQuantity()`: FIFO lock custody sama `consumeFromCustody()`, per baris:
    ledger `DEPLOY` (`to_network_node_id`, `to_pop_id`, qty, `unit_price_snapshot`
    dari custody) + `InfrastructureBalance::lockForUpdate()->firstOrCreate(['item_id'=>...,'network_node_id'=>$node->id,'lot_no'=>''])->increment('qty',$take)`.
  - `deployRoll()`: pola `consumeFromRoll()`. Validasi `status ∈ {ISSUED,IN_USE}`,
    custody tim, `$metersUsed <= length_remaining`, `ownership_mode===INFRASTRUCTURE_ASSET`.
    Kurangi `length_remaining`, `status = sisa<=0 ? DEPLETED : IN_USE`. Roll TIDAK
    pindah custody. Ledger `DEPLOY` dengan `to_pop_id` snapshot. `InfrastructureBalance`
    dengan `lot_no=$roll->roll_code`.
  - `retrieveSerialFromLocation()`: re-fetch+lock, validasi `status===DEPLOYED`.
    Simpan `$fromNodeId` dan `$fromPopId = resolvePop()` sebelum dikosongkan.
    Update `status=ISSUED`, `current_technician_id=$actor->id`,
    `current_network_node_id=null`. Ledger `RETRIEVE_INFRA`,
    `from_network_node_id`, `from_pop_id`, `to_technician_id=$actor->id`.
- [ ] **Step 4: Run, verify pass**
- [ ] **Step 5: Commit**: `feat: InventoryService deploy/retrieve infrastruktur dengan snapshot cabang di ledger`

---

### Task 11: `InventoryAdjustmentService::adjustSerialStatus()`: kosongkan lokasi infra

**Files:**
- Modify: `app/Services/InventoryAdjustmentService.php`
- Test: `tests/Feature/Warehouse/AdjustDeployedSerialTest.php`

- [ ] **Step 1: Write failing test**: SN `status=DEPLOYED` +
  `current_network_node_id` terisi, adjust ke `DAMAGED`, assert
  `current_network_node_id` jadi `null`.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement**: di blok yang sudah mengosongkan `customer_id`/`fop_task_id`/
  `installed_at` untuk SN `INSTALLED`, tambah cabang: kalau status SEBELUM diubah
  `DEPLOYED`, kosongkan `current_network_node_id`. `current_technician_id` dibiarkan
  (jejak forensik). Ledger adjust tidak diubah.
- [ ] **Step 4: Run, verify passes**
- [ ] **Step 5: Commit**: `fix: adjustSerialStatus() kosongkan current_network_node_id untuk SN DEPLOYED`

---

### Task 12: Seksi "Pasang/Ganti Infrastruktur" di Laporan O-REQ/INFR REQ

**Files:**
- Modify: `app/Http/Controllers/TaskMaintenanceController.php`
- Modify: `resources/views/tasks/maintenance-report.blade.php`
- Test: `tests/Feature/TaskInfrastructureDeployReportTest.php`

**Interfaces:**
- Consumes: `InventoryService::deploySerial/deployQuantity/deployRoll/retrieveSerialFromLocation()` (Task 10),
  `NetworkNode`/`assertNetworkNodeInScope()` (Task 4/9).

- [ ] **Step 1: Write failing tests**: 4 skenario:
  1. Teknisi submit laporan INFR REQ dengan `infra_target_network_node_id`
     + `infra_selected_serial_id` → SN jadi `DEPLOYED` di node itu.
  2. `infra_target_network_node_id` di luar scope cabang task → `assertSessionHasErrors`.
  3. O-REQ dengan `infra_selected_roll_id`+`infra_roll_meters_used` →
     `infrastructure_balances` bertambah.
  4. `infra_retrieve_old_serial_id` + `infra_selected_serial_id` bersamaan (ganti
     aset) → SN lama `ISSUED` balik custody, SN baru `DEPLOYED`.
- [ ] **Step 2: Run, verify fail**
- [ ] **Step 3: Implement controller**: di `report()`: `$eligibleInfraSerials`/
  `$eligibleInfraCustody`/`$eligibleInfraRolls` (copy 3 method `eligible*ForTeam()`
  existing, filter `ownership_mode=INFRASTRUCTURE_ASSET`). `$nodeOptions` = node
  yang `resolvePop()`-nya di scope task. Pakai `scopeInPops([task->pop_id])`
  supaya tidak N+1. Seksi tampil kondisional `task_type ∈ {OREQ, INFR}`.

  Di `store()`, rules nullable: `infra_target_network_node_id` (`exists:network_nodes,id`),
  `infra_selected_serial_id`/`infra_retrieve_old_serial_id` (`Rule::in($eligibleInfraSerialIds)`),
  `infra_quantity_item_id`+`infra_quantity_qty`, `infra_selected_roll_id`+`infra_roll_meters_used`.
  Validasi custom: kalau ada field `infra_*` lain terisi, `infra_target_network_node_id`
  wajib. Node wajib lolos `assertNetworkNodeInScope()` (lempar `ValidationException`,
  bukan `abort()`, supaya muncul di `assertSessionHasErrors`).

  Di blok transaction `store()`: resolve `$node`, lalu `retrieveSerialFromLocation()`
  (kalau ada `infra_retrieve_old_serial_id`), `deploySerial()`, `deployQuantity()`,
  `deployRoll()` sesuai field yang terisi.
- [ ] **Step 4: Implement view**: seksi Alpine `x-show` (pola Material Terpakai).
  Tree picker node: dropdown berjenjang, mulai dari cabang task, turun lewat
  `parent_id` lewat data yang sudah dioper controller. Dropdown SN/qty/roll dari 3
  variabel eligible. Dropdown opsional "SN lama yang dicabut".
- [ ] **Step 5: Run, verify pass**
- [ ] **Step 6: Commit**: `vendor/bin/pint --dirty --format agent`, `npm run build`,
  `feat: seksi Pasang/Ganti Infrastruktur (SN + qty + roll) di Laporan O-REQ/INFR REQ`

---

### Task 13: Halaman "Aset & Kapasitas per Lokasi": gabung ke `NetworkNodeController`

Memperluas `NetworkNodeController::index()`/view (Task 7) supaya tiap node
juga menampilkan aset Gudang yang nempel, tidak cuma kapasitas port.

**Files:**
- Modify: `app/Http/Controllers/Network/NetworkNodeController.php`
- Modify: `resources/views/network/nodes/index.blade.php`
- Test: `tests/Feature/Warehouse/NetworkNodeAssetViewTest.php`

**Interfaces:**
- Consumes: `InventorySerial`(`status=DEPLOYED`)/`InfrastructureBalance` (Task 8),
  `scopeInPops()` (Task 4), `assertNetworkNodeInScope()` (Task 9).

- [ ] **Step 1: Write failing test**: node dengan 1 SN `DEPLOYED` + 1 baris
  `infrastructure_balances`, `index()` menampilkan SN + qty bersebelahan dengan data port.
  User di luar scope tidak melihat node cabang lain.
- [ ] **Step 2: Run, verify fails**
- [ ] **Step 3: Implement**: eager-load `NetworkNode::with('deployedSerials', 'infrastructureBalances')`.
  Relasi baru: `deployedSerials(): HasMany(InventorySerial::class,'current_network_node_id')`,
  `infrastructureBalances(): HasMany(InfrastructureBalance::class)`. Query index
  dibatasi `scopeInPops()` dengan `getAllowedPopIds()` dari `EffectiveAccessService`.
  View: SN (link ke `warehouse.traceability.index`) + saldo `infrastructure_balances`.
- [ ] **Step 4: Run, verify pass**
- [ ] **Step 5: Commit**: `feat: halaman Network Node tampilkan aset Gudang terpasang, bukan cuma kapasitas port`

---

## Urutan Eksekusi

Task 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12 → 13 (linear).

- Task 6 (`move()`) sebelum Task 7 karena reshape memakai `assertPlacement()`.
- Task 7 (rekonsiliasi) wajib setelah Task 3-6 dan sebelum Task 12-13 (butuh `NetworkNodeController` final).
- Task 9 sebelum Task 12 dan 13 (dipakai di keduanya).

## Open Item (tidak menghalangi eksekusi)

Lihat spec §7. Ringkasnya: chassis OLT (node pembungkus, setelah v3), tipe-di-bawah-tipe
(ditunda), pindah lintas cabang (permission khusus?), task O-REQ/INFR terbuka saat node pindah,
dan log pindah (audit log existing cukup?). Task 6 harus memutuskan poin log pindah
sebelum commit.
