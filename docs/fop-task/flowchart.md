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
  Ambil semua FopTask aktif (Proses/Pending) di $date, load technicians
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
                                        (tiket Selesai/Cancel, filter sama)
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
