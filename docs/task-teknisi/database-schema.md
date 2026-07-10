# Database Schema — Task Teknisi

## Entity Relationship

```
tasks ──belongsTo──▶ customers (nullable)
      ──belongsTo──▶ pops
      ──belongsTo──▶ users (fop_id, created_by, updated_by)
      ──hasMany───▶ task_teams ──belongsTo──▶ users
      ──hasMany───▶ task_evidences ──belongsTo──▶ users (uploaded_by)
      ──hasOne────▶ task_maintenances
```

## Tabel `tasks`

Migrasi: `2026_06_24_000001_create`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `task_number` | string, unique | | Format `TASK-{tahun}-{urutan 4 digit}` |
| `customer_id` | FK → `customers.id`, restrict delete | ✔ | Kosong untuk task non-customer (maintenance infrastruktur, dll) |
| `pop_id` | FK → `pops.id`, restrict delete | | |
| `task_type` | string(30) | | Enum `App\Enums\TaskType` (sama dengan yang dipakai `FopTask`, lihat [docs/fop-task/database-schema.md](../fop-task/database-schema.md)) |
| `title` | string | | |
| `description` | text | ✔ | |
| `status` | string(30), default `draft` | | Enum `App\Enums\TaskStatus` |
| `scheduled_at`, `started_at`, `completed_at`, `cancelled_at` | timestamp | ✔ | |
| `cancel_reason`, `pending_reason`, `reject_reason` | string | ✔ | |
| `fop_review_status` | string | ✔ | `pending`/`approved`/`rejected` — diisi saat task `selesai` |
| `fop_id` | FK → `users.id`, null on delete | ✔ | FOP yang bikin/kelola task |
| `sla_minutes` | unsignedSmallInteger | ✔ | Dari `TaskType::slaMinutes()` saat create |
| `conflict_override` | boolean, default false | | Apakah konflik jadwal di-override saat create/edit |
| `created_by`, `updated_by` | FK → `users.id`, null on delete | ✔ | |
| `created_at`/`updated_at` | timestamp | | |

Index: `pop_id`, `customer_id`, `status`, `task_type`, `scheduled_at`, `fop_id`.

## Tabel `task_teams`

Migrasi: `2026_06_24_000002_create`. Pivot anggota tim per Task (beda dari `fop_task_team_user` di modul FOP — ini scoped ke 1 Task, bukan roster harian).

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `task_id` | FK → `tasks.id`, cascade delete | |
| `user_id` | FK → `users.id`, **restrict** delete (beda dari kebanyakan FK user lain yang nullOnDelete — user gak bisa dihapus kalau masih jadi anggota tim task) | |
| `role_in_task` | string(30), default `teknisi` | `lead` (anggota pertama) atau `teknisi` |

Unique: (`task_id`, `user_id`). Index: `user_id`.

## Tabel `task_evidences`

Migrasi: `2026_06_24_000005_create`.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `task_id` | FK → `tasks.id`, cascade delete | |
| `uploaded_by` | FK → `users.id`, null on delete | |
| `file_path` | string | |
| `caption` | string | ✔ |
| `created_at`/`updated_at` | timestamp | |

Index: `task_id`.

## Tabel `task_maintenances`

Migrasi: `2026_07_01_152851_create`. 1:1 dengan `tasks` (khusus task tipe non-Survey/Pemasangan).

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `task_id` | FK → `tasks.id`, cascade delete | |
| `kendala_teknis` | text | Wajib |
| `kabel`, `modem`, `patchcord`, `sleeve`, `lainnya` | string | Opsional, catatan part yang dipakai |
| `opm_photo`, `speedtest_photo` | string | Wajib diisi saat submit laporan |

## Perbandingan dengan `FopTask` / `fop_tasks`

| | `tasks` (modul ini) | `fop_tasks` (lihat [docs/fop-task](../fop-task/README.md)) |
|---|---|---|
| Representasi | Eksekusi teknisi (checklist, timer, evidence) | Tiket administratif FOP (SLA, prioritas, kategori) |
| Tim | `task_teams` (per-task, 1-3 anggota) | `technicians` (pivot `fop_task_user`) + `FopTaskTeam` (roster harian lintas-tiket) |
| Siapa bikin | `TaskService::create()` (FOP manual) ATAU auto dari `CustomerWorkflowService::transition()` | `FopTaskController` (manual ATAU auto-sync dari status Customer) |
| Link | `fop_tasks.task_id` → `tasks.id` (1 FopTask generate 1 Task saat teknisi di-assign) | — |

## Model Relations (ringkas)

```php
// Task
customer(): BelongsTo(Customer::class)
pop(): BelongsTo(Pop::class)
fop(): BelongsTo(User::class, 'fop_id')
teamMembers(): HasMany(TaskTeam::class)
evidences(): HasMany(TaskEvidence::class)
maintenanceReport(): HasOne(TaskMaintenance::class)
auditLogs(): MorphMany(AuditLog::class, 'auditable')

// TaskTeam
task(): BelongsTo(Task::class)
user(): BelongsTo(User::class)

// TaskEvidence
task(): BelongsTo(Task::class)
uploader(): BelongsTo(User::class, 'uploaded_by')
```

## Audit

`Task` — trait `RecordsAuditLogs`, module `Task Management`, event `created`/`updated`/`deleted` otomatis. Ditambah manual `AuditLog::log()` di `TaskService` untuk aksi domain-specific: `completed`, `cancelled`, `reassigned`, dan di `TaskController` untuk `approved`/`rejected` (hasil review FOP). `task_teams`, `task_evidences`, `task_maintenances` **tidak** py audit trait sendiri — perubahan di tabel ini cuma keliatan lewat perubahan `tasks` punya (kalau di-log manual) atau tidak sama sekali.
