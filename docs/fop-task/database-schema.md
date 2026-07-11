# Database Schema — Modul FOP Task

## Entity Relationship

```
pops ──┐
       │
villages ──┐
           │
customers ─┼──▶ fop_tasks ◀──── fop_task_teams ◀──── fop_task_team_user ──▶ users
           │        │  ▲                                                     ▲
           │        │  │                                                     │
           │        ▼  │                                                     │
           │      tasks │                                                     │
           │            │                                                     │
           └── fop_task_user ───────────────────────────────────────────────┘
```

- `fop_tasks.team_id` → `fop_task_teams.id` (nullable, `set null` on delete)
- `fop_tasks.task_id` → `tasks.id` (nullable, `null on delete`) — Task eksekusi teknisi yang di-generate otomatis
- `fop_task_user` — pivot teknisi PIC per tiket (many-to-many `fop_tasks` ↔ `users`)
- `fop_task_team_user` — pivot roster anggota Team (many-to-many `fop_task_teams` ↔ `users`)

## Tabel `fop_tasks`

Tiket kerja FOP. Sumber migrasi: `2026_06_30_000001`, `_153441_add_fields`, `2026_07_01_105316_add_task_id`, `2026_07_01_110148_migrate_category`, `2026_07_06_082621_add_team_id`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `task_id` | FK → `tasks.id` | ✔ | Task eksekusi teknisi yang ter-generate (null kalau belum ada teknisi di-assign) |
| `task_number` | string, unique | | Format `TFOP-{tahun}-{urutan 4 digit}`, e.g. `TFOP-2026-0001` |
| `category` | string(20) | | Enum `App\Enums\TaskType` (SURVEY, PSB, MTN, DEAC, RELOKASI, C-REQ, O-REQ, INFR REQ) |
| `task_date` | datetime | ✔ | Tanggal & waktu tiket dijadwalkan |
| `tugas` | string | | Deskripsi tugas dinamis, e.g. "Survey Pelanggan: Budi" |
| `village_id` | FK → `villages.id` | ✔ | Area desa (`restrict` on delete) |
| `pop_id` | FK → `pops.id` | ✔ | POP/Cabang (`restrict` on delete) |
| `customer_id` | FK → `customers.id` | ✔ | Pelanggan terkait, kalau ada (`null on delete`) |
| `team_id` | FK → `fop_task_teams.id` | ✔ | Team harian penanggung jawab — sekarang **auto-assigned** oleh `FopTaskTeamService::rebuildTeamsForDate()` (lihat [flowchart.md](flowchart.md#5-auto-team-formation-connected-components)), bukan dipilih manual lagi (`set null` on delete) |
| `manual_override_at` | timestamp | ✔ | **Baru** (Task 1, migrasi `2026_07_10_000001`). Kalau terisi, `team_id` task ini adalah hasil drop-in manual FOP (Skenario C2/C3) atau hasil `switch-technician` (Task 2) — `rebuildTeamsForDate()` gak akan nimpa `team_id` task ini sampai teknisinya diganti lagi lewat assignment biasa (`store`/`update`), yang otomatis nge-null-in kolom ini balik |
| `issue` | string | ✔ | Jenis gangguan/keperluan, e.g. "FO CUT", "ODP LOS" |
| `notes` | text | ✔ | Catatan bebas |
| `status` | string(20), default `Proses` | | Enum `App\Enums\FopTaskStatus`: Proses, Pending, Selesai, Cancel |
| `priority` | string(20), default `low` | | Enum `App\Enums\FopTaskPriority`: low, Medium, High, Urgent — dihitung dinamis dari SLA (lihat [flowchart.md](flowchart.md#3-kalkulasi-prioritas-dinamis-sla-based)) |
| `pending_reason` | string | ✔ | Wajib diisi kalau `status = Pending` |
| `client_request_date` | date | ✔ | Wajib diisi kalau `status = Pending` |
| `cancelled_at` | timestamp | ✔ | Waktu pembatalan, di-set kalau `status = Cancel` |
| `created_at` / `updated_at` | timestamp | | |

Index: `status`, `priority`, `category`, `task_date` (**baru**, migrasi `2026_07_10_000002` — dipakai query graf overlap teknisi per hari di `rebuildTeamsForDate()`), `team_id` (implisit dari FK constraint), `work_date` (di tabel Team). Pivot `fop_task_user.user_id` juga diindex (migrasi sama) buat query "teknisi ini lagi kerja di task apa aja hari ini".

## Tabel `fop_task_teams`

Team harian (roster teknisi berlaku 1 hari). Sumber migrasi: `2026_07_06_082619_create`, `2026_07_06_110154_drop_pop_id` (kolom `pop_id` sempat ada, sudah dihapus — Team gak lagi discope per-POP).

**Sejak Task 1, tabel ini gak lagi dikelola manual oleh FOP** — dibuat/di-update/dihapus sepenuhnya oleh `FopTaskTeamService::rebuildTeamsForDate()` (Connected Components algorithm berdasar overlap teknisi per `task_date`). Endpoint manual CRUD (`teamStore`/`teamUpdate`/`teamDestroy` + route `fop-tasks.teams.*`) sudah **dihapus total**.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `name` | string | | **Auto-generated**: `"Team {n}"`, `n` = nomor terkecil yang belum kepakai di tanggal (`work_date`) yang sama (`FopTaskTeamService::nextTeamName()`) — dulu formatnya `"Tim {nama lead}"`/manual, sekarang selalu format ini, gak pernah di-rename manual |
| `work_date` | date | | Tanggal berlaku Team (indexed) |
| `created_by` | FK → `users.id` | ✔ | `set null` on delete — untuk team hasil rebuild otomatis, ini `auth()->id()` FOP yang men-trigger rebuild (lewat create/edit tiket) |
| `created_at` / `updated_at` | timestamp | | |

`isActive()` (derived, bukan kolom): true kalau ada `fop_tasks` dengan `team_id` ini yang status BUKAN Selesai/Cancel. Team yang gak lagi aktif (semua task-nya lepas/selesai/cancel) otomatis **dihapus** oleh `rebuildTeamsForDate()` di step cleanup, bukan cuma dibiarkan jadi "riwayat".

## Tabel pivot `fop_task_user`

Teknisi PIC per tiket (`FopTask::technicians()`).

| Kolom | Tipe |
|-------|------|
| `id` | bigint PK |
| `fop_task_id` | FK → `fop_tasks.id`, cascade delete |
| `user_id` | FK → `users.id`, cascade delete |
| `created_at` / `updated_at` | timestamp |

Unique: (`fop_task_id`, `user_id`).

## Tabel pivot `fop_task_team_user`

Roster anggota Team (`FopTaskTeam::members()`).

| Kolom | Tipe |
|-------|------|
| `id` | bigint PK |
| `fop_task_team_id` | FK → `fop_task_teams.id`, cascade delete |
| `user_id` | FK → `users.id`, cascade delete |
| `created_at` / `updated_at` | timestamp |

Unique: (`fop_task_team_id`, `user_id`).

## SLA per `TaskType` (dipakai kalkulasi prioritas & deadline)

Sumber: `App\Enums\TaskType::slaMinutes()`.

| Kategori | Value | SLA |
|----------|-------|-----|
| Survey Pelanggan | `SURVEY` | 120 menit (2 jam) — atau 1×24 jam kalau belum ada Task tereksekusi, lihat catatan di bawah |
| Pemasangan Baru | `PSB` | 240 menit (4 jam) — atau 3×24 jam kalau belum ada Task tereksekusi |
| Maintenance | `MTN` | 180 menit (3 jam) |
| Ambil Modem | `DEAC` | 60 menit (1 jam) |
| Relokasi/Pemindahan | `RELOKASI` | 240 menit (4 jam) |
| Customer Request | `C-REQ` | 120 menit (2 jam) |
| Office Request | `O-REQ` | 240 menit (4 jam) |
| Infrastruktur Request | `INFR REQ` | 480 menit (8 jam) |

`SURVEY` & `PSB` gak bisa dipilih manual di form (`TaskType::autoOnlyValues()`) — cuma muncul via auto-sync dari data `customers`.

## Model relations (ringkas)

```php
// FopTask
village(): BelongsTo(Village::class)
pop(): BelongsTo(Pop::class)
customer(): BelongsTo(Customer::class)
technicians(): BelongsToMany(User::class, 'fop_task_user', 'fop_task_id', 'user_id')
task(): BelongsTo(Task::class)
team(): BelongsTo(FopTaskTeam::class, 'team_id')

// FopTaskTeam
creator(): BelongsTo(User::class, 'created_by')
members(): BelongsToMany(User::class, 'fop_task_team_user', 'fop_task_team_id', 'user_id')
fopTasks(): HasMany(FopTask::class, 'team_id')
```

## Service: `FopTaskTeamService` (Task 1 & 2)

**File:** `app/Services/FopTaskTeamService.php` — satu-satunya tempat yang boleh nulis `fop_task_teams` (create/update/delete) dan `fop_tasks.team_id`.

- `rebuildTeamsForDate(Carbon $date): array{conflicts: array}` — hitung ulang seluruh struktur Team di 1 tanggal dari graf overlap teknisi (union-find), dipanggil abis tiap perubahan assignment teknisi (`store`/`update`/`assignToTeam`/`switchTechnician`). Juga sinkronin `Task.title` (execution layer) ke nama Team asli via `syncExecutionTaskTitle()` privat.
- `nextTeamName(Carbon $date): string` — generator nama `"Team {n}"` (public, dipakai controller juga buat bikin Team baru manual lewat drop-in).

Lihat [analisa-auto-team.md](analisa-auto-team.md) (algoritma Skenario A/B/C1/C2/C3) dan [analisa-sync-execution-task.md](analisa-sync-execution-task.md) (bugfix + sync ke execution Task) buat detail lengkap.
