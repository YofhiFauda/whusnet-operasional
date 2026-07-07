# Modul Task Teknisi (Eksekusi Lapangan)

`Task` = unit kerja yang benar-benar dikerjakan teknisi di lapangan (checklist, timer mulai/selesai, evidence foto, laporan). Beda dari `FopTask` (tiket administratif FOP, lihat [docs/fop-task](../fop-task/README.md)) — 1 `FopTask` biasanya generate 1 `Task` begitu teknisi di-assign, tapi `Task` juga bisa lahir langsung dari transisi status pelanggan (survey/pemasangan, lihat [docs/customer-lifecycle](../customer-lifecycle/README.md)).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | State machine Task, aturan konflik jadwal, guard tiap transisi, integrasi review FOP |
| [flowchart.md](flowchart.md) | Alur create→jadwal→start→complete/pending/cancel, alur reassign tim, alur review FOP |
| [user-flow.md](user-flow.md) | Langkah Teknisi & FOP di dashboard `/tasks-saya`, `/tasks/{id}` |
| [database-schema.md](database-schema.md) | Tabel `tasks`, `task_teams`, `task_evidences`, `task_maintenances` |
| [bug.md](bug.md) | ⚠️ Bug ditemukan: 2 jalur aktivasi pelanggan yang gak konsisten (approve via Task vs via Verifikasi Admin) |

## Konsep Inti

```
Task (1 pekerjaan lapangan)
  ├── teamMembers (1-3 teknisi, task_teams pivot, role: lead/teknisi)
  ├── evidences (task_evidences, foto bukti, min syarat sebelum complete)
  ├── maintenanceReport (task_maintenances, khusus task_type=MAINTENANCE)
  └── fop_review_status (pending/approved/rejected — FOP review laporan setelah Selesai)
```

- **Status**: `draft` → `terjadwal` → `in_progress` → `selesai` (+ `pending`, `dibatalkan` sebagai cabang). Lihat `App\Enums\TaskStatus`.
- **1 teknisi cuma boleh pegang 1 Task `in_progress` di waktu bersamaan** — guard ini dicek di 3 tempat berbeda (start Task biasa, start Survey, start Pemasangan) karena Task teknisi dan proses Customer survey/install saling terhubung lewat `task_type`.
- **Review FOP**: task `selesai` masuk status `fop_review_status=pending` — FOP approve/reject/pending ulang lewat `TaskController::review()`, yang untuk task tipe Survey/Pemasangan juga **trigger transisi status Customer** (lihat [business-logic.md](business-logic.md)).

## Aktor & Permission

| Aksi | Aktor | Permission |
|------|-------|-----------|
| Lihat dashboard sendiri (`/tasks-saya`) | Teknisi | `task.view.own` |
| Lihat semua task | FOP/Admin | `task.view.all` |
| Edit judul/jadwal/tim | FOP | `task.edit`, `task.schedule`, `task.assign.team` |
| Ubah tipe task | FOP (khusus) | `task.edit.type` |
| Mulai/Selesai/Pending task | Teknisi (anggota tim) | `task.status.start/.complete/.pending` |
| Upload bukti foto | Teknisi (anggota tim) | `task.evidence.upload` |
| Cancel task | FOP | via `WorkflowTransitionPermission` (`task.cancel`, dst) |
| Review laporan (approve/reject/pending) | FOP | dinamis via `WorkflowTransitionPermission` |
| Reassign teknisi | FOP | (route tanpa middleware permission eksplisit — cek [business-logic.md](business-logic.md)) |

## File Kode Terkait

| Area | File |
|------|------|
| Model | `app/Models/Task.php`, `TaskTeam.php`, `TaskEvidence.php`, `TaskMaintenance.php` |
| Service (state machine + konflik) | `app/Services/TaskService.php` |
| Policy | `app/Policies/TaskPolicy.php` |
| Controller utama | `app/Http/Controllers/TaskController.php` |
| Controller status (start/complete/pending) | `app/Http/Controllers/TaskStatusController.php` |
| Controller reassign tim | `app/Http/Controllers/TaskTeamController.php` |
| Controller evidence | `app/Http/Controllers/TaskEvidenceController.php` |
| Controller laporan maintenance | `app/Http/Controllers/TaskMaintenanceController.php` |
| Enum | `app/Enums/TaskStatus.php`, `TaskType.php` |
| Workflow transition dinamis | `app/Models/WorkflowTransitionPermission.php` (lihat [docs/rbac](../rbac/README.md)) |
| View | `resources/views/tasks/{own,show,edit,maintenance-report}.blade.php` |

## Routes

| Route | Method | Guard | Controller |
|-------|--------|-------|------------|
| `/tasks-saya` | GET | `viewOwn` policy | `TaskController@indexOwn` |
| `/tasks-saya/partial/{task}` | GET | `viewOwn` + member check | `TaskController@cardPartial` |
| `/tasks/{task}` | GET | `view` policy | `TaskController@show` |
| `/tasks/{task}/edit`, `PUT /tasks/{task}` | GET/PUT | `edit` policy | `TaskController@edit,update` |
| `PATCH /tasks/{task}/team` | PATCH | — | `TaskTeamController@update` |
| `POST /tasks/{task}/cancel` | POST | `cancel` policy | `TaskController@cancel` |
| `POST /tasks/{task}/review` | POST | `review` policy | `TaskController@review` |
| `POST /tasks/{task}/fop-reject`, `/fop-pending` | POST | `fopReject`/`fopPending` policy | `TaskController@reject,pending` |
| `POST /tasks/{task}/start` | POST | `statusStart` policy | `TaskStatusController@start` |
| `POST /tasks/{task}/complete` | POST | `statusComplete` policy | `TaskStatusController@complete` |
| `POST /tasks/{task}/pending` | POST | `statusPending` policy | `TaskStatusController@pending` |
| `GET,POST /tasks/{task}/maintenance-report` | GET/POST | `statusComplete` policy | `TaskMaintenanceController@report,store` |
| `POST /tasks/{task}/evidences`, `DELETE .../{evidence}` | POST/DELETE | `uploadEvidence`/`edit` policy | `TaskEvidenceController@store,destroy` |
| `GET /api/tasks/check-conflict`, `/api/tasks/search-customers` | GET/POST | `lookup` policy | `TaskController@checkConflict,searchCustomers` |

## Terhubung dengan Modul Lain

- [docs/fop-task](../fop-task/README.md) — `FopTaskController` auto-create `Task` saat teknisi di-assign ke tiket FOP.
- [docs/customer-lifecycle](../customer-lifecycle/README.md) — `CustomerWorkflowService` auto-create `Task` (tipe Survey/Pemasangan) saat status Customer masuk `waiting_survey`/`waiting_installation`; `TaskController::review()` approve laporan Survey/Pemasangan ikut men-transisi status Customer.
- [docs/rbac](../rbac/README.md) — sebagian transisi status Task (cancel, review, start dari kondisi non-standar) dikontrol dinamis lewat `WorkflowTransitionPermission`, bukan permission string statis biasa.

---

**Last updated:** 2026-07-07
