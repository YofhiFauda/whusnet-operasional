# Fase 0 — Akar Infrastruktur (Chassis OLT, Feeder, Aturan Tipe) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membenahi akar pohon infrastruktur (Fase 0, keputusan user 2026-10-14)
sebelum fitur peta/sinkronisasi API dilanjutkan: Mini POP jadi representasi
lokasi fisik (bukan 1 OLT), chassis OLT jadi node sungguhan yang menaungi
kartu, tipe node `feeder` ditambahkan, aturan susunan tipe (`olt_chassis`/
`olt_card`/`odc`) disesuaikan, dan aset infrastruktur lama (yang sudah
berdiri bertahun-tahun, belum pernah lewat ledger) bisa diregistrasi manual
satu-satu tanpa menyamar sebagai `DEPLOY` (Task 6-8, jawaban §6 Q4).

**Architecture:** Kerja ini murni di atas pohon `network_nodes` generik yang
sudah ada (tidak ada tabel baru untuk topologi). `olt_slots` pindah kepemilikan
dari `pops` ke `network_nodes` (chassis). Jalur deploy Gudang (`deploySerial`/
`deployQuantity`/`deployRoll` via laporan O-REQ/INFR, `infra_target_network_node_id`)
TIDAK disentuh — sudah generik untuk node apa pun, termasuk chassis dan feeder
baru.

**Tech Stack:** Laravel 13, PHPUnit 12, SQLite `:memory:` untuk test.

**Spec:**
`docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` §11
(revisi 2026-10-14) dan `docs/plan/infrastruktur/analisa-gap-peta-infrastruktur.md`
§6 (jawaban Q1-Q5) + §L1 (chassis OLT).

## Global Constraints

- Semua file migration/seeder/controller/test yang disentuh di plan ini masih
  **uncommitted** di `dev` (lihat `git status`) — edit langsung file yang ada,
  JANGAN tambah migration "fix forward" kecuali eksplisit disebut di task.
- Tipe node tetap master data (`network_node_types`), bukan PHP enum/hardcode.
- `olt_slots` restrict-on-delete ke pemiliknya (sekarang `pops`, nanti
  `network_nodes`) — jangan diubah ke cascade.
- Satu Mini POP (atau POP Cabang) boleh menaungi N chassis OLT — jangan
  menulis aturan/unique constraint yang membatasi ke 1 chassis per lokasi.
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum tiap commit.
- Test terarah saja (`php artisan test --compact <file>` / `--filter=`) —
  JANGAN jalankan full suite.

## Review Focus

- Mini POP dengan 3-4 chassis OLT sekaligus — slot/kartu tiap chassis tidak
  boleh "tabrakan" nomor slot antar chassis yang berbeda.
- Chassis OLT di anchor langsung ke POP Cabang (bukan Mini POP) — alur store
  slot & attach card harus tetap jalan tanpa cek `type==='mini_pop'` yang lama.
- `olt_card` yang masih anchor langsung ke POP (data lama, kalau ada) harus
  tetap bisa dibaca tanpa error — tapi tidak boleh lagi BISA dibuat baru
  dengan cara itu.
- `odc` sebagai anak `feeder` — rute port assignment (`assignNode`) harus
  tetap menegakkan "target adalah anak langsung pemilik port".
- Menghapus chassis yang masih punya kartu/slot terisi harus tetap ditolak
  (`restrictOnDelete`) — jangan sampai Task 2 melonggarkan ini.

---

## Task 1: Aturan tipe node — `olt_chassis` boleh anchor Cabang, `olt_card` wajib anak chassis, tambah tipe `feeder`

**Files:**
- Modify: `database/seeders/NetworkNodeTypeSeeder.php` (array `$types` baris
  52-61, array `$defaultRules` baris 89-98)
- Modify: `database/migrations/2026_10_13_140545_backfill_default_network_node_type_rules.php`
  (array `$defaultRules` baris 11-20)
- Modify: `database/migrations/2026_10_13_145033_add_infrastructure_node_type_to_items.php`
  (loop baris 12-32 — ini yang sebenarnya mengisi rule `olt_chassis`/`olt_card`
  di DB yang sudah punya migration lama, karena backfill di atas jalan
  SEBELUM migration ini membuat tipenya — lihat catatan di bawah)
- Create: `database/migrations/2026_10_14_000001_add_feeder_node_type_and_fix_olt_rules.php`
- Test: `tests/Feature/NetworkNodePlacementRuleTest.php` (ubah test yang ada,
  tambah test baru)

**Interfaces:**
- Consumes: `NetworkNodeService::assertTypePlacement(NetworkNodeType $type, ?NetworkNode $parent, ?Pop $anchor): void` (`app/Services/NetworkNodeService.php:147`, TIDAK diubah — cuma datanya yang berubah).
- Produces: tipe node `feeder` (code `feeder`, `has_ports=true`, `max_ports=null`) tersedia di `network_node_types` untuk Task lain & test.

Aturan final yang harus terbentuk di `network_node_type_rules` (gantikan yang lama):

| child (code) | anchor_pop_type | parent_type (code) |
|---|---|---|
| `olt_chassis` | `mini_pop` DAN `cabang` (dua baris) | — |
| `olt_card` | *(hapus baris anchor `mini_pop` yang lama)* | `olt_chassis` |
| `feeder` | `mini_pop` | `olt_chassis` (dua baris: satu anchor, satu parent) |
| `odc` | *(tetap `mini_pop`, `cabang` yang sudah ada)* | tambah `feeder` ke daftar parent yang sudah ada (`olt_card`, `pon_port`) |

- [ ] **Step 1: Tulis test yang gagal untuk aturan baru**

Di `tests/Feature/NetworkNodePlacementRuleTest.php`, ganti
`test_anchor_pop_rule_only_allows_selected_pop_type` (baris 39-49) — `olt_card`
TIDAK LAGI boleh anchor langsung ke Mini POP, jadi assert sebaliknya, dan
tambah assert kartu boleh jadi anak chassis:

```php
public function test_olt_card_wajib_anak_chassis_bukan_anchor_pop_langsung(): void
{
    $this->seed(\Database\Seeders\NetworkNodeTypeSeeder::class);
    $mini = Pop::factory()->create(['type' => 'mini_pop']);
    $cardType = NetworkNodeType::where('code', 'olt_card')->firstOrFail();
    $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
    $chassis = NetworkNode::factory()->create(['anchor_pop_id' => $mini->id, 'node_type_id' => $chassisType->id]);

    app(NetworkNodeService::class)->assertTypePlacement($cardType, $chassis, null);

    $this->expectException(InvalidArgumentException::class);
    app(NetworkNodeService::class)->assertTypePlacement($cardType, null, $mini);
}

public function test_olt_chassis_boleh_anchor_mini_pop_atau_cabang_langsung(): void
{
    $this->seed(\Database\Seeders\NetworkNodeTypeSeeder::class);
    $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
    $mini = Pop::factory()->create(['type' => 'mini_pop']);
    $branch = Pop::factory()->create(['type' => 'cabang']);

    app(NetworkNodeService::class)->assertTypePlacement($chassisType, null, $mini);
    app(NetworkNodeService::class)->assertTypePlacement($chassisType, null, $branch);
}

public function test_feeder_boleh_jadi_anak_chassis_dan_odc_boleh_jadi_anak_feeder(): void
{
    $this->seed(\Database\Seeders\NetworkNodeTypeSeeder::class);
    $mini = Pop::factory()->create(['type' => 'mini_pop']);
    $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
    $feederType = NetworkNodeType::where('code', 'feeder')->firstOrFail();
    $odcType = NetworkNodeType::where('code', 'odc')->firstOrFail();
    $chassis = NetworkNode::factory()->create(['anchor_pop_id' => $mini->id, 'node_type_id' => $chassisType->id]);
    $feeder = NetworkNode::factory()->create(['parent_id' => $chassis->id, 'node_type_id' => $feederType->id]);

    app(NetworkNodeService::class)->assertTypePlacement($feederType, $chassis, null);
    app(NetworkNodeService::class)->assertTypePlacement($odcType, $feeder, null);
}

public function test_satu_mini_pop_boleh_menaungi_beberapa_chassis(): void
{
    $this->seed(\Database\Seeders\NetworkNodeTypeSeeder::class);
    $mini = Pop::factory()->create(['type' => 'mini_pop']);
    $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
    NetworkNode::factory()->count(4)->create(['anchor_pop_id' => $mini->id, 'node_type_id' => $chassisType->id]);

    $this->assertSame(4, NetworkNode::where('anchor_pop_id', $mini->id)->where('node_type_id', $chassisType->id)->count());
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=NetworkNodePlacementRuleTest`
Expected: FAIL (`olt_card` lama masih punya rule anchor `mini_pop`; tipe
`feeder` belum ada — kolom `code` not found / assertion gagal).

- [ ] **Step 3: Perbaiki `NetworkNodeTypeSeeder::$types` dan `$defaultRules`**

Tambah baris tipe `feeder` ke `$types` (baris 52-61):
`['code' => 'feeder', 'name' => 'Kabel Feeder', 'has_ports' => true, 'monitors_capacity' => false, 'max_ports' => null, 'red_zone_threshold' => null]`.

Ubah `$defaultRules` (baris 89-98) sesuai tabel final di atas: `olt_chassis`
=> `['pops' => ['mini_pop', 'cabang'], 'nodes' => []]`; `olt_card` =>
`['pops' => [], 'nodes' => ['olt_chassis']]`; `odc` => tambah `'feeder'` ke
array `nodes` yang sudah ada; tambah entri `feeder` =>
`['pops' => ['mini_pop'], 'nodes' => ['olt_chassis']]`.

- [ ] **Step 4: Perbaiki migration lama (`140545`) + migration late-add (`145033`)**

Terapkan tabel final yang sama ke `$defaultRules` di migration `140545`
(baris 11-20). Di migration `145033`, loop `foreach (['olt_chassis' => ...,
'olt_card' => ...] as $code => ...)` (baris 12) HANYA insert
`anchor_pop_type=mini_pop` untuk keduanya — ubah supaya: `olt_chassis` insert
DUA baris anchor (`mini_pop`, `cabang`); `olt_card` insert SATU baris
`parent_type_id` menunjuk id `olt_chassis` (bukan `anchor_pop_type`). Catatan
kenapa ini perlu diubah di sini juga: migration `140545` berjalan SEBELUM
`145033` menciptakan baris tipe `olt_chassis`/`olt_card`, jadi backfill di
`140545` skip (`$childId === null`) untuk dua tipe ini — `145033` adalah
tempat aturan itu SEBENARNYA terbentuk di DB yang migration-nya sudah jalan
sebelum perubahan ini.

- [ ] **Step 5: Buat migration baru untuk `feeder` (DB yang migration lama sudah jalan)**

`database/migrations/2026_10_14_000001_add_feeder_node_type_and_fix_olt_rules.php`
— pola sama seperti `145033` (insert tipe `feeder` kalau belum ada, lalu
insert rule `anchor_pop_type=mini_pop` dan `parent_type_id=olt_chassis`),
PLUS: insert rule tambahan `odc` child → `parent_type_id=feeder` kalau belum
ada.

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --compact --filter=NetworkNodePlacementRuleTest`
Expected: PASS semua test di file ini.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/NetworkNodeTypeSeeder.php database/migrations/2026_10_13_140545_backfill_default_network_node_type_rules.php database/migrations/2026_10_13_145033_add_infrastructure_node_type_to_items.php database/migrations/2026_10_14_000001_add_feeder_node_type_and_fix_olt_rules.php tests/Feature/NetworkNodePlacementRuleTest.php
git commit -m "feat: chassis OLT boleh anchor Cabang, kartu wajib anak chassis, tambah tipe feeder"
```

---

## Task 2: `olt_slots` pindah kepemilikan dari POP ke node chassis

**Files:**
- Modify: `database/migrations/2026_10_13_112437_create_olt_slots_table.php`
- Modify: `app/Models/OltSlot.php`
- Modify: `app/Models/NetworkNode.php` (tambah relasi `oltSlots()`)
- Modify: `app/Models/Pop.php` (hapus relasi `oltSlots()`, baris sekitar 54)
- Test: `tests/Feature/OltSlotManagementTest.php` (disesuaikan lebih jauh di Task 3,
  cukup update query `OltSlot::where('pop_id', ...)` di task ini kalau ada test
  yang jalan duluan — tapi karena route-nya ikut berubah di Task 3, biarkan test
  file ini gagal sampai Task 3 selesai; jangan jalankan test file ini di akhir
  Task 2)

**Interfaces:**
- Consumes: tabel `network_nodes` (FK target), tipe `olt_chassis` dari Task 1.
- Produces: `OltSlot::chassis(): BelongsTo` (ganti nama dari `pop()`),
  `NetworkNode::oltSlots(): HasMany` — dipakai Task 3 (`OltSlotController`)
  dan Task 4 (view).

- [ ] **Step 1: Ubah migration `create_olt_slots_table`**

Ganti `$table->foreignId('pop_id')->constrained('pops')->restrictOnDelete();`
jadi `$table->foreignId('chassis_node_id')->constrained('network_nodes')->restrictOnDelete();`
dan `$table->unique(['pop_id', 'number'])` jadi
`$table->unique(['chassis_node_id', 'number'])`. Tidak perlu `up()`/`down()`
baru — file ini masih uncommitted, edit di tempat.

- [ ] **Step 2: Ubah `OltSlot` model**

Ganti method `pop(): BelongsTo` (return `$this->belongsTo(Pop::class)`)
jadi `chassis(): BelongsTo` (`$this->belongsTo(NetworkNode::class, 'chassis_node_id')`).
Update `#[Fillable(['pop_id', 'number', 'card_node_id'])]` jadi
`#[Fillable(['chassis_node_id', 'number', 'card_node_id'])]`.

- [ ] **Step 3: Tambah `NetworkNode::oltSlots(): HasMany`**

Di `app/Models/NetworkNode.php`, tambah method `oltSlots(): HasMany` →
`$this->hasMany(OltSlot::class, 'chassis_node_id')`. Letakkan dekat relasi
`ports()`/`deployedSerials()` yang sudah ada.

- [ ] **Step 4: Hapus `Pop::oltSlots()`**

Di `app/Models/Pop.php` baris ~54, hapus method `oltSlots(): HasMany` dan
import `OltSlot` kalau jadi tidak terpakai lagi di file itu.

- [ ] **Step 5: Jalankan Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_10_13_112437_create_olt_slots_table.php app/Models/OltSlot.php app/Models/NetworkNode.php app/Models/Pop.php
git commit -m "refactor: olt_slots dimiliki node chassis, bukan POP"
```

---

## Task 3: `OltSlotController` — operasi slot/kartu pindah ke node chassis

**Files:**
- Modify: `app/Http/Controllers/Network/OltSlotController.php`
- Modify: `routes/web.php` (baris 640-641)
- Test: `tests/Feature/OltSlotManagementTest.php` (tulis ulang total)

**Interfaces:**
- Consumes: `NetworkNode::oltSlots()` (Task 2), `NetworkNodeType::code`,
  `EffectiveAccessService::hasAllPopAccess()`/`getAllowedPopIds()` (pola sama
  dengan `NetworkNodeController::authorizeNodeScope()`,
  `app/Http/Controllers/Network/NetworkNodeController.php:281-293`).
- Produces: route `network.nodes.olt-slots.store`, `network.nodes.olt-slots.card`.

- [ ] **Step 1: Tulis ulang test (gagal dulu)**

Ganti isi `tests/Feature/OltSlotManagementTest.php` — tiga test lama dibuat
ulang dengan model baru (chassis anchor langsung, card jadi anak chassis via
`parent_id`), plus skenario user (3-4 chassis di 1 Mini POP) dan chassis
anchor ke Cabang langsung:

```php
public function test_hanya_chassis_olt_memiliki_slot_dan_kartu_mengisi_sebagai_anak(): void
{
    // seed NetworkNodeTypeSeeder, buat Mini POP, buat node olt_chassis
    // anchor ke Mini POP itu, buat node olt_card dengan parent_id = chassis.
    // POST network.nodes.olt-slots.store ke node BUKAN chassis -> assertSessionHasErrors.
    // POST ke chassis -> redirect ke network.nodes.show(chassis), slot tercipta.
    // POST network.nodes.olt-slots.card dengan network_node_id = card -> slot->card_node_id terisi.
}

public function test_kartu_yang_bukan_anak_chassis_ini_ditolak(): void
{
    // card dengan parent_id milik chassis LAIN -> attachCard 422/ValidationException.
}

public function test_chassis_boleh_anchor_cabang_langsung_tanpa_mini_pop(): void
{
    // Pop type=cabang, chassis anchor ke situ langsung, store slot sukses.
}

public function test_satu_mini_pop_boleh_punya_4_chassis_slot_terpisah_per_chassis(): void
{
    // 4 node olt_chassis anchor ke 1 Mini POP, tiap chassis store slot_count=2
    // secara independen -> total 8 OltSlot, nomor 1-2 di tiap chassis tidak
    // dianggap konflik (unique per chassis_node_id, bukan per pop).
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact tests/Feature/OltSlotManagementTest.php`
Expected: FAIL (route lama masih `master.pop.olt.slots.*`, belum ada
`network.nodes.olt-slots.*`).

- [ ] **Step 3: Tulis ulang `OltSlotController`**

```php
public function store(Request $request, NetworkNode $chassis): RedirectResponse
public function attachCard(Request $request, NetworkNode $chassis, OltSlot $slot): RedirectResponse
```

`store()`: ganti cek `$pop->type !== 'mini_pop'` jadi
`$chassis->nodeType?->code !== 'olt_chassis'` (pesan error:
`'Slot OLT hanya dapat dibuat pada node Chassis OLT.'`), tambah
`$this->authorizeNodeScope($chassis)` (copy pola dari `NetworkNodeController`,
lihat Interfaces), redirect ke `route('network.nodes.show', $chassis)`.

`attachCard()`: ganti validasi baris 39-50 — cek `$slot->chassis_node_id ===
$chassis->id` (bukan `$slot->pop_id !== $pop->id`), dan cek kartu:
`$card->parent_id !== $chassis->id || $card->nodeType?->code !== 'olt_card'`
(HAPUS cek `$card->anchor_pop_id !== $pop->id || $card->parent_id !== null`
yang lama — kartu SEKARANG harus punya `parent_id`, bukan `anchor_pop_id`).
Redirect ke `route('network.nodes.show', $chassis)`.

Ganti `authorizePop()` jadi `authorizeNodeScope(NetworkNode $node): void` —
copy persis dari `NetworkNodeController::authorizeNodeScope()`
(`app/Http/Controllers/Network/NetworkNodeController.php:281-293`), konstruktor
controller butuh `EffectiveAccessService $access` seperti
`NetworkNodeController`.

- [ ] **Step 4: Ubah route**

Di `routes/web.php`, dalam grup `permission:network_nodes.update` (baris
1200-1205, bukan grup master pop lama), tambah:

```php
Route::post('/network/nodes/{chassis}/olt-slots', [OltSlotController::class, 'store'])->name('network.nodes.olt-slots.store');
Route::post('/network/nodes/{chassis}/olt-slots/{slot}/card', [OltSlotController::class, 'attachCard'])->name('network.nodes.olt-slots.card');
```

Hapus dua baris lama (640-641) yang pakai `master.pop.olt.slots.*`.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --compact tests/Feature/OltSlotManagementTest.php`
Expected: PASS semua test.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Network/OltSlotController.php routes/web.php tests/Feature/OltSlotManagementTest.php
git commit -m "feat: slot dan kartu OLT dikelola per node chassis, bukan per POP"
```

---

## Task 4: View — pindahkan UI slot/kartu dari halaman POP ke halaman node chassis

**Files:**
- Modify: `resources/views/master/pop/show.blade.php` (baris 17-76 — hapus blok
  "OLT · Slot dan Kartu", pertahankan/ringkas blok "Chassis OLT terpasang"
  jadi daftar link ke tiap chassis)
- Modify: `resources/views/network/nodes/show.blade.php` (tambah blok slot/
  kartu kalau `$node->nodeType->code === 'olt_chassis'`)
- Modify: controller yang menyuplai `$chassisNodes`/`$cardOptions`/`$pop` ke
  `master/pop/show.blade.php` — cari dengan
  `grep -rn "chassisNodes\|cardOptions" app/Http/Controllers` sebelum mengubah
  (nama file controller belum dikonfirmasi di riset plan ini)
- Modify: `app/Http/Controllers/Network/NetworkNodeController.php::show()`
  (baris 151-157) — suplai `$node->oltSlots` + opsi kartu bebas kalau tipe
  node = `olt_chassis`

**Interfaces:**
- Consumes: `NetworkNode::oltSlots()` (Task 2), route
  `network.nodes.olt-slots.store`/`.card` (Task 3).
- Produces: tidak ada interface baru untuk task lain (task terakhir di
  rantai ini).

- [ ] **Step 1: Cari controller yang menyuplai `master/pop/show.blade.php`**

Run: `grep -rn "chassisNodes\|cardOptions\|master.pop.show" app/Http/Controllers`

- [ ] **Step 2: Pindahkan blok "OLT · Slot dan Kartu" (baris 36-76 `pop/show.blade.php`)**

Hapus blok itu dari `master/pop/show.blade.php`. Ganti blok "Chassis OLT
terpasang" (baris 18-35) supaya tiap `$chassis` dalam `@forelse($chassisNodes
as $chassis)` jadi link `<a href="{{ route('network.nodes.show', $chassis) }}">`
ke halaman node-nya (bukan ditampilkan expand di halaman POP).

- [ ] **Step 3: Tambah blok slot/kartu di `network/nodes/show.blade.php`**

Tambah section baru (pola Blade sama dengan blok yang dipindah dari
`pop/show.blade.php`, form action ganti ke
`route('network.nodes.olt-slots.store', $node)` dan
`route('network.nodes.olt-slots.card', [$node, $slot])`), dibungkus
`@if($node->nodeType->code === 'olt_chassis')`. Loop lewat `$node->oltSlots`
(perlu di-load di controller, lihat Step 4). Opsi kartu kosong: query node
anak `$node->children` dengan `nodeType->code === 'olt_card'` yang belum
terpakai di slot manapun (`whereDoesntHave` ke `OltSlot` by `card_node_id`).

- [ ] **Step 4: Suplai data di `NetworkNodeController::show()`**

Tambah eager-load `oltSlots.cardNode.deployedSerials`, `children.nodeType` ke
`$node->load(...)` (baris 154), hitung `$cardOptions` (anak langsung bertipe
`olt_card` yang `card_node_id`-nya belum terisi di `OltSlot` manapun) kalau
`$node->nodeType->code === 'olt_chassis'`, lempar ke view lewat
`compact('node', 'location', 'cardOptions')`.

- [ ] **Step 5: Test manual — jalankan test Task 3 ulang (regresi view)**

Run: `php artisan test --compact tests/Feature/OltSlotManagementTest.php`
Expected: PASS (test Task 3 sudah mengecek `assertSee` konten view lewat
`network.nodes.show`, bukan lagi `master.pop.show` — pastikan assertion di
Task 3 Step 1 disesuaikan ke route baru kalau belum).

- [ ] **Step 6: `npm run build`**

Run: `npm run build`
Expected: build sukses, tidak ada Blade/Alpine error.

- [ ] **Step 7: Commit**

```bash
git add resources/views/master/pop/show.blade.php resources/views/network/nodes/show.blade.php app/Http/Controllers/Network/NetworkNodeController.php
git commit -m "feat: UI slot dan kartu OLT pindah ke halaman node chassis"
```

---

## Task 6: Ledger `REGISTER_INFRA` + `InventoryService::registerExistingInfraSerial()`

**Files:**
- Modify: `app/Enums/InventoryTransactionType.php` (tambah case
  `REGISTER_INFRA`, label-nya)
- Modify: `app/Enums/ActionCode.php` (tambah case `REGISTER_INFRA`)
- Modify: `config/rbac.php` (array `'network_nodes'` baris 668-672 — tambah
  `ActionCode::REGISTER_INFRA->value`)
- Modify: `app/Services/InventoryService.php`
- Test: `tests/Unit/InventoryServiceRegisterInfraTest.php` (baru)

**Interfaces:**
- Consumes: `NetworkNode::resolvePop()` (`app/Models/NetworkNode.php:209`),
  `NetworkNode::breadcrumbLabel()` (`:317`), `OwnershipMode::INFRASTRUCTURE_ASSET`,
  `SerialStatus::DEPLOYED`.
- Produces: `InventoryService::registerExistingInfraSerial(string $serialNumber, Item $item, NetworkNode $node, string $reason, User $actor): InventorySerial`
  — dipakai Task 7 (`InfraAssetRegistrationController`).

Beda sengaja dari `deploySerial()` (baris 522): TIDAK syaratkan `FopTask`,
TIDAK syaratkan SN sudah `ISSUED` ke custody teknisi (SN belum pernah ada di
sistem sama sekali), `reason` WAJIB diisi (alasan registrasi, bukan opsional
kayak `notes` di `DEPLOY`), dan SN dibuat baru langsung berstatus `DEPLOYED`
dalam satu transaksi dengan baris ledger-nya.

- [ ] **Step 1: Tulis test yang gagal**

```php
public function test_registrasi_aset_lama_membuat_sn_deployed_dan_ledger_register_infra(): void
{
    // Item ownership_mode=infrastructure_asset, tracking_type=serialized.
    // NetworkNode $node dibuat lebih dulu (anchor ke Mini POP).
    // registerExistingInfraSerial('SN-LEGACY-001', $item, $node, 'Opname awal, chassis sudah berdiri sejak 2023', $actor)
    // -> InventorySerial status DEPLOYED, current_network_node_id = $node->id
    // -> satu InventoryTransaction type=REGISTER_INFRA, serial_id terisi,
    //    to_network_node_id = $node->id, reason = alasan di atas, fop_task_id NULL.
}

public function test_registrasi_tanpa_alasan_ditolak(): void
{
    // reason='' -> InvalidArgumentException.
}

public function test_registrasi_item_bukan_infrastructure_asset_ditolak(): void
{
    // item ownership_mode != infrastructure_asset -> InvalidArgumentException.
}

public function test_registrasi_serial_number_yang_sudah_ada_ditolak(): void
{
    // SN sudah dipakai record lain -> InvalidArgumentException (unique global, lihat migration create_inventory_serials_table baris 34).
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact tests/Unit/InventoryServiceRegisterInfraTest.php`
Expected: FAIL (method belum ada).

- [ ] **Step 3: Tambah `InventoryTransactionType::REGISTER_INFRA`**

Tambah case `REGISTER_INFRA = 'register_infra'` + label `'Registrasi Aset Lama (Infrastruktur)'`,
docblock pendek: SN yang sudah lama berdiri di lapangan, belum pernah lewat
RECEIVE/ISSUE/DEPLOY — satu-satunya jalur yang boleh membuat SN langsung
`DEPLOYED` tanpa `FopTask`.

- [ ] **Step 4: Tambah `ActionCode::REGISTER_INFRA` + permission**

Tambah case `REGISTER_INFRA = 'register_infra'` di `ActionCode` (docblock:
kenapa bukan `CREATE` biasa — ini jalur ledger khusus yang bisa menciptakan
aset tanpa jejak penerimaan Gudang normal, sengaja permission sempit
terpisah). Tambah `ActionCode::REGISTER_INFRA->value` ke array
`'network_nodes'` di `config/rbac.php` (hasil: permission
`network_nodes.register_infra`).

- [ ] **Step 5: Implementasi `InventoryService::registerExistingInfraSerial()`**

```php
public function registerExistingInfraSerial(string $serialNumber, Item $item, NetworkNode $node, string $reason, User $actor): InventorySerial
```

Validasi dalam `DB::transaction()`: `trim($reason) === ''` → tolak;
`$item->ownership_mode !== OwnershipMode::INFRASTRUCTURE_ASSET` → tolak;
`$item->tracking_type` bukan serialized → tolak; `InventorySerial::where('serial_number', $serialNumber)->exists()` → tolak
(pesan: SN sudah terdaftar). Buat `InventorySerial::create([...status =>
SerialStatus::DEPLOYED, current_network_node_id => $node->id, current_technician_id => null])`,
lalu `InventoryTransaction::create([type => REGISTER_INFRA, serial_id,
item_id, qty => 1, to_network_node_id => $node->id, to_pop_id =>
$node->resolvePop()?->id, reason => trim($reason), fop_task_id => null,
created_by => $actor->id, notes => "SN {$serialNumber} diregistrasi sebagai
aset lama di {$node->breadcrumbLabel()}."])`. Return SN yang dibuat.

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --compact tests/Unit/InventoryServiceRegisterInfraTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Enums/InventoryTransactionType.php app/Enums/ActionCode.php config/rbac.php app/Services/InventoryService.php tests/Unit/InventoryServiceRegisterInfraTest.php
git commit -m "feat: ledger REGISTER_INFRA untuk registrasi aset infrastruktur lama"
```

---

## Task 7: Halaman registrasi aset lama (create tersendiri, bukan modal)

**Files:**
- Create: `app/Http/Controllers/Network/InfraAssetRegistrationController.php`
- Create: `resources/views/network/nodes/register-asset.blade.php`
- Modify: `routes/web.php` (grup `permission:network_nodes.view` baris
  1191-1196 untuk GET create; grup baru `permission:network_nodes.register_infra`
  untuk POST store)
- Modify: `resources/views/network/nodes/show.blade.php` (tombol "Registrasi
  Aset Lama" → `route('network.nodes.register-asset.create', $node)`)
- Test: `tests/Feature/Network/InfraAssetRegistrationTest.php` (baru)

**Interfaces:**
- Consumes: `InventoryService::registerExistingInfraSerial()` (Task 6),
  `NetworkNodeController::authorizeNodeScope()` pattern (scope POP sama).
- Produces: route `network.nodes.register-asset.create` (GET),
  `network.nodes.register-asset.store` (POST).

Pola halaman: **create tersendiri**, bukan modal — ini mutasi ledger
(aturan 3 pola aksi di `CLAUDE.md`: mutasi data → halaman create sendiri,
supaya `withErrors()->withInput()` gak nutup modal di atas List kosong).

- [ ] **Step 1: Tulis test yang gagal**

```php
public function test_user_tanpa_permission_register_infra_ditolak(): void
{
    // role tanpa network_nodes.register_infra -> GET create 403.
}

public function test_registrasi_sukses_redirect_ke_detail_node_dengan_sn_baru(): void
{
    // POST network.nodes.register-asset.store dengan item_id, serial_number, reason
    // -> redirect ke network.nodes.show($node), flash success,
    //    GET network.nodes.show($node) assertSee(serial_number baru).
}

public function test_registrasi_gagal_validasi_tampil_di_halaman_create_bukan_list(): void
{
    // POST tanpa reason -> redirect balik ke create (withErrors), BUKAN ke index.
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact tests/Feature/Network/InfraAssetRegistrationTest.php`

- [ ] **Step 3: Implementasi controller**

```php
public function create(NetworkNode $node): View   // pilih Item (filter ownership_mode=infrastructure_asset, tracking_type=serialized), form serial_number + reason
public function store(Request $request, NetworkNode $node): RedirectResponse
```

`store()`: `$request->validate(['item_id' => 'required|exists:items,id', 'serial_number' => 'required|string|max:100', 'reason' => 'required|string|max:500'])`,
authorize scope node (pola `authorizeNodeScope()`), panggil
`InventoryService::registerExistingInfraSerial()`, tangkap
`InvalidArgumentException` → `ValidationException::withMessages(['serial_number' => $e->getMessage()])`,
redirect `route('network.nodes.show', $node)` dengan flash success (pola PRG
— create satu record → halaman Detail).

- [ ] **Step 4: Tambah route**

```php
// grup permission:network_nodes.view
Route::get('/network/nodes/{node}/registrasi-aset', [InfraAssetRegistrationController::class, 'create'])->name('network.nodes.register-asset.create');
// grup BARU permission:network_nodes.register_infra
Route::post('/network/nodes/{node}/registrasi-aset', [InfraAssetRegistrationController::class, 'store'])->name('network.nodes.register-asset.store');
```

- [ ] **Step 5: Buat view `register-asset.blade.php` + tombol di `show.blade.php`**

Form sederhana: dropdown Item (infrastructure_asset serialized), input
`serial_number`, textarea `reason` (wajib). Tombol di `network/nodes/show.blade.php`
muncul `@can('network_nodes.register_infra')`.

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --compact tests/Feature/Network/InfraAssetRegistrationTest.php`

- [ ] **Step 7: `npm run build`**

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Network/InfraAssetRegistrationController.php resources/views/network/nodes/register-asset.blade.php resources/views/network/nodes/show.blade.php routes/web.php tests/Feature/Network/InfraAssetRegistrationTest.php
git commit -m "feat: halaman registrasi aset infrastruktur lama per node"
```

---

## Task 8: Seed permission role + regresi RBAC

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php` (atau seeder role
  matrix yang berlaku — cari dulu dengan
  `grep -n "network_nodes" database/seeders/RolePermissionSeeder.php`)
- Test: jalankan test RBAC yang ada (`php artisan test --compact --filter=RolePermissionSeeder`
  atau nama test yang relevan ditemukan dari langkah pencarian)

**Interfaces:**
- Consumes: permission `network_nodes.register_infra` (Task 6).
- Produces: tidak ada (task penutup Task 6-8).

- [ ] **Step 1: Cari role mana yang sekarang pegang `network_nodes.*`**

Run: `grep -n "network_nodes" database/seeders/RolePermissionSeeder.php`

- [ ] **Step 2: Tambah `network_nodes.register_infra` ke role yang sama**

Ikuti role yang sudah pegang `network_nodes.create`/`.update` di baris yang
ditemukan Step 1 (kemungkinan `owner`, `admin`, `fop` — PASTIKAN lewat matrix
role, JANGAN permission langsung ke user, sesuai larangan RBAC di `CLAUDE.md`).

- [ ] **Step 3: Jalankan test RBAC terkait, pastikan lulus**

- [ ] **Step 4: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php
git commit -m "feat: role matrix untuk permission network_nodes.register_infra"
```

---

## Task 9: Regresi lintas task + dokumentasi status

**Files:**
- Test: jalankan ulang semua file yang disentuh plan ini + regresi tetangga
  yang disebut gap doc (`NetworkNodeLocationTest`, `NetworkNodePlacementRuleTest`,
  `OltSlotManagementTest`, `NetworkPortAssignmentTest`)
- Modify: `docs/TASKS.md` (tambah baris ADHOC baru, status Fase 0 beres)

**Interfaces:**
- Consumes: semua task sebelumnya.
- Produces: tidak ada (task penutup).

- [ ] **Step 1: Jalankan regresi terarah**

Run: `php artisan test --compact tests/Feature/NetworkNodePlacementRuleTest.php tests/Feature/OltSlotManagementTest.php tests/Feature/NetworkNodeLocationTest.php tests/Feature/NetworkPortAssignmentTest.php tests/Unit/InventoryServiceRegisterInfraTest.php tests/Feature/Network/InfraAssetRegistrationTest.php`
Expected: semua PASS. Kalau `NetworkPortAssignmentTest` menyentuh `olt_card`
anchor langsung (cek dengan `grep -n "olt_card" tests/Feature/NetworkPortAssignmentTest.php`
dulu), sesuaikan fixture-nya ke model chassis→card baru.

- [ ] **Step 2: `vendor/bin/pint --dirty --format agent`**

- [ ] **Step 3: Tambah baris ADHOC di `docs/TASKS.md`**

Tambah baris baru (format sama dengan ADHOC-169/170 yang sudah ada) yang
menyatakan Fase 0 (§6 Q1-Q5 gap doc) selesai diimplementasikan: Mini POP =
lokasi fisik N-chassis, `olt_card` wajib anak `olt_chassis`, chassis boleh
anchor Cabang langsung, tipe `feeder` ditambahkan, `olt_slots` pindah ke
`chassis_node_id`, ledger `REGISTER_INFRA` + halaman registrasi aset lama per
node. Sebut file plan ini dan jumlah test yang lulus.

- [ ] **Step 4: Commit**

```bash
git add docs/TASKS.md
git commit -m "docs: catat penutupan Fase 0 akar infrastruktur"
```
