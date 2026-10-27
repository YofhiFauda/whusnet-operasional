# FOP Dashboard (`/fop`)

Landing page FOP: **Operational Command Center** 1 halaman — rancangan & audit lengkap di [docs/plan/fop/rancangan-fop-unified-dashboard.md](../plan/fop/rancangan-fop-unified-dashboard.md) (§5 berisi tabel audit "temuan → status implementasi" per 2026-10-12). Dokumen ini fokus ke mekanisme teknis; untuk daftar lengkap zona visual & keputusan desain, baca rancangan itu dulu.

Data terpadu lintas modul (pipeline pelanggan, tiket, task/tim FOP, teknisi, gudang, ODP) disusun oleh `App\Services\FopUnifiedDashboardService` — **bukan** cuma `FopDashboardController` seperti versi dokumen lama ini sebelumnya (lihat §"Struktur halaman" di bawah).

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

### Struktur halaman (redesign 2026-10-10/12 — Command Center)

Dual Mode Switcher di header (`⚡ Operasional Harian` ↔ `📊 Rekapitulasi & Analitik`, link ke `/fop/analitik`, gerbang `@can('fop_analytics.view')`) — lihat [dashboard analitik FOP](#dashboard-analitik-fop-fopanalitik) di bawah.

1. **Zona 1 — Emergency Ticker** (`fop.partials._emergency_ticker`) — alert tiket priority urgent/emergency + overdue survey, dari `FopUnifiedDashboardService::getEmergencyAlerts()`.
2. **Zona 2 — Hero Metrics** (`fop.partials._hero_metrics`, 6 kartu) — Antrean Survey, Perlu Aksi FOP, Task Selesai Hari Ini, Tiket Gangguan, **SLA Compliance**, Armada Teknisi. `FopUnifiedDashboardService::getHeroMetrics()`.
   - **SLA Compliance %** (diperbaiki 2026-10-12 — dulu rasio buatan campur 3 antrean beda makna) — sekarang breach rate sungguhan: `Ticket::isSlaBreached()` atas tiket terbuka + `Task::isOverSla()` atas task hari ini, satu populasi gabungan. Lihat §5 rancangan.
   - **Armada Teknisi Utilization%** (diperbaiki 2026-10-12 — dulu `(aktif+terjadwal)/total` menggelembungkan angka) — sekarang murni `aktif/total`.
3. **Zona 3 — Customer Delivery Pipeline** (`fop.partials._pipeline_funnel`) — 7-step funnel (Registrasi → Aktif), `getCustomerPipelineSummary()`.
4. **Zona 4 — Two-Column Command Grid**:
   - **Kolom kiri (tactical board, tab switcher)**: Team FOP Aktif (drag & drop antar tim, lihat §"Team FOP Aktif" lama di bawah — mekanismenya sama), Antrean Survey, Tiket Gangguan & C-REQ (`fop.partials._tickets_telemetry`, link ke riwayat FopTask kalau tiket sudah eskalasi).
   - **Kolom kanan (telemetry)**: Radar Teknisi (`fop.partials._technician_radar` — bar beban kerja relatif ke teknisi tersibuk, badge alat kerja dari `FopTask->workTools`, banner "beban tidak merata" kalau ada yang >4 task sementara yang lain standby), Inventory Telemetry (`fop.partials._inventory_telemetry` — Keluar/Terpakai/Sisa, widget Stok Kritis ONT/Modem, watchlist modem retur transit, **link ke Rekap Pemakaian Material** `/warehouse/usage` gerbang `warehouse.usage.view`), **Rincian Pemakaian Hari Ini** (`fop.partials._material_usage_detail` — daftar transaksi individual material/alat hari ini: barang, siapa mengerjakan, dipakai pada pelanggan atau infrastruktur, `FopUnifiedDashboardService::getMaterialUsageDetail()`), **Kapasitas ODP** (`fop.partials._distribution_glance` — entitas `NetworkNode`/`NetworkNodePort` terpisah dari Distribusi CID, Red Zone watchlist port terpakai ≥ `red_zone_threshold` per tipe node, `getOdpSummary()`, link `@can('network_nodes.view')` ke `/network/nodes`).

**Team FOP Aktif** (mekanisme drag & drop, tidak berubah dari sebelum redesign): grid card per `FopTaskTeam` aktif — header nama team + `work_date`, body list tiket (`tugas` + badge status warna, drag antar tim lewat `switch-team`), footer avatar inisial anggota, klik card → `openTeamDetail()` (Alpine) buka detail panel. Native HTML5 drag & drop auto-scroll window saat kursor dekat tepi viewport (`autoScrollOnDragOver()`, 2026-10-10 — drag tim di baris paling atas ke tim yang letaknya jauh di bawah viewport).

### Design system

Warna status pakai CSS var: `--color-success`, `--color-warning`, `--color-error`, `--color-info` (+ varian `-bg`/`-border`). Komponen baru (`metric-card`, partial-partial di atas) sudah full token `bg-surface`/`border-border`/`text-text-main`.

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
- Sub-komponen punya gerbang sendiri: tiket `tickets.view`/`tickets.history.view`, gudang `warehouse.usage.view` (bukan `warehouse.view` penuh — sengaja sempit biar `fop` gak ikut kebuka Stok/Scan/History/Damaged/Retrievals, lihat `routes/web.php` gerbang OR `warehouse.view|warehouse.usage.view`), ODP `network_nodes.view`/`.create`/`.update`.

## Dashboard Analitik FOP (`/fop/analitik`)

Halaman terpisah (`FopAnalyticsController`), agregat lintas periode (harian/mingguan/bulanan/tahunan + custom `from`/`to`) — **bukan** operasional live kayak `/fop`. Permission sendiri (`fop_analytics.view`). Sejak audit 2026-10-12: setiap tabel ranking/leaderboard/backlog/durasi punya link drill-down ke `FopAnalyticsController::drilldown()` (route `fop.analytics.drilldown`) yang nampilin daftar Task/FopTask individual di balik satu angka ranking, tetap di-scope `applyUserScope($user)` + filter POP. Detail lengkap tiap section: [docs/plan/analisa-dashboard-analitik-fop.md](../plan/analisa-dashboard-analitik-fop.md) dan §5 [rancangan-fop-unified-dashboard.md](../plan/fop/rancangan-fop-unified-dashboard.md).

## Riwayat Scan QR (`/qr-scan-logs`)

Halaman baru 2026-10-12 (`QrScanLogController`, permission `qr_scan_logs.view` — **owner, atasan, fop**, bukan `admin`) — satu-satunya pembaca tabel `qr_scan_logs` yang sejak Fase 1 QR Attendance sudah menjejak SETIAP percobaan scan termasuk gagal (`bad_signature`/`token_revoked`/`out_of_radius`/dll) tapi gak pernah dibaca controller/view apa pun. Jawaban langsung buat "sulit melihat celah orang main di lapangan" — scan gagal berulang dari teknisi yang sama adalah sinyal nyata. POP diturunkan dari relasi (`customer`/`task`/`ticket`, paling spesifik duluan); baris yang gak bisa diturunkan POP-nya dibuang buat user yang discope POP (deny-by-default). Rancangan asal & tabel kolom: `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §4.2/§5.

## Related

- [flowchart.md](flowchart.md) — alur status tiket & prioritas SLA, auto-team formation (Task 1), switch teknisi (Task 2)
- [user-flow.md](user-flow.md) — langkah pakai dashboard & `/fop-tasks`
- [database-schema.md](database-schema.md) — tabel `fop_tasks`, `fop_task_teams`
- [analisa-auto-team.md](analisa-auto-team.md) — analisa & Sprint Backlog Task 1/2
- [analisa-sync-execution-task.md](analisa-sync-execution-task.md) — bugfix & sync execution Task terkait Team
- [docs/plan/fop/rancangan-fop-unified-dashboard.md](../plan/fop/rancangan-fop-unified-dashboard.md) — rancangan Command Center + §5 audit implementasi (2026-10-12)
- [docs/master/odp/README.md](../master/odp/README.md) — Master ODP (entitas Kapasitas ODP di Zona 4)

---

**Files:**
- `app/Http/Controllers/FopDashboardController.php`
- `app/Services/FopUnifiedDashboardService.php` — agregator data lintas modul (pipeline, tiket, teknisi, gudang, ODP, material usage detail)
- `resources/views/fop/dashboard.blade.php` + `resources/views/fop/partials/_*.blade.php`
- `routes/web.php` — `GET /fop`, `GET /api/fop/pipeline`, `GET /fop/analitik`, `GET /fop/analitik/rincian`, `GET /qr-scan-logs`, `GET /network/nodes`

**Last updated:** 2026-10-12 (audit dashboard FOP & Analitik FOP — Command Center redesign, SLA/utilization real metric, ODP Red Zone, drill-down Analitik, Riwayat Scan QR, Rincian Pemakaian Material)
