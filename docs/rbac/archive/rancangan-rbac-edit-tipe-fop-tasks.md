> **Arsip.** Dokumen desain/rencana historis RBAC — sebagian besar sudah diimplementasi (lihat [../README.md](../README.md), [../business-logic.md](../business-logic.md) untuk kondisi kode terkini).

# Rancangan: RBAC Edit Tipe Task pada /fop-tasks + Batasi Tipe Task saat Tambah

## Konteks kode existing
- Modul `/fop-tasks` terpisah dari `/tasks` — controller `FopTaskController.php`, model `FopTask.php`, view tunggal `resources/views/fop_tasks/index.blade.php` (list + 1 modal Alpine dipakai bareng utk Create & Edit, `modal.isEdit` sbg flag).
- `category` (tipe task) pakai enum sama: `App\Enums\TaskType` (SURVEY, PSB, MTN, DEAC, RELOKASI, C-REQ, O-REQ, INFR REQ).
- `store()` (line 105) & `update()` (line 195) validasi `category` pakai `Rule::enum(TaskType::class)` — semua value diterima, termasuk SURVEY & PSB, tanpa RBAC gate.
- `autoSyncAndCalculatePriority()` (line 356) auto-create FopTask category Survey/PSB langsung dari `Customer` yg belum disurvei/dipasang, panggil `FopTask::create()` langsung (bypass `store()`/validasi) — **tidak boleh disentuh**, tetap jalan otomatis.
- RBAC: bukan Spatie, custom `hasPermission()` via `EffectiveAccessService`. Permission digenerate dari `config/rbac.php` (`allowed_actions['fop_tasks']`) lewat `PermissionGeneratorService`, lalu role-assignment manual di `RolePermissionSeeder.php`.
- Permission `fop_tasks.*` sekarang: view, create, update, delete — role `fop` & `admin` dapat **wildcard** `fop_tasks.*` (line 58, 133 `RolePermissionSeeder.php`). Wildcard di-expand saat seed time (prefix match ke semua kode permission `fop_tasks.` yg ada di DB), bukan runtime — jadi permission baru otomatis ikut ke-assign ke role yg pakai wildcard KECUALI di-exclude eksplisit.
- Pola existing utk "field sensitif perlu permission lebih" sudah ada: `customers.detail.devices.update_sensitive` (`ActionCode::UPDATE_SENSITIVE`) — dipakai di `CustomerDeviceController.php:53` dengan cara **strip field dari `$validated`** kalau user gak punya izin (bukan `abort(403)`). Ikuti pola sama.

## 1. Batasi Tipe Task saat Tambah (SRV & PSB diblok, manual create)

- `FopTaskController::store()` (line 110): ganti `Rule::enum(TaskType::class)` jadi `Rule::in($this->manualCategoryValues())` — exclude SURVEY & PEMASANGAN.
- `autoSyncAndCalculatePriority()` tetap pakai `FopTask::create()` langsung → tidak lewat `store()`, tidak kena batasan ini.
- View: modal Create pakai daftar kategori terbatas (exclude SURVEY/PSB) — lihat #3.

## 2. RBAC Edit Tipe Task

- Permission baru: `fop_tasks.update_sensitive` (via `ActionCode::UPDATE_SENSITIVE`, ditambah ke `config/rbac.php` → `allowed_actions.fop_tasks`).
- `RolePermissionSeeder.php`: role `fop` exclude eksplisit `fop_tasks.update_sensitive` dari hasil expand wildcard `fop_tasks.*` (pola sama seperti exclude `customers.detail.devices.update_sensitive` utk role `admin`/`pop_admin`, line 228-246). Role `admin` & `owner` tetap dapat (admin lewat wildcard tanpa exclude, owner lewat `*`).
- `FopTaskController::update()` (line 195): kalau `auth()->user()` gak punya `fop_tasks.update_sensitive`, **unset `$validated['category']`** sebelum diproses — field lain (status, prioritas, teknisi, dst) tetap bisa diedit normal. Tidak ada pembatasan value (SURVEY/PSB tetap boleh dipertahankan) di edit — cuma gate "boleh ubah atau tidak", sesuai requirement.
- Tidak buat Policy class baru (`FopTaskController` sudah pola inline `hasPermission()` lewat `authorizeAccess()`, konsisten diikuti).

## 3. Perubahan View (`fop_tasks/index.blade.php`)

Modal Create & Edit sama-sama pakai satu `<select name="category">`. Karena aturan value berbeda (Create: exclude SRV/PSB; Edit: semua value boleh tapi field bisa dikunci), opsi select dibuat dinamis client-side:

- Kirim 2 variabel dari controller: `$categories` (semua, tetap dipakai utk filter & badge tabel) dan `$manualCategories` (exclude SURVEY & PEMASANGAN, khusus opsi Create).
- Kirim flag `$canEditFopTaskType = auth()->user()->hasPermission('fop_tasks.update_sensitive')`.
- Alpine: `availableCategories` = `modal.isEdit ? allCategoriesData : manualCategoriesData` (computed).
- `<select>` diberi `:disabled="modal.isEdit && !canEditCategoryPermission"`.
- Saat disabled (edit tanpa izin), tambahkan `<template x-if="...">` hidden input `name="category"` bawa value existing supaya tetap ke-submit (disabled select tidak ikut ke-submit form HTML native).

## 4. File yang disentuh
| File | Perubahan |
|---|---|
| `config/rbac.php` | + `ActionCode::UPDATE_SENSITIVE->value` di `allowed_actions.fop_tasks` |
| `database/seeders/RolePermissionSeeder.php` | exclude `fop_tasks.update_sensitive` dari hasil wildcard role `fop` |
| `app/Http/Controllers/FopTaskController.php` | `store()` & helper `manualCategoryValues()` exclude SRV/PSB; `index()` kirim `$manualCategories` + `$canEditFopTaskType`; `update()` strip `category` kalau gak punya izin |
| `resources/views/fop_tasks/index.blade.php` | select tipe task jadi dinamis (create vs edit), disabled + hidden input fallback saat edit tanpa izin |

## 5. Testing checklist
- [ ] Modal Create: dropdown tipe tidak ada Survey/PSB
- [ ] POST store `category=SURVEY` manual → 422
- [ ] Auto-sync (`autoSyncAndCalculatePriority`) tetap bikin FopTask Survey/PSB otomatis dari customer, tidak error
- [ ] Role `fop` (tanpa `fop_tasks.update_sensitive`) buka Edit → dropdown tipe disabled, field lain tetap bisa diedit & submit sukses
- [ ] PUT update dgn payload `category` diubah, user tanpa izin → tipe tidak berubah (silently ignored, bukan 403), field lain tersimpan
- [ ] Role/permission dgn `fop_tasks.update_sensitive` (misal admin) → dropdown tipe di Edit aktif, bisa ganti termasuk ke/dari Survey/PSB
- [ ] Owner tetap bisa edit semua

Jalankan `php artisan db:seed --class=RolePermissionSeeder` setelah ubah `config/rbac.php` (regenerate permission + resync role).
