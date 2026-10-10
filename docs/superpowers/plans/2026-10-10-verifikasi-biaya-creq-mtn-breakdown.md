# Breakdown Per-Item Verifikasi Biaya + Billing MTN Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti field `amount`/`description` tunggal (dan 2-field Harga Modem+Jasa Task 6 lama) di Verifikasi Biaya C-REQ jadi breakdown N-baris (nama+nominal) tersimpan terstruktur di DB, dipakai bareng buat C-REQ & task MTN polos yang sekarang sama sekali tidak punya jalur billing.

**Architecture:** Dua tabel baru (`task_maintenance_billing_details` — antrean billing MTN, mirror subset generik `task_creq_details`; `task_billing_items` — baris breakdown, dipakai C-REQ & MTN sekaligus). `TaskCreqBillingController` (nama TIDAK diubah) jadi titik verifikasi tunggal buat kedua task_type — `index()`/`authorizeBillable()`/`approve()`/`reject()` branch tipis berdasar `task_type`, logic breakdown+invoice SATU jalur.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, Blade+Alpine, sqlite test.

**Spec:** `docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md`

## Global Constraints

- `Invoice` schema TIDAK diubah — tetap flat `subtotal`/`total_amount` + 1 `description` string, `ManualCategoryInvoiceService::issue()` signature TIDAK berubah.
- FK baru ke tabel keuangan (`task_billing_items.task_id`/`invoice_id`/`inventory_serial_sale_id`) pakai `restrictOnDelete()`.
- Nama controller/route/permission (`creq_billing_verification`, `tasks.creq-billing.*`, `TaskCreqBillingController`) **TIDAK di-rename** — cuma query/logic internal dilebarin.
- `items.*.amount` wajib `min:0.01` — tidak ada baris bernominal 0 (beda dari Task 6 lama yang mengizinkan `service_fee=0`; desain baru tidak punya baris "jasa gratis" — kalau gratis ya tidak usah ditambah barisnya).
- Minimal 1 baris `items` wajib kalau task ditandai berbayar — tidak ada submit kosong.
- Kategori invoice MTN berbayar = hardcode `ManualInvoiceCategory::PERBAIKAN` — bukan pilihan CS.
- `DeviceOwnership`/`DeviceAcquisitionSource`/`OwnershipMode` (plan ownership-modem-sewa-beli) TIDAK disentuh.
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum tiap commit yang menyentuh PHP.

## Review Focus

- **CS hapus/kosongkan baris "Jual Modem" pas task punya `InventorySerialSale`** — breakdown harus tetap merepresentasikan transaksi nyata; submit yang tidak menyertakan baris bertaut `inventory_serial_sale_id` ke sale task itu wajib ditolak, bukan diam-diam lolos tanpa baris modem.
- **Task MTN ditandai berbayar tanpa catatan biaya** — `mtn_billing_note` wajib kalau `mtn_is_billable=true`, sama persis pola `creq_billing_note` sekarang; submit tanpa catatan harus gagal validasi.
- **`items` dikirim array kosong `[]` pas task berbayar** — harus ditolak (`min:1`), bukan lolos jadi invoice Rp0 atau sukses tanpa baris sama sekali.
- **Dua request `approve()`/`reject()` bersamaan pada task yang sama (TOCTOU)** — `lockForUpdate()` + cek `verification_status===PENDING` wajib tetap berlaku utuh buat KEDUA jalur (`TaskCreqDetail` maupun `TaskMaintenanceBillingDetail`), bukan cuma salah satu.
- **Antrean (`index()`) campuran C-REQ+MTN, filter status spesifik (`verified`/`rejected`)** — task MTN yang statusnya beda dari filter tidak boleh ikut nongol hanya karena struktur query OR salah susun; tiap filter harus kena ke `verification_status` milik tipe row itu sendiri, bukan nyampur status antar tipe.

---

## Task 1: Skema — `task_maintenance_billing_details`, `task_billing_items`, model & relasi

**Files:**
- Create: `database/migrations/2026_10_10_090000_create_task_maintenance_billing_details_table.php`
- Create: `database/migrations/2026_10_10_090100_create_task_billing_items_table.php`
- Create: `app/Models/TaskMaintenanceBillingDetail.php`
- Create: `app/Models/TaskBillingItem.php`
- Modify: `app/Models/Task.php` (tambah relasi `maintenanceBillingDetail()`, `billingItems()`)
- Test: `tests/Unit/TaskBillingDetailModelsTest.php`

**Interfaces:**
- Produces: `TaskMaintenanceBillingDetail` model — fillable `['task_id', 'is_billable', 'billing_note', 'verification_status', 'verified_by', 'verified_at', 'rejection_reason', 'invoice_id']`, casts `is_billable` → `boolean`, `verification_status` → `App\Enums\CReqVerificationStatus::class` (reuse, bukan enum baru), `verified_at` → `datetime`. Relasi: `task(): BelongsTo`, `verifier(): BelongsTo` (`User::class`, `verified_by`), `invoice(): BelongsTo`.
- Produces: `TaskBillingItem` model — fillable `['task_id', 'invoice_id', 'name', 'amount', 'inventory_serial_sale_id', 'created_by']`, cast `amount` → `decimal:2`. Relasi: `task(): BelongsTo`, `invoice(): BelongsTo`, `inventorySerial Sale(): BelongsTo` (nama relasi `inventorySerialSale`, target `InventorySerialSale::class`), `createdBy(): BelongsTo` (`User::class`, `created_by`).
- Produces: `Task::maintenanceBillingDetail(): HasOne` (target `TaskMaintenanceBillingDetail::class`), `Task::billingItems(): HasMany` (target `TaskBillingItem::class`).

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function task_maintenance_billing_detail_dan_billing_item_relasi_lengkap(): void
{
    $task = Task::factory()->create(['task_type' => TaskType::MAINTENANCE->value]);
    $detail = TaskMaintenanceBillingDetail::create(['task_id' => $task->id, 'is_billable' => true]);

    $this->assertTrue($task->refresh()->maintenanceBillingDetail->is($detail));
    $this->assertSame(CReqVerificationStatus::PENDING, $detail->verification_status);

    $invoice = Invoice::factory()->create();
    $item = TaskBillingItem::create([
        'task_id' => $task->id, 'invoice_id' => $invoice->id,
        'name' => 'Biaya Jasa', 'amount' => 50000, 'created_by' => User::factory()->create()->id,
    ]);

    $this->assertTrue($task->refresh()->billingItems->first()->is($item));
    $this->assertTrue($item->task->is($task));
    $this->assertTrue($item->invoice->is($invoice));
    $this->assertNull($item->inventorySerialSale);
}
```

- [ ] **Step 2: Run test, verify fails** (classes not found)

Run: `php artisan test --compact tests/Unit/TaskBillingDetailModelsTest.php`

- [ ] **Step 3: Write migrations** — `task_maintenance_billing_details` persis skema §3.1 spec (`task_id` unique FK cascade, `is_billable` default false, `billing_note` nullable, `verification_status` default `'pending'`, `verified_by` nullOnDelete, `verified_at` nullable, `rejection_reason` nullable, `invoice_id` nullOnDelete); `task_billing_items` persis skema §3.2 spec (`task_id`/`invoice_id`/`inventory_serial_sale_id` restrictOnDelete, `invoice_id` NOT NULL, `name` string 150, `amount` decimal 12,2, `inventory_serial_sale_id` nullable, `created_by` restrictOnDelete).

- [ ] **Step 4: Implement model & relasi** per Interfaces block di atas.

- [ ] **Step 5: Run migrations + test, verify pass**

Run: `php artisan test --compact tests/Unit/TaskBillingDetailModelsTest.php`
Expected: PASS

- [ ] **Step 6: Pint + commit**

```bash
git add database/migrations/2026_10_10_09* app/Models/TaskMaintenanceBillingDetail.php app/Models/TaskBillingItem.php app/Models/Task.php tests/Unit/TaskBillingDetailModelsTest.php
git commit -m "feat: skema task_maintenance_billing_details & task_billing_items"
```

---

## Task 2: Laporan MTN — checkbox "Task ini berbayar" di luar C-REQ

**Files:**
- Modify: `app/Http/Controllers/TaskMaintenanceController.php` (`store()`, blok validasi ~baris 316-320, blok eksekusi `if ($isCreq) { TaskCreqDetail::updateOrCreate(...) }` ~baris 413-442)
- Modify: `resources/views/tasks/maintenance-report.blade.php` (blok "Task Berbayar" ~baris 169-184, saat ini di dalam `@if($isCreq)`)
- Test: `tests/Feature/TaskMaintenanceBillingDetailReportTest.php`

**Interfaces:**
- Consumes: `TaskMaintenanceBillingDetail` (Task 1).
- Produces: request field `mtn_is_billable` (boolean), `mtn_billing_note` (string, required kalau `mtn_is_billable=true`) — HANYA relevan kalau `! $isCreq` (task MTN/O-REQ/INFR REQ polos; field C-REQ `creq_is_billable`/`creq_billing_note` TIDAK berubah, tetap eksklusif buat `$isCreq`).

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function mtn_berbayar_wajib_catatan_biaya_dan_masuk_antrean(): void
{
    $task = $this->makeMtnTask(); // task_type MAINTENANCE polos, bukan CREQ

    $gagal = $this->actingAs($this->technician)
        ->post(route('tasks.maintenance.store', $task), $this->basePayload() + ['mtn_is_billable' => '1']);
    $gagal->assertSessionHasErrors('mtn_billing_note');

    $ok = $this->actingAs($this->technician)
        ->post(route('tasks.maintenance.store', $task), $this->basePayload() + [
            'mtn_is_billable' => '1',
            'mtn_billing_note' => 'Ganti konektor rusak akibat kelalaian pelanggan, dikenakan biaya.',
        ]);

    $ok->assertSessionHasNoErrors();
    $detail = $task->fresh()->maintenanceBillingDetail;
    $this->assertTrue($detail->is_billable);
    $this->assertSame(CReqVerificationStatus::PENDING, $detail->verification_status);
}

#[Test]
public function mtn_tidak_berbayar_tidak_bikin_baris_billing_detail(): void
{
    $task = $this->makeMtnTask();

    $response = $this->actingAs($this->technician)->post(route('tasks.maintenance.store', $task), $this->basePayload());

    $response->assertSessionHasNoErrors();
    $this->assertNull($task->fresh()->maintenanceBillingDetail);
}
```

(Setup test: salin `setUp()`/`basePayload()` dari `tests/Feature/TaskCreqBillingReportTest.php`, tambah `makeMtnTask()` — sama seperti `makeCreqTask()` tapi `task_type' => TaskType::MAINTENANCE->value` dan tanpa field `creq_*` di payload.)

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskMaintenanceBillingDetailReportTest.php`

- [ ] **Step 3: Implement validasi.** Tambah ke `$rules` DI LUAR blok `if ($isCreq)` (dekat baris 240 existing, sejajar rule lain yang selalu aktif):
  ```php
  'mtn_is_billable' => ['nullable', 'boolean'],
  'mtn_billing_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->boolean('mtn_is_billable'))],
  ```
  Tambah message: `'mtn_billing_note.required' => 'Catatan biaya wajib diisi kalau task ini ditandai berbayar.'`.

- [ ] **Step 4: Implement eksekusi.** Tambah blok BARU sejajar (bukan di dalam) blok `if ($isCreq) { TaskCreqDetail::updateOrCreate(...) }`:
  ```php
  if (! $isCreq && $request->boolean('mtn_is_billable')) {
      TaskMaintenanceBillingDetail::updateOrCreate(['task_id' => $task->id], [
          'is_billable' => true,
          'billing_note' => $validated['mtn_billing_note'],
          'verification_status' => CReqVerificationStatus::PENDING->value,
          'verified_by' => null,
          'verified_at' => null,
          'rejection_reason' => null,
      ]);
  }
  ```
  (Tidak bikin baris sama sekali kalau `mtn_is_billable` falsy — beda dari C-REQ yang SELALU bikin `TaskCreqDetail` tiap submit; MTN polos jauh lebih sering TIDAK berbayar, baris kosong `is_billable=false` tiap laporan MTN cuma nambah data percuma.)

- [ ] **Step 5: Implement UI blade.** Pindah/duplikasi blok "Task Berbayar" (checkbox+textarea, ~baris 169-184) ke LUAR `@if($isCreq)...@endif` — render untuk SEMUA task_type, field `name="mtn_is_billable"`/`mtn_billing_note"` (JS toggle sejenis `creqToggleBillable()`, nama fungsi beda `mtnToggleBillable()` biar gak collision). Blok C-REQ yang existing TETAP di dalam `@if($isCreq)` TIDAK diubah.

- [ ] **Step 6: Run tests, verify pass + regresi**

Run: `php artisan test --compact tests/Feature/TaskMaintenanceBillingDetailReportTest.php tests/Feature/TaskCreqBillingReportTest.php tests/Feature/TaskMaintenanceModemInstallTest.php`
Expected: PASS semua (termasuk C-REQ checkbox lama tidak terganggu)

- [ ] **Step 7: Pint + commit**

```bash
git add app/Http/Controllers/TaskMaintenanceController.php resources/views/tasks/maintenance-report.blade.php tests/Feature/TaskMaintenanceBillingDetailReportTest.php
git commit -m "feat: checkbox Task Berbayar di laporan MTN polos"
```

---

## Task 3: Antrean & otorisasi — generalize ke MTN

**Files:**
- Modify: `app/Http/Controllers/TaskCreqBillingController.php` (`index()` ~baris 37-83, `authorizeBillable()` ~baris 290-306)
- Test: `tests/Feature/TaskCreqBillingQueueIncludesMtnTest.php`

**Interfaces:**
- Consumes: `Task::maintenanceBillingDetail()` (Task 1), `TaskMaintenanceBillingDetail` (Task 1/2).

- [ ] **Step 1: Write failing test**

```php
#[Test]
public function antrean_tampilkan_task_creq_dan_mtn_berbayar_sekaligus(): void
{
    $creqTask = /* task CREQ berbayar, is_billable=true, pending */;
    $mtnTask = /* task MTN berbayar via TaskMaintenanceBillingDetail, pending */;
    $mtnTidakBerbayar = /* task MTN, maintenanceBillingDetail null */;

    $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index'));

    $response->assertSee($creqTask->task_number);
    $response->assertSee($mtnTask->task_number);
    $response->assertDontSee($mtnTidakBerbayar->task_number);
}

#[Test]
public function filter_status_verified_tidak_nyampur_antar_tipe(): void
{
    $creqPending = /* CREQ pending */;
    $mtnVerified = /* MTN verified */;

    $response = $this->actingAs($this->helpdesk)->get(route('tasks.creq-billing.index', ['status' => 'verified']));

    $response->assertSee($mtnVerified->task_number);
    $response->assertDontSee($creqPending->task_number);
}

#[Test]
public function show_task_mtn_berbayar_lolos_otorisasi_pop_scope(): void
{
    $mtnTask = /* task MTN berbayar, pop di luar scope helpdesk B */;
    $response = $this->actingAs($this->helpdeskPopLain)->get(route('tasks.creq-billing.show', $mtnTask));
    $response->assertForbidden();
}
```

- [ ] **Step 2: Run tests, verify fail**

Run: `php artisan test --compact tests/Feature/TaskCreqBillingQueueIncludesMtnTest.php`

- [ ] **Step 3: Implement `authorizeBillable()`.** Ganti baris `abort_unless($task->task_type === TaskType::CREQ && $task->creqDetail?->is_billable, ...)`:
  ```php
  $task->loadMissing(['creqDetail', 'maintenanceBillingDetail']);

  $isBillable = match ($task->task_type) {
      TaskType::CREQ->value => (bool) $task->creqDetail?->is_billable,
      TaskType::MAINTENANCE->value => (bool) $task->maintenanceBillingDetail?->is_billable,
      default => false,
  };

  abort_unless($isBillable, 404, 'Task ini bukan task berbayar yang menunggu verifikasi.');
  ```
  Sisa method (cek POP scope) TIDAK berubah.

- [ ] **Step 4: Implement `index()`.** Ganti `$baseQuery` jadi OR dua tipe:
  ```php
  $baseQuery = Task::query()->applyUserScope()->where(function ($q) {
      $q->where(fn ($cq) => $cq->where('task_type', TaskType::CREQ->value)->whereHas('creqDetail', fn ($sq) => $sq->where('is_billable', true)))
        ->orWhere(fn ($mq) => $mq->where('task_type', TaskType::MAINTENANCE->value)->whereHas('maintenanceBillingDetail', fn ($sq) => $sq->where('is_billable', true)));
  });
  ```
  `$statusCounts` — GANTI raw-SQL join tunggal (gak bisa lagi, dua tabel beda) jadi DUA count query terpisah (satu join `task_creq_details`, satu join `task_maintenance_billing_details`, pola `CASE WHEN ... THEN 1 END` yang sama persis existing per tabel), lalu merge 4 angka (`pending_count`/`verified_count`/`rejected_count`/`total_count`) jadi satu objek dengan SUM masing-masing — bentuk return HARUS tetap objek dengan 4 properti itu (view `index.blade.php` baca langsung `$statusCounts->pending_count` dkk, tidak berubah).
  `$query` (list) — `->when($statusFilter !== 'all', ...)` ganti jadi filter OR per-tipe juga (status harus dicek ke RELASI MILIK TIPE itu sendiri, bukan whereHas tunggal):
  ```php
  ->when($statusFilter !== 'all', function ($q) use ($statusFilter) {
      $q->where(function ($sq) use ($statusFilter) {
          $sq->where(fn ($cq) => $cq->where('task_type', TaskType::CREQ->value)->whereHas('creqDetail', fn ($ssq) => $ssq->where('verification_status', $statusFilter)))
             ->orWhere(fn ($mq) => $mq->where('task_type', TaskType::MAINTENANCE->value)->whereHas('maintenanceBillingDetail', fn ($ssq) => $ssq->where('verification_status', $statusFilter)));
      });
  })
  ```
  `->with([...])` tambah `'maintenanceBillingDetail.verifier', 'maintenanceBillingDetail.invoice'` di samping relasi `creqDetail.*` yang sudah ada.

- [ ] **Step 5: Run tests, verify pass + regresi**

Run: `php artisan test --compact tests/Feature/TaskCreqBillingQueueIncludesMtnTest.php tests/Feature/CreqBillingVerificationTest.php`
Expected: PASS semua

- [ ] **Step 6: Pint + commit**

```bash
git add app/Http/Controllers/TaskCreqBillingController.php tests/Feature/TaskCreqBillingQueueIncludesMtnTest.php
git commit -m "feat: antrean Verifikasi Biaya tampilkan task MTN berbayar juga"
```

---

## Task 4: Breakdown per-item generik — `approve()`/`reject()` + UI

**Files:**
- Modify: `app/Http/Controllers/TaskCreqBillingController.php` (`approve()` ~baris 111-250, `reject()` ~baris 249-284)
- Modify: `resources/views/tasks/creq-billing/show.blade.php` (section breakdown ~baris 622-747 — hapus field `amount`/`description` tunggal DAN field `modem_price`/`service_fee` Task 6, ganti tabel baris dinamis)
- Modify: `tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php` (rewrite — kasus modem auto-seed pakai struktur `items[]` baru, bukan `modem_price`/`service_fee`)
- Test baru: `tests/Feature/TaskBillingItemsBreakdownTest.php`, `tests/Feature/TaskMaintenanceBillingApprovalTest.php`

**Interfaces:**
- Consumes: `TaskBillingItem`, `TaskMaintenanceBillingDetail` (Task 1); `index()`/`authorizeBillable()` generalized (Task 3).
- Produces: request field `items` (array, min 1), `items.*.name` (string, required, max 150), `items.*.amount` (numeric, min:0.01), `items.*.inventory_serial_sale_id` (nullable, integer, cuma valid kalau sama dengan sale task ini).

- [ ] **Step 1: Write failing test — breakdown generik tanpa modem**

```php
// tests/Feature/TaskBillingItemsBreakdownTest.php
#[Test]
public function approve_creq_tanpa_sale_row_breakdown_n_baris_jadi_satu_invoice(): void
{
    // task CREQ berbayar, task_billing_items BELUM ada
    $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $task), [
        'items' => [
            ['name' => 'Pasang Kabel Ekstra', 'amount' => '50.000'],
            ['name' => 'Ongkos Jasa', 'amount' => '30.000'],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $invoice = $task->fresh()->creqDetail->invoice;
    $this->assertEquals(80000, (float) $invoice->total_amount);
    $this->assertStringContainsString('Pasang Kabel Ekstra: Rp50.000', $invoice->description);
    $this->assertStringContainsString('Ongkos Jasa: Rp30.000', $invoice->description);
    $this->assertDatabaseCount('task_billing_items', 2);
}

#[Test]
public function approve_items_kosong_ditolak(): void
{
    $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $task), ['items' => []]);
    $response->assertSessionHasErrors('items');
}

#[Test]
public function approve_item_amount_nol_ditolak(): void
{
    $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $task), [
        'items' => [['name' => 'Jasa', 'amount' => '0']],
    ]);
    $response->assertSessionHasErrors('items.0.amount');
}
```

```php
// tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php — REWRITE kasus existing
#[Test]
public function approve_dengan_sale_row_items_termasuk_baris_modem_jadi_satu_invoice(): void
{
    // task punya InventorySerialSale price=150000
    $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $task), [
        'items' => [
            ['name' => 'Jual Modem', 'amount' => '150.000', 'inventory_serial_sale_id' => $sale->id],
            ['name' => 'Biaya Jasa', 'amount' => '50.000'],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertEquals(200000, (float) $task->fresh()->creqDetail->invoice->total_amount);
    $this->assertSame(150000.0, (float) $sale->fresh()->price);
}

#[Test]
public function approve_dengan_sale_row_tanpa_baris_modem_ditolak(): void
{
    // task punya InventorySerialSale, items dikirim TANPA baris inventory_serial_sale_id
    $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $task), [
        'items' => [['name' => 'Biaya Jasa', 'amount' => '50.000']],
    ]);
    $response->assertSessionHasErrors('items');
    $this->assertDatabaseCount('task_billing_items', 0);
    $this->assertNotEquals(150000.0, (float) $sale->fresh()->price); // tidak tersentuh
}
```

```php
// tests/Feature/TaskMaintenanceBillingApprovalTest.php
#[Test]
public function approve_task_mtn_berbayar_terbitkan_invoice_kategori_perbaikan(): void
{
    // task MTN, TaskMaintenanceBillingDetail is_billable=true pending
    $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $task), [
        'items' => [['name' => 'Ganti Konektor', 'amount' => '25.000']],
    ]);

    $response->assertSessionHasNoErrors();
    $detail = $task->fresh()->maintenanceBillingDetail;
    $this->assertSame(CReqVerificationStatus::VERIFIED, $detail->verification_status);
    $this->assertSame(ManualInvoiceCategory::PERBAIKAN->value, $detail->invoice->manual_category);
}

#[Test]
public function approve_task_mtn_yang_sudah_verified_ditolak_422(): void
{
    // task MTN, maintenanceBillingDetail verification_status SUDAH 'verified' (approve kedua)
    $response = $this->actingAs($this->helpdesk)->put(route('tasks.creq-billing.approve', $task), [
        'items' => [['name' => 'Ganti Konektor', 'amount' => '25.000']],
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('task_billing_items', 0); // guard lockForUpdate+cek PENDING berlaku juga di jalur MTN, bukan cuma CREQ
}
```

- [ ] **Step 2: Run semua test di atas, verify fail**

Run: `php artisan test --compact tests/Feature/TaskBillingItemsBreakdownTest.php tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php tests/Feature/TaskMaintenanceBillingApprovalTest.php`

- [ ] **Step 3: Implement `approve()`.** Ganti total isi method (dari resolve `$sale` sampai transaksi) jadi:
  1. Resolve `$sale` — TIDAK berubah (`TaskWorkToolService::resolveTaskFor()` → `InventorySerialSale`).
  2. Parse rupiah per baris SEBELUM validasi (array dinamis, `RupiahInput::parseKeys()` tidak cocok buat array — loop manual):
     ```php
     $items = $request->input('items', []);
     foreach ($items as $i => $row) {
         $items[$i]['amount'] = RupiahInput::parse($row['amount'] ?? null);
     }
     $request->merge(['items' => $items]);
     ```
  3. `$manualCategory = $task->task_type === TaskType::CREQ->value ? $task->creqDetail->category->toManualInvoiceCategory() : ManualInvoiceCategory::PERBAIKAN;`
  4. Validasi:
     ```php
     $validated = $request->validate([
         'items' => 'required|array|min:1',
         'items.*.name' => 'required|string|max:150',
         'items.*.amount' => 'required|numeric|min:0.01|max:99999999.99',
         'items.*.inventory_serial_sale_id' => ['nullable', 'integer', Rule::in($sale ? [$sale->id] : [])],
         'manual_subtype_name' => [Rule::requiredIf($manualCategory->requiresSubtypeName()), 'nullable', 'string', 'max:150'],
     ]);
     ```
  5. Kalau `$sale` ada — pastikan salah satu baris `inventory_serial_sale_id === $sale->id`, kalau tidak lempar `ValidationException::withMessages(['items' => 'Baris "Jual Modem" wajib ada — task ini mencatat penjualan modem, breakdown tidak boleh menghilangkan barisnya.'])` SEBELUM transaksi dimulai.
  6. `$amount = collect($validated['items'])->sum('amount')`; `$description = collect($validated['items'])->map(fn ($i) => "{$i['name']}: Rp".FormatHelper::rupiahInput($i['amount']))->implode(' + ')`.
  7. Scope pelanggan — TIDAK berubah.
  8. `DB::transaction()`: branch lock detail row by `$task->task_type` (`TaskCreqDetail::whereKey($task->creqDetail->id)` ATAU `TaskMaintenanceBillingDetail::whereKey($task->maintenanceBillingDetail->id)`, `->lockForUpdate()->firstOrFail()`), cek `verification_status===PENDING` — TIDAK berubah strukturnya. Kalau `$sale` ada, `$sale->update(['price' => <amount baris modem>])` — TIDAK berubah. `ManualCategoryInvoiceService::issue(...)` — TIDAK berubah signature/pemanggilan. SETELAH invoice terbit: `foreach ($validated['items'] as $item) { TaskBillingItem::create(['task_id' => $task->id, 'invoice_id' => $invoice->id, 'name' => $item['name'], 'amount' => $item['amount'], 'inventory_serial_sale_id' => $item['inventory_serial_sale_id'] ?? null, 'created_by' => auth()->id()]); }`. Update detail row jadi VERIFIED+`invoice_id` — TIDAK berubah struktur, cuma target model beda per tipe. `AuditLog` — TIDAK berubah (module tetap "Verifikasi Biaya C-REQ", `auditable_type`/`auditable_id` ikut model yang dipakai).

- [ ] **Step 4: Implement `reject()`.** Branch lock/update detail row sama kayak `approve()` Step 3.8 — sisanya (`reason` validation, `AuditLog`) TIDAK berubah.

- [ ] **Step 5: Implement UI blade.** Hapus field `amount`/`description` tunggal DAN field `modem_price`/`service_fee` Task 6 dari DOM sepenuhnya. Ganti jadi tabel baris dinamis Alpine (`x-data` array `items`, tombol "+ Tambah Baris" push row kosong, tombol hapus per baris kecuali baris modem kalau `$modemSale` ada): kalau `$modemSale` ada, `items` Alpine di-init dengan 1 baris `{name: 'Jual Modem', amount: $modemSale->price, inventory_serial_sale_id: $modemSale->id, locked: true}` — baris `locked` render `name` sebagai teks statis (bukan input) + hidden input buat tetap ter-submit, `amount` tetap `<input>` editable. Baris lain (`locked: false`) dua `<input>` bebas (nama+nominal) + tombol hapus. Total (SUM live, Alpine `x-text`) ditampilkan read-only di bawah tabel sebagai preview, bukan input.

- [ ] **Step 6: Run semua test + regresi, verify pass**

Run: `php artisan test --compact tests/Feature/TaskBillingItemsBreakdownTest.php tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php tests/Feature/TaskMaintenanceBillingApprovalTest.php tests/Feature/TaskCreqBillingReportTest.php tests/Feature/TaskCreqBillingQueueIncludesMtnTest.php tests/Feature/CreqBillingVerificationTest.php`
Expected: PASS semua

- [ ] **Step 7: Pint + commit**

```bash
git add app/Http/Controllers/TaskCreqBillingController.php resources/views/tasks/creq-billing/show.blade.php tests/Feature/TaskBillingItemsBreakdownTest.php tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php tests/Feature/TaskMaintenanceBillingApprovalTest.php
git commit -m "feat: breakdown per-item generik Verifikasi Biaya (C-REQ + MTN), ganti amount tunggal & Harga Modem+Jasa"
```

---

## Task 5: Regresi akhir & dokumentasi

**Files:**
- Modify: `docs/TASKS.md`
- Modify: `docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md` (tandai Gap 2 status)
- Modify: `docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md` (tandai status selesai)

- [ ] **Step 1: Jalankan seluruh test file yang disentuh Task 1-4 sekaligus**

Run: `php artisan test --compact tests/Unit/TaskBillingDetailModelsTest.php tests/Feature/TaskMaintenanceBillingDetailReportTest.php tests/Feature/TaskCreqBillingReportTest.php tests/Feature/TaskMaintenanceModemInstallTest.php tests/Feature/TaskCreqBillingQueueIncludesMtnTest.php tests/Feature/CreqBillingVerificationTest.php tests/Feature/TaskBillingItemsBreakdownTest.php tests/Feature/TaskCreqBillingModemSaleBreakdownTest.php tests/Feature/TaskMaintenanceBillingApprovalTest.php`

Expected: PASS semua.

- [ ] **Step 2: Update `docs/TASKS.md`** — tambah baris Ad-Hoc baru (nomor lanjut dari yang terakhir), tandai DONE.

- [ ] **Step 3: Update `analisa-gap-ownership-modem-dan-rincian-billing-creq.md`** — tambah catatan "Gap 2 — SELESAI, lihat rancangan+plan [tanggal]" di atas section Gap 2, link ke rancangan ini. Jangan hapus isi Q&A lama.

- [ ] **Step 4: Update `rancangan-verifikasi-biaya-creq-mtn.md`** — ganti `Status: rancangan — belum ada perubahan kode.` jadi `Status: SELESAI dikerjakan — [tanggal].`

- [ ] **Step 5: Commit**

```bash
git add docs/TASKS.md docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md docs/plan/task-teknisi/rancangan-verifikasi-biaya-creq-mtn.md
git commit -m "docs: tandai Gap 2 breakdown biaya + billing MTN selesai"
```

- [ ] **Step 6: Beri tahu user bahwa full suite (`composer test` tanpa filter) perlu dijalankan user sendiri sesuai aturan proyek — JANGAN jalankan dari sini.**
