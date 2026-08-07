# Modul Task Teknisi (Eksekusi Lapangan)

`Task` = unit kerja yang benar-benar dikerjakan teknisi di lapangan (checklist, timer mulai/selesai, laporan). Beda dari `FopTask` (tiket administratif FOP, lihat [docs/fop-task](../fop-task/README.md)) — 1 `FopTask` biasanya generate 1 `Task` begitu teknisi di-assign, tapi `Task` juga bisa lahir langsung dari transisi status pelanggan (survey/pemasangan, lihat [docs/customer-lifecycle](../customer-lifecycle/README.md)).

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [business-logic.md](business-logic.md) | State machine Task, aturan konflik jadwal, guard tiap transisi, integrasi review FOP, laporan pekerjaan di Detail Task |
| [flowchart.md](flowchart.md) | Alur create→jadwal→start→complete/pending/cancel, alur reassign tim, alur review FOP |
| [user-flow.md](user-flow.md) | Langkah Teknisi & FOP di dashboard `/tasks-saya`, `/tasks-saya/riwayat`, `/tasks/{id}` |
| [database-schema.md](database-schema.md) | Tabel `tasks`, `task_teams`, `task_maintenances` |
| [bug.md](bug.md) | ⚠️ Bug ditemukan: 2 jalur aktivasi pelanggan yang gak konsisten (approve via Task vs via Verifikasi Admin) |

## Konsep Inti

```
Task (1 pekerjaan lapangan)
  ├── teamMembers (1-3 teknisi, task_teams pivot, role: lead/teknisi)
  ├── maintenanceReport (task_maintenances, khusus task_type non-Survey/Pemasangan)
  └── fop_review_status (pending/approved/rejected — FOP review laporan setelah Selesai)
```

**Foto Bukti (`TaskEvidence`/`task_evidences`) dihapus total (2026-08-06)** — upload foto generik ini gak pernah gate completion (`canComplete()` udah lama hardcoded `true`) dan tumpang tindih sama foto WAJIB yang sudah ada di tiap Laporan per tipe task (Survey: `survey_photo`/`house_photo`; Pemasangan: `installation_photo`/`contract_photo`/`signature_photo`/`speedtest_photo`; Maintenance/lainnya: `opm_photo`/`speedtest_photo`). Model, controller, route, tabel `task_evidences`, dan section "Foto Bukti" di `/tasks/{id}` sudah dibuang — tile ringkasan yang dulu nampilin jumlah foto sekarang jadi **Durasi Aktual**. Detail apa yang teknisi kerjakan (kendala teknis + material terpakai + foto wajib) sekarang tampil balik di Detail Task lewat blok **"Laporan Pekerjaan Teknisi"** — lihat [business-logic.md § 5](business-logic.md#5-syarat-complete--laporan-pekerjaan-teknisi).

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
| Lihat riwayat task selesai sendiri (`/tasks-saya/riwayat`) | Teknisi | `task.view.own` |
| Cancel task | FOP | via `WorkflowTransitionPermission` (`task.cancel`, dst) |
| Review laporan (approve/reject/pending) | FOP | dinamis via `WorkflowTransitionPermission` |
| Reassign teknisi | FOP | (route tanpa middleware permission eksplisit — cek [business-logic.md](business-logic.md)) |

## File Kode Terkait

| Area | File |
|------|------|
| Model | `app/Models/Task.php`, `TaskTeam.php`, `TaskMaintenance.php` |
| Service (state machine + konflik) | `app/Services/TaskService.php` |
| Policy | `app/Policies/TaskPolicy.php` |
| Controller utama | `app/Http/Controllers/TaskController.php` (termasuk `historyOwn()` → `/tasks-saya/riwayat`) |
| Controller status (start/complete/pending) | `app/Http/Controllers/TaskStatusController.php` |
| Controller reassign tim | `app/Http/Controllers/TaskTeamController.php` |
| Controller laporan maintenance | `app/Http/Controllers/TaskMaintenanceController.php` |
| Enum | `app/Enums/TaskStatus.php`, `TaskType.php` |
| Workflow transition dinamis | `app/Models/WorkflowTransitionPermission.php` (lihat [docs/rbac](../rbac/README.md)) |
| View | `resources/views/tasks/{own,own-history,show,edit,maintenance-report}.blade.php` |

## Routes

| Route | Method | Guard | Controller |
|-------|--------|-------|------------|
| `/tasks-saya` | GET | `viewOwn` policy | `TaskController@indexOwn` |
| `/tasks-saya/partial/{task}` | GET | `viewOwn` + member check | `TaskController@cardPartial` |
| `/tasks-saya/riwayat` | GET | `viewOwn` policy | `TaskController@historyOwn` |
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
| `GET /api/tasks/check-conflict`, `/api/tasks/search-customers` | GET/POST | `lookup` policy | `TaskController@checkConflict,searchCustomers` |

## Terhubung dengan Modul Lain

- [docs/fop-task](../fop-task/README.md) — `FopTaskController` auto-create `Task` saat teknisi di-assign ke tiket FOP. Sebaliknya, SETIAP perubahan `Task.status`/`report_deferred`/`fop_review_status` (lewat `start()`/`complete()`/`pending()`/`reschedule()`/`review()`/`cancel()` — semua jalur di modul ini) otomatis dipantau `App\Observers\TaskObserver` (registered di `AppServiceProvider`) buat sync status `FopTask` + tulis log `fop_task_status_history` + akumulasi durasi/SLA ke `task_reports` (Task 10, dual-cycle) — modul ini gak perlu manggil apa-apa secara eksplisit, semua kejadian otomatis lewat Observer hook.
- [docs/customer-lifecycle](../customer-lifecycle/README.md) — `CustomerWorkflowService` auto-create `Task` (tipe Survey/Pemasangan) saat status Customer masuk `waiting_survey`/`waiting_installation`; `TaskController::review()` approve laporan Survey/Pemasangan ikut men-transisi status Customer. **Catatan penting:** ada 2 jalur "reject" yang beda efeknya — reject laporan di modul ini (`TaskController::review()`, kualitas laporan jelek → `Task.status` balik `in_progress`, teknisi redo) VS reject final customer di `CustomerVerificationController::reject()` (Customer module, gak eligible/belum bayar → `Task.status` TETAP `selesai`, cuma `fop_review_status=rejected`, terminal). Lihat `docs/project_verifikasi_reject_gap.md`.
- [docs/rbac](../rbac/README.md) — sebagian transisi status Task (cancel, review, start dari kondisi non-standar) dikontrol dinamis lewat `WorkflowTransitionPermission`, bukan permission string statis biasa.

---

## Pola Redirect (PRG)

Ubah task (`update`) → `tasks.show` (Detail). Batalkan task (`cancel`) → `fop.dashboard` (papan kerja,
pengecualian sadar). Aturan lengkap + kenapa: **[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

---

**Last updated:** 2026-08-06 (hapus fitur Foto Bukti/`TaskEvidence`, tambah blok Laporan Pekerjaan Teknisi + tile Durasi Aktual di Detail Task, tambah halaman Riwayat Task Saya `/tasks-saya/riwayat`, fix redirect `return_to` Laporan Survey/Pemasangan)
