# Flowchart — Modul FOP Task

## 1. Status Tiket `FopTask`

**⚠️ Diagram di bawah ini cuma menggambarkan NILAI status yang mungkin — bukan cara transisinya.** Status `FopTask.status` full **derived otomatis** dari status `Task` eksekusi teknisi terkait lewat `TaskObserver` (kecuali `dibatalkan`, yang masih ada tombol manual buat task_type NON-SURVEY/PSB). Detail lengkap mapping & trigger di [§ 9. Status Realtime](#9-status-realtime--sync-task-eksekusi--foptask-task-9) di bawah.

**⚠️⚠️ UNIFIKASI ENUM (2026-07-20):** `App\Enums\FopTaskStatus` (Proses/Pending/Selesai/Cancel, 4 bucket) **DIHAPUS TOTAL**. `fop_tasks.status` sekarang pakai `App\Enums\TaskStatus` — SAMA PERSIS enum yang dipakai `tasks.status` (6 nilai: `draft`, `terjadwal`, `in_progress`, `pending`, `selesai`, `dibatalkan`). Diagram di bawah (ditulis versi lama, 4 bucket) di-supersede oleh diagram Task-lifecycle biasa — lihat `docs/task-teknisi/flowchart.md` buat diagram `TaskStatus` yang akurat. Ringkasnya: `FopTask` yang punya `task_id` (Task terhubung) statusnya SELALU sama persis `Task.status` (mirror langsung, gak ada mapping bucket lagi). `FopTask` standalone (`task_id` NULL, belum ada teknisi) mulai dari `draft`, naik ke `terjadwal` otomatis begitu teknisi di-assign.

```
                 ┌──────────┐
   create ──────▶│ terjadwal│◀────────────┐
                 └────┬─────┘              │
                      │                    │ Task eksekusi balik ke terjadwal/
        Task eksekusi jadi                 │ in_progress (mirror, TaskObserver)
        pending (teknisi ATAU FOP —        │
        1 logic sejak 2026-07-15)          │
                      ▼                    │
                 ┌──────────┐              │
                 │ pending  │──────────────┘
                 └────┬─────┘
                      │
        ┌─────────────┼─────────────┐
        ▼                           ▼
   ┌──────────┐               ┌──────────┐
   │ selesai  │               │dibatalkan│
   └──────────┘               └──────────┘
   (mirror — Task eksekusi     (task_type SRV/PSB: TERKUNCI dari sini,
    jadi selesai)               harus lewat halaman Customer — lihat § 12.
                                 Task_type lain: tombol manual masih ada)
```

- `terjadwal` → `dibatalkan` juga valid (tiket dibatalkan tanpa lewat Pending) — manual, kapan aja selagi belum `selesai`/`dibatalkan`, **KECUALI** task_type SURVEY/PEMASANGAN (lihat § 12, WAJIB lewat halaman Customer).
- **Perubahan perilaku sejak Task 9:** dulu FOP bisa reopen `Cancel` → `Selesai` (atau status lain) lewat dropdown edit bebas. Modal edit sekarang **read-only** buat status tiket existing (cuma badge, gak ada dropdown — lihat § 9), jadi reopen manual dari `dibatalkan` **gak ada jalur UI lagi**. Ditambah `TaskObserver` SENGAJA skip sync begitu `FopTask.status = dibatalkan` (proteksi override, lihat § 9) — jadi kalaupun `Task` eksekusi terkait berubah status lagi, `FopTask` tetap nyangkut di `dibatalkan` sampai ada perubahan manual langsung ke DB. Edge case ini dicatat, bukan dikerjain ulang (di luar scope).
- Status hidup di enum `App\Enums\TaskStatus` (6 nilai, SAMA persis punya `Task` — enum `FopTaskStatus` yang dulu 4 nilai UDAH DIHAPUS, unifikasi 2026-07-20).

## 2. Auto-Sync Customer → FopTask (jalan tiap `GET /fop-tasks`)

```
FopTaskController::autoSyncAndCalculatePriority()
│
├─ 1. Customer status IN (calon_pelanggan, waiting_survey, registered)
│     AND belum punya FopTask kategori Survey yang aktif (BUKAN selesai/dibatalkan)
│     → buat FopTask baru, category=Survey, status=draft, priority=Medium (sementara)
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

## 4. Assignment Teknisi → Auto-buat `Task` Eksekusi + Auto-Team Rebuild

**Berubah total sejak Task 1/2** — dulu FOP pilih `team_id` manual di form; sekarang Team-nya kebentuk sendiri.

```
FOP assign teknisi ke FopTask (store/update)
        │
        ▼
  technicians()->sync($ids)   [pivot fop_task_user]
        │
        ▼
  fop_task.task_id kosong? ──── ya ──▶ TaskService::create() → Task baru (title polos "FOP: <tugas>")
        │ tidak                              │
        ▼                                    ▼
  TaskService::update($task, title polos)   fop_task.task_id = task.id
        │
        ▼
  FopTaskTeamService::rebuildTeamsForDate(task_date)   ◀── lihat bagian 5
        │
        ▼
  team_id ke-assign otomatis (atau null kalau solo/konflik)
        │
        ▼
  syncExecutionTaskTitle(): Task.title di-update jadi
  "[Team {n}] FOP: <tugas>" (atau polos kalau team_id null)
```

- **Title Task eksekusi gak lagi ditebak dari nama teknisi pertama** (`'Tim ' . strtok(...)`, cara lama) — dibuat polos dulu pas `store()`/`update()`, lalu di-isi ulang otomatis sama `rebuildTeamsForDate()` begitu Team-nya kebentuk/berubah, pakai nama Team yang SEBENARNYA. Jadi title selalu sinkron sama Team terkini, gak pernah basi walau Team-nya di-merge/rename belakangan.
- `conflict_override: true` — assignment FOP task gak dicek bentrok jadwal kayak Task manual biasa.
- Kalau technicians array literally berubah dan task sebelumnya punya `manual_override_at` — kolom itu di-null-in juga (lepas pin manual lama, biar rebuild bebas nentuin ulang).

## 5. Auto-Team Formation (Connected Components)

**Baru — Task 1.** Ganti total lifecycle manual Team lama (bikin Team dulu → baru assign tiket). Sekarang: FOP langsung assign teknisi ke tiket, Team-nya kebentuk/berubah sendiri lewat `FopTaskTeamService::rebuildTeamsForDate($task_date)`, dipanggil abis TIAP perubahan assignment teknisi (create/update/assign-to-team/switch-technician).

```
rebuildTeamsForDate($date)
        │
        ▼
  Ambil semua FopTask aktif (BUKAN selesai/dibatalkan) di $date, load technicians
        │
        ▼
  Pisah: locked (manual_override_at terisi) vs open
        │
        ▼
  ┌─────────────────────────────────────────────────────────┐
  │ Untuk tiap task MULTI-teknisi (open):                    │
  │  • teknisinya udah ada di >=2 Team existing BEDA?        │
  │      YA  → Skenario C3: JANGAN auto-union,               │
  │            catat sbg conflict (task_id + 2 kandidat Team)│
  │            team_id di-null-in, nunggu FOP putusin manual │
  │      TIDAK → union teknisi jadi 1 komponen graf          │
  │              (Skenario A: baru, atau B: nyambung ke      │
  │              Team existing lewat 1 teknisi jembatan)     │
  └─────────────────────────────────────────────────────────┘
        │
        ▼
  Untuk tiap task SOLO (1 teknisi):
   • teknisinya udah py Team (dari task ini sendiri
     ATAU dari task lain, snapshot SEBELUM rebuild)?
       YA → ikut Team itu (Skenario C1, termasuk kasus
            task multi-teknisi yang nyusut jadi solo —
            teknisi yg tersisa TETAP di Team lamanya)
       TIDAK → team_id = null (Skenario C2, nunggu FOP
               drop-in manual lewat "+ Masukkan ke Team...")
        │
        ▼
  Bikin/update FopTaskTeam per komponen graf (nama auto
  "Team {n}", roster di-sync), assign team_id ke semua
  task dalam komponen itu
        │
        ▼
  Hapus FopTaskTeam yang gak py task aktif lagi (cleanup)
        │
        ▼
  Sync Task.title (execution layer) ke nama Team final
        │
        ▼
  return ['conflicts' => [...]]  → FE nampilin modal
  konflik kalau ada isinya
```

**Drop-in manual** (Skenario C2/C3): endpoint `POST /fop-tasks/{task}/assign-to-team` — FOP pilih Team tujuan (atau minta Team baru) buat task solo tanpa Team, atau buat nyelesein conflict C3. Task yang di-drop-in dapet `manual_override_at = now()` — pin ini bikin `rebuildTeamsForDate()` gak nimpa `team_id`-nya lagi sampai teknisinya diganti lewat assignment biasa. Kalau drop-in ini bikin teknisi keluar dari Team lamanya (task lain di tanggal sama, Team beda), teknisi itu otomatis dicabut dari task lama tsb + roster Team lama ke-refresh.

**Konflik yang ke-close/hilang:** `FopTaskController::index()` hitung ulang conflict LANGSUNG dari state DB tiap kali halaman diakses (`currentTeamConflicts()`) — bukan cuma dari session flash sekali-pakai — jadi modal konflik bisa dibuka ulang kapan aja lewat tombol "Konflik Team (n)" di header, walau sempet ke-close atau halaman di-refresh.

## 6. Switch Teknisi antar Team (Task 2)

**Baru.** Endpoint atomic `POST /fop-tasks/switch-technician` — pindahin 1 teknisi dari Task asal ke Task tujuan (Team beda) DALAM 1 SUBMIT, wajib isi pengganti di Task asal supaya Task asal gak pernah kosong teknisi.

```
FOP klik chip nama teknisi di tabel /fop-tasks
        │
        ▼
  Modal: pilih Task Tujuan (task lain, tanggal sama)
         + pilih Pengganti (teknisi manapun, termasuk yang
           udah ada di Task asal — gak wajib org baru)
        │
        ▼
  Validasi (SEBELUM transaksi, gagal = gak ada perubahan sama sekali):
   • teknisi beneran anggota Task asal?
   • pengganti != teknisi yang dipindah?
   • Task asal & Task tujuan tanggal SAMA? (intra-hari only,
     beda hari ditolak — arahkan ke jalur Pending/reschedule)
   • pengganti lagi in_progress di task lain? (reuse query
     yang sama dgn TaskService::start(), bukan bikin baru)
        │ lolos semua
        ▼
  DB::transaction():
   • sync pivot fop_task_user Task asal (teknisi keluar,
     pengganti masuk) & Task tujuan (teknisi masuk)
     — manual_override_at dilepas di kedua task
     — team_id SENGAJA GAK di-null-in (beda dari update()
       biasa) biar rebuild masih bisa pakai team_id lama
       sbg anchor kalau salah satu task nyusut jadi solo
   • sync ke Task eksekusi (TaskService::update, 2x)
   • AuditLog 2 entry (switch_technician_out / _in)
   • notifikasi in-app ke 2 teknisi (keluar & masuk)
        │
        ▼
  rebuildTeamsForDate() untuk tanggal asal & tujuan
  (sama tanggal karena intra-hari only)
```

## 7. Overview Halaman

```
/fop  (Dashboard)                     /fop-tasks (Kelola Tiket)
┌─────────────────────────┐           ┌─────────────────────────────┐
│ Stat cards (antrean,     │           │ Filter (search/kategori/     │
│ perlu aksi, overdue)     │           │ status/prioritas/desa/team)  │
│                          │──────────▶│                              │
│ Team FOP Aktif (card,    │           │ Tabel tiket aktif             │
│  auto-generated)         │           │  → klik chip teknisi = Switch│
│  → klik buka detail team │           │  → "+ Masukkan ke Team..."   │
│                          │           │    (task solo tanpa team)    │
│ Antrean survey, teknisi  │           │ Modal create/edit tiket       │
└─────────────────────────┘           │ Modal konflik Team (C3)       │
                                       │ Tombol "Konflik Team (n)"     │
                                       └──────────────┬───────────────┘
                                                       │
                                                       ▼
                                        /fop-tasks/history
                                        (Selesai/Pending/Dibatalkan — label
                                        seragam TaskStatus::displayLabel(),
                                        klik row → Detail Riwayat, § 10 & 11)
```

Panel "Kelola Team" manual **sudah dihapus** — Team gak lagi dibuat/di-edit/dihapus lewat UI terpisah, sepenuhnya derived dari assignment teknisi (lihat bagian 5).

## 8. Antrian Sorting berdasarkan `client_request_date` (Task 8)

**Baru.** `FopTaskController::index()` sekarang sort 4 CASE berurutan (bukan cuma 2 kayak sebelumnya) — CASE baru ditaruh PALING DEPAN, jadi presedensinya di atas priority/category:

```
ORDER BY
  1. CASE: client_request_date terisi DAN >= besok?
       YA  → bucket 1 (Upcoming, di-sink ke BAWAH daftar)
       TIDAK (kosong, atau <= hari ini) → bucket 0 (ikut sorting normal)
  2. CASE priority: Urgent(1) → High(2) → Medium(3) → low(4) → else(5)
  3. CASE category IN (Survey, PSB) → created_at ASC   (yang lama duluan)
  4. CASE category NOT IN (Survey, PSB) → created_at DESC  (yang baru duluan)
```

- Task dengan `client_request_date` di masa depan (besok atau lebih) **selalu** tampil di bawah tiket lain, walau priority-nya Urgent — bucket 1 kalah sama bucket 0 di ORDER BY pertama, gak peduli apa pun nilai CASE sesudahnya.
- Task dengan `client_request_date` hari ini (atau udah lewat) masuk bucket 0 — ikut aturan sorting normal (priority dulu, baru category/created_at) berbarengan sama task yang gak punya `client_request_date` sama sekali.
- **Gak ada cron.** Bucket dihitung ulang tiap kali `GET /fop-tasks` di-load — begitu tanggal sistem nyampe/lewat `client_request_date`, task otomatis "naik" ke sorting normal di request berikutnya, tanpa job terjadwal.
- Badge visual di kolom "Tanggal" (`fop_tasks/index.blade.php`): **"JADWAL HARI INI"** (merah) kalau `client_request_date <= hari ini`, **"Terjadwal — {tanggal}"** (abu-abu) kalau di masa depan.
- **Kenapa `>= besok`, bukan `> hari ini`:** ditemukan lewat test bahwa kolom `client_request_date` tersimpan dengan suffix waktu (`'... 00:00:00'`) di DB — perbandingan string `> 'YYYY-MM-DD'` (tanpa waktu) SELALU true karena string yang lebih panjang (ada suffix) dianggap "lebih besar" dari prefix-nya. Threshold `>= tanggal besok` menghindari ini sekaligus tetap portable ke MySQL & SQLite (gak pakai `CURDATE()` yang MySQL-only — dipakai binding parameter PHP `now()->addDay()->toDateString()` sebagai gantinya).
- Detail implementasi & test: [analisa-auto-team.md § Task 8](analisa-auto-team.md).

## 9. Status Realtime — Sync `Task` Eksekusi → `FopTask` (Task 9)

**Baru.** Status `FopTask` gak lagi diubah manual FOP lewat dropdown (kecuali `dibatalkan`, task_type NON-SURVEY/PSB) — full **derived otomatis** (mirror langsung) dari perubahan status `Task` eksekusi teknisi terkait, lewat `App\Observers\TaskObserver` (hook `updated()`, di-register di `AppServiceProvider::boot()`).

```
Task (eksekusi) berubah status/report_deferred/fop_review_status
        │  (lewat TaskController::reschedule() [Task 7], TaskStatusController::pending()
        │   [Task 6 Lapor Nanti / existing fopPending], TaskService::start/complete(),
        │   TaskController::review()/cancel(), dst — SEMUA jalur yg nge-save Task)
        ▼
  TaskObserver::updated($task)
        │
        ▼
  Field yg relevan (status/report_deferred/fop_review_status) beneran berubah?
        │ tidak ──▶ no-op (skip, gak nulis history)
        │ ya
        ▼
  Cari FopTask::where('task_id', $task->id) — gak ketemu? ──▶ no-op (Task bukan hasil FOP-flow)
        │ ketemu
        ▼
  FopTask.status udah dibatalkan? ──▶ YA: skip total (proteksi override manual FOP, lihat Task 12)
        │ tidak
        ▼
  Resolve target [TaskStatus (COPY LANGSUNG dari $task->status, unifikasi
  2026-07-20), label histori granular] dari kombinasi Task.status +
  report_deferred + fop_review_status (tabel lengkap di
  database-schema.md § Observer: TaskObserver)
        │
        ▼
  FopTask.status di-update (idempotent — cuma nulis kalau beda)
        │
        ▼
  Tulis 1 baris baru ke fop_task_status_history
  (from_status, to_status granular, changed_by, changed_at)
```

**Mapping ringkas** (detail penuh + tabel di [database-schema.md](database-schema.md)) — sejak unifikasi enum (2026-07-20), kolom `FopTask.status` SELALU mirror `Task.status` apa adanya, kolom "Label histori" doang yang masih granular:

| Aksi teknisi/FOP | `Task.status` | `FopTask.status` | Label histori (`to_status`) |
|---|---|---|---|
| Task di-assign, belum mulai | `terjadwal` | `terjadwal` (mirror) | `terjadwal` |
| Teknisi klik "Mulai" | `in_progress` | `in_progress` (mirror) | `in_progress` (badge "Sedang Dikerjakan") |
| Teknisi klik `Pending` top-level ATAU FOP klik `Set Pending` manual (2026-07-15: **1 logic**, `TaskStatus::RESCHEDULE` dihapus) | `pending` (+`report_deferred=false`) | `pending` (mirror) | `pending_fop` (badge "Pending") |
| Teknisi pilih `Lapor Nanti` di dialog laporan (Task 6) | `pending` (+`report_deferred=true`) | `pending` (mirror) | `lapor_nanti` (badge "Lapor Nanti") |
| Teknisi submit laporan — **task_type apapun** (unifikasi 2026-07-20: MTN/DEAC/dst SEKARANG SAMA kayak SURVEY/PEMASANGAN, gak ada lagi demosi ke bucket lama) | `selesai` (+`fop_review_status=pending`) | `selesai` (mirror) | `selesai_menunggu_verifikasi` (badge tetep **"Selesai"** polos) |
| FOP approve laporan | `selesai` (+`fop_review_status=approved`) | `selesai` (mirror) | `selesai` |
| FOP reject laporan (kualitas laporan jelek, redo — task_type MTN/dst) | `in_progress` (+`fop_review_status=rejected`) | `in_progress` (mirror) | `in_progress` |
| **Admin tolak final di Verif & Pemasangan/Survey queue** — task_type SURVEY/PEMASANGAN | `selesai` (+`fop_review_status=rejected`) | `selesai` (mirror, TETAP) | `selesai_ditolak_verifikasi` (badge tetep **"Selesai"** polos) |
| Task eksekusi dibatalkan | `dibatalkan` | `dibatalkan` (mirror) | `dibatalkan` |

**Poin penting:**
- **Unifikasi enum FopTaskStatus → TaskStatus (2026-07-20):** `App\Enums\FopTaskStatus` (4 bucket lama: Proses/Pending/Selesai/Cancel) **DIHAPUS TOTAL**. `resolveTarget()` gak mapping bucket lagi — target SELALU `$task->status` mentah. Yang paling kena dampak: dulu Task `selesai`+`fop_review_status=pending` buat task_type NON-SURVEY/PSB (MTN/dst) didemosikan ke `Proses` (badge "Perlu Review") — SEKARANG TIDAK, `FopTask`-nya tetap `selesai` sama kayak SURVEY/PEMASANGAN, nuansa "masih ditinjau" murni badge overlay dari label histori.
- **Cancel/Dibatalkan buat SRV/PSB TERKUNCI dari sisi Task/FopTask (2026-07-21)** — lihat § 12 di bawah. Task_type LAIN masih bisa dibatalkan manual seperti biasa.
- **Prinsip inti (desain final, 2026-07-14/15, diperluas 2026-07-20):** Task (kerjaan lapangan teknisi) VS keputusan bisnis (customer diterima/ditolak) itu 2 hal beda, sengaja gak dicampur — bukan cuma di nilai status, tapi juga di label yang ditampilin. Buat task_type SURVEY/PEMASANGAN, begitu `Task.status=selesai`, `FopTask` SELALU `selesai` dan LABELNYA SELALU "Selesai" — nasib customer (approved/pending/rejected) kesimpen di `fop_review_status` + `fop_task_status_history` (audit trail), TAPI GAK PERNAH nongol jadi variasi teks/badge di UI. Sejak 2026-07-20, prinsip yang sama diterapin ke SEMUA task_type (bukan cuma SURVEY/PEMASANGAN) — 1 sumber kebenaran status, konsisten. Detail: `docs/project_status_label_unifikasi.md`.
- **Dua jalur "reject" yang BEDA, jangan ketuker:** (1) *Reject laporan* — FOP nolak kualitas laporan teknisi (foto kurang jelas, dst) lewat `TaskController::review()`, revert `Task.status` ke `in_progress` biar teknisi kerjain ulang, `FopTask` ikut mirror balik ke `in_progress` (task_type MTN/dst — task-nya sendiri belum kelar). (2) *Tolak final Verifikasi Admin* — admin nolak CUSTOMER-nya (gak eligible/belum bayar) lewat `CustomerVerificationController::reject()` (Customer module, halaman Verif & Pemasangan/queue survey, task_type SURVEY/PEMASANGAN), `Task.status` TETAP `selesai` (kerjaan lapangan teknisi udah bener, yang ditolak keputusan bisnisnya), cuma `fop_review_status` jadi `rejected` → `FopTask` TETAP `selesai`, label TETAP "Selesai" polos, TERMINAL di Customer module (gak ada jalur reopen, customer harus registrasi ulang). Detail lengkap: `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL).
- **`Pending` (kolom, `report_deferred=false`) SEKARANG SELALU berarti tim dilepas** — teknisi top-level ATAU FOP manual, 2 trigger, 1 perilaku (`TaskController::releaseTeamAndSetPending()`, dipanggil dari `reschedule()` dan `pending()`). `TaskStatus::RESCHEDULE` (enum case terpisah) DIHAPUS 2026-07-15 — sebelumnya cuma `reschedule()` yang lepas tim, `fopPending()` gak. Migration data (`2026_07_19_000001_migrate_reschedule_status_to_pending.php`) convert row lama `status=reschedule` → `pending`. Detail: `docs/project_status_label_unifikasi.md`.
- `fop_tasks.status` (kolom) sekarang pakai enum `App\Enums\TaskStatus` (6 nilai, SAMA persis `tasks.status`) — bukan `FopTaskStatus` lagi. Granularitas ekstra (bedain `lapor_nanti` vs `pending_fop`, walau sama-sama `pending`; atau `selesai_menunggu_verifikasi`/`selesai_ditolak_verifikasi` vs `selesai` biasa, walau sama-sama `selesai`) cuma ada di `fop_task_status_history.to_status` (kolom string bebas, buat AUDIT LOG doang, bukan badge UI lagi).
- **`dibatalkan` tetap satu-satunya transisi manual FOP, TAPI TERKUNCI buat SRV/PSB** — tombol di `fop_tasks/index.blade.php` (reuse endpoint `update()` existing, payload `{status: 'dibatalkan'}`) sekarang **disembunyikan** buat `category` SURVEY/PSB (lihat § 12). `TaskObserver` skip sync begitu `FopTask.status` udah `dibatalkan`, biar gak ke-overwrite diam-diam.
- **Approve/reject laporan gak dapet endpoint baru di `FopTaskController`** — `fop_review_status` itu kolom di `tasks` (bukan `fop_tasks`), dan `TaskController::review()` udah punya business logic lengkap (transisi customer workflow, guard CID/Invoice PSB, notifikasi). Badge di `fop_tasks/index.blade.php` nampilin link **"Review Laporan →"** ke `route('tasks.show', $task->task_id)` kalau lagi nunggu review — FOP diarahkan ke tombol Approve/Reject yang udah ada di halaman Task, bukan duplikasi logic.
- **UI (2026-07-15, disesuaikan 2026-07-20):** kolom Status di `fop_tasks/index.blade.php`, `fop_tasks/history.blade.php`, `tasks/own.blade.php`, `tasks/partials/own-card.blade.php` — SEMUA sekarang manggil `TaskStatus::displayLabel($task->report_deferred)` yang SAMA (1 sumber, bukan `statusHistories()->first()->label()` lagi buat badge utama). FopTask standalone (task_id null, status masih `draft`) dikasih label khusus "Belum Ditugaskan" (bukan nampilin "Draft" polos, biar gak nyesatin). Modal edit tiket EXISTING masih read-only (badge + hidden input), modal CREATE tiket BARU pakai `<select>` 2 opsi (`terjadwal`/`pending`) — klasifikasi awal tiket baru, gak berubah.
- **Riwayat status realtime Task 6/7 tertutup penuh oleh Task 9:** gap "sinkron FopTask" (Task 6 checklist) dan "riwayat histori reschedule" (Task 7 checklist) yang tadinya di-BLOCKED nunggu Task 9, sekarang otomatis kepenuhi — `TaskObserver` jalan generik buat SEMUA jalur transisi `Task`, termasuk yang dari `TaskController::releaseTeamAndSetPending()` (dipanggil `reschedule()`/`pending()`) dan `TaskStatusController::pending()` (Task 6), tanpa perlu kode tambahan di controller masing-masing.

## 12. Cancel/Dibatalkan SRV & PSB Terkunci dari Task/FopTask (2026-07-21)

**Prinsip:** pembatalan pelanggan (SRV/PSB) SEHARUSNYA cuma bisa lewat 1 pintu — halaman Customer — bukan dari sisi Task/FopTask, biar `Customer.status` konsisten ikut ke-set (`rejected` → masuk List Pelanggan Gagal). Cancel langsung dari Task/FopTask sebelumnya BYPASS Customer sama sekali (Task/FopTask jadi `dibatalkan`, tapi Customer nyangkut selamanya di status lama, gak pernah masuk Pelanggan Gagal).

```
User klik Cancel/Batalkan di halaman Task ATAU tabel FopTask
        │
        ▼
  task_type == SURVEY atau PEMASANGAN?
        │
        ├─ YA ──▶ DITOLAK (2 titik guard independen):
        │          1. TaskPolicy::cancel() → false (tombol Cancel di tasks/show.blade.php
        │             otomatis hilang via @can('cancel',$task); guard ini berlaku buat
        │             SEMUA role termasuk owner — before() sengaja dikecualiin buat
        │             ability 'cancel')
        │          2. FopTaskController::update() → abort(422) kalau status target
        │             'dibatalkan' + category SURVEY/PSB (tombol Cancel di
        │             fop_tasks/index.blade.php disembunyikan by category juga)
        │          → user diarahkan ke halaman Customer (tab Survey/Pemasangan)
        │
        └─ TIDAK (MTN/DEAC/RELOKASI/C-REQ/O-REQ/INFR REQ) ──▶ tetap bisa dibatalkan
                   langsung dari Task/FopTask seperti biasa, gak terikat workflow Customer
```

**Jalur sah buat batalkan SRV/PSB — halaman Customer:**

| Tahap | Endpoint | Tempat tombol | Efek |
|---|---|---|---|
| Survey belum ditugaskan/belum dikerjakan (`waiting_survey`) ATAU lagi dikerjakan (`survey_in_progress`) | `CustomerSurveyController::cancel()` | Tab Survey halaman Customer detail + tombol **"Batalkan"** baru di `/surveys/queue` (Antrean Survey Lapangan) | Cancel Task Survey terkait + `CustomerSurvey.survey_status=failed` + `Customer.status→rejected` (List Pelanggan Gagal), 1 transaksi |
| Pemasangan belum ditugaskan/belum dikerjakan (`waiting_installation`) ATAU lagi dikerjakan/revisi (`installation_in_progress`/`revision_installation`) | `CustomerInstallationController::cancel()` **(baru, 2026-07-21)** | Tab Pemasangan halaman Customer detail — tombol "Batalkan Pemasangan" | Cancel Task Pemasangan terkait + `CustomerInstallation.installation_status=failed` + `Customer.status→rejected`, 1 transaksi |

Permission: `customers.detail.survey.reject` (survey, udah ada) dan `customers.detail.installation.reject` (pemasangan, **baru ditambah** ke `config/rbac.php` + role `noc`/`fop` di `RolePermissionSeeder`).

Kalau Task SRV/PSB udah `selesai` (laporan disubmit, tinggal diverifikasi) — bukan lagi "cancel", tapi "reject" via `CustomerVerificationController::reject()` (halaman Verif & Pemasangan), beda jalur (lihat § 9 poin "Dua jalur reject").

Detail implementasi, deviasi desain, & test: [analisa-auto-team.md § Task 9](analisa-auto-team.md).

## 10. Riwayat Lengkap + SLA Deadline Dual-Cycle (Task 10)

**Baru.** Halaman `GET /fop-tasks/history/{fop_task}` (`FopTaskController::showHistory()` → `fop_tasks/history_detail.blade.php`) — klik row mana pun di tabel Riwayat buka detail lengkap 1 tiket.

```
Task eksekusi berubah status
        │
        ▼
  TaskObserver::syncTaskReport($task)   (independen dari sync FopTask di § 9,
        │                                jalan duluan, gak nunggu FopTask ada)
        ▼
  status == in_progress?
   • belum ada TaskReport → started_at = now(), sla_target_minutes = task.sla_minutes
   • udah ada (siklus ke-2+) → resumed_at = now()
        │
  status == pending/reschedule?
   • accumulatedDuration += (resumed_at ?? started_at) → now()
   • pending_at = now()   (siklus DITUTUP, jeda pending gak ikut kehitung)
        │
  status == selesai?
   • accumulatedDuration += siklus terakhir
   • completed_at = now()
   • sla_status = on_time/over (bandingin total_duration_minutes vs sla_target_minutes)
        ▼
  task_reports row ke-update (1:1 per Task, HasOne)
```

Halaman Detail Riwayat nampilin 4 section:
1. **Info Task** — kategori, tanggal, area, prioritas, teknisi, team, issue, alasan Cancel (manual FOP, kalau ada) DAN alasan Ditolak Verifikasi Admin (kalau ada — section independen, lihat § 11).
2. **Durasi & SLA Pengerjaan** — dari `task_reports`: mulai, pending terakhir, resume terakhir, selesai, durasi aktual (akumulasi, exclude jeda pending), target SLA, status SLA (Tepat Waktu/Lewat SLA + overrun menit).
3. **Laporan** — baca LANGSUNG dari `CustomerSurvey`/`CustomerInstallation`/`TaskMaintenance` sesuai `category` (Survey/PSB/MTN), BUKAN duplikasi data ke `task_reports` (itu cuma nyimpen durasi/SLA, bukan konten laporan).
4. **Histori Status** — list `fop_task_status_history` (terbaru dulu), tiap baris: label granular + `from_status` + siapa yang ubah + kapan. Ini **audit trail utama** buat nelusurin kenapa suatu task nyampe ke status akhirnya (termasuk kapan masuk "Selesai — Menunggu Verifikasi" dan kapan/kenapa berujung "Selesai — Ditolak Verifikasi" — lihat § 11).

## 11. Task Tetap "Selesai" Terlepas dari Keputusan Customer (fix reject-sync gap, desain final — REVISI 2026-07-15)

**Kasus:** 2 teknisi ngerjain Task PSB, laporan submit (`Task.status=selesai`, `fop_review_status=pending`), customer masuk status `verification_admin` — TAPI admin belum approve/reject. Task ini berstatus apa dan ada di mana selama nunggu?

**Prinsip:** Task (kerjaan lapangan) VS keputusan bisnis (customer diterima/ditolak) itu 2 hal beda, sengaja gak dicampur — bukan cuma di bucket status, TAPI JUGA di label yang ditampilin ke user. Task 10 (SLA dual-cycle) udah nganggep kerjaan "sukses" begitu `Task.status=selesai` — badge yang dilihat FOP/teknisi harus konsisten sama prinsip ini, TITIK, gak boleh keliatan kayak "masih nunggu sesuatu".

```
Task.status=selesai + fop_review_status=pending (task_type SURVEY/PEMASANGAN)
        │
        ▼
  TaskObserver::resolveTarget() → TaskStatus::SELESAI (mirror langsung dari Task.status)
  (label histori 'selesai_menunggu_verifikasi', buat audit log)
        │
        ├──▶ FopTaskController::index() (antrian aktif)
        │     → OTOMATIS gak nangkring (selesai emang gak pernah
        │       masuk whereNotIn(selesai,dibatalkan) — TANPA query tambahan)
        │     → $teams activeCount/workload counter Tim OTOMATIS akurat
        │       (gak butuh exclusion khusus)
        │
        └──▶ FopTaskController::history() (Riwayat)
              → OTOMATIS masuk (whereIn(selesai,dibatalkan) polos, gak
                butuh orWhere tambahan)
              → badge status: TaskStatus::displayLabel() → "Selesai" POLOS
                — GAK ADA badge kedua/overlay verifikasi lagi
```

**Revisi 2026-07-15 (penting, beda dari draft sebelumnya):** Versi awal nambah **badge KEDUA "Verifikasi: Menunggu/Diterima/Ditolak"** + filter Riwayat terpisah (`verifikasi=menunggu|diterima|ditolak`) buat nunjukin nasib keputusan customer. **INI UDAH DICABUT TOTAL** — dianggap bikin ambigu (2 badge kesan kontradiksi: "Selesai" tapi "Menunggu" di sebelahnya). Sekarang: **cuma ada 1 badge status, "Selesai" polos, titik** — gak ada indikasi verifikasi apapun di UI Task FOP. Nasib keputusan customer (approved/pending/rejected) TETAP kesimpen lengkap di `fop_review_status` + `fop_task_status_history` (buat audit/histori), tapi mau lihat statusnya HARUS lewat halaman Verif & Pemasangan (Customer module) — bukan lewat Task FOP/Riwayat. Kolom/filter `verifikasi` di `history()` & `FopTask::verificationStatus()` (method) masih ada di kode (gak dipakai buat query filter, cuma method biasa), tapi UDAH GAK DIPANGGIL dari blade manapun.

**Kenapa gak taruh keputusan approve/reject di modul FopTask/Riwayat:** Riwayat murni nampung ("task yang udah dikerjakan dengan berbagai status"), keputusan sebenarnya (approve/reject pelanggan) tetap di Customer module — prinsip yang sama kayak reject laporan biasa gak dobel-implement di FopTaskController (lihat § 9 poin "Approve/reject laporan gak dapet endpoint baru").

Detail lengkap + test: `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL), `docs/project_status_label_unifikasi.md`, `tests/Feature/FopTaskVerificationOverlayTest.php`, `tests/Feature/CustomerVerificationRejectFopSyncTest.php`.

## 13. Cancel dengan Alasan — task_type NON-SRV/PSB (Task 12, 2026-07-22)

**Scope:** cuma MTN/DEAC/RELOKASI/C-REQ/O-REQ/INFR REQ — SURVEY/PEMASANGAN udah dikunci total di § 12 di atas, gak lewat jalur ini.

```
FOP klik "Cancel" di tabel /fop-tasks (kategori NON-SRV/PSB)
        │
        ▼
  Modal muncul — textarea alasan wajib (Alpine cancelModal, ganti
  window.Confirm polos yang lama)
        │
        ▼
  Submit PUT /fop-tasks/{id} {status: 'dibatalkan', cancel_reason: '...'}
        │
        ▼
  FopTaskController::update()
        │
        ├─ cancel_reason kosong? ──▶ 422 "Alasan pembatalan wajib diisi."
        │
        ├─ user gak punya permission fop_tasks.cancel? ──▶ 403
        │   (gate BARU, terpisah dari fop_tasks.update biasa)
        │
        ▼
  FopTask.status=dibatalkan, cancelled_at=now(), cancel_reason=alasan
        │
        ▼
  Task eksekusi terkait ikut dibatalkan — TaskService::cancel($task, $actor, $reason)
  (alasan yang SAMA diteruskan, bukan pesan generik lagi)
        │
        ├─ Task.status sebelumnya == in_progress?
        │        │
        │        ├─ YA ──▶ notifyTeam() ke semua anggota tim — AppNotification
        │        │          type=error, "Task dibatalkan: {alasan}"
        │        │
        │        └─ TIDAK (terjadwal/draft) ──▶ gak ada notifikasi
        │
        ▼
  rebuildTeamsForDate() jalan otomatis (kode existing, gak ada logic baru) —
  task yang dibatalkan otomatis ke-exclude dari query aktif, team yang cuma
  ditempatin task ini ikut kehapus di step cleanup
```

**Permission `fop_tasks.cancel`** (baru di `config/rbac.php`, `ActionCode::CANCEL`) — role `admin`/`fop` otomatis dapet lewat wildcard `fop_tasks.*` existing (gak perlu ubah `RolePermissionSeeder`, cuma `fop_tasks.update_sensitive` yang di-exclude eksplisit dari wildcard `fop` role).

**Notifikasi cancel ini SATU jalur buat SEMUA pemicu cancel** — logic-nya ditaruh di `TaskService::cancel()` sendiri (bukan di controller), jadi otomatis berlaku juga buat cancel SRV/PSB dari halaman Customer (§ 12) kalau task-nya kebetulan lagi `in_progress` pas dibatalin.

Test: `tests/Feature/FopTaskCancelTest.php` (7 test). Detail realisasi & file list lengkap: `docs/fop-task/analisa-auto-team.md` § Task 12.
