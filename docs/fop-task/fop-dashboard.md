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
4. **`$activeTeams`** — Task yang punya >1 anggota tim (`teamMembers`), terjadwal/in-progress hari ini — dikelompokkan sebagai "Tim Gabungan" (nama diparsing dari prefix `[Tim X]` di judul Task).
5. **`$activeFopTeams`** — semua `FopTaskTeam` yang `isActive()` (POP-scoped), tiap team bawa list `FopTask` + status (fallback ke status Task eksekusi kalau ada) + progress percent + avatar inisial anggota.
6. **`$pops`** — daftar POP untuk filter (opsional).

Return: `view('fop.dashboard', compact('surveyQueue','stats','teknisiList','activeTeams','activeFopTeams','pops'))`.

## View: `resources/views/fop/dashboard.blade.php`

### Struktur halaman

1. **Header** — judul + tanggal hari ini.
2. **Stat cards** (grid 2/4 kolom) — Antrean Survey, Perlu Aksi FOP, Sedang Berjalan, Selesai Hari Ini. Badge merah "X Terlambat" muncul kalau `overdue_survey`/`overdue_installation` > 0.
3. **Team FOP Aktif** — grid card per `FopTaskTeam` aktif:
   - Header: nama team + `work_date`
   - Body: list tiket (`tugas` + badge status warna sesuai FopTaskStatus)
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

## Access Control

- **Guard:** `$this->authorize('viewAll', Task::class)` — policy, bukan middleware permission string.
- **POP scope:** `EffectiveAccessService::hasAllPopAccess()` / `getAllowedPopIds()` — user tanpa akses semua POP cuma lihat data POP dia.

## Related

- [flowchart.md](flowchart.md) — alur status tiket & prioritas SLA
- [user-flow.md](user-flow.md) — langkah pakai dashboard & `/fop-tasks`
- [database-schema.md](database-schema.md) — tabel `fop_tasks`, `fop_task_teams`

---

**Files:**
- `app/Http/Controllers/FopDashboardController.php`
- `resources/views/fop/dashboard.blade.php`
- `routes/web.php` — `GET /fop`, `GET /api/fop/pipeline`

**Last updated:** 2026-07-07
