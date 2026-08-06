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
- `fop_task_status_history.fop_task_id` → `fop_tasks.id` (cascade delete) — **baru, Task 9**: log transisi status realtime, ditulis otomatis oleh `TaskObserver` tiap kali `Task` eksekusi (`fop_tasks.task_id`) berubah status. Bukan bagian ERD di atas (1 tabel log terpisah, hasMany dari `fop_tasks`), lihat detail di bawah.

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
| `status` | string(20), default `draft` | | **Unifikasi enum (2026-07-20):** enum `App\Enums\FopTaskStatus` (Proses/Pending/Selesai/Cancel, 4 bucket) **DIHAPUS TOTAL** — kolom ini sekarang pakai `App\Enums\TaskStatus` yang SAMA PERSIS dipakai `tasks.status` (draft, terjadwal, in_progress, pending, selesai, dibatalkan — 6 nilai). Kalau `FopTask` punya `task_id` terhubung, nilai kolom ini **full mirror** dari `Task.status` lewat `TaskObserver` (copy langsung, bukan mapping bucket lagi — lihat [Observer: `TaskObserver`](#observer-taskobserver-task-9) di bawah). `FopTask` **standalone** (`task_id` NULL — tiket manual/auto-sync yang belum ada teknisi di-assign) mulai dari `draft`, naik ke `terjadwal` otomatis begitu teknisi di-assign (`TaskService::create()` bikin Task linked). **Cancel/Dibatalkan buat SRV/PSB TERKUNCI dari sisi Task/FopTask** (2026-07-21) — `TaskPolicy::cancel()` block task_type SURVEY/PEMASANGAN buat SEMUA role (termasuk owner, lewat pengecualian di `before()`), dan `FopTaskController::update()` nolak (422) kalau target status `dibatalkan` + category SURVEY/PSB. Satu-satunya jalur sah buat batalin SRV/PSB: `CustomerSurveyController::cancel()` / `CustomerInstallationController::cancel()` (lihat `docs/customer-lifecycle/business-logic.md`) — biar `Customer.status` ikut ke-set `rejected` (masuk List Pelanggan Gagal), bukan cuma Task/FopTask doang. Task_type LAIN (MTN/DEAC/RELOKASI/C-REQ/O-REQ/INFR REQ) tetap bisa `dibatalkan` langsung dari tombol Task/FopTask, gak terikat workflow Customer. **Verifikasi Admin (fix reject-sync gap, desain final):** buat task_type SURVEY/PEMASANGAN, begitu `Task.status=selesai`, kolom ini SELALU `selesai` — gak peduli `Task.fop_review_status` udah `approved`/`rejected` atau masih `pending`. Task (kerjaan lapangan) VS keputusan bisnis (customer diterima/ditolak) itu 2 hal beda, sengaja gak dicampur di nilai status utama — nasib customer cuma beda di label histori granular (`selesai`/`selesai_menunggu_verifikasi`/`selesai_ditolak_verifikasi`) + badge overlay `FopTask::verificationStatus()`, gak pernah ngubah nilai `status` utama. Lihat [flowchart.md § 11](flowchart.md) dan `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL). |
| `priority` | string(20), default `low` | | Enum `App\Enums\FopTaskPriority`: low, Medium, High, Urgent — dihitung dinamis dari SLA (lihat [flowchart.md](flowchart.md#3-kalkulasi-prioritas-dinamis-sla-based)) |
| `pending_reason` | string | ✔ | Wajib diisi kalau `status = pending` |
| `client_request_date` | date | ✔ | **Dua sumber isian, sadari bedanya (2026-07-31).** (a) Kategori **PSB/Pemasangan**: nilai **TURUNAN** dari `customer_surveys.requested_installation_date`, di-refresh tiap `autoSyncAndCalculatePriority()` — jangan diedit manual, akan ditimpa sync berikutnya. (b) Kategori lain: diisi manual lewat alur `status = pending` (wajib) dan di-null-kan tiap status keluar dari Pending. **Sejak Task 8**, juga dipakai buat sorting antrian di `index()` (lihat [flowchart.md](flowchart.md#8-antrian-sorting-berdasarkan-client_request_date-task-8)) dan badge "JADWAL HARI INI"/"Terjadwal — {tanggal}" di `fop_tasks/index.blade.php`. **Catatan teknis:** meski kolomnya `date`, nilai yang tersimpan (di kedua driver MySQL & SQLite) punya suffix waktu (`'2026-07-11 00:00:00'`), bukan `'2026-07-11'` murni — perbandingan raw SQL harus pakai `>=` terhadap tanggal besok, BUKAN `>` terhadap tanggal hari ini (`>` selalu true gara-gara suffix waktu itu). |
| `cancelled_at` | timestamp | ✔ | Waktu pembatalan, di-set kalau `status = dibatalkan` |
| `cancel_reason` | text | ✔ | **Baru (Task 12, migrasi `2026_07_22_000001_add_cancel_reason_to_fop_tasks_table`).** Wajib diisi (`required_if:status,dibatalkan`) buat task_type NON-SRV/PSB — SRV/PSB gak pernah nyampe validasi ini (udah ke-block duluan di kolom `status` di atas). Ditampilin di `fop_tasks/history.blade.php` (sebaris badge status) dan `history_detail.blade.php` ("Alasan Cancel" — sebelumnya SALAH baca `pending_reason`, dibenerin bareng Task 12). |
| `handling_sla_hours` | integer | ✔ | **Baru (Task 10, migrasi `2026_07_08_120001`)**. Snapshot batas waktu wajib mulai ditangani (jam), di-freeze saat `FopTask` dibuat. Dua jalur pengisian: (a) `FopTask` dibuat LANGSUNG (SURVEY, PEMASANGAN, MTN/C-REQ manual FOP) → `FopTask::booted()` resolve dari `InternetPackage::getHandlingSla($category)` atau fallback `TaskType::defaultHandlingSlaHours()`; (b) **sejak `2026_08_05`**, `FopTask` lahir dari eskalasi Ticketing (`TicketService::syncToFopTask()`) → nilainya **diwarisi langsung** dari `tickets.sla_hours`, `booted()` skip resolve mandiri (kolom udah gak `null` pas `creating`). Dipakai `FopTask::slaDeadline()`/`slaTotalSeconds()` buat countdown SLA di card FOP — lihat [Master Timeline SLA](../master/sla-timeline) dan [Target SLA Ticketing](../ticketing/business-logic.md#16-target-sla-ticketing). |
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

`isActive()` (derived, bukan kolom): true kalau ada `fop_tasks` dengan `team_id` ini yang status BUKAN `selesai`/`dibatalkan`. Team yang gak lagi aktif (semua task-nya lepas/selesai/cancel) otomatis **dihapus** oleh `rebuildTeamsForDate()` di step cleanup, bukan cuma dibiarkan jadi "riwayat".

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

## Tabel `fop_task_status_history` (Task 9)

**Baru.** Log tiap transisi status realtime `FopTask` yang di-derive dari `Task` eksekusi — ditulis otomatis oleh `TaskObserver`, gak pernah ditulis manual dari controller. Sumber migrasi: `2026_07_17_000001_create_fop_task_status_history_table`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `fop_task_id` | FK → `fop_tasks.id`, cascade delete | | Tiket FOP yang statusnya berubah |
| `from_status` | string(30) | ✔ | Nilai `TaskStatus` SEBELUM transisi (mis. `terjadwal`) |
| `to_status` | string(30) | | **Label granular**, kolom bebas (bukan enum-cast) — buat kasus umum (terjadwal/in_progress/dibatalkan) isinya SAMA PERSIS `TaskStatus->value` mentah (gak ada mapping lagi, unifikasi 2026-07-20). Cuma `pending` & `selesai` yang masih dipecah granular (perlu 2-3 nuansa berbeda per raw value yang sama): `lapor_nanti`, `pending_fop`, `selesai`, `selesai_menunggu_verifikasi`, `selesai_ditolak_verifikasi`. `proses`/`proses_dikerjakan`/`proses_review`/`pending_reschedule`/`cancel` GAK DITULIS LAGI buat transisi baru (bucket lama `FopTaskStatus` dihapus total) tapi tetap ADA di data historis lama — jangan dihapus dari mapping `label()`. Lihat mapping lengkap di [flowchart.md § Status Realtime](flowchart.md#9-status-realtime--sync-task-eksekusi--foptask-task-9). |
| `changed_by` | FK → `users.id`, `null on delete` | ✔ | `auth()->id()` pas transisi terjadi (bisa null kalau dipicu proses tanpa auth context, mis. artisan command) |
| `changed_at` | timestamp | | Waktu transisi (diisi `now()` oleh Observer, bukan `created_at` biar konsisten walau ada delay processing) |
| `created_at` / `updated_at` | timestamp | | |

Index: `fop_task_id`.

**Kenapa `to_status` masih kolom bebas, bukan langsung `TaskStatus->value` semua:** sejak unifikasi (2026-07-20), `fop_tasks.status` UDAH share vocab persis `TaskStatus` — jadi buat mayoritas transisi (`terjadwal`, `in_progress`, `dibatalkan`, `draft`), `to_status` ini sama aja isinya sama `status` raw. Yang masih butuh kolom terpisah cuma `pending` (bedain "Lapor Nanti" vs "Pending biasa dari FOP", walau `fop_tasks.status` sama-sama `pending`) dan `selesai` (bedain "Selesai approved" vs "Selesai nunggu verifikasi" vs "Selesai ditolak verifikasi", walau `fop_tasks.status` sama-sama `selesai`) — 2 kasus many-nuance-per-1-raw-value ini yang gak bisa direpresentasiin cuma dari kolom `status`.

Model: `app/Models/FopTaskStatusHistory.php` — relasi `fopTask(): BelongsTo(FopTask::class)`, `changedByUser(): BelongsTo(User::class, 'changed_by')`, method `label(): string` — mapping AKTIF (2026-07-20): `draft`→Draft, `terjadwal`→Terjadwal, `in_progress`→Sedang Dikerjakan, `lapor_nanti`→Lapor Nanti, `pending_fop`→Pending, `selesai`→Selesai, `selesai_menunggu_verifikasi`→Selesai — Menunggu Verifikasi, `selesai_ditolak_verifikasi`→Selesai — Ditolak Verifikasi, `dibatalkan`→Dibatalkan. Default fallback: tampilin `to_status` mentah kalau belum ada mapping (termasuk buat data historis lama `proses`/`proses_dikerjakan`/`proses_review`/`pending_reschedule`/`cancel` yang gak lagi punya mapping eksplisit). **Catatan scope (2026-07-15):** method ini SEKARANG cuma dipake buat section "Histori Status" (log audit granular) di halaman Detail Riwayat — BUKAN lagi buat badge status utama di `/fop-tasks`/`/fop-tasks/history`/`/tasks-saya`, yang sekarang seragam pake `TaskStatus::displayLabel()` (lihat `docs/project_status_label_unifikasi.md`).

## Tabel `task_reports` (Task 10 — Riwayat Lengkap + SLA Dual-Cycle)

**Baru.** Nyimpen durasi & SLA pengerjaan per `Task` eksekusi teknisi — independen dari `FopTask`/`fop_task_status_history` (jalan duluan, gak nunggu/butuh `FopTask` terkait ada). Ditulis otomatis oleh `TaskObserver::syncTaskReport()`, gak pernah ditulis manual dari controller. Sumber migrasi: `2026_07_18_000001_create_task_reports_table`.

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint PK | | |
| `task_id` | FK → `tasks.id`, cascade delete | | 1:1 dengan `Task` (`Task::report(): HasOne`) |
| `started_at` | datetime | ✔ | Diisi pas `Task.status` pertama kali jadi `in_progress` (siklus pertama) |
| `pending_at` | datetime | ✔ | Diisi pas `Task.status` masuk `pending`/`reschedule` — siklus kerja ditutup sementara |
| `resumed_at` | datetime | ✔ | Diisi pas teknisi klik "Mulai" lagi setelah pending (siklus ke-2+) |
| `completed_at` | datetime | ✔ | Diisi pas `Task.status` jadi `selesai` |
| `total_duration_minutes` | integer, default 0 | | Akumulasi durasi kerja AKTUAL (menit) — dijumlah tiap siklus ditutup (`resumed_at ?? started_at` → `now()`), jeda pending/reschedule sengaja TIDAK ikut kehitung |
| `sla_target_minutes` | integer | ✔ | Snapshot target SLA (menit) dari `Task.sla_minutes` pas siklus pertama mulai |
| `sla_status` | string | ✔ | `on_time` / `over` — dihitung pas `completed_at` diisi (`total_duration_minutes <= sla_target_minutes`) |
| `sla_overrun_minutes` | integer | ✔ | Selisih menit kalau `sla_status = over`, else 0 |
| `created_at` / `updated_at` | timestamp | | |

Model: `app/Models/TaskReport.php` — relasi `task(): BelongsTo(Task::class)`, method `accumulatedDurationMinutes(): int` (durasi live termasuk siklus yang lagi jalan, dipakai tampilan real-time di halaman Detail Riwayat sebelum `completed_at` keisi).

**Ditampilin di:** halaman Detail Riwayat (`GET /fop-tasks/history/{fop_task}` → `FopTaskController::showHistory()` → `resources/views/fop_tasks/history_detail.blade.php`, section "Durasi & SLA Pengerjaan") — beserta section "Info Task", "Detail Registrasi" (**baru 2026-07-20** — data pelanggan live dari `Customer`+relasi, buat SRV/PSB/MTN-C-REQ native; controller eager-load `customer.pop`/`customer.customerAddress`/`customer.customerTechnicalDetail`/`customer.internetPackage`/`customer.customerDevice`), "Detail Ticket" (MTN/C-REQ asal Ticketing, snapshot dari `Ticket`, section terpisah — mutually exclusive sama Detail Registrasi), "Laporan" (baca langsung dari `CustomerSurvey`/`CustomerInstallation`/`TaskMaintenance` sesuai `task_type`, BUKAN duplikasi ke `task_reports`), dan "Histori Status" (list `fop_task_status_history`, terurut terbaru dulu — audit trail granular per perubahan status). Detail lengkap tiap section: `docs/fop-task/flowchart.md` § 10.

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
statusHistories(): HasMany(FopTaskStatusHistory::class)   // Task 9, orderByDesc('changed_at')
verificationStatus(): ?string                             // fix reject-sync gap — 'pending'|'approved'|'rejected'|null, gak pernah dipakai buat query filter. Sejak 2026-07-15 UDAH GAK ditampilin lagi di UI Riwayat (dicabut, bikin ambigu) — method masih ada, cuma gak dipanggil dari blade lagi.

// FopTaskTeam
creator(): BelongsTo(User::class, 'created_by')
members(): BelongsToMany(User::class, 'fop_task_team_user', 'fop_task_team_id', 'user_id')
fopTasks(): HasMany(FopTask::class, 'team_id')

// FopTaskStatusHistory (Task 9)
fopTask(): BelongsTo(FopTask::class)
changedByUser(): BelongsTo(User::class, 'changed_by')

// Task (execution, app/Models/Task.php)
report(): HasOne(TaskReport::class)                       // Task 10, dual-cycle SLA

// TaskReport (Task 10)
task(): BelongsTo(Task::class)
```

## Observer: `TaskObserver` (Task 9)

**File:** `app/Observers/TaskObserver.php`, registered via `Task::observe(TaskObserver::class)` di `AppServiceProvider::boot()`.

Hook `updated(Task $task)` — fire tiap kali model `Task` (eksekusi teknisi) di-save dengan perubahan di kolom `status`, `report_deferred`, atau `fop_review_status`. Cari `FopTask` terkait (`FopTask::where('task_id', $task->id)`), skip total kalau gak ketemu (Task yang bukan hasil FOP-flow, mis. task manual admin, gak kena efek apa-apa) ATAU kalau `FopTask.status` udah `dibatalkan` (proteksi override manual FOP, lihat Task 12).

**Unifikasi enum (2026-07-20):** `resolveTarget()` udah GAK mapping bucket lagi — target status SEKARANG SELALU `$task->status` apa adanya (copy langsung, `FopTask` share vocab persis `TaskStatus`). Yang masih di-`match()` cuma `to_status` (label histori granular), sesuai tabel di bawah:

| `Task.status` | Kondisi tambahan | `FopTask.status` target | `to_status` (histori) |
|---|---|---|---|
| `terjadwal` | | `terjadwal` (mirror) | `terjadwal` |
| `in_progress` | | `in_progress` (mirror) | `in_progress` |
| `pending` | `report_deferred = true` (Task 6, Lapor Nanti) | `pending` (mirror) | `lapor_nanti` |
| `pending` | `report_deferred = false` (teknisi top-level ATAU `fopPending` — **1 logic sejak 2026-07-15**, `TaskStatus::RESCHEDULE` dihapus) | `pending` (mirror) | `pending_fop` |
| `selesai` | `fop_review_status = approved` | `selesai` (mirror) | `selesai` |
| `selesai` | task_type SURVEY/PEMASANGAN, `fop_review_status = rejected` **(fix reject-sync gap, desain final)** | `selesai` (mirror) | `selesai_ditolak_verifikasi` |
| `selesai` | task_type SURVEY/PEMASANGAN ATAU task_type LAIN, `fop_review_status = pending` (nunggu direview) **(unifikasi 2026-07-20 — dulu task_type LAIN didemosikan ke bucket `Proses`/`proses_review`, SEKARANG SAMA kayak SURVEY/PEMASANGAN: tetap `selesai`)** | `selesai` (mirror) | `selesai_menunggu_verifikasi` |
| `dibatalkan` | | `dibatalkan` (mirror) | `dibatalkan` |

**Perubahan besar vs versi lama:** dulu Task `selesai` + `fop_review_status=pending` buat task_type NON-SURVEY/PEMASANGAN (MTN/dst) bikin `FopTask` didemosikan balik ke bucket `Proses` (label "Perlu Review") — supaya dashboard FOP gak nganggep tugasnya kelar padahal laporannya belum ditinjau. Sejak unifikasi enum, demosi ini DIHAPUS — `FopTask.status` SELALU mirror `Task.status` apa adanya (kalau Task-nya `selesai`, FopTask-nya `selesai`, titik). Nuansa "laporan masih ditinjau" sekarang MURNI badge overlay di UI (label histori `selesai_menunggu_verifikasi` + `FopTask::verificationStatus()`), BUKAN status/kolom terpisah — konsisten sama prinsip "1 sumber kebenaran" yang udah dipakai buat SURVEY/PEMASANGAN dari awal.

Efek: `FopTask.status` di-update (idempotent, cuma nulis kalau nilainya beda) + 1 baris baru selalu ditulis ke `fop_task_status_history` (`from_status`/`to_status`/`changed_by`/`changed_at`) tiap ada transisi yang match `wasChanged(['status','report_deferred','fop_review_status'])`.

**Catatan Riwayat vs antrian aktif:** Task/FopTask `selesai` → otomatis masuk Riwayat (`whereIn(status, [selesai, dibatalkan])`) dan otomatis KELUAR dari antrian aktif (`whereNotIn(status, [selesai, dibatalkan])`) — **gak butuh query exclusion tambahan sama sekali**. `FopTask::verificationStatus()` (method masih ada di model, dipakai buat kebutuhan lain/test) TAPI **udah gak ditampilin lagi sebagai badge "Verifikasi: Menunggu/Ditolak" di UI Riwayat** (2026-07-15, dianggap bikin ambigu — lihat `docs/project_status_label_unifikasi.md`). Badge status utama di Riwayat sekarang cuma nampilin `TaskStatus::displayLabel()` polos ("Selesai"/"Dibatalkan") — nasib keputusan customer TETAP kesimpen di `fop_review_status` + `fop_task_status_history` buat audit, cuma gak nongol jadi badge terpisah. Keputusan approve/reject sebenernya tetep di Customer module, bukan di modul FopTask.

**Cancel SRV/PSB terkunci (2026-07-21):** `TaskPolicy::cancel()` block `task_type` SURVEY/PEMASANGAN buat SEMUA role (`before()` sengaja dikecualiin buat ability `cancel`, jadi owner/wildcard permission TETAP kena guard ini). `FopTaskController::update()` punya guard sama persis (422) kalau target status `dibatalkan` + category SURVEY/PSB. Satu-satunya jalur sah: `CustomerSurveyController::cancel()` (tab Survey halaman Customer + tombol "Batalkan" di `/surveys/queue`) dan `CustomerInstallationController::cancel()` (tab Pemasangan halaman Customer) — keduanya manggil `TaskService::cancel()` + `CustomerWorkflowService::transition(REJECTED)` dalam 1 transaksi, biar Task/FopTask DAN Customer.status konsisten sekaligus (masuk List Pelanggan Gagal). Lihat `docs/customer-lifecycle/business-logic.md`.

**Bukan bagian dari Observer:** `team_id`, pivot teknisi, dan trigger `rebuildTeamsForDate()` — itu tetap tanggung jawab controller yang mengubah `Task` (`TaskController::releaseTeamAndSetPending()`, dipanggil dari `reschedule()` DAN `pending()` — 2026-07-15, lihat § Task 7 di `analisa-auto-team.md`), Observer murni sinkron kolom `status` + tulis histori.

**Cancel task_type NON-SRV/PSB dengan alasan (Task 12, 2026-07-22):** `FopTaskController::update()` — target status `dibatalkan` butuh 2 hal ekstra di luar guard SRV/PSB di atas: (1) `cancel_reason` wajib diisi (`required_if:status,dibatalkan`), (2) permission eksplisit `fop_tasks.cancel` (bukan cuma `fop_tasks.update` biasa — abort 403 kalau gak ada). Alasan yang diisi FOP diteruskan APA ADANYA ke `TaskService::cancel($linkedTask, $actor, $validated['cancel_reason'])` (dulu hardcoded pesan generik `"Dibatalkan dari Task FOP {nomor}."`) — jadi `Task.cancel_reason` dan `FopTask.cancel_reason` sekarang SAMA, bukan 2 alasan beda. `rebuildTeamsForDate()` gak butuh kode tambahan buat "mecahin team pas cancel" — udah otomatis kepanggil unconditional di akhir `update()` (selama `task_date` ada), dan karena task yang `dibatalkan` otomatis ke-exclude dari query aktif rebuild (`whereNotIn(status,[selesai,dibatalkan])`), team yang cuma ditempatin task itu ikut kehapus di step cleanup.

**Notifikasi cancel task in_progress (Task 12):** `TaskService::cancel()` sekarang cek status Task SEBELUM diubah — kalau `in_progress`, panggil `notifyTeam()` (private method yang sama dipakai buat notif "Task baru dijadwalkan"/"Jadwal diubah") ke semua anggota tim, pesan `"Task dibatalkan: {alasan}"`, `eventType='cancelled'` (notifikasi jenis `error` di `AppNotification`). Task `terjadwal` yang dibatalkan (belum ada teknisi kerja) TIDAK memicu notifikasi apapun — sengaja, gak ada yang perlu dikabarin. Ini jalan buat SEMUA jalur cancel (Task 12 langsung, maupun `CustomerSurveyController::cancel()`/`CustomerInstallationController::cancel()` yang juga manggil `TaskService::cancel()` sama persis) — 1 titik logic, gak diduplikasi.

## Service: `FopTaskTeamService` (Task 1 & 2)

**File:** `app/Services/FopTaskTeamService.php` — satu-satunya tempat yang boleh nulis `fop_task_teams` (create/update/delete) dan `fop_tasks.team_id`.

- `rebuildTeamsForDate(Carbon $date): array{conflicts: array}` — hitung ulang seluruh struktur Team di 1 tanggal dari graf overlap teknisi (union-find), dipanggil abis tiap perubahan assignment teknisi (`store`/`update`/`assignToTeam`/`switchTechnician`). Juga sinkronin `Task.title` (execution layer) ke nama Team asli via `syncExecutionTaskTitle()` privat.
- `nextTeamName(Carbon $date): string` — generator nama `"Team {n}"` (public, dipakai controller juga buat bikin Team baru manual lewat drop-in).

Lihat [analisa-auto-team.md](analisa-auto-team.md) (algoritma Skenario A/B/C1/C2/C3) dan [analisa-sync-execution-task.md](analisa-sync-execution-task.md) (bugfix + sync ke execution Task) buat detail lengkap.
