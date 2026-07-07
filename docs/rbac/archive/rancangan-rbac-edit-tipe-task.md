> **Arsip.** Dokumen desain/rencana historis RBAC — sebagian besar sudah diimplementasi (lihat [../README.md](../README.md), [../business-logic.md](../business-logic.md) untuk kondisi kode terkini).

# Rancangan: RBAC Edit Tipe Task + Batasi Tipe Task saat Tambah

## Konteks kode existing
- Enum tipe task: `app/Enums/TaskType.php` — SURVEY, PSB (PEMASANGAN), MTN, DEAC, RELOKASI, C-REQ, O-REQ, INFR REQ.
- Auto-create task SURVEY sudah jalan saat Registrasi Pelanggan: `CustomerController.php:292` (`Task::create([..., 'task_type' => TaskType::SURVEY->value])`).
- RBAC custom (bukan Spatie): `User->can()` / `User->hasPermission()`, permission disimpan di tabel `permissions` dgn `code` dot-notation, di-assign ke role via `role->permissions()`. Seeder: `database/seeders/TaskFeatureSeeder.php`.
- `TaskPolicy.php` — semua gate lewat `hasPermission('task.xxx')`, owner bypass via `before()` wildcard `*`.
- `tasks/create.blade.php:35-48` — tipe task radio, render semua `TaskType::options()` tanpa filter.
- `tasks/edit.blade.php:38-48` — tipe task SUDAH read-only untuk semua (hidden input + hardcode). Belum RBAC-gated, cuma hardcode lock.
- `TaskController::store()` validasi `task_type` — line 196: terima semua enum value.
- `TaskController::update()` — line 354-361: tidak terima `task_type` sama sekali dari request.

## 1. Batasi Tipe Task saat Tambah (SRV & PSB diblok)

Alasan: Survey & Pemasangan Baru wajib lewat Registrasi Pelanggan (auto-create task). Task manual tak boleh pakai tipe ini biar tak duplikat/bypass alur customer.

- Backend `TaskController::store()`: validasi `task_type` exclude SURVEY & PEMASANGAN.
- `CustomerController::store()` pakai `Task::create()` langsung, tidak lewat validator ini — aman, tidak kena batasan.
- Frontend `TaskController::create()`: filter `$types` sebelum kirim ke view, exclude SURVEY & PEMASANGAN. Tambah note kecil di create.blade.php.

## 2. RBAC untuk Edit Tipe Task

- Permission baru: `task.edit.type` ("Ubah Tipe Task") di `TaskFeatureSeeder.php`. Tidak di-assign default ke role manapun — Owner/Admin assign manual lewat halaman Role & Permission. Owner tetap bypass via wildcard `*`.
- `TaskPolicy::editType(User $user, Task $task)`: `$user->hasPermission('task.edit.type') && $task->status->isEditable()`.
- `TaskController::update()`: terima `task_type` (sometimes, exclude SURVEY/PEMASANGAN dari opsi), authorize `editType` kalau value berubah dari existing.
- `TaskController::edit()`: filter `$types` juga exclude SURVEY/PEMASANGAN.
- `tasks/edit.blade.php`: blok Tipe Task jadi `@can('editType', $task)` → dropdown select; `@else` → read-only existing.

## 3. File yang disentuh
| File | Perubahan |
|---|---|
| `database/seeders/TaskFeatureSeeder.php` | + permission `task.edit.type` |
| `app/Policies/TaskPolicy.php` | + method `editType()` |
| `app/Http/Controllers/TaskController.php` | `store()` validasi exclude SRV/PSB; `create()` filter `$types`; `edit()` filter `$types`; `update()` terima+authorize `task_type` |
| `resources/views/tasks/create.blade.php` | note kecil (opsional) |
| `resources/views/tasks/edit.blade.php` | blok Tipe Task jadi conditional `@can('editType', $task)` |

## 4. Testing checklist
- [ ] Form create: radio SURVEY & PSB tidak muncul
- [ ] POST store dgn `task_type=SURVEY` manual → 422
- [ ] Registrasi Pelanggan tetap auto-create task SURVEY normal
- [ ] User tanpa `task.edit.type` → tipe read-only di edit
- [ ] User dgn `task.edit.type` → dropdown tipe muncul, bisa ganti (bukan SRV/PSB)
- [ ] Update task_type tanpa permission → 403
- [ ] Owner (wildcard `*`) selalu bisa edit tipe

Jalankan `php artisan db:seed --class=TaskFeatureSeeder` setelah tambah permission baru.
