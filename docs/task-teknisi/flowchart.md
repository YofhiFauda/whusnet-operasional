# Flowchart — Task Teknisi

## 1. Status Task

```
                    ┌─────────┐
   create ─────────▶│  draft  │ (jarang, cuma kalau tim/jadwal kosong keduanya... 
                    └────┬────┘  sebenarnya create selalu isi salah satu → pending/terjadwal)
                         │
              ┌──────────┴──────────┐
              ▼                     ▼
        ┌───────────┐        ┌───────────┐
        │ terjadwal │◀──────▶│  pending  │
        └─────┬─────┘        └─────┬─────┘
              │ start()             │ start() (fallback: MTN atau Survey/PSB pending)
              ▼                     │
        ┌─────────────┐            │
        │ in_progress │◀───────────┘
        └──────┬──────┘
               │
     ┌─────────┼─────────┐
     ▼         ▼         ▼
 complete()  setPending() cancel()
     │         │         │
     ▼         ▼         ▼
 ┌────────┐ ┌─────────┐ ┌─────────────┐
 │ selesai│ │ pending │ │ dibatalkan  │ (final)
 └────────┘ └─────────┘ └─────────────┘
```

## 2. Create Task (via `TaskService::create()`)

```
FOP isi data Task (title, task_type, pop_id, customer_id?, team_member_ids?, scheduled_at?)
        │
        ▼
generate task_number (TASK-YYYY-NNNN)
        │
        ▼
   team_member_ids terisi DAN scheduled_at terisi?
        │
        ├─ ya → status = terjadwal
        └─ tidak → status = pending
        │
        ▼
Task::create() — sla_minutes = TaskType::slaMinutes()
        │
        ▼
Simpan TaskTeam per anggota (index 0 = lead, sisanya teknisi)
        │
        ▼
team_member_ids ada isinya? ──ya──▶ notifyTeam() — in-app notif + broadcast TaskScheduled
        │
        ▼
AuditLog::log('created')
```

## 3. Mulai Task (`TaskService::start()` / `TaskStatusController::start()`)

```
Teknisi klik "Mulai" (POST /tasks/{task}/start)
        │
        ▼
Policy statusStart: canTransitionTo(user, task, 'in_progress') via WorkflowTransitionPermission
   GAGAL? → fallback: task_type=MAINTENANCE atau status in [terjadwal,pending]
             DAN user punya task.status.start
        │
        ▼
   isMember(user)? ──tidak──▶ TOLAK (403)
        │ ya
        ▼
status != terjadwal? ──ya──▶ TOLAK 422 "hanya bisa dimulai dari Terjadwal"
        │ tidak
        ▼
scheduled_at di masa depan (belum hari-H)? ──ya──▶ TOLAK 422
        │ tidak
        ▼
cek konflik: ada anggota tim yang lagi pegang Task LAIN status in_progress?
        │
        ├─ ya ──▶ TOLAK "selesaikan/pending-kan task sebelumnya"
        └─ tidak
              ▼
        status=in_progress, started_at=now()
        broadcast TaskStarted
```

## 4. Selesaikan Task (`TaskService::complete()`)

```
Teknisi klik "Selesai" (POST /tasks/{task}/complete)
        │
        ▼
Policy statusComplete: canTransitionTo(...,'selesai')
   GAGAL? → fallback: status=pending DAN task_type in [SURVEY,PEMASANGAN] → izinkan
        │
        ▼
isMember(user)? ──tidak──▶ TOLAK
        │ ya
        ▼
status in [in_progress, pending]? ──tidak──▶ TOLAK 422
        │ ya
        ▼
canComplete()? (saat ini SELALU true — placeholder, gak ada hard check evidence)
        │ ya
        ▼
status=selesai, fop_review_status=pending, completed_at=now()
broadcast TaskCompleted
        │
        ▼
Cari semua User role=fop yang scope POP-nya cocok pop_id task
        │
        ▼
Kirim in-app notification "Butuh review Anda" ke tiap FOP itu
        │
        ▼
AuditLog::log('completed')
```

## 5. Review FOP (`TaskController::review()`)

```
FOP buka detail task selesai, pilih aksi: approve | reject | pending
        │
        ▼
Policy review: canTransitionTo(...,'approved') AND status==selesai AND fop_review_status!=approved
        │
   ┌────┴─────────────┬──────────────────┐
   ▼                   ▼                  ▼
 approve             reject             pending
   │                   │                  │
   ▼                   ▼                  ▼
task_type==PEMASANGAN?  status=in_progress  status=pending
   │                    fop_review_status   fop_review_status
   ├─ ya → TOLAK          =rejected          =pending
   │       "wajib lewat   reject_reason      pending_reason
   │       Verifikasi        │                  │
   │       Admin"        task_type==SURVEY?   notify tim
   │                       → Customer→
   └─ tidak                  SURVEY_IN_PROGRESS
       ▼                   task_type==PEMASANGAN?
   fop_review_status         → Customer→
    =approved                  INSTALLATION_IN_PROGRESS
       │                       │
   task_type==SURVEY?       notify tim (error)
    → Customer→WAITING_     AuditLog('rejected')
      INSTALLATION
       │
   notify tim (success)
   AuditLog('approved')
```

Approve Pemasangan **cuma** bisa lewat `/verifications/{customer}/admin` (`finalVerify()`, lihat [docs/customer-lifecycle/flowchart.md §5](../customer-lifecycle/flowchart.md#5-alur-verifikasi-admin-aktivasi--reject--revisi)) — generate Invoice AWAL + CID sekaligus. Riwayat bug & perbaikan: [bug.md](bug.md).

## 6. Pending & Cancel

```
Teknisi set Pending (POST /tasks/{task}/pending)
        │
        ▼
Policy statusPending: hasPermission('task.status.pending') AND isMember
        │
        ▼
status != in_progress? ──ya──▶ TOLAK 422
        │ tidak
        ▼
status=pending, pending_reason=...
Kalau task_type SURVEY/PEMASANGAN & customer_id ada:
  → tutup timer CustomerSurvey/CustomerInstallation yang masih started_at tanpa completed_at
```

```
FOP cancel (POST /tasks/{task}/cancel)
        │
        ▼
Policy cancel: task_type in [SURVEY, PEMASANGAN]? ──ya──▶ TOLAK (2026-07-21)
        │                                              — batalkan SRV/PSB WAJIB
        │                                                lewat halaman Customer,
        │                                                lihat docs/fop-task/
        │                                                flowchart.md § 12
        │ tidak
        ▼
Policy cancel: canTransitionTo(...,'dibatalkan') AND status not in [selesai,dibatalkan]
        │
        ▼
status=dibatalkan, cancelled_at=now(), cancel_reason=...
AuditLog::log('cancelled')
        │
        ▼
status SEBELUM diubah == in_progress? (dicek Task 12, 2026-07-22)
   • ya  → notifyTeam() ke semua anggota tim — AppNotification type=error,
           "Task dibatalkan: {alasan}"
   • tidak (terjadwal/draft) → gak ada notifikasi
```

Notifikasi ini ditaruh di `TaskService::cancel()` sendiri (bukan di controller) — jadi berlaku SAMA buat semua pemicu cancel: tombol Cancel di halaman Task ini, tombol Cancel di tabel FOP Task (`FopTaskController::update()`, task_type NON-SRV/PSB, lihat `docs/fop-task/flowchart.md` § 13), MAUPUN cancel SRV/PSB dari halaman Customer (`CustomerSurveyController::cancel()`/`CustomerInstallationController::cancel()`, § 12) — 1 titik logic, gak diduplikasi per jalur.

## 7. Reassign Teknisi (`TaskTeamController` → `TaskService::reassignTeam()`)

```
FOP pilih ganti 1 teknisi (old_user_id → new_user_id), opsional scheduled_at baru
        │
        ▼
status not in [terjadwal, in_progress]? ──ya──▶ TOLAK (exception)
        │ tidak
        ▼
jadwal berubah? → cek konflik untuk (anggota lain + new_user_id) di jadwal baru
tidak berubah?  → cek konflik cuma untuk new_user_id di jadwal existing
        │
        ▼
   ada konflik? ──ya──▶ TOLAK (exception, sebut task_number yang bentrok)
        │ tidak
        ▼
old_user_id ada di tim? ──tidak──▶ TOLAK (exception)
        │ ya
        ▼
UPDATE task_teams SET user_id=new_user_id WHERE task_id=... AND user_id=old_user_id
(jadwal berubah?) → update Task.scheduled_at
        │
        ▼
AuditLog::log('reassigned')
notify: new_user (assigned), old_user (unassigned), anggota lain (kalau jadwal berubah)
broadcast TaskScheduled
```

## 8. Laporan Maintenance (jalur khusus, bukan Survey/Instalasi)

```
Teknisi buka /tasks/{task}/maintenance-report
        │
        ▼
task_type in [SURVEY, PEMASANGAN]? ──ya──▶ redirect, "gunakan form khusus"
status not in [in_progress, pending]? ──ya──▶ redirect error
        │
        ▼
Isi: kendala teknis, kabel/modem/patchcord/sleeve/lainnya, foto OPM + speedtest (wajib)
        │
        ▼
Submit → TaskMaintenance::create() + TaskService::complete() — 1 aksi
        │
        ▼
Task langsung selesai, fop_review_status=pending (sama seperti alur complete biasa)
```
