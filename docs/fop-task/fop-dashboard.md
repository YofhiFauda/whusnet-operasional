# FOP Dashboard (`/fop`)

Landing page FOP: ringkasan operasional harian — stat card, Team FOP Aktif, antrean survey, list teknisi.

## Route

```
GET /fop                    Policy: viewAll (Task)     → FopDashboardController@index
GET /api/fop/pipeline       Policy: viewAll (Task)     → FopDashboardController@pipeline
```

## Controller: `FopDashboardController`

**File:** `app/Http/Controllers/FopDashboardController.php`

### `index(Request $request): View`

Data yang disiapkan (POP-scoped via `EffectiveAccessService` — user tanpa akses semua POP cuma lihat data POP yang di-assign ke dia):

1. **`$surveyQueue`** — max 50 pelanggan status `calon_pelanggan`/`waiting_survey`/`registered`, urut created_at terlama dulu. Tiap item bawa `remain_seconds`/`total_seconds` (SLA 1×24 jam) buat countdown di UI.
2. **`$stats`**:
   - `antrian_survey` — count `$surveyQueue`
   - `perlu_aksi_fop` — customer status `waiting_acc`/`surveyed`
   - `berjalan` — Task hari ini status `in_progress`
   - `selesai_hari_ini` — Task hari ini status `selesai`
   - `total_hari_ini` — total Task terjadwal/berjalan/selesai hari ini
   - `overdue_survey` — customer antrean survey yang `created_at + 1 hari < now()`
   - `overdue_installation` — customer nunggu instalasi yang Task survey-nya `completed_at + 3 hari < now()`
3. **`$teknisiList`** — semua user role `teknisi` (scoped POP), status `aktif`/`terjadwal`/`standby` + lokasi (alamat customer) kalau lagi ada Task in-progress.
4. **`$activeTeams`** — legacy, **gak dipakai di blade** (dikonfirmasi lewat riset kode, lihat [analisa-sync-execution-task.md](analisa-sync-execution-task.md#3-logika-tombol--masukkan-ke-team--simptom-dari-bug-1-bukan-bug-terpisah)). Task yang punya >1 anggota tim (`teamMembers`), terjadwal/in-progress hari ini, dikelompokkan sebagai "Tim Gabungan" dengan nama diparsing dari prefix `[...]` di judul Task — variabel ini masih dihitung tapi gak pernah dirender, jadi harus diabaikan sebagai sumber kebenaran soal Team (pakai poin 5 di bawah).
5. **`$activeFopTeams`** — sumber data Team yang BENERAN ditampilkan. `FopTaskTeam` yang `isActive()` (POP-scoped), tiap team bawa list `FopTask` + status (fallback ke status Task eksekusi kalau ada) + progress percent + avatar inisial anggota. Sejak Task 1/2, ini query LANGSUNG ke DB tiap load — otomatis reflect hasil auto-team rebuild & switch teknisi tanpa cache/delay (dikonfirmasi lewat test, lihat [analisa-sync-execution-task.md](analisa-sync-execution-task.md)).

   **Jendela tanggal (diperbaiki 2026-08-13).** Perbaikan 2026-07-22 memangkas papan jadi `work_date` = **hari ini saja** untuk membunuh query 300+ team per refresh (versi lama memuat SEMUA team beserta anaknya lalu memfilter di PHP). Itu kebablasan: tim yang sudah dijadwalkan di `/fop-tasks` **lenyap dari papan begitu ganti hari**, padahal task-nya masih hidup dan teknisinya masih melihatnya (`TaskController::index()` punya cabang overdue, papan tidak).

   Aturan sekarang:
   - `work_date` = hari ini → **selalu** tampil;
   - `work_date` lampau → tampil **selama masih punya task aktif** (bukan `selesai`/`dibatalkan`), disaring di SQL lewat `whereHas` sehingga tim yang sudah rampung tidak ikut dimuat sama sekali — beban query yang dulu jadi alasan pembatasan tidak kembali;
   - dibatasi `BOARD_MAX_PAST_DAYS` = **30 hari** ke belakang supaya papan tidak pelan-pelan berubah jadi arsip; lebih tua dari itu hanya lewat `/fop-tasks`;
   - diurut `work_date` menaik — tanggal terlama di atas, karena tim yang harinya sudah lewat lebih perlu ditangani duluan.

   Kartu tim sudah menampilkan `work_date`-nya sendiri, jadi tim dari tanggal lampau terbaca apa adanya tanpa perlu penanda tambahan.

   Penjaga: `FopDashboardPastTeamsTest` (6 test).
6. **`$pops`** — daftar POP untuk filter (opsional).

> Catatan: `fop:reset-cancelled-tasks` **sudah dihapus** (2026-08-13). Dulu ia mengubah task `dibatalkan` jadi `in_progress` tiap 00:01 tanpa memberi `task_date` baru — yang akan menjadikannya penghuni tetap papan sebagai tim lampau, di samping menghapus keputusan pembatalan tanpa jejak di riwayat tiket. **Pembatalan Task FOP bersifat final**; penundaan sehari lewat Pending atau ubah tanggal. Lihat `docs/RUNBOOK_COMMANDS.md` dan ADHOC-34 di `docs/TASKS.md`.

## View: `resources/views/fop/dashboard.blade.php`

### Struktur halaman

1. **Header** — judul + tanggal hari ini.
2. **Stat cards** (grid 2/4 kolom) — Antrean Survey, Perlu Aksi FOP, Sedang Berjalan, Selesai Hari Ini. Badge merah "X Terlambat" muncul kalau `overdue_survey`/`overdue_installation` > 0.
3. **Team FOP Aktif** — grid card per `FopTaskTeam` aktif:
   - Header: nama team + `work_date`
   - Body: list tiket (`tugas` + badge status warna — sejak unifikasi 2026-07-20, `TaskStatus` kalau ada Task terhubung, atau `FopTask.status` sendiri kalau standalone/draft, lihat `database-schema.md`)
   - Footer: avatar inisial anggota (max 4 + counter) + total tiket
   - Klik card → `openTeamDetail(team.id)` (Alpine) buka detail panel
   - Link "Kelola Team →" ke `/fop-tasks`
4. Antrean survey list + tabel teknisi (di bawah, tidak ditampilkan penuh di sini — lihat file blade langsung).

### Design system

Warna status pakai CSS var: `--color-success`, `--color-warning`, `--color-error`, `--color-info` (+ varian `-bg`/`-border`). Card dasar masih pakai Tailwind `bg-white border-slate-200` (belum full migrasi ke `--color-surface`).

## SLA Overdue Logic

### Overdue Survey (1×24 jam)

```php
Customer::whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
    ->whereRaw('DATE_ADD(created_at, INTERVAL 1 DAY) < NOW()')
    ->count();
```

### Overdue Installation (3×24 jam sejak survey selesai)

```php
Customer::whereIn('status', ['waiting_installation', 'installation_in_progress', 'verification_admin', 'waiting_acc', 'surveyed'])
    ->whereHas('tasks', function ($q) {
        $q->where('task_type', TaskType::SURVEY->value)
          ->where('status', 'selesai')
          ->whereRaw('DATE_ADD(completed_at, INTERVAL 3 DAY) < NOW()');
    })
    ->count();
```

Query ini basisnya tabel `customers` join `tasks` (bukan `customer_surveys` seperti versi dokumen lama) — samain sama logic di `FopTask::slaDeadline()` ([database-schema.md](database-schema.md)).

**Alert SLA breach (baru, 2026-08-07) — bukan cuma indikator visual pasif lagi.** Sebelumnya angka/badge SLA di dashboard ini murni pull (dashboard harus dibuka buat kelihatan). Command scheduled `fop-tasks:check-sla-breach` (`everyThirtyMinutes()`, `routes/console.php`) sekarang notif in-app (lonceng + toast) ke semua user role `fop` di POP terkait begitu `FopTask::slaDeadline()` kelewat, pakai deadline yang SAMA PERSIS dipakai badge di dashboard ini — gak ada logic kedua yang bisa menyimpang. Dedup lewat kolom `fop_tasks.sla_breach_notified_at` (lihat [database-schema.md](database-schema.md)). Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.4.

> ⚠️ **Known issue (ketemu gak sengaja pas verifikasi Task 1/2, belum difix):** `DATE_ADD(...)`/`NOW()` di atas MySQL-only, error kalau `index()` dijalanin di atas SQLite. Gak berdampak ke production (asumsi DB production MySQL), tapi bikin dashboard ini gak bisa diikutkan test otomatis di atas SQLite. Detail di [analisa-sync-execution-task.md](analisa-sync-execution-task.md#10-isu-lain-yang-ketemu-terpisah-belum-difix).

## Access Control

- **Guard:** `$this->authorize('viewAll', Task::class)` — policy, bukan middleware permission string.
- **POP scope:** `EffectiveAccessService::hasAllPopAccess()` / `getAllowedPopIds()` — user tanpa akses semua POP cuma lihat data POP dia.

## Related

- [flowchart.md](flowchart.md) — alur status tiket & prioritas SLA, auto-team formation (Task 1), switch teknisi (Task 2)
- [user-flow.md](user-flow.md) — langkah pakai dashboard & `/fop-tasks`
- [database-schema.md](database-schema.md) — tabel `fop_tasks`, `fop_task_teams`
- [analisa-auto-team.md](analisa-auto-team.md) — analisa & Sprint Backlog Task 1/2
- [analisa-sync-execution-task.md](analisa-sync-execution-task.md) — bugfix & sync execution Task terkait Team

---

**Files:**
- `app/Http/Controllers/FopDashboardController.php`
- `resources/views/fop/dashboard.blade.php`
- `routes/web.php` — `GET /fop`, `GET /api/fop/pipeline`

**Last updated:** 2026-07-11 (ditambah catatan Task 1/2: sumber data Team `$activeFopTeams`, known issue SQL MySQL-only)
