# Perubahan: Simplifikasi Modul Task & FOP

Ringkasan seluruh perubahan dalam satu sesi kerja — dari RBAC Edit Tipe Task sampai penghapusan fitur yang udah gak relevan (List Task, Kalender, Form Penjadwalan, Kanban, Checklist).

## Scope akhir yang relevan
- `/fop` — Dashboard FOP (disederhanain, tanpa kanban)
- `/fop-tasks` — Task FOP (CRUD utama operasional harian)
- `/tasks-saya` — Dashboard Teknisi
- `/tasks/{id}` — Detail Task (show/edit, dipakai lintas modul di atas)

---

## 1. RBAC Edit Tipe Task

### `/tasks/{id}/edit`
- Permission baru `task.edit.type` — gate dropdown Tipe Task. Default: gak di-assign ke role manapun (Owner selalu bypass via hardcode `EffectiveAccessService`).
- `TaskPolicy::editType()` ditambah.
- `TaskController::update()` terima `task_type`, authorize `editType` kalau value berubah.

### `/fop-tasks`
- Permission baru `fop_tasks.update_sensitive` — gate field Tipe Task di modal create/edit (excl SURVEY/PSB utk manual entry, disable dropdown pas edit kalau gak punya izin).
- `FopTaskController::update()` strip field `category` kalau user gak punya izin (silent, bukan 403) — pola sama kayak `customers.detail.devices.update_sensitive`.
- Admin dikasih permission ini; role `fop` sengaja di-exclude dari wildcard `fop_tasks.*`.

### Business rule tambahan
- Tipe SURVEY & PEMASANGAN (PSB) gak bisa dipilih manual saat tambah task (baik `/tasks/create` — udah dihapus — maupun `/fop-tasks`) karena wajib lewat Registrasi Pelanggan (auto-create).
- Konsolidasi: `TaskType::autoOnlyValues()`, `TaskType::manualValues()`, `TaskType::manualOptions()` — single source of truth, sebelumnya duplikat di 2 controller.

---

## 2. Penghapusan Fitur (gak relevan lagi)

### Central List Task
- Route `/tasks` (`tasks.index`), `TaskController::index()`, view `tasks/index.blade.php` — **dihapus**.

### Tambah Task Manual
- Route `/tasks/create` + `POST /tasks`, `TaskController::create()`/`store()`, view `tasks/create.blade.php` — **dihapus**.

### FOP Calendar Scheduler
- Route `/fop/calendar`, `FopCalendarController` (termasuk auto-sync task PSB yang cuma jalan pas buka kalender), view `fop/calendar.blade.php` — **dihapus**.

### Form Penjadwalan Tim Teknisi (3 lokasi)
- Route `POST /tasks/{task}/schedule`, `TaskController::schedule()` — **dihapus**.
- Drawer "Jadwalkan & Tugaskan" + Kolom "Antrean Tiket" di `fop/dashboard.blade.php` — **dihapus**.
- Modal "Jadwalkan Task" di `tasks/show.blade.php` — **dihapus**.
- Penjadwalan sekarang cuma lewat `/fop-tasks` (assign teknisi otomatis sinkron ke tabel `tasks` via `TaskService`).

### Kanban di `/fop` Dashboard
- 4 kolom (Terjadwal/Berjalan/Perlu Aksi FOP/Selesai) — **dihapus**, diganti query ringan cuma buat angka stat card.
- Method `FopDashboardController::pipeline()` + `taskToCard()` — **dihapus** (ternyata udah dead code, API `/api/fop/pipeline` gak pernah dipanggil JS).
- Component `resources/views/components/task-card.blade.php` + `resources/views/fop/_partials/task-card.blade.php` — **dihapus** (orphan, satu-satunya consumer ya kanban).
- Sisa `/fop`: stat cards, Antrean Survey, Tim Gabungan, Status Teknisi.

### Checklist (progress bar + centang)
- Model `TaskChecklist`, `TaskChecklistTemplate`, controller `TaskChecklistController` — **file dihapus**.
- `Task::checklists()` relation, `pendingRequiredChecklists()` — **dihapus** (catatan: `canComplete()` ternyata `return true` hardcode dari dulu, checklist gak pernah beneran gating completion).
- `TaskService::create()` — hapus auto-copy checklist dari template. `TaskService::updateChecklist()` — dihapus.
- Auto-centang checklist pas submit laporan di `CustomerSurveyController`, `CustomerInstallationController`, `TaskMaintenanceController` — **dihapus** (3 lokasi).
- Route `PATCH /tasks/{task}/checklists/{checklist}` — **dihapus**.
- UI progress bar (ikut kehapus bareng task-card component), blok "Checklist Pekerjaan" di tab riwayat tiket customer — **dihapus**.
- Permission `task.checklist.update` — **dihapus dari seeder + DB**.
- Tabel DB `task_checklists`/`task_checklist_templates` — **dibiarin** (data lama gak di-drop, cuma jadi tabel orphan).
- Task Evidence (upload bukti foto) — **tetap dipakai**, gak disentuh.

---

## 3. Rapihan RBAC (rename + hapus dead permission)

| Permission lama | Status | Keterangan |
|---|---|---|
| `task.create` | → rename `task.lookup` | Fungsi asli "Buat Task" udah mati. Sisa cuma gate 2 API utility: `searchCustomers()` (autocomplete pelanggan di `/fop-tasks`) & `checkConflict()` (cek konflik jadwal di edit task). |
| `task.schedule` | tetap, label diperjelas | "Ubah Jadwal Task (via Edit)" — dulu gate form/drawer/modal penjadwalan terpisah (semua udah dihapus), sekarang cuma gate field `scheduled_at` di form Edit Task. |
| `task.report.view` | **dihapus total** | `TaskPolicy::viewReport()` ada tapi 0 pemanggil di codebase — dead dari awal. |
| `task.checklist.update` | **dihapus total** | Ikut checklist feature yang dihapus. |
| `task.edit.type` | baru | Lihat bagian 1. |
| `fop_tasks.update_sensitive` | baru | Lihat bagian 1. |

### Bug ke-expose & di-fix
- Role **admin** ternyata 0 permission `task.*` (gap lama, bukan gara-gara sesi ini) — nambahin `task.lookup` biar modal cari-pelanggan di `/fop-tasks` bisa dipakai admin.
- Route `check-conflict` + `search-customers` yang tadinya beda middleware (`task.view.all` vs `task.create`) padahal controller-nya panggil ability yang sama — disatuin jadi 1 middleware `permission:task.lookup`.

---

## 4. File yang disentuh (ringkasan)

**Dihapus total:**
- `app/Http/Controllers/FopCalendarController.php`
- `app/Http/Controllers/TaskChecklistController.php`
- `app/Models/TaskChecklist.php`, `app/Models/TaskChecklistTemplate.php`
- `resources/views/fop/calendar.blade.php`
- `resources/views/tasks/index.blade.php`, `resources/views/tasks/create.blade.php`
- `resources/views/components/task-card.blade.php`, `resources/views/fop/_partials/task-card.blade.php`

**Diedit berat:**
- `routes/web.php` — banyak route dihapus/digabung
- `app/Http/Controllers/TaskController.php` — hapus `index/create/store/schedule/calendarData`, rename ability `create→lookup`
- `app/Http/Controllers/FopDashboardController.php` — hapus kanban + pipeline API
- `app/Services/TaskService.php` — hapus `scheduleTask()`, checklist logic
- `app/Policies/TaskPolicy.php` — hapus `viewReport`, `updateChecklist`; rename `create→lookup`
- `app/Models/Task.php` — hapus relasi checklist
- `resources/views/fop/dashboard.blade.php` — hapus kanban + form penjadwalan
- `resources/views/tasks/show.blade.php` — hapus modal jadwal
- `resources/views/tasks/edit.blade.php` — dropdown Tipe Task RBAC
- `resources/views/fop_tasks/index.blade.php` — dropdown Tipe Task RBAC
- `database/seeders/TaskFeatureSeeder.php`, `database/seeders/RolePermissionSeeder.php` — permission cleanup
- `app/Enums/TaskType.php` — tambah `autoOnlyValues()`/`manualValues()`/`manualOptions()`

**Kena imbas kecil (3 baris auto-centang checklist dihapus):**
- `app/Http/Controllers/CustomerSurveyController.php`
- `app/Http/Controllers/CustomerInstallationController.php`
- `app/Http/Controllers/TaskMaintenanceController.php`

**Test disesuaikan:**
- `tests/Feature/TaskBroadcastingTest.php` — hapus fixture `TaskChecklist`

---

## 5. Belum diverifikasi (butuh manual test di browser)
- Alur create/edit task FOP end-to-end (permission gate beneran jalan sesuai role)
- Alur laporan survey/pemasangan/maintenance selesai tanpa checklist auto-centang
- Dashboard `/fop` render bener tanpa kanban (stat card angka masih akurat)
