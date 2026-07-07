# Flowchart — Modul FOP Task

## 1. Status Tiket `FopTask`

```
                 ┌──────────┐
   create ──────▶│  Proses  │◀────────────┐
                 └────┬─────┘              │
                      │                    │ set status=Proses
       set status=Pending                  │ (clear pending_reason,
       (wajib isi pending_reason           │  client_request_date,
        + client_request_date)             │  cancelled_at)
                      ▼                    │
                 ┌──────────┐              │
                 │ Pending  │──────────────┘
                 └────┬─────┘
                      │
        ┌─────────────┼─────────────┐
        ▼                           ▼
   ┌──────────┐               ┌──────────┐
   │ Selesai  │               │  Cancel  │
   └──────────┘               └──────────┘
   (cancelled_at=null)        (cancelled_at=now())
```

- `Proses` → `Cancel` juga valid (tiket dibatalkan tanpa lewat Pending).
- `Cancel` → `Selesai` valid (reopen/koreksi) — `cancelled_at` di-null-kan lagi.
- Status hidup di enum `App\Enums\FopTaskStatus`.

## 2. Auto-Sync Customer → FopTask (jalan tiap `GET /fop-tasks`)

```
FopTaskController::autoSyncAndCalculatePriority()
│
├─ 1. Customer status IN (calon_pelanggan, waiting_survey, registered)
│     AND belum punya FopTask kategori Survey yang aktif (Proses/Pending)
│     → buat FopTask baru, category=Survey, priority=Medium (sementara)
│
├─ 2. Customer status IN (waiting_installation, waiting_installations, surveyed)
│     AND belum punya FopTask kategori PSB yang aktif
│     → buat FopTask baru, category=PSB, priority=Medium (sementara)
│
└─ 3. Recalculate priority semua FopTask aktif kategori Survey/PSB
      berdasar sisa waktu SLA (lihat bagian 3)
```

## 3. Kalkulasi Prioritas Dinamis (SLA-based)

```
                     hitung % sisa SLA
                            │
     percentage = (sisa_detik / total_detik) * 100
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
  percentage < 0      percentage ≤ 25      percentage ≤ 50     lainnya
  (SLA lewat)         (mepet)              (setengah jalan)    (masih longgar)
        │                   │                   │                   │
        ▼                   ▼                   ▼                   ▼
     Urgent               High               Medium                Low
```

SLA per kategori:
- **Survey** (belum ada Task tereksekusi): `customer.created_at + 1 hari`, total 86400 detik.
- **PSB/Pemasangan** (belum ada Task): `survey.completed_at` (fallback `customer.updated_at`) `+ 3 hari`, total 259200 detik.
- **Tipe lain / udah ada Task tereksekusi**: `task.scheduled_at + TaskType::slaMinutes()` (lihat tabel di [database-schema.md](database-schema.md)).

Method sumber: `FopTask::slaDeadline()`, `FopTask::slaTotalSeconds()`.

## 4. Assignment Teknisi → Auto-buat `Task` Eksekusi

```
FOP assign teknisi ke FopTask (store/update)
        │
        ▼
  technicians()->sync($ids)   [pivot fop_task_user]
        │
        ▼
  fop_task.task_id kosong? ──── ya ──▶ TaskService::create() → Task baru
        │ tidak                              │
        ▼                                    ▼
  TaskService::update($task, ...)     fop_task.task_id = task.id
```

- Judul Task auto-prefix `[Tim <nama>]` kalau lebih dari 1 teknisi.
- `conflict_override: true` — assignment FOP task gak dicek bentrok jadwal kayak Task manual biasa.

## 5. Lifecycle `FopTaskTeam` (Team Harian)

```
FOP bikin Team (nama opsional, work_date, member_ids)
        │
        ▼
  cek konflik: teknisi udah di team aktif lain
  di tanggal sama? ──── ya ──▶ tolak (422, pesan konflik)
        │ tidak
        ▼
  Team dibuat, roster tersimpan (fop_task_team_user)
        │
        ▼
  FOP assign FopTask satu-satu ke anggota Team (manual, bukan auto-split)
        │
        ▼
  Team dianggap AKTIF selama ada FopTask dgn team_id ini
  yang status BUKAN Selesai/Cancel (termasuk Pending)
        │
        ▼
  semua FopTask di Team itu Selesai/Cancel → Team jadi RIWAYAT (derived, gak ada kolom fisik)
```

Catatan: kalau FopTask berstatus Pending nyambung ke hari berikutnya, Team lama tetap aktif meski `work_date` udah lewat — jalan paralel sama Team baru hari itu, sampai tiket pending-nya ditutup.

## 6. Overview Halaman

```
/fop  (Dashboard)                     /fop-tasks (Kelola Tiket + Team)
┌─────────────────────────┐           ┌─────────────────────────────┐
│ Stat cards (antrean,     │           │ Filter (search/kategori/     │
│ perlu aksi, overdue)     │           │ status/prioritas/desa/team)  │
│                          │──────────▶│                              │
│ Team FOP Aktif (card)    │  "Kelola  │ Tabel tiket aktif            │
│  → klik buka detail team │   Team →" │ Modal create/edit tiket      │
│                          │           │ Panel Kelola Team             │
│ Antrean survey, teknisi  │           │  (create/edit/delete roster) │
└─────────────────────────┘           └──────────────┬───────────────┘
                                                       │
                                                       ▼
                                        /fop-tasks/history
                                        (tiket Selesai/Cancel, filter sama)
```
