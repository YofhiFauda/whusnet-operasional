# Breakdown Per-Item Verifikasi Biaya + Billing MTN Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verifikasi Biaya (CS) isi breakdown N-baris (nama+nominal) bukan 1 field `amount` polos, dipakai bareng buat C-REQ & MTN — MTN polos sekarang juga bisa ditandai berbayar dan masuk antrean yang sama.

**Architecture:** Tabel baru `task_maintenance_billing_details` (mirror subset generik `task_creq_details`, khusus MTN) + `task_billing_items` (baris breakdown, SATU-SATUNYA record rinci biaya — `Invoice` tetap flat). `TaskCreqBillingController` (nama TIDAK diubah) generalized: `authorizeBillable()`/`approve()`/`reject()`/`index()`/`show()` branch tipis by `task_type` baca dari relasi yang sesuai (`creqDetail` vs `maintenanceBillingDetail`), tapi `ManualCategoryInvoiceService::issue()` dan transaction flow tetap SATU jalur. Breakdown modem (Task 6 plan ownership-modem) DIGANTI total oleh baris auto-seed di mekanisme items[] baru.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, Blade+Alpine, sqlite test.

**Spec:** `docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md`

## Global Constraints

- Skema `Invoice` TIDAK diubah — tetap flat `amount`+`description`, breakdown cuma di `task_billing_items`.
- `task_billing_items`: FK `task_id`/`invoice_id`/`inventory_serial_sale_id` → `restrictOnDelete()` (tabel finansial). `invoice_id` NOT NULL (ditulis setelah invoice terbit, transaksi sama).
- `task_maintenance_billing_details`: FK `task_id` → `cascade` (unique), `verified_by`/`invoice_id` → `nullOnDelete()` — mirror persis `task_creq_details`.
- `verification_status` di tabel baru reuse cast `App\Enums\CReqVerificationStatus` — JANGAN bikin enum baru atau rename class ini.
- Nama controller/route/permission (`creq_billing_verification`, `tasks.creq-billing.*`, `TaskCreqBillingController`) TIDAK di-rename.
- Kategori invoice MTN berbayar = HARDCODE `ManualInvoiceCategory::PERBAIKAN` — bukan pilihan CS, bukan enum baru.
- Minimal 1 baris breakdown wajib (`items` array min:1) kalau task ditandai berbayar.
- `items.*.amount` → `min:0.01` — TIDAK ada baris Rp0 (beda dari Task 6 lama yang `service_fee` boleh 0; desain baru gak punya baris "jasa gratis", kalau gratis ya gak usah ditambah baris itu).
- Baris modem (kalau `InventorySerialSale` ada) WAJIB tetap ada di breakdown yang disubmit — CS gak boleh menghapusnya kalau modemnya beneran terjual.
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum tiap commit yang menyentuh PHP.
- `php artisan test --compact <file>` per task — JANGAN jalankan full suite (aturan proyek).

## Review Focus

- **MTN ditandai berbayar TAPI catatan biaya kosong** — `mtn_billing_note` wajib begitu `mtn_is_billable` dicentang, sama persis pola `creq_billing_note` sekarang (`required_if`). Lupa pasang guard ini = CS dapat task masuk antrean tanpa konteks kerjaan apa yang ditagih.
- **CS hapus/kosongkan baris modem padahal `InventorySerialSale` ada** — breakdown harus tetap merepresentasikan transaksi nyata; submit tanpa baris modem (atau baris modem amount 0) pada task yang punya sale row WAJIB ditolak, bukan lolos diam-diam jadi invoice yang gak nyebut modemnya.
- **Approve/reject dua kali bersamaan (TOCTOU) untuk task MTN** — pola `lockForUpdate()` + cek `verification_status===PENDING` DI DALAM transaction yang sudah ada buat C-REQ wajib dipasang IDENTIK buat cabang MTN, bukan cuma di-copy sebagian.
- **`index()`/`show()` diakses untuk task MTN oleh user di luar POP scope task itu** — `authorizeBillable()` yang di-generalize wajib tetap menolak (403) persis kayak cabang C-REQ, POP scope check-nya jangan cuma nempel di cabang `if ($task->task_type === CREQ)`.
- **Task C-REQ existing (udah `VERIFIED` sebelum migrasi ini) dibuka lagi di `show()`** — `$detail`/`$manualCategory` branching baru (baca `creqDetail` vs `maintenanceBillingDetail`) wajib tetap jalan buat baris C-REQ lama yang `task_billing_items`-nya kosong (data lama, no backfill) — halaman gak boleh error/kosong cuma karena belum pernah punya breakdown items.

---

## Task 1: Migrasi, model, relasi — `task_maintenance_billing_details` & `task_billing_items`

**Files:**
- Create: `database/migrations/2026_10_10_090000_create_task_maintenance_billing_details_table.php`
- Create: `database/migrations/2026_10_10_090100_create_task_billing_items_table.php`
- Create: `app/Models/TaskMaintenanceBillingDetail.php`
- Create: `app/Models/TaskBillingItem.php`
- Modify: `app/Models/Task.php` (tambah relasi, dekat `creqDetail()` baris 127)
- Test: `tests/Unit/TaskBillingItemAndMaintenanceBillingDetailTest.php`

**Interfaces:**
- Produces: `TaskMaintenanceBillingDetail` model — fillable `['task_id', 'is_billable', 'billing_note', 'verification_status', 'verified_by', 'verified_at', 'rejection_reason', 'invoice_id']`, casts `verification_status` → `CReqVerificationStatus::class`, `is_billable` → `boolean`, `verified_at` → `datetime`. Relations: `task(): BelongsTo`, `verifier(): BelongsTo` (`User::class`, `verified_by`), `invoice(): BelongsTo`.
- Produces: `TaskBillingItem` model — fillable `['task_id', 'invoice_id', 'name', 'amount', 'inventory_serial_sale_id', 'created_by']`, casts `amount` → `decimal:2`. Relations: `task(): BelongsTo`, `invoice(): BelongsTo`, `inventorySerialSale(): BelongsTo`, `createdBy(): BelongsTo` (`User::class`, `created_by`).
- Produces: `Task::maintenanceBillingDetail(): HasOne`, `Task::billingItems(): HasMany`.

- [ ] **Step 1: Write failing test — models + relations wiring**

```php
#[Test]
public function task_maintenance_billing_detail_fillable_dan_cast_sesuai_spec(): void
{
    $task = Task::factory()->create(['task_type' => TaskType::MAINTENANCE]);
    $detail = TaskMaintenanceBillingDetail::create([
        'task_id' => $task->id, 'is_billable' => true, 'billing_note' => 'Ganti patchcord ekstra',
    ]);

    $this->assertSame(CReqVerificationStatus::PENDING, $detail->refresh()->verification_status);
    $this->assertTrue($task->maintenanceBillingDetail->is($detail));
}

#[Test]
public function task_billing_item_fillable_dan_cast_sesuai_spec(): void
{
    // Arrange: $task, $invoice (ManualInvoiceCategory::PERBAIKAN biasa), $actor
    $item = TaskBillingItem::create([
        'task_id' => $task->id, 'invoice_id' => $invoice->id, 'name' => 'Biaya Jasa',
        'amount' => 50000, 'created_by' => $actor->id,
    ]);

    $this->assertSame('50000.00', (string) $item->refresh()->amount);
    $this->assertTrue($task->billingItems->first()->is($item));
}
```

- [ ] **Step 2: Run test, verify fails** (class not found)

Run: `php artisan test --compact tests/Unit/TaskBillingItemAndMaintenanceBillingDetailTest.php`

- [ ] **Step 3: Write migrations.** `task_maintenance_billing_details` — exact kolom per §3.1 spec. `task_billing_items` — exact kolom per §3.2 spec (FK `restrictOnDelete()`, `invoice_id` NOT NULL).

- [ ] **Step 4: Implement models + `Task` relations** per Interfaces block. Letakkan `maintenanceBillingDetail()`/`billingItems()` di `Task.php` tepat setelah `creqDetail()` (baris ~127), pola identik method itu.

- [ ] **Step 5: Run migrations in test DB + run test, verify passes**

Run: `php artisan test --compact tests/Unit/TaskBillingItemAndMaintenanceBillingDetailTest.php`
Expected: PASS

- [ ] **Step 6: Pint + commit**

```bash
git add database/migrations/2026_10_10_09* app/Models/TaskMaintenanceBillingDetail.php app/Models/TaskBillingItem.php app/Models/Task.php tests/Unit/TaskBillingItemAndMaintenanceBillingDetailTest.php
git commit -m "feat: tabel task_maintenance_billing_details & task_billing_items"
```

---

## Task 2: Checkbox "Task ini berbayar" di Laporan MTN polos

**Files:**
- Modify: `app/Http/Controllers/TaskMaintenanceController.php` (`store()` — rules dekat baris 265-280, eksekusi dekat baris 442)
- Modify: `resources/views/tasks/maintenance-report.blade.php` (blok baru setelah `@endif` baris 187, JS setelah `@endif` baris 618)
- Test: `tests/Feature/TaskMaintenanceBillingMtnTest.php`

**Interfaces:**
- Consumes: `TaskMaintenanceBillingDetail` (Task 1).
- Produces: request field `mtn_is_billable` (bool), `mtn_billing_note` (string, required kalau `mtn_is_billable` true) — field BARU, beda nama dari `creq_is_billable`/`creq_billing_note` yang TIDAK diubah.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function mtn_ditandai_berbayar_wajib_isi_catatan(): void
{
    // POST store() task MTN (bukan C-REQ), mtn_is_billable=1, mtn_billing_note KOSONG
    $response->assertSessionHasErrors('mtn_billing_note');
}

#[Test]
public function mtn_berbayar_menulis_task_maintenance_billing_details_pending(): void
{
    // POST store() task MTN, mtn_is_billable=1, mtn_billing_note='Ganti konektor rusak'
    $this->assertDatabaseHas('task_maintenance_billing_details', [
        'task_id' => $task->id, 'is_billable' => true, 'verification_status' => 'pending',
    ]);
}

#[Test]
public function mtn_tidak_dicentang_berbayar_tidak_menulis_detail(): void
{
    // POST store() task MTN biasa, mtn_is_billable TIDAK dikirim
    $this->assertDatabaseCount('task_maintenance_billing_details', 0);
}

#[Test]
public function task_creq_tidak_terpengaruh_field_mtn(): void
{
    // regresi: POST store() task C-REQ seperti biasa (creq_is_billable) — TaskMaintenanceBillingDetail TIDAK dibuat, TaskCreqDetail tetap seperti existing test
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskMaintenanceBillingMtnTest.php`

- [ ] **Step 3: Implement validasi.** Setelah blok `if ($isCreq) { $rules['creq_is_billable']=...; ... }` (sekitar baris 308-320 existing), tambah:
  ```php
  if (! $isCreq) {
      $rules['mtn_is_billable'] = ['nullable', 'boolean'];
      $rules['mtn_billing_note'] = ['nullable', 'string', 'max:1000', Rule::requiredIf($request->boolean('mtn_is_billable'))];
      $messages['mtn_billing_note.required'] = 'Catatan biaya wajib diisi kalau task ini ditandai berbayar.';
  }
  ```

- [ ] **Step 4: Implement eksekusi.** Tepat setelah `if ($isCreq) { TaskCreqDetail::updateOrCreate(...); }` tutup (baris ~442), tambah:
  ```php
  if (! $isCreq) {
      TaskMaintenanceBillingDetail::updateOrCreate(['task_id' => $task->id], [
          'is_billable' => $request->boolean('mtn_is_billable'),
          'billing_note' => $validated['mtn_billing_note'] ?? null,
          'verification_status' => CReqVerificationStatus::PENDING->value,
          'verified_by' => null, 'verified_at' => null, 'rejection_reason' => null,
      ]);
  }
  ```
  (`updateOrCreate` — sama alasan `TaskCreqDetail`: laporan MTN bisa dikirim ulang.)

- [ ] **Step 5: Implement UI blade.** Setelah `@endif` baris 187 (tutup blok `@if($task->task_type === \App\Enums\TaskType::CREQ)`), tambah blok baru:
  ```blade
  @if($task->task_type !== \App\Enums\TaskType::CREQ)
  <div class="px-6 py-4" style="border-bottom:1px solid var(--color-border)">
      {{-- checkbox mtn_is_billable + textarea mtn_billing_note, markup identik blok "Task Berbayar" C-REQ baris 170-185, id/name diganti mtn_*, onchange="mtnToggleBillable()" --}}
  </div>
  @endif
  ```
  JS: setelah `@endif` baris 618 (tutup blok script C-REQ), tambah `function mtnToggleBillable() {...}` (identik `creqToggleBillable()` baris 604-612, id diganti `mtn_*`) + panggil di `DOMContentLoaded` (boleh listener baru, jangan taruh di dalam blok `@if(CREQ)` yang sudah ada).

- [ ] **Step 6: Run tests, verify pass + regresi**

Run: `php artisan test --compact tests/Feature/TaskMaintenanceBillingMtnTest.php tests/Feature/TaskMaintenanceModemInstallTest.php`
Expected: PASS semua

- [ ] **Step 7: Pint + commit**

```bash
git add app/Http/Controllers/TaskMaintenanceController.php resources/views/tasks/maintenance-report.blade.php tests/Feature/TaskMaintenanceBillingMtnTest.php
git commit -m "feat: checkbox Task Berbayar untuk laporan MTN polos"
```

---

## Task 3: Generalize `authorizeBillable()`/`approve()`/`reject()` — breakdown items[] + sumber MTN

**Files:**
- Modify: `app/Http/Controllers/TaskCreqBillingController.php` (`authorizeBillable()` ~291-308, `approve()` ~114-247, `reject()` ~249-287)
- Test: `tests/Feature/TaskBillingItemsBreakdownTest.php`

**Interfaces:**
- Consumes: `TaskMaintenanceBillingDetail`, `TaskBillingItem` (Task 1).
- Produces: request field `items` (array, min 1), `items.*.name` (string, required, max 150), `items.*.amount` (numeric, required, min:0.01), `items.*.inventory_serial_sale_id` (nullable, integer — dikirim balik dari form buat baris modem yang auto-seed, dicek server-side terhadap `InventorySerialSale` yang resolve dari task ini, BUKAN dipercaya mentah dari client).
- Removes: field `modem_price`/`service_fee`/`amount`/`description` dari `approve()` — TIDAK ada lagi.

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function approve_creq_tanpa_sale_row_breakdown_n_baris_jadi_satu_invoice(): void
{
    // task C-REQ berbayar, TANPA InventorySerialSale
    // POST approve() items=[['name'=>'Kabel Ekstra','amount'=>50000],['name'=>'Jasa','amount'=>30000]]
    // assert Invoice::total_amount===80000, description berisi 'Kabel Ekstra: Rp50.000' dan 'Jasa: Rp30.000'
    // assert task_billing_items 2 baris, masing2 invoice_id terisi, inventory_serial_sale_id null
}

#[Test]
public function approve_dengan_sale_row_wajib_salah_satu_baris_mengarah_ke_sale_itu(): void
{
    // task punya InventorySerialSale price=150000
    // POST approve() items TANPA baris inventory_serial_sale_id yang cocok → ditolak (422/validation error)
}

#[Test]
public function approve_dengan_sale_row_baris_modem_valid_tulis_balik_price(): void
{
    // POST approve() items=[['name'=>'Jual Modem','amount'=>140000,'inventory_serial_sale_id'=>$sale->id],['name'=>'Jasa','amount'=>30000]]
    // assert InventorySerialSale::price jadi 140000, Invoice total 170000
}

#[Test]
public function approve_mtn_berbayar_terbit_invoice_kategori_perbaikan(): void
{
    // task MTN (task_type MAINTENANCE) dengan TaskMaintenanceBillingDetail is_billable=true pending
    // POST approve() items=[['name'=>'Ganti Konektor','amount'=>25000]]
    // assert Invoice::manual_category===ManualInvoiceCategory::PERBAIKAN
    // assert task_maintenance_billing_details verification_status=verified, invoice_id terisi
}

#[Test]
public function approve_tanpa_items_ditolak(): void
{
    // POST approve() items=[] → assertSessionHasErrors('items')
}

#[Test]
public function approve_task_mtn_bukan_berbayar_ditolak_404(): void
{
    // task MTN tanpa TaskMaintenanceBillingDetail / is_billable=false → authorizeBillable() abort 404
}

#[Test]
public function approve_mtn_luar_pop_scope_ditolak_403(): void
{
    // regresi POP scope — sama pola cabang C-REQ existing, tapi task_type MTN
}

#[Test]
public function reject_mtn_berbayar_menulis_rejection_reason(): void
{
    // POST reject() task MTN berbayar pending → task_maintenance_billing_details verification_status=rejected
}

#[Test]
public function approve_mtn_dua_kali_ditolak_kedua_kalinya(): void
{
    // task MTN berbayar pending — POST approve() pertama sukses (verification_status jadi verified)
    // POST approve() KEDUA (items berbeda/sama) → 422 "sudah diproses", task_billing_items TIDAK bertambah
    // dari approve kedua, invoice TIDAK terbit dua kali — membuktikan lockForUpdate()+cek PENDING
    // yang sudah ada buat cabang C-REQ berlaku IDENTIK buat cabang MTN, bukan cuma di-copy sebagian
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskBillingItemsBreakdownTest.php`

- [ ] **Step 3: Implement `authorizeBillable()`.** Ganti kondisi abort dari `$task->task_type === TaskType::CREQ && $task->creqDetail?->is_billable` jadi cek dua sumber:
  ```php
  $task->loadMissing(['creqDetail', 'maintenanceBillingDetail']);
  $billable = ($task->task_type === TaskType::CREQ && $task->creqDetail?->is_billable)
      || ($task->task_type === TaskType::MAINTENANCE && $task->maintenanceBillingDetail?->is_billable);
  abort_unless($billable, 404, 'Task ini bukan task berbayar yang menunggu verifikasi.');
  ```
  POP scope check di bawahnya TIDAK berubah (sudah generik, gak nyentuh `creqDetail`).

- [ ] **Step 4: Implement `approve()`.** Hapus total blok `if ($sale) {...} else {...}` lama (validasi `modem_price`/`service_fee`/`amount`/`description`). Ganti:
  1. Resolve `$sale` SAMA cara existing (`TaskWorkToolService::resolveTaskFor()` → `InventorySerialSale`).
  2. `$manualCategory = $task->task_type === TaskType::CREQ ? $task->creqDetail->category->toManualInvoiceCategory() : ManualInvoiceCategory::PERBAIKAN;`
  3. Validasi: `'items' => 'required|array|min:1', 'items.*.name' => 'required|string|max:150', 'items.*.amount' => 'required|numeric|min:0.01|max:99999999.99', 'items.*.inventory_serial_sale_id' => 'nullable|integer', 'manual_subtype_name' => [Rule::requiredIf($manualCategory->requiresSubtypeName()), 'nullable', 'string', 'max:150']` — ingat `RupiahInput::parseKeys()` buat tiap `items.*.amount` sebelum validate (pola sama existing, field array butuh di-walk).
  4. Kalau `$sale` ada: cek SALAH SATU baris submitted punya `inventory_serial_sale_id === $sale->id` dengan `amount > 0` — kalau tidak ada, `ValidationException::withMessages(['items' => 'Baris Jual Modem wajib tetap ada — task ini punya penjualan modem yang belum ditagih.'])`.
  5. `$amount = SUM(items.*.amount)`, `$description = implode(' + ', array_map(fn ($i) => "{$i['name']}: Rp" . FormatHelper::rupiahInput($i['amount']), $items))`.
  6. Resolve detail model yang sesuai (`$task->creqDetail` atau `$task->maintenanceBillingDetail`) buat `lockForUpdate()`/cek PENDING/update VERIFIED — branch tipis by `$task->task_type`, pola `lockForUpdate()` + transaction TIDAK berubah strukturnya.
  7. Di dalam transaction, SETELAH invoice terbit: kalau `$sale` ada, `$sale->update(['price' => <amount baris yang inventory_serial_sale_id cocok>])`. Lalu `foreach ($items as $item) { TaskBillingItem::create([...'invoice_id' => $invoice->id, 'created_by' => auth()->id()]); }`.

- [ ] **Step 5: Implement `reject()`.** Resolve detail model yang sesuai (sama pola Step 4.6), sisanya struktur TIDAK berubah.

- [ ] **Step 6: Run tests, verify pass + regresi**

Run: `php artisan test --compact tests/Feature/TaskBillingItemsBreakdownTest.php tests/Feature/TaskCreqBillingReportTest.php`
Expected: PASS semua (test `TaskCreqBillingModemSaleBreakdownTest` DIHAPUS di task ini — digantikan test baru di file ini, lihat Step 7)

- [ ] **Step 7: Hapus test lama yang sudah digantikan.** `tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php` menguji mekanisme `modem_price`/`service_fee` yang sudah tidak ada — hapus file ini (test barunya sudah di `TaskBillingItemsBreakdownTest.php` Step 1).

- [ ] **Step 8: Pint + commit**

```bash
git add app/Http/Controllers/TaskCreqBillingController.php tests/Feature/TaskBillingItemsBreakdownTest.php
git rm tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php
git commit -m "feat: breakdown items[] generik + buka approve/reject untuk task MTN berbayar"
```

---

## Task 4: UI Verifikasi — tabel baris dinamis, buang field lama

**Files:**
- Modify: `resources/views/tasks/creq-billing/show.blade.php` (`@php` block baris 8-65, breakdown form baris 648-731, `@section('title'...)` baris 3-6)
- Test: `tests/Feature/TaskBillingItemsBreakdownUiTest.php`

**Interfaces:**
- Consumes: request field `items[]` (Task 3).

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function halaman_verifikasi_task_mtn_berbayar_tampil_tanpa_error(): void
{
    // GET show() task MTN berbayar pending — assertOk(), assertSee('Ganti Konektor') (dari billing_note)
}

#[Test]
public function halaman_verifikasi_creq_dengan_sale_row_prefill_baris_modem(): void
{
    // GET show() task C-REQ dengan InventorySerialSale price=150000
    // assertSee value modem prefilled di response (cek atribut value atau x-data JSON)
}

#[Test]
public function halaman_verifikasi_creq_lama_tanpa_task_billing_items_tetap_tampil(): void
{
    // task C-REQ sudah VERIFIED lama (sebelum migrasi ini, task_billing_items kosong) — GET show() tetap assertOk()
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskBillingItemsBreakdownUiTest.php`

- [ ] **Step 3: Implement `@php` block.** Baris 9 `$detail = $task->creqDetail;` → branch:
  ```php
  $isCreqTask = $task->task_type === \App\Enums\TaskType::CREQ;
  $detail = $isCreqTask ? $task->creqDetail : $task->maintenanceBillingDetail;
  $manualCategory = $isCreqTask ? $detail->category?->toManualInvoiceCategory() : \App\Enums\ManualInvoiceCategory::PERBAIKAN;
  ```
  Baris 61-63 `$approveHasErrors` → cukup `$errors->hasAny(['items', 'manual_subtype_name', 'customer_id'])` (gak perlu branch `$modemSale` lagi — satu bentuk form buat semua kasus).
  `$modemSale` (baris 57-59) TETAP ADA (masih dipakai buat auto-seed baris pertama), tapi `$reportFopTask`-nya tetap resolve dari `$task` TANPA branch (pola existing sudah generik, FopTask gak peduli C-REQ/MTN).

- [ ] **Step 4: Implement form breakdown.** Ganti seluruh blok `@if($modemSale) ... @else ... @endif` (baris 648-731 existing: 2 input Harga Modem+Biaya Jasa / 1 input amount+description) jadi SATU struktur Alpine (`x-data="{ items: [...] }"`):
  - Seed awal: kalau `$modemSale`, `items` mulai isi 1 elemen `{name: 'Jual Modem', amount: $modemSale->price, inventory_serial_sale_id: $modemSale->id, locked: true}`; kalau tidak, `items` mulai kosong/1 baris blank.
  - Tiap baris: input `items[{i}][name]` (readonly kalau `locked`), input `items[{i}][amount]` (data-rupiah, selalu editable), hidden input `items[{i}][inventory_serial_sale_id]` kalau ada.
  - Tombol "+ Tambah Baris" nambah elemen baru ke `items` (name kosong, amount kosong, bukan locked) — tombol "Hapus" per baris, DIsable/sembunyikan buat baris yang `locked`.
  - Total SUM ditampilkan live (`x-text`) di bawah tabel, bukan input.
  - Field `amount`/`description`/`modem_price`/`service_fee` tunggal — HAPUS SELURUHNYA dari DOM.

- [ ] **Step 5: Run tests, verify pass + regresi**

Run: `php artisan test --compact tests/Feature/TaskBillingItemsBreakdownUiTest.php tests/Feature/TaskBillingItemsBreakdownTest.php`
Expected: PASS semua

- [ ] **Step 6: Pint + commit**

```bash
git add resources/views/tasks/creq-billing/show.blade.php tests/Feature/TaskBillingItemsBreakdownUiTest.php
git commit -m "feat: UI breakdown baris dinamis di halaman Verifikasi Biaya"
```

---

## Task 5: `index()` — antrean gabung C-REQ + MTN

**Files:**
- Modify: `app/Http/Controllers/TaskCreqBillingController.php` (`index()` baris 39-91)
- Modify: `resources/views/tasks/creq-billing/index.blade.php` (baris 171, 223-227, 259 + judul halaman generik)
- Test: `tests/Feature/TaskBillingQueueIncludesMtnTest.php`

**Interfaces:**
- Consumes: `maintenanceBillingDetail` (Task 1/2).

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function antrean_tampilkan_task_creq_dan_mtn_berbayar_sekaligus(): void
{
    // 1 task C-REQ berbayar pending, 1 task MTN berbayar pending — GET index() assertSee kedua task_number
}

#[Test]
public function antrean_filter_status_jalan_untuk_mtn(): void
{
    // task MTN verified — GET index(status=verified) assertSee task itu, GET index(status=pending) assertDontSee
}

#[Test]
public function status_counts_mengagregasi_dua_sumber(): void
{
    // 2 C-REQ pending + 1 MTN pending + 1 MTN verified — assert $statusCounts->pending_count===3, verified_count===1
}

#[Test]
public function antrean_tetap_terpotong_pop_scope_untuk_mtn(): void
{
    // regresi POP scope existing, task_type MTN
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskBillingQueueIncludesMtnTest.php`

- [ ] **Step 3: Implement.** Ganti `$baseQuery`'s `where('task_type', CREQ)->whereHas('creqDetail', ...)` jadi `where(fn ($q) => $q->where(fn ($cq) => $cq->where('task_type', TaskType::CREQ->value)->whereHas('creqDetail', fn ($sq) => $sq->where('is_billable', true)))->orWhere(fn ($mq) => $mq->where('task_type', TaskType::MAINTENANCE->value)->whereHas('maintenanceBillingDetail', fn ($sq) => $sq->where('is_billable', true))))`.

  `$statusCounts` — JOIN tunggal ke `task_creq_details` tidak lagi cukup (baris MTN gak ada di tabel itu). Ganti jadi 2 query count terpisah per status lalu di-sum di PHP (clone `$baseQuery`, masing2 `whereHas` ke relasi yang sesuai per task_type, atau hitung lewat `$query->get()->groupBy(...)` kalau volume kecil — cek volume realistis dulu, ini antrean verifikasi bukan laporan besar).

  `$query->when($statusFilter !== 'all', ...)` — widen jadi OR kedua relasi juga (pola sama `$baseQuery`).

  `->with([...])` — tambah `'maintenanceBillingDetail.verifier', 'maintenanceBillingDetail.invoice'` di samping `'creqDetail.verifier', 'creqDetail.invoice'` yang sudah ada.

- [ ] **Step 4: Implement view.** Baris 171 `$detail = $task->creqDetail;` → `$detail = $task->task_type === \App\Enums\TaskType::CREQ ? $task->creqDetail : $task->maintenanceBillingDetail;`. Baris 223 kategori label → `{{ $detail?->category?->label() ?? ($task->task_type === \App\Enums\TaskType::MAINTENANCE ? 'Maintenance Berbayar' : 'C-REQ') }}`. Judul halaman (`@section('page_title', ...)`, breadcrumb) boleh digenerik-in jadi "Verifikasi Biaya Task" (§5.3 spec) — permission/route/controller code TIDAK berubah.

- [ ] **Step 5: Run tests, verify pass + regresi**

Run: `php artisan test --compact tests/Feature/TaskBillingQueueIncludesMtnTest.php tests/Feature/TaskCreqBillingReportTest.php`
Expected: PASS semua

- [ ] **Step 6: Pint + commit**

```bash
git add app/Http/Controllers/TaskCreqBillingController.php resources/views/tasks/creq-billing/index.blade.php tests/Feature/TaskBillingQueueIncludesMtnTest.php
git commit -m "feat: antrean Verifikasi Biaya gabung task C-REQ dan MTN berbayar"
```

---

## Task 6: Regresi akhir & dokumentasi

**Files:**
- Modify: `docs/TASKS.md`
- Modify: `docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md` (tandai status SELESAI)
- Modify: `docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md` (tandai Gap 2 status sisa selesai)

- [ ] **Step 1: Jalankan seluruh test file yang disentuh task 1-5 sekaligus (bukan full suite)**

Run: `php artisan test --compact tests/Unit/TaskBillingItemAndMaintenanceBillingDetailTest.php tests/Feature/TaskMaintenanceBillingMtnTest.php tests/Feature/TaskMaintenanceModemInstallTest.php tests/Feature/TaskBillingItemsBreakdownTest.php tests/Feature/TaskCreqBillingReportTest.php tests/Feature/TaskBillingItemsBreakdownUiTest.php tests/Feature/TaskBillingQueueIncludesMtnTest.php`

Expected: PASS semua.

- [ ] **Step 2: Update `docs/TASKS.md`** — tambah baris ADHOC baru (pola sama baris ADHOC-153 existing), tandai DONE.

- [ ] **Step 3: Update status dokumen rancangan** — tambah catatan "SELESAI, dikerjakan [tanggal]" di bagian atas `rancangan-verifikasi-biaya-creq-mtn.md`, dan catatan serupa di `analisa-gap-ownership-modem-dan-rincian-billing-creq.md` dekat Gap 2 (jangan hapus isi Q&A lama).

- [ ] **Step 4: Commit**

```bash
git add docs/TASKS.md docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md
git commit -m "docs: tandai breakdown verifikasi biaya + billing MTN selesai"
```

- [ ] **Step 5: Beri tahu user bahwa full suite (`composer test` tanpa filter) perlu dijalankan user sendiri sesuai aturan proyek — JANGAN jalankan dari sini.**
