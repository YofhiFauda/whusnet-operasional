# Modul FOP Task (Field Operations Planner)

Modul operasional lapangan: FOP (koordinator lapangan) kelola tiket kerja teknisi (survey, pemasangan, maintenance, dll), bentuk **Team harian**, assign teknisi per tiket, pantau progress lewat dashboard.

> **Riwayat sprint lama** (Kanban board, Calendar scheduler, desain awal Team) ada di [`archive/`](archive/) — kode-nya udah diganti arsitektur di bawah ini. Jangan dipakai sebagai referensi kode aktif.

## Konsep Inti

Modul ini punya **2 entity task yang beda tapi nyambung**:

| Entity | Peran | Contoh |
|--------|-------|--------|
| `FopTask` | Tiket kerja FOP — 1 row = 1 pekerjaan yang perlu diselesaikan (nomor `TFOP-2026-0001`) | "Survey Pelanggan: Budi", "Perbaikan ODP LOS Desa X" |
| `Task` | Task eksekusi teknisi (jadwal, checklist, laporan, evidence foto) | Task yang dikerjakan teknisi di lapangan |

`FopTask` auto-generate `Task` begitu ada teknisi di-assign (lewat `TaskService::create()`), disimpan di `fop_tasks.task_id`. FOP kerja di level `FopTask` (bikin/atur tiket + team); teknisi kerja di level `Task` (checklist, submit laporan) — lihat [task-workflow (archive)](archive/task-workflow.md) utk detail approval flow Task, itu masih berlaku.

**Team** (`FopTaskTeam`) = roster teknisi yang berlaku 1 hari (bisa nyambung ke hari berikutnya kalau ada tiket Pending). 1 Team bisa pegang banyak `FopTask` sekaligus; assignment teknisi ke tiket tetap manual per-tiket (bukan auto-split).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [flowchart.md](flowchart.md) | Alur status tiket, auto-sync, prioritas SLA, lifecycle Team |
| [user-flow.md](user-flow.md) | Langkah FOP di `/fop`, `/fop-tasks`, `/fop-tasks/history` |
| [database-schema.md](database-schema.md) | Tabel, kolom, relasi, migrasi |
| [fop-dashboard.md](fop-dashboard.md) | Detail dashboard `/fop` (stat card, Team FOP Aktif, antrean survey) |
| [archive/](archive/) | Dokumen sprint lama (Kanban, Calendar, desain awal Team, dll) — historis |

## Routes & Permission

| Route | Method | Permission | Controller |
|-------|--------|------------|------------|
| `/fop` | GET | `task.view.all` (policy) | `FopDashboardController@index` |
| `/api/fop/pipeline` | GET | `task.view.all` | `FopDashboardController@pipeline` |
| `/fop-tasks` | GET | `fop_tasks.view` | `FopTaskController@index` |
| `/fop-tasks/history` | GET | `fop_tasks.view` | `FopTaskController@history` |
| `/fop-tasks` | POST | `fop_tasks.create` | `FopTaskController@store` |
| `/fop-tasks/{fop_task}` | PUT | `fop_tasks.update` | `FopTaskController@update` |
| `/fop-tasks/{fop_task}` | DELETE | `fop_tasks.delete` | `FopTaskController@destroy` |
| `/fop-tasks/teams` | POST | `fop_tasks.create` | `FopTaskController@teamStore` |
| `/fop-tasks/teams/{team}` | PUT | `fop_tasks.update` | `FopTaskController@teamUpdate` |
| `/fop-tasks/teams/{team}` | DELETE | `fop_tasks.delete` | `FopTaskController@teamDestroy` |

**RBAC catatan khusus:** ubah `category` (tipe tiket) & `priority` cuma boleh user dengan permission `fop_tasks.update_sensitive` (lihat `FopTaskController::update()`). Tipe `Survey` & `PSB` (Pemasangan) gak bisa dipilih manual — cuma via auto-sync dari Registrasi Pelanggan (`TaskType::autoOnlyValues()`).

## Views

- `resources/views/fop/dashboard.blade.php` — dashboard utama
- `resources/views/fop_tasks/index.blade.php` — daftar tiket aktif (Proses/Pending) + modal create/edit + panel Kelola Team
- `resources/views/fop_tasks/history.blade.php` — riwayat tiket Selesai/Cancel

## Teknologi

| Komponen | Stack |
|----------|-------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade, Alpine.js, Tailwind + design system CSS vars |
| Database | MySQL — `fop_tasks`, `fop_task_teams`, `fop_task_user`, `fop_task_team_user` |

---

**Last updated:** 2026-07-07
