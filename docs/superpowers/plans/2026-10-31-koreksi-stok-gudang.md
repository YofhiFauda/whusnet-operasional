# Koreksi Stok Gudang Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti 4 halaman koreksi stok gudang (Stock Opname, Opname SN/Roll, Penyesuaian Stok, Tandai Status SN) dengan satu pintu "Koreksi Stok" berisi 2 kartu situasi.

**Architecture:** Halaman pintu + dua form dinamis (Alpine) yang memilih mode qty/SN/roll dari satu endpoint baca `resolve`. Semua simpan memakai endpoint POST lama (`opname.store`, `unit-opname.store`, `balance.store`, `serial.store`); service dan validasi tidak berubah. Bagian alasan+bukti diekstrak ke partial Blade bersama.

**Tech Stack:** Laravel 13, Blade + Alpine 3, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-10-31-koreksi-stok-gudang-design.md`

## Global Constraints

- Permission tetap `warehouse_adjustment.create`; tanpa migrasi, tanpa perubahan `InventoryAdjustmentService`.
- URL aksi POST dirender server lewat `route()` (atribut `data-*`/`action` default); klien hanya memilih di antara URL itu, tidak merakit path (lihat `PostTargetRenderedServerSideTest`).
- Route statis sebelum dinamis di `routes/web.php` (blok `permission:warehouse_adjustment.create`, ±baris 999).
- Semua query lewat POP scope: `AuthorizesWarehousePop::assertPopInScope()`.
- Barang kategori `ItemCategory::CODE_MERCHANDISE_MARKETING` dikecualikan dari semua pencarian.
- Teks UI, komentar, commit: bahasa Indonesia. Test: `#[Test]`, nama sesuai gejala. Jangan jalankan full suite.
- Setiap commit: `vendor/bin/pint --dirty` dulu.

## Review Focus

1. SN/roll milik gudang lain (atau sedang di custody teknisi) diketik di gudang terpilih → `none`, bukan terbuka. (Task 1)
2. Mengetik **nama** barang ber-SN/roll → petunjuk `need_unit`, bukan form qty yang salah. (Task 1)
3. Barang Merchandise Marketing tidak pernah muncul. (Task 1)
4. Link lama (`balance/create`, `opname/create`, `opname/unit/create`) dengan query `pop_id,item_id,lot_no` tetap sampai di form baru dengan data terisi. (Task 2)
5. "Salah catat"/hilang/rusak qty dengan jumlah kosong atau 0 ditolak dan error tampil di form, bukan layar kosong. (Task 4)

## File Structure

- Create `app/Http/Controllers/Warehouse/WarehouseAdjustmentResolveController.php` — endpoint baca `resolve`, satu tanggung jawab (menghindari membengkakkan `WarehouseAdjustmentController`).
- Modify `app/Http/Controllers/Warehouse/WarehouseAdjustmentController.php` — tambah `index`, `createCheck`, `createProblem`; `createBalance/createOpname/createUnitOpname` jadi redirect.
- Create views di `resources/views/warehouse/adjustments/`: `index.blade.php`, `check.blade.php`, `problem.blade.php`, `partials/_unit-finder.blade.php`, `partials/_reason-evidence.blade.php`.
- Delete `balance.blade.php`, `opname.blade.php`, `unit-opname.blade.php` (setelah Task 2–4).
- Modify `routes/web.php`, `components/warehouse/header.blade.php`, `warehouse/index.blade.php`, `warehouse/stock/board/_detail.blade.php`.
- Tests baru: `tests/Feature/WarehouseAdjustmentResolveTest.php`, `WarehouseAdjustmentHubTest.php`, `WarehouseCheckFormTest.php`, `WarehouseProblemFormTest.php`.

---

### Task 1: Endpoint `resolve`

**Files:**
- Create: `app/Http/Controllers/Warehouse/WarehouseAdjustmentResolveController.php`
- Modify: `routes/web.php` (di blok `warehouse_adjustment.create`)
- Test: `tests/Feature/WarehouseAdjustmentResolveTest.php`

**Interfaces:**
- Produces: route `warehouse.adjustments.resolve` (`GET /warehouse/adjustments/resolve?pop_id=&q=`), JSON dengan `type` salah satu:
  - `serial` → `{type, id, label, serial_number, status_label}`
  - `roll` → `{type, id, label, roll_code, length_remaining}`
  - `qty` → `{type, item_id, label, unit, lots: [{lot_no, qty}]}`
  - `choose` → `{type, options: [{item_id, label}]}` (lebih dari satu item cocok)
  - `need_unit` → `{type, label}` (item ber-SN/roll cocok nama)
  - `none` → `{type}`

- [ ] **Step 1: Tulis test** — setUp meniru `WarehouseUnitOpnameTest` (seeder yang sama, owner, `$pusat`), plus satu gudang kedua dan user scoped dari pola test POP-scope gudang yang sudah ada (cari `Anda tidak memiliki akses ke Gudang` di `tests/Feature`). Test (nama = gejala), assertion JSON:
  - `sn_di_gudang_terpilih_terselesaikan_sebagai_serial` → `type=serial`
  - `sn_milik_gudang_lain_tidak_terselesaikan` → `type=none`
  - `sn_yang_sedang_di_custody_teknisi_tidak_terselesaikan` → `type=none` (current_pop_id null)
  - `kode_roll_terselesaikan_sebagai_roll`
  - `nama_barang_curah_terselesaikan_sebagai_qty_beserta_lot_dan_saldo`
  - `nama_barang_ber_sn_memberi_petunjuk_need_unit`
  - `dua_barang_cocok_mengembalikan_daftar_choose`
  - `barang_merchandise_marketing_tidak_pernah_muncul` → `none`
  - `pop_di_luar_scope_user_ditolak_403` (user scoped)
  - `tanpa_permission_adjustment_create_ditolak_403`

- [ ] **Step 2: Jalankan** `php artisan test --compact tests/Feature/WarehouseAdjustmentResolveTest.php` → FAIL (route tidak ada).

- [ ] **Step 3: Implement** `WarehouseAdjustmentResolveController::__invoke(Request $request, EffectiveAccessService $access): JsonResponse` (pakai trait `AuthorizesWarehousePop`). Validasi `pop_id` exists, `q` string min 2 max 100. Urutan cocok: SN persis (`serial_number`, `current_pop_id = pop`) → roll persis (`roll_code`, `current_pop_id = pop`) → item aktif non-marketing `code`/`name LIKE`. Satu item `QUANTITY` → `qty` dengan `lots` dari `inventory_balances` (pop+item); satu item `SERIALIZED`/`ROLL` → `need_unit`; >1 item → `choose` (maks 10). Tambah route `GET /warehouse/adjustments/resolve` bernama `warehouse.adjustments.resolve`.

- [ ] **Step 4: Jalankan test** → PASS.

- [ ] **Step 5: Commit** `feat(warehouse): endpoint resolve jenis barang untuk Koreksi Stok`

### Task 2: Halaman pintu, tautan masuk, redirect route lama

**Files:**
- Modify: `routes/web.php`, `WarehouseAdjustmentController.php`, `components/warehouse/header.blade.php` (±235–255 dan ±792–805), `warehouse/index.blade.php` (±443), `warehouse/stock/board/_detail.blade.php` (±41)
- Create: `resources/views/warehouse/adjustments/index.blade.php`
- Test: `tests/Feature/WarehouseAdjustmentHubTest.php`

**Interfaces:**
- Consumes: Task 1 tidak dibutuhkan.
- Produces: route `warehouse.adjustments.index` (`GET /warehouse/adjustments`), `warehouse.adjustments.check.create` (`GET .../adjustments/check`), `warehouse.adjustments.problem.create` (`GET .../adjustments/problem`); method `index(): View`, `createCheck(Request, EffectiveAccessService): View`, `createProblem(Request, EffectiveAccessService): View` (dua terakhir masih mengembalikan view kosong yang dibuat Task 3/4 — buat file view placeholder minimal agar route hidup).

- [ ] **Step 1: Tulis test** — `halaman_pintu_menampilkan_dua_kartu_untuk_pemegang_permission` (assertSee "Cek & cocokkan stok", "Ada barang bermasalah", link ke `check.create` & `problem.create`); `halaman_pintu_ditolak_tanpa_permission`; `link_lama_opname_create_redirect_ke_check_dengan_query` (query `pop_id,item_id,lot_no` terbawa); `link_lama_unit_opname_redirect_ke_check`; `link_lama_balance_create_redirect_ke_problem_dengan_query`; `header_gudang_hanya_punya_satu_link_koreksi_stok` (halaman stok tidak lagi berisi `balance/create` maupun `opname/create`, berisi `adjustments`).

- [ ] **Step 2: Jalankan** → FAIL.

- [ ] **Step 3: Implement** route baru (statis: `/adjustments`, `/adjustments/check`, `/adjustments/problem`, `/adjustments/resolve` diletakkan **sebelum** route `{custody}/{serial}/{roll}`); ubah `createBalance/createOpname/createUnitOpname` menjadi `redirect()->route(...)->withInput`-free `redirect()->route('...check.create' atau 'problem.create', $request->query())`; kartu di `index.blade.php` memakai komponen `<x-warehouse.header active="stock" ...>` seperti halaman lain. Header gudang: ganti dua blok link jadi satu link "Koreksi Stok" (+ info-tip singkat). `warehouse/index.blade.php` & `_detail.blade.php`: arahkan ke `check.create` (parameter tetap).

- [ ] **Step 4: Jalankan** test Hub + `tests/Feature/WarehouseStockOpnameTest.php` + `tests/Feature/WarehouseUnitOpnameTest.php`; perbaiki assertion halaman lama yang GET view lama (ubah ke redirect/halaman baru, jangan hapus test).

- [ ] **Step 5: Commit** `feat(warehouse): halaman pintu Koreksi Stok, redirect route lama`

### Task 3: Partial bersama + form "Cek & cocokkan stok"

**Files:**
- Create: `partials/_unit-finder.blade.php`, `partials/_reason-evidence.blade.php`, `check.blade.php` (ganti placeholder)
- Modify: `WarehouseAdjustmentController::createCheck`
- Test: `tests/Feature/WarehouseCheckFormTest.php`

**Interfaces:**
- Consumes: route `warehouse.adjustments.resolve` (Task 1), `check.create` (Task 2).
- Produces:
  - `_unit-finder`: variabel `$pops`, `$resolveUrl`; komponen Alpine `x-data` yang meng-`fetch` resolve (debounce) dan mengekspos `result`.
  - `_reason-evidence`: variabel `$evidenceRequired` (bool atau ekspresi Alpine), `$reasonLabel`, `$suggestions` (array), `$withReason` (bool, default true).

- [ ] **Step 1: Tulis test** — `form_cek_stok_merender_tiga_url_simpan_dari_server` (assert HTML memuat `route('warehouse.adjustments.opname.store')` dan `route('warehouse.adjustments.unit-opname.store')` di atribut `data-*`, dan `action` default berisi URL opname.store, tidak kosong); `form_cek_stok_terisi_dari_query_pop_item_lot` (stok sistem tampil seperti `createOpname` lama); `form_cek_stok_hanya_menampilkan_gudang_dalam_scope`; test fungsional POST lama tetap hijau (dijalankan di Step 4).

- [ ] **Step 2: Jalankan** → FAIL.

- [ ] **Step 3: Implement.** `createCheck` menyiapkan `$pops` (query sama dengan `createOpname`) dan `$currentBalance` bila query lengkap. `check.blade.php`: kolom pencarian → `_unit-finder`; blok per `result.type`: qty (input `counted_qty`, `lot_no` bila >1 lot, `notes`; field sama dengan `opname.store`), serial (`is_present`, `reason`, `evidence` wajib; field sama dengan `unit-opname.store` dengan `tracking_type=serialized`, `code`), roll (`counted_length`, `tracking_type=roll`). Form `:action` memilih `data-url-qty|unit` yang dirender `route()`. Error validasi/`session('error')` ditampilkan di atas form. `_reason-evidence` dipakai untuk mode serial/roll.

- [ ] **Step 4: Jalankan** `WarehouseCheckFormTest`, `WarehouseStockOpnameTest`, `WarehouseUnitOpnameTest`, `PostTargetRenderedServerSideTest` → PASS.

- [ ] **Step 5: Commit** `feat(warehouse): form Cek & cocokkan stok dengan deteksi jenis barang`

### Task 4: Form "Ada barang bermasalah"

**Files:**
- Create: `problem.blade.php` (ganti placeholder)
- Modify: `WarehouseAdjustmentController::createProblem`
- Test: `tests/Feature/WarehouseProblemFormTest.php`

**Interfaces:**
- Consumes: `_unit-finder`, `_reason-evidence` (Task 3), route `serial.store` dan `balance.store` (lama).
- Produces: kejadian → nilai kirim (konstanta di view, pemetaan didokumentasikan di komentar Blade):

| Kejadian | SN: `new_status` | Qty: `qty_delta` | `reason` |
|---|---|---|---|
| Hilang | `lost` | −jumlah | `hilang` |
| Rusak | `damaged` | −jumlah | `rusak` |
| Dibuang | `scrapped` | −jumlah | `dibuang` |
| Karantina | `quarantine` | (tidak tersedia) | `karantina` |
| Salah catat | (tidak tersedia) | ±jumlah (pilih arah) | `salah_catat` |

- [ ] **Step 1: Tulis test** — `form_masalah_merender_url_serial_dan_balance_dari_server`; `kejadian_hilang_pada_sn_mengubah_status_lost_lewat_endpoint_serial` (POST lama dengan `new_status=lost`, `reason=hilang`, foto → `SerialStatus::LOST`); `kejadian_rusak_qty_mengurangi_saldo_lewat_endpoint_balance` (`qty_delta=-5` → saldo turun 5); `salah_catat_qty_boleh_menambah_saldo`; `jumlah_nol_ditolak_dan_error_tampil_di_form` (assertSessionHas `error`, redirect back); `hilang_sn_tanpa_foto_ditolak`; `karantina_sn_tanpa_foto_diterima`.

- [ ] **Step 2: Jalankan** → FAIL.

- [ ] **Step 3: Implement** `createProblem` (data sama dengan `createCheck`) dan `problem.blade.php`: finder → pilih kejadian (opsi tak berlaku untuk jenis terpilih disembunyikan via `x-show` + tidak dikirim `x-if`), input `jumlah`, field tersembunyi `qty_delta` = tanda × jumlah (Alpine), `new_status`, `reason`; foto lewat `_reason-evidence` dengan `evidenceRequired` = SN dan kejadian ∈ {Hilang, Rusak, Dibuang}. Daftar saran alasan lama dipindah ke `$suggestions` partial (satu sumber).

- [ ] **Step 4: Jalankan** `WarehouseProblemFormTest`, `WarehouseAdjustmentEvidenceUploadTest`, `InventoryAdjustmentEvidenceRequiredTest` → PASS.

- [ ] **Step 5: Commit** `feat(warehouse): form Ada barang bermasalah`

### Task 5: Migrasi form yang tidak dilebur ke partial + bersih-bersih

**Files:**
- Modify: `serial.blade.php`, `custody.blade.php`, `roll.blade.php`, `repair-serial.blade.php` (pakai `_reason-evidence`, **nama field tidak berubah**)
- Delete: `balance.blade.php`, `opname.blade.php`, `unit-opname.blade.php`
- Modify: `docs/TASKS.md`, dokumen modul gudang yang menyebut nama halaman lama (cari `Penyesuaian Saldo`, `Stock Opname`, `Opname SN / Roll` di `docs/`)
- Test: regresi saja

- [ ] **Step 1:** Ganti blok alasan/catatan/bukti di keempat form dengan `@include('warehouse.adjustments.partials._reason-evidence', [...])`; atribut `name`, `required`, dan aturan bukti identik dengan sebelumnya.
- [ ] **Step 2:** Hapus tiga view lama; pastikan tidak ada `view('warehouse.adjustments.(balance|opname|unit-opname)')` tersisa (`grep`).
- [ ] **Step 3:** Jalankan `php artisan test --compact` untuk: `WarehouseAdjustmentEvidenceUploadTest`, `InventoryAdjustmentEvidenceRequiredTest`, `InventoryAdjustmentRollTest`, `WarehouseReassignAdjustmentRollControllerTest`, `WarehouseReportAdjustmentSummaryTest`, `WarehouseStockOpnameTest`, `WarehouseUnitOpnameTest` + empat test baru → PASS.
- [ ] **Step 4:** `vendor/bin/pint --dirty`, lalu `docker compose run --rm assets` (atau `npm run build`) agar kelas Tailwind baru terbundel; update `docs/TASKS.md` (Done: Koreksi Stok satu pintu).
- [ ] **Step 5: Commit** `refactor(warehouse): partial alasan+bukti bersama, hapus view koreksi lama`
