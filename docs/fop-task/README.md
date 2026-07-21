# Modul FOP Task (Field Operations Planner)

Modul operasional lapangan: FOP (koordinator lapangan) kelola tiket kerja teknisi (survey, pemasangan, maintenance, dll), bentuk **Team harian**, assign teknisi per tiket, pantau progress lewat dashboard.

> **Riwayat sprint lama** (Kanban board, Calendar scheduler, desain awal Team) ada di [`archive/`](archive/) — kode-nya udah diganti arsitektur di bawah ini. Jangan dipakai sebagai referensi kode aktif.

## Konsep Inti

Modul ini punya **2 entity task yang beda tapi nyambung**:

| Entity | Peran | Contoh |
|--------|-------|--------|
| `FopTask` | Tiket kerja FOP — 1 row = 1 pekerjaan yang perlu diselesaikan (nomor `TFOP-2026-0001`) | "Survey Pelanggan: Budi", "Perbaikan ODP LOS Desa X" |
| `Task` | Task eksekusi teknisi (jadwal, checklist, laporan, evidence foto) | Task yang dikerjakan teknisi di lapangan |

`FopTask` auto-generate `Task` begitu ada teknisi di-assign (lewat `TaskService::create()`), disimpan di `fop_tasks.task_id`. FOP kerja di level `FopTask` (bikin/atur tiket); teknisi kerja di level `Task` (checklist, submit laporan) — lihat [task-workflow (archive)](archive/task-workflow.md) utk detail approval flow Task, itu masih berlaku.

Status `FopTask` (Task 9) di-derive otomatis dari `Task` eksekusi lewat `TaskObserver`, plus (Task 10) tiap Task selesai kerja dicatat durasi & SLA-nya di tabel `task_reports` (dual-cycle: akumulasi durasi kerja aktual, exclude jeda pending) — bisa dilihat lengkap di halaman Detail Riwayat. **Prinsip fix reject-sync gap (2026-07-14):** Task (kerjaan lapangan) VS keputusan bisnis (customer diterima/ditolak) itu 2 hal beda — begitu teknisi selesai kerja (tiket kategori Survey/Pemasangan), `FopTask` LANGSUNG `Selesai` (gak nangkring di antrian aktif), nasib customer (Menunggu/Diterima/Ditolak) tampil sebagai badge KEDUA di Riwayat, gak pernah ngubah status utama. Detail: `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL).

**Team** (`FopTaskTeam`) = roster teknisi yang berlaku 1 hari (bisa nyambung ke hari berikutnya kalau ada tiket Pending). **Sejak Task 1 (Auto-Team Formation), Team gak lagi dibuat manual** — kebentuk/berubah sendiri lewat `FopTaskTeamService::rebuildTeamsForDate()` berdasar graf overlap teknisi per tiket (siapa kerja bareng siapa hari itu). FOP cuma perlu drop-in manual (`assign-to-team`) buat kasus solo/konflik, atau pakai **Switch Teknisi** (Task 2) buat mindahin teknisi antar Team dalam 1 submit atomic. Detail algoritma & rasional di [analisa-auto-team.md](analisa-auto-team.md) dan [analisa-sync-execution-task.md](analisa-sync-execution-task.md).

**Integrasi Ticketing (2026-07-23/24):** `FopTask` category MTN & C-REQ bisa punya `ticket` terkait (`FopTask::ticket()`, hasOne ke `Ticket`) — hasil auto-sync dari tiket internal perusahaan yang diajukan helpdesk/NOC/sales, atau dari FOP sendiri yang submit langsung dari modal "Tambah Task FOP" (mode Ticketing otomatis nyala kalau kategori MTN/C-REQ dipilih, termasuk pas Edit kalau tiket udah nyambung). Detail lengkap: [docs/ticketing/README.md](../ticketing/README.md).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [flowchart.md](flowchart.md) | Alur status tiket, auto-sync, prioritas SLA, auto-team formation (Task 1), switch teknisi (Task 2) |
| [user-flow.md](user-flow.md) | Langkah FOP di `/fop`, `/fop-tasks`, `/fop-tasks/history` |
| [database-schema.md](database-schema.md) | Tabel, kolom, relasi, migrasi |
| [fop-dashboard.md](fop-dashboard.md) | Detail dashboard `/fop` (stat card, Team FOP Aktif, antrean survey) |
| [analisa-auto-team.md](analisa-auto-team.md) | Analisa kebutuhan & Sprint Backlog Task 1 (Auto-Team) & Task 2 (Switch Teknisi) |
| [analisa-sync-execution-task.md](analisa-sync-execution-task.md) | Bugfix Task 1/2 + sync `FopTaskTeam` ↔ execution `Task` |
| [perbandingan-assign-to-team-vs-switch-technician.md](perbandingan-assign-to-team-vs-switch-technician.md) | `assignToTeam()` (Task 1) vs `switchTechnician()` (Task 2) — beda implementasi, cara nguji, kapan pakai yang mana |
| [../project_verifikasi_reject_gap.md](../project_verifikasi_reject_gap.md) | Fix: task ditolak verifikasi nyangkut di antrian aktif → desain final: Task selalu `Selesai`, keputusan customer jadi badge kedua overlay di Riwayat |
| [archive/](archive/) | Dokumen sprint lama (Kanban, Calendar, desain awal Team manual, dll) — historis, arsitektur udah diganti |

## Routes & Permission

| Route | Method | Permission | Controller |
|-------|--------|------------|------------|
| `/fop` | GET | `task.view.all` (policy) | `FopDashboardController@index` |
| `/api/fop/pipeline` | GET | `task.view.all` | `FopDashboardController@pipeline` |
| `/fop-tasks` | GET | `fop_tasks.view` | `FopTaskController@index` |
| `/fop-tasks/history` | GET | `fop_tasks.view` | `FopTaskController@history` |
| `/fop-tasks/history/{fop_task}` | GET | `fop_tasks.view` | `FopTaskController@showHistory` — **Task 10**, Detail Riwayat (Info Task, Durasi & SLA dual-cycle, Laporan, Histori Status) |
| `/fop-tasks` | POST | `fop_tasks.create` | `FopTaskController@store` |
| `/fop-tasks/{fop_task}` | PUT | `fop_tasks.update` (+ `fop_tasks.cancel` kalau target `status=dibatalkan`, Task 12) | `FopTaskController@update` |
| `/fop-tasks/{fop_task}` | DELETE | `fop_tasks.delete` | `FopTaskController@destroy` |
| `/fop-tasks/{fop_task}/assign-to-team` | POST | `fop_tasks.update` | `FopTaskController@assignToTeam` — drop-in manual Team (solo/konflik) |
| `/fop-tasks/switch-technician` | POST | `fop_tasks.update` | `FopTaskController@switchTechnician` — **Task 2**, switch teknisi antar Team 1x-submit |

> Route CRUD Team manual (`fop-tasks.teams.store/update/destroy`) **udah dihapus total sejak Task 1** — 404 kalau diakses. Team gak lagi punya endpoint mutasi sendiri, semuanya derived dari assignment teknisi.

**RBAC catatan khusus:** ubah `category` (tipe tiket) & `priority` cuma boleh user dengan permission `fop_tasks.update_sensitive` (lihat `FopTaskController::update()`). Tipe `Survey` & `PSB` (Pemasangan) gak bisa dipilih manual — cuma via auto-sync dari Registrasi Pelanggan (`TaskType::autoOnlyValues()`). Cancel (`status=dibatalkan`) butuh permission terpisah `fop_tasks.cancel` (**Task 12, 2026-07-22**) + `cancel_reason` wajib diisi — role `admin`/`fop` dapet otomatis lewat wildcard `fop_tasks.*`. Cascade cancel ke `Task` eksekusi (waktu FopTask yang punya `task_id` dibatalkan) lewat `TaskPolicy::cancelViaFopTask()` — otoritasnya tetap `fop_tasks.cancel`, BUKAN `task.cancel` (role `admin` gak punya `task.cancel` tapi harus tetap bisa cancel tiket), detail rasional di [docs/ticketing/business-logic.md §8](../ticketing/business-logic.md#8-rbac-pembatalan--3-lapis-bukan-satu).

## Views

- `resources/views/fop/dashboard.blade.php` — dashboard utama
- `resources/views/fop_tasks/index.blade.php` — daftar tiket aktif (draft/terjadwal/in_progress/pending) + modal create/edit + modal drop-in Team + modal konflik Team (C3) + modal Switch Teknisi. **Panel "Kelola Team" manual udah dihapus.** Tombol Cancel disembunyikan buat kategori Survey/PSB (2026-07-21, lihat flowchart.md § 12).
- `resources/views/fop_tasks/history.blade.php` — riwayat tiket: Selesai / Dibatalkan (kolom Status polos) + kolom kedua **Verifikasi** (Menunggu/Diterima/Ditolak, overlay khusus Survey/Pemasangan, fix reject-sync gap) — filter Status & Verifikasi independen
- `resources/views/fop_tasks/history_detail.blade.php` — **Task 10**, detail 1 tiket (Info Task, Durasi & SLA Pengerjaan dual-cycle, Laporan, Histori Status)

## Teknologi

| Komponen | Stack |
|----------|-------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade, Alpine.js, Tailwind + design system CSS vars |
| Database | MySQL — `fop_tasks`, `fop_task_teams`, `fop_task_user`, `fop_task_team_user` |

---

## Pola Redirect (PRG)

Assign team / switch teknisi → redirect ke `fop-tasks.index` (papan kerja). Ini **pengecualian sadar**
dari aturan "create/update → Detail": FOP Task **tidak punya halaman detail** (hanya index + edit modal
+ history), papan `fop-tasks.index` adalah surface kerjanya. Aturan lengkap:
**[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

---

**Last updated:** 2026-07-24 (integrasi penuh Ticketing: modal create/edit ikut mode Ticketing buat MTN/C-REQ, Detail Task nampilin section "Detail Ticket", cascade cancel lewat `TaskPolicy::cancelViaFopTask()`, fix bug FopTask Draft gak naik status meski udah di-assign teknisi — lihat [docs/ticketing/](../ticketing/README.md))
