# Konversi Pack↔PCS Gudang — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Barang QUANTITY berkemasan (Sleave, Clam, Ties, RJ45 — pack=50pcs, bisa beda per item) diterima/ditransfer/diissue-ke-teknisi dalam satuan Pack, dikonversi otomatis ke pcs di ledger; pemakaian teknisi (pemasangan/maintenance) tetap pcs, tak tersentuh.

**Architecture:** `items.pack_size` (nullable, per-item) menandai barang mana yang pakai Pack. 3 Service (`InventoryReceiveService::receiveQuantity`, `InventoryIssueService::issueQuantity`, `InventoryTransferService::dispatchQuantity`/`receiveTransfer`) terima jumlah Pack dari baris form, kalikan `pack_size` jadi `qty` pcs seperti biasa, dan simpan jumlah Pack asli ke kolom baru `inventory_transactions.entered_pack_qty` buat histori. Controller 3 modul itu yang melakukan konversi (form tetap satu field, labelnya berganti "Jumlah Pack" kalau item punya `pack_size`). Tidak ada perubahan ke `inventory_balances`, custody, FIFO, atau alur pemakaian teknisi.

**Tech Stack:** Laravel 13 migration/Eloquent, Blade + Alpine.js (form create sudah Alpine, form confirm transfer server-rendered polos), PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-11-01-pack-pcs-conversion-design.md`

## Global Constraints

- `pack_size` nullable per-item (bukan konstanta global 50) — admin isi di Master Barang.
- `qty` (kolom existing di `inventory_transactions`) SELALU pcs — tidak boleh ada tempat yang menyimpan nilai Pack ke kolom itu.
- `entered_pack_qty` murni audit/tampilan — tidak pernah dibaca ulang buat hitung saldo/custody/FIFO.
- Hanya 3 titik yang dapat UI Pack: RECEIVE, TRANSFER (dispatch + confirm, termasuk partial), ISSUE. `RETURN`/`TRANSFER_CUSTODY`/`ADJUSTMENT`/`STOCK_OPNAME`/pemakaian teknisi TIDAK disentuh.
- Item tanpa `pack_size` (mayoritas barang) — nol perubahan perilaku form/service.

## Review Focus

- Item dengan `pack_size` tapi staf kirim 0/negatif/desimal ganjil sebagai jumlah Pack — harus kena validasi `min:0.01` yang sama seperti qty biasa, bukan lolos diam-diam.
- Partial receive Transfer: confirm sebagian Pack dari dispatch yang lebih besar — `entered_pack_qty` baris confirm harus cocok dengan pecahan yang confirm-qty pcs-nya, bukan ikut jumlah Pack dispatch penuh.
- Item TANPA `pack_size` di 3 form yang sama — pastikan regresi: `qty` tetap manual pcs, `entered_pack_qty` NULL, tidak ada baris kode baru yang memaksa konversi.
- "Terima Semua" (bukan partial) di Transfer confirm untuk item berpack_size — `entered_pack_qty` baris confirm tetap terisi (dihitung dari sisa, bukan dibiarkan NULL) biar histori tetap konsisten.
- Item berkategori SERIALIZED/ROLL yang (keliru) diisi `pack_size` di Master Barang — harus ditolak di validasi Item (prohibited di luar QUANTITY), supaya tidak ada baris ledger SERIALIZED/ROLL yang membawa `entered_pack_qty`.

---

## Task 1: Schema, Model, Master Barang, FormatHelper

**Files:**
- Create: `database/migrations/2026_11_01_000001_add_pack_size_to_items_table.php`
- Create: `database/migrations/2026_11_01_000002_add_entered_pack_qty_to_inventory_transactions_table.php`
- Modify: `app/Models/Item.php` (tambah `pack_size` ke `#[Fillable]` + cast `'pack_size' => 'decimal:2'`)
- Modify: `app/Models/InventoryTransaction.php` (tambah `entered_pack_qty` ke `#[Fillable]` + cast `'entered_pack_qty' => 'decimal:2'`)
- Modify: `app/Http/Controllers/Master/ItemController.php` (rule `pack_size` di `store()`/`update()`, sekitar blok `meter_per_roll` baris ~185-188, ~273)
- Modify: `resources/views/master/items/create.blade.php` (field baru dekat `meter_per_roll`, ~baris 179)
- Modify: `resources/views/master/items/edit.blade.php` (field baru dekat `meter_per_roll`, ~baris 186)
- Modify: `app/Helpers/FormatHelper.php` (method baru `qtyWithPack`)
- Test: `tests/Feature/Master/ItemPackSizeTest.php`
- Test: `tests/Unit/FormatHelperPackQtyTest.php`

**Interfaces:**
- Produces: `Item::$pack_size` (`?float`, decimal:2); `InventoryTransaction::$entered_pack_qty` (`?float`, decimal:2); `FormatHelper::qtyWithPack(InventoryTransaction $line): string`.

- [ ] **Step 1: Write failing feature test for Item master pack_size**

```php
// tests/Feature/Master/ItemPackSizeTest.php
#[Test]
public function pack_size_tersimpan_untuk_item_quantity(): void
{
    $this->loginAsAdmin();
    $category = ItemCategory::factory()->create(['is_operational' => true]);

    $response = $this->post(route('master.items.store'), [
        'code' => 'RJ45-01',
        'name' => 'RJ45 Connector',
        'item_category_id' => $category->id,
        'unit' => 'pcs',
        'tracking_type' => TrackingType::QUANTITY->value,
        'ownership_mode' => OwnershipMode::NOT_APPLICABLE->value,
        'pack_size' => 50,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('items', ['code' => 'RJ45-01', 'pack_size' => 50]);
}

#[Test]
public function pack_size_ditolak_untuk_item_serialized(): void
{
    $this->loginAsAdmin();
    $category = ItemCategory::factory()->create(['is_operational' => true]);

    $response = $this->post(route('master.items.store'), [
        'code' => 'ONT-01',
        'name' => 'ONT',
        'item_category_id' => $category->id,
        'unit' => 'unit',
        'tracking_type' => TrackingType::SERIALIZED->value,
        'ownership_mode' => OwnershipMode::COMPANY->value,
        'pack_size' => 10,
    ]);

    $response->assertSessionHasErrors('pack_size');
}
```

Sesuaikan field wajib lain (lihat rule lengkap `ItemController::store()` sebelum nulis — field di atas adalah dugaan minimal, lengkapi field required yang hilang supaya test ini sendiri lulus validasi dasar sebelum menguji `pack_size`).

- [ ] **Step 2: Run test, verify fails**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Feature/Master/ItemPackSizeTest.php`
Expected: FAIL (`pack_size` unknown column / validation tidak sesuai harapan)

- [ ] **Step 3: Migration `items.pack_size`**

```php
Schema::table('items', function (Blueprint $table) {
    $table->decimal('pack_size', 10, 2)->nullable()->after('meter_per_roll');
});
```

Pola komentar sama seperti `add_meter_per_roll_to_items_table` — jelaskan: nullable, cuma relevan tracking_type QUANTITY, konversi tampil lewat `entered_pack_qty` biar histori gak perlu baca ulang kolom ini kalau admin ubah belakangan.

- [ ] **Step 4: Migration `inventory_transactions.entered_pack_qty`**

```php
Schema::table('inventory_transactions', function (Blueprint $table) {
    $table->decimal('entered_pack_qty', 12, 2)->nullable()->after('qty');
});
```

Komentar: snapshot jumlah Pack yang diketik staf, murni audit — `qty` tetap satu-satunya sumber kebenaran pcs.

- [ ] **Step 5: Tambah `pack_size`/`entered_pack_qty` ke Fillable + casts di `Item`/`InventoryTransaction`**

- [ ] **Step 6: Validasi di `ItemController`**

Di blok yang sama dengan `meter_per_roll` (`store()`/`update()`): kalau `tracking_type === TrackingType::QUANTITY`, `pack_size` = `['nullable', 'numeric', 'min:0.01']`; selain itu `['prohibited']`. Di `update()` pada baris sejenis `$validated['meter_per_roll'] = null;` (~273), tambahkan `$validated['pack_size'] = null;` di cabang yang sama (tracking_type berubah keluar dari QUANTITY).

- [ ] **Step 7: Field form di `create.blade.php`/`edit.blade.php`**

Input number nullable mengikuti markup `meter_per_roll` (step 0.01, min 0.01), label "Jumlah per Pack (pcs)", placeholder "mis. 50", tampil untuk semua tracking_type (karena nullable, server yang menolak kalau salah kombinasi — konsisten dengan pola field opsional lain di form ini, bukan di-hide JS).

- [ ] **Step 8: Run test, verify passes**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Feature/Master/ItemPackSizeTest.php`
Expected: PASS

- [ ] **Step 9: Write failing unit test untuk `FormatHelper::qtyWithPack`**

```php
// tests/Unit/FormatHelperPackQtyTest.php
#[Test]
public function qty_with_pack_shows_pack_and_pcs_when_entered_pack_qty_present(): void
{
    $line = new InventoryTransaction(['qty' => 100, 'entered_pack_qty' => 2]);
    $line->setRelation('item', new Item(['unit' => 'pcs']));

    $this->assertSame('2 Pack (100 pcs)', FormatHelper::qtyWithPack($line));
}

#[Test]
public function qty_with_pack_shows_plain_qty_when_entered_pack_qty_null(): void
{
    $line = new InventoryTransaction(['qty' => 7.5]);
    $line->setRelation('item', new Item(['unit' => 'meter']));

    $this->assertSame('7,5 meter', FormatHelper::qtyWithPack($line));
}
```

Pakai format angka Indonesia (koma desimal) sama seperti `rtrim(rtrim(number_format(...),'0'),',')` yang sudah dipakai di view-view existing — reuse pola trimming itu di dalam helper supaya outputnya konsisten dengan tampilan qty lain di Gudang.

- [ ] **Step 10: Run test, verify fails**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Unit/FormatHelperPackQtyTest.php`
Expected: FAIL (method belum ada)

- [ ] **Step 11: Implement `FormatHelper::qtyWithPack(InventoryTransaction $line): string`**

Body: kalau `$line->entered_pack_qty` tidak null → `"{trim(entered_pack_qty)} Pack ({trim(qty)} pcs)"`; selain itu → `"{trim(qty)} {line->item->unit}"`. Reuse helper trim desimal yang sudah ada di class ini kalau ada, atau buat private static kecil kalau belum.

- [ ] **Step 12: Run test, verify passes**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Unit/FormatHelperPackQtyTest.php`
Expected: PASS

- [ ] **Step 13: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_11_01_000001_add_pack_size_to_items_table.php database/migrations/2026_11_01_000002_add_entered_pack_qty_to_inventory_transactions_table.php app/Models/Item.php app/Models/InventoryTransaction.php app/Http/Controllers/Master/ItemController.php resources/views/master/items/create.blade.php resources/views/master/items/edit.blade.php app/Helpers/FormatHelper.php tests/Feature/Master/ItemPackSizeTest.php tests/Unit/FormatHelperPackQtyTest.php
git commit -m "feat(warehouse): tambah pack_size item dan entered_pack_qty ledger"
```

---

## Task 2: Receive — Pack masuk Pusat

**Files:**
- Modify: `app/Services/InventoryReceiveService.php` (`receiveQuantity()`, `receiveBatch()`)
- Modify: `app/Http/Controllers/Warehouse/WarehouseReceiveController.php` (`normalizeLines()`)
- Modify: `resources/views/warehouse/receive/create.blade.php` (baris 34, label qty ~512)
- Modify: `resources/views/warehouse/receive/show.blade.php` (baris 173 — pakai `FormatHelper::qtyWithPack`)
- Test: `tests/Feature/WarehousePackConversionTest.php` (file baru, dipakai bersama Task 3-5)

**Interfaces:**
- Consumes: `Item::$pack_size`, `InventoryTransaction::$entered_pack_qty`, `FormatHelper::qtyWithPack()` (Task 1).
- Produces: `InventoryReceiveService::receiveQuantity(Pop $pusat, Item $item, float $qty, float $unitPrice, ?User $actor = null, ?string $notes = null, ?string $referenceNumber = null, ?float $enteredPackQty = null): InventoryTransaction` — param baru di akhir, dipakai Task lain kalau perlu.

- [ ] **Step 1: Write failing test — Receive dengan pack_size terkonversi**

```php
// tests/Feature/WarehousePackConversionTest.php
#[Test]
public function receive_barang_berpack_size_dikonversi_ke_pcs(): void
{
    $actor = $this->loginAsAdmin();
    $pusat = Pop::factory()->create(['type' => 'pusat']);
    $item = Item::factory()->create([
        'tracking_type' => TrackingType::QUANTITY,
        'unit' => 'pcs',
        'pack_size' => 50,
    ]);

    $response = $this->post(route('warehouse.receive.store'), [
        'pop_id' => $pusat->id,
        'lines' => [
            ['item_id' => $item->id, 'qty' => 2, 'unit_price' => 1000],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'type' => InventoryTransactionType::RECEIVE->value,
        'qty' => 100,
        'entered_pack_qty' => 2,
    ]);
}

#[Test]
public function receive_barang_tanpa_pack_size_tetap_pcs_langsung(): void
{
    $actor = $this->loginAsAdmin();
    $pusat = Pop::factory()->create(['type' => 'pusat']);
    $item = Item::factory()->create([
        'tracking_type' => TrackingType::QUANTITY,
        'unit' => 'pcs',
        'pack_size' => null,
    ]);

    $response = $this->post(route('warehouse.receive.store'), [
        'pop_id' => $pusat->id,
        'lines' => [
            ['item_id' => $item->id, 'qty' => 73, 'unit_price' => 1000],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'qty' => 73,
        'entered_pack_qty' => null,
    ]);
}
```

Cek dulu field request minimal yang `WarehouseReceiveController::store()` butuhkan (`pop_id` harus POP tipe pusat yang masuk scope actor — pakai `loginAsAdmin()` yang full access) sebelum finalisasi payload test.

- [ ] **Step 2: Run tests, verify first case fails (qty tersimpan 2, bukan 100) dan kedua lulus**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Feature/WarehousePackConversionTest.php`
Expected: test pertama FAIL, kedua PASS (karena belum ada konversi sama sekali)

- [ ] **Step 3: `InventoryReceiveService::receiveQuantity()` — tambah param `?float $enteredPackQty = null`, simpan ke `InventoryTransaction::create([...])`**

Tambahkan `'entered_pack_qty' => $enteredPackQty,` di array `create()` yang sudah ada (~baris 68-76). Tidak ada logic konversi di Service — Service terima `$qty` yang SUDAH pcs (dihitung Controller) dan `$enteredPackQty` cuma utk snapshot.

- [ ] **Step 4: `InventoryReceiveService::receiveBatch()` — pass `$line['entered_pack_qty'] ?? null` ke `receiveQuantity()` di cabang `else` (~baris 351)**

- [ ] **Step 5: `WarehouseReceiveController::normalizeLines()` — konversi Pack→pcs**

Load `pack_size` di query Item (`$items = Item::whereIn('id', $itemIds)->get(['id', 'tracking_type', 'auto_generate_serial', 'pack_size'])->keyBy('id');` — sudah dipakai pola `keyBy('id')` di method ini). Di cabang akhir (QUANTITY, ~baris 206-213):

```php
if ($item?->pack_size) {
    $packQty = (float) ($row['qty'] ?? 0);

    return [
        'item_id' => $itemId,
        'qty' => $packQty * (float) $item->pack_size,
        'entered_pack_qty' => $packQty,
        'unit_price' => $unitPrice,
    ];
}

return [
    'item_id' => $itemId,
    'qty' => (float) ($row['qty'] ?? 0),
    'unit_price' => $unitPrice,
];
```

- [ ] **Step 6: Run tests, verify both pass**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Feature/WarehousePackConversionTest.php`
Expected: PASS (keduanya)

- [ ] **Step 7: Form `create.blade.php` — label Pack**

Baris 34: `'unit' => $item->pack_size ? 'Pack' : $item->unit,` (JSON `itemsJson`/`items` yang di-pass ke Alpine — nama variabel persis ikuti yang ada di file, baca sekitar baris 20-40 dulu). Field qty (label ~512) sudah `x-text="row.unit || 'Unit'"` — otomatis jadi "Jumlah (Pack)" tanpa ubahan lain di situ.

- [ ] **Step 8: `receive/show.blade.php` baris 173 — pakai helper**

Ganti `{{ rtrim(...).' '.$line->item->unit }}` jadi `{{ \App\Helpers\FormatHelper::qtyWithPack($line) }}` (atau `use` di atas file kalau Blade file ini sudah py pola import lain — ikuti konvensi file).

- [ ] **Step 9: Tambah assert tampilan ke test pertama (lanjutan Step 1, edit test yang sudah ada)**

Tambahkan ke `receive_barang_berpack_size_dikonversi_ke_pcs()`:
```php
$transaction = InventoryTransaction::where('item_id', $item->id)->first();
$this->get(route('warehouse.receive.show', $transaction->reference_number))
    ->assertSee('2 Pack (100 pcs)');
```

- [ ] **Step 10: Run full file, verify pass**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Feature/WarehousePackConversionTest.php`
Expected: PASS

- [ ] **Step 11: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/InventoryReceiveService.php app/Http/Controllers/Warehouse/WarehouseReceiveController.php resources/views/warehouse/receive/create.blade.php resources/views/warehouse/receive/show.blade.php tests/Feature/WarehousePackConversionTest.php
git commit -m "feat(warehouse): konversi Pack ke pcs di Receive barang"
```

---

## Task 3: Issue — Pack ke custody Teknisi

**Files:**
- Modify: `app/Services/InventoryIssueService.php` (`issueQuantity()`, `issue()`)
- Modify: `app/Http/Controllers/Warehouse/WarehouseIssueController.php` (`normalizeLines()`)
- Modify: `resources/views/warehouse/issues/create.blade.php` (baris 34)
- Modify: `resources/views/warehouse/issues/show.blade.php` (baris 97)
- Modify: `tests/Feature/WarehousePackConversionTest.php` (tambah test)

**Interfaces:**
- Consumes: Task 1 (`pack_size`, `entered_pack_qty`, `FormatHelper::qtyWithPack`).
- Produces: `InventoryIssueService::issueQuantity(string $referenceNumber, Pop $cabang, User $technician, Item $item, float $qty, ?string $lotNo, User $actor, ?float $enteredPackQty = null): array` — param baru di akhir.

- [ ] **Step 1: Write failing test — Issue dengan pack_size**

```php
#[Test]
public function issue_barang_berpack_size_dikonversi_ke_pcs(): void
{
    $actor = $this->loginAsAdmin();
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $item = Item::factory()->create([
        'tracking_type' => TrackingType::QUANTITY,
        'unit' => 'pcs',
        'pack_size' => 50,
    ]);
    $technician = User::factory()->create();
    // seed saldo cabang dulu lewat InventoryBalance (atau Receive+Transfer kalau helper factory belum ada)
    InventoryBalance::factory()->create(['pop_id' => $cabang->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => 200]);

    $response = $this->post(route('warehouse.issues.store'), [
        'pop_id' => $cabang->id,
        'technician_id' => $technician->id,
        'lines' => [
            ['item_id' => $item->id, 'qty' => 3],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'type' => InventoryTransactionType::ISSUE->value,
        'qty' => 150,
        'entered_pack_qty' => 3,
    ]);
}
```

Cek dulu nama field request yang dipakai `WarehouseIssueController::store()` (`technician_id` nama dugaan — verifikasi di controller sebelum final) dan apakah `InventoryBalance` punya factory; kalau tidak, buat saldo lewat `InventoryBalance::create()` langsung.

- [ ] **Step 2: Run test, verify fails**

Run: `docker compose exec -u www-data app php artisan test --compact tests/Feature/WarehousePackConversionTest.php`
Expected: FAIL

- [ ] **Step 3: `InventoryIssueService::issueQuantity()` — tambah param, simpan ke `create()` (~baris 234-241)**

- [ ] **Step 4: `InventoryIssueService::issue()` — pass `$line['entered_pack_qty'] ?? null` ke `issueQuantity()` di `match` (~baris 67)**

- [ ] **Step 5: `WarehouseIssueController::normalizeLines()` — sama pola Task 2 Step 5**

Ganti `$trackingTypes = Item::whereIn(...)->pluck('tracking_type', 'id')` jadi `$items = Item::whereIn(...)->get(['id', 'tracking_type', 'pack_size'])->keyBy('id')`, lalu `$trackingType = $items[$itemId]?->tracking_type`. Cabang akhir (~baris 262-267) dapat if `pack_size` sama seperti Task 2.

- [ ] **Step 6: Run test, verify passes**

- [ ] **Step 7: `issues/create.blade.php` baris 34 — label Pack (sama Task 2 Step 7)**

- [ ] **Step 8: `issues/show.blade.php` baris 97 — pakai `FormatHelper::qtyWithPack`**

- [ ] **Step 9: Tambah assert tampilan ke test, run, verify pass**

- [ ] **Step 10: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/InventoryIssueService.php app/Http/Controllers/Warehouse/WarehouseIssueController.php resources/views/warehouse/issues/create.blade.php resources/views/warehouse/issues/show.blade.php tests/Feature/WarehousePackConversionTest.php
git commit -m "feat(warehouse): konversi Pack ke pcs di Issue ke teknisi"
```

---

## Task 4: Transfer Dispatch — Pack Pusat→Cabang

**Files:**
- Modify: `app/Services/InventoryTransferService.php` (`dispatchQuantity()`, `createTransfer()`)
- Modify: `app/Http/Controllers/Warehouse/WarehouseTransferController.php` (`normalizeLines()` dispatch, ~baris 571)
- Modify: `resources/views/warehouse/transfers/create.blade.php` (baris 34)
- Modify: `resources/views/warehouse/transfers/show.blade.php` (baris 113, 208, 271 — baris dispatch, bukan baris confirm di 118/125 yang ditangani Task 5)
- Modify: `tests/Feature/WarehousePackConversionTest.php`

**Interfaces:**
- Consumes: Task 1.
- Produces: `InventoryTransferService::dispatchQuantity(InventoryTransfer $transfer, Pop $fromPusat, Item $item, float $qty, ?string $lotNo, ?User $actor, bool $marketing = false, ?float $enteredPackQty = null): void` — param baru di akhir.

- [ ] **Step 1: Write failing test — dispatch Transfer dengan pack_size**

```php
#[Test]
public function dispatch_transfer_barang_berpack_size_dikonversi_ke_pcs(): void
{
    $actor = $this->loginAsAdmin();
    $pusat = Pop::factory()->create(['type' => 'pusat']);
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $item = Item::factory()->create([
        'tracking_type' => TrackingType::QUANTITY,
        'unit' => 'pcs',
        'pack_size' => 50,
    ]);
    InventoryBalance::create(['pop_id' => $pusat->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => 500]);

    $response = $this->post(route('warehouse.transfers.store'), [
        'from_pop_id' => $pusat->id,
        'to_pop_id' => $cabang->id,
        'lines' => [
            ['item_id' => $item->id, 'qty' => 4],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'type' => InventoryTransactionType::TRANSFER->value,
        'qty' => 200,
        'entered_pack_qty' => 4,
        'from_pop_id' => $pusat->id,
    ]);
}
```

Verifikasi nama field `from_pop_id`/`to_pop_id` persis dengan yang divalidasi `WarehouseTransferController::store()` sebelum final.

- [ ] **Step 2: Run test, verify fails**

- [ ] **Step 3: `InventoryTransferService::dispatchQuantity()` — tambah param, simpan ke `create()` (~baris 250-260)**

- [ ] **Step 4: `InventoryTransferService::createTransfer()` — pass `$line['entered_pack_qty'] ?? null` di `match`/`default` (~baris 128)**

- [ ] **Step 5: `WarehouseTransferController::normalizeLines()` (dispatch, ~baris 571) — sama pola Task 2 Step 5**

- [ ] **Step 6: Run test, verify passes**

- [ ] **Step 7: `transfers/create.blade.php` baris 34 — label Pack**

- [ ] **Step 8: `transfers/show.blade.php` baris 113, 208, 271 — pakai `FormatHelper::qtyWithPack` (baris dispatch; baris 118/125 di area confirm TIDAK disentuh di sini, itu Task 5)**

- [ ] **Step 9: Tambah assert tampilan ke test, run, verify pass**

- [ ] **Step 10: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/InventoryTransferService.php app/Http/Controllers/Warehouse/WarehouseTransferController.php resources/views/warehouse/transfers/create.blade.php resources/views/warehouse/transfers/show.blade.php tests/Feature/WarehousePackConversionTest.php
git commit -m "feat(warehouse): konversi Pack ke pcs di dispatch Transfer"
```

---

## Task 5: Transfer Confirm — Pack diterima Cabang (termasuk partial)

**Files:**
- Modify: `app/Services/InventoryTransferService.php` (`receiveTransfer()`)
- Modify: `app/Http/Controllers/Warehouse/WarehouseTransferController.php` (`receive()`, ~baris 312)
- Modify: `resources/views/warehouse/transfers/show.blade.php` (baris 118, 125 — form & label sisa confirm)
- Modify: `tests/Feature/WarehousePackConversionTest.php`

**Interfaces:**
- Consumes: Task 4 (butuh transfer sudah dispatch).
- Produces: `InventoryTransferService::receiveTransfer(InventoryTransfer $transfer, array $confirmedSerialNumbers, array $confirmedQuantities, User $actor, array $confirmedRollCodes = [], array $confirmedPackQuantities = []): InventoryTransfer` — param baru `$confirmedPackQuantities`, struktur kunci SAMA dengan `$confirmedQuantities` (`['_lines' => [lineId => packCount]]`).

- [ ] **Step 1: Write failing test — confirm partial dengan pack_size**

```php
#[Test]
public function confirm_partial_transfer_barang_berpack_size(): void
{
    $actor = $this->loginAsAdmin();
    $pusat = Pop::factory()->create(['type' => 'pusat']);
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $item = Item::factory()->create([
        'tracking_type' => TrackingType::QUANTITY,
        'unit' => 'pcs',
        'pack_size' => 50,
    ]);
    InventoryBalance::create(['pop_id' => $pusat->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => 500]);

    $this->post(route('warehouse.transfers.store'), [
        'from_pop_id' => $pusat->id,
        'to_pop_id' => $cabang->id,
        'lines' => [['item_id' => $item->id, 'qty' => 4]], // 4 Pack = 200 pcs
    ]);
    $dispatchLine = InventoryTransaction::where('item_id', $item->id)->whereNotNull('from_pop_id')->first();
    $transfer = $dispatchLine->inventoryTransfer;

    $response = $this->post(route('warehouse.transfers.receive', $transfer), [
        'receive_mode' => 'partial',
        'confirmed_quantities' => [$dispatchLine->id => 2], // staf isi 2 Pack
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'to_pop_id' => $cabang->id,
        'qty' => 100,
        'entered_pack_qty' => 2,
    ]);
}

#[Test]
public function confirm_semua_transfer_barang_berpack_size_hitung_entered_pack_qty_dari_sisa(): void
{
    $actor = $this->loginAsAdmin();
    $pusat = Pop::factory()->create(['type' => 'pusat']);
    $cabang = Pop::factory()->create(['type' => 'cabang']);
    $item = Item::factory()->create([
        'tracking_type' => TrackingType::QUANTITY,
        'unit' => 'pcs',
        'pack_size' => 50,
    ]);
    InventoryBalance::create(['pop_id' => $pusat->id, 'item_id' => $item->id, 'lot_no' => '', 'qty' => 500]);

    $this->post(route('warehouse.transfers.store'), [
        'from_pop_id' => $pusat->id,
        'to_pop_id' => $cabang->id,
        'lines' => [['item_id' => $item->id, 'qty' => 3]], // 3 Pack = 150 pcs
    ]);
    $dispatchLine = InventoryTransaction::where('item_id', $item->id)->whereNotNull('from_pop_id')->first();
    $transfer = $dispatchLine->inventoryTransfer;

    $response = $this->post(route('warehouse.transfers.receive', $transfer), [
        'receive_mode' => 'all',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('inventory_transactions', [
        'item_id' => $item->id,
        'to_pop_id' => $cabang->id,
        'qty' => 150,
        'entered_pack_qty' => 3,
    ]);
}
```

Verifikasi dulu nama route `warehouse.transfers.receive` dan relasi `InventoryTransaction::inventoryTransfer()` (kemungkinan nama relasi `inventoryTransfer` atau `transfer` — cek model sebelum final).

- [ ] **Step 2: Run tests, verify fail**

- [ ] **Step 3: `InventoryTransferService::receiveTransfer()` — tambah param `array $confirmedPackQuantities = []`**

Di cabang QUANTITY (~baris 416-449): hitung `$enteredPackQty`:

```php
$confirmedPackForItem = $confirmedPackQuantities['_lines'][$line->id] ?? null;
$enteredPackQty = $confirmedPackForItem !== null
    ? (float) $confirmedPackForItem
    : ($item->pack_size ? round($confirmedQty / $item->pack_size, 2) : null);
```

(`$item` perlu di-resolve dari `$line->item_id` — cek apakah `$line` di loop ini sudah py relasi `item` dimuat; kalau belum, tambahkan `with('item')` di query `$dispatchLines` di atas method, atau `Item::find($line->item_id)` sekali per baris.) Tambahkan `'entered_pack_qty' => $enteredPackQty,` ke `InventoryTransaction::create([...])` QUANTITY (~baris 438-447). **Jangan** ubah cabang SERIALIZED/ROLL di atasnya — itu di luar scope (bukan QUANTITY).

- [ ] **Step 4: `WarehouseTransferController::receive()` — bangun `$confirmedPackQuantities` & konversi Pack→pcs sebelum masuk `$confirmedQuantities`**

Load item pack_size untuk semua `$dispatchLines` (`$items = Item::whereIn('id', $dispatchLines->pluck('item_id'))->get(['id','pack_size'])->keyBy('id')`). Mode `partial`: untuk setiap entry `confirmed_quantities[$lineId]`, cari `$dispatchLines->firstWhere('id', $lineId)->item_id`, kalau item itu punya `pack_size`, nilai request dianggap PACK → `confirmedPackQuantities['_lines'][$lineId] = $packValue; confirmedQuantities['_lines'][$lineId] = $packValue * $item->pack_size;`. Mode `all` (default, ~baris 334-349): kalau item berpack_size, isi juga `confirmedPackQuantities['_lines'][$line->id] = round($remaining / $item->pack_size, 2);` (qty-nya `$remaining` tetap seperti sekarang, service yang hitung ulang di Step 3 kalau controller tidak isi — tapi lebih eksplisit diisi di sini biar konsisten). Pass `$confirmedPackQuantities` sebagai argumen ke-5 (setelah `$confirmedRollCodes`) di panggilan `$service->receiveTransfer(...)` (~baris 358-364).

- [ ] **Step 5: Run tests, verify pass**

- [ ] **Step 6: `transfers/show.blade.php` baris 118 — label sisa**

Ganti bagian `$line->item->unit` di baris 118 jadi kondisional: kalau `$line->item->pack_size`, tampilkan `"Sisa " . round($remainingByLine[$line->id] / $line->item->pack_size, 2) . " Pack"`; selain itu seperti sekarang.

- [ ] **Step 7: `transfers/show.blade.php` baris 125 — input confirm**

Ubah `max` jadi `{{ $line->item->pack_size ? round($remainingByLine[$line->id] / $line->item->pack_size, 2) : (float) $remainingByLine[$line->id] }}` dan tambahkan placeholder/label kecil "Pack" di sebelah input kalau `$line->item->pack_size` terisi (teks statis, tidak perlu Alpine — form ini server-rendered polos).

- [ ] **Step 8: Tambah assert tampilan ke test pertama, run, verify pass**

```php
$this->get(route('warehouse.transfers.show', $transfer))->assertSee('2 Pack (100 pcs)');
```

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/InventoryTransferService.php app/Http/Controllers/Warehouse/WarehouseTransferController.php resources/views/warehouse/transfers/show.blade.php tests/Feature/WarehousePackConversionTest.php
git commit -m "feat(warehouse): konversi Pack ke pcs di confirm Transfer (full & partial)"
```

---

## Task 6: Riwayat/History — Tampilan Pack

**Files:**
- Modify: `resources/views/warehouse/history/index.blade.php` (baris 617, 750 — dan baris 473 kalau format sama)
- Test: `tests/Feature/WarehousePackConversionTest.php` (tambah 1 test)

**Interfaces:**
- Consumes: Task 1 (`FormatHelper::qtyWithPack`), Task 2 (data RECEIVE sudah ada dari test sebelumnya bisa dipakai ulang).

- [ ] **Step 1: Write failing test — Riwayat menampilkan Pack**

```php
#[Test]
public function riwayat_menampilkan_jumlah_pack_untuk_barang_berpack_size(): void
{
    $actor = $this->loginAsAdmin();
    $pusat = Pop::factory()->create(['type' => 'pusat']);
    $item = Item::factory()->create([
        'tracking_type' => TrackingType::QUANTITY,
        'unit' => 'pcs',
        'pack_size' => 50,
        'name' => 'RJ45 Connector',
    ]);

    $this->post(route('warehouse.receive.store'), [
        'pop_id' => $pusat->id,
        'lines' => [['item_id' => $item->id, 'qty' => 2, 'unit_price' => 1000]],
    ]);

    $this->get(route('warehouse.history.index'))->assertSee('2 Pack (100 pcs)');
}
```

Cek dulu nama route histori (`warehouse.history.index` dugaan — verifikasi di `routes/web.php`) dan apakah halaman ini perlu filter/tanggal tertentu buat baris barusan muncul (default filter "hari ini" biasanya sudah mencakup).

- [ ] **Step 2: Run test, verify fails**

- [ ] **Step 3: `history/index.blade.php` baris 617 dan 750 — ganti ke `FormatHelper::qtyWithPack($txn)`**

Baris 473 (`'unit' => ...`) itu buat badge grup, bukan angka qty — baca konteksnya dulu; kalau memang cuma label satuan untuk ringkasan grup (bukan baris individual), BUKAN scope task ini (biarkan, karena grup gabungan lintas-lot tidak punya satu `entered_pack_qty` yang valid untuk ditampilkan).

- [ ] **Step 4: Run test, verify passes**

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/warehouse/history/index.blade.php tests/Feature/WarehousePackConversionTest.php
git commit -m "feat(warehouse): tampilkan jumlah Pack di Riwayat Gudang"
```

---

## Final: Regresi Terarah

- [ ] Jalankan seluruh file yang disentuh dalam satu run:

Run: `docker compose exec -u www-data app php artisan test --compact tests/Feature/WarehousePackConversionTest.php tests/Feature/Master/ItemPackSizeTest.php tests/Unit/FormatHelperPackQtyTest.php tests/Feature/WarehouseReceiveTest.php tests/Feature/WarehouseTransferAndIssueTest.php tests/Feature/WarehouseTransferReceiveSimplifiedTest.php tests/Feature/InventoryIssueRollTest.php tests/Feature/InventoryTransferRollTest.php`
Expected: semua PASS — file existing di daftar itu memastikan item TANPA `pack_size` (mayoritas test lama) tidak terdampak.

- [ ] Update `docs/TASKS.md` — tambah baris ADHOC baru (status DONE) sesuai pola entri lain, cantumkan ringkasan + jumlah test lulus.
