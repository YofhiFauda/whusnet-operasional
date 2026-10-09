# BUG 11 — Analisa & Rancangan Penghapusan Fitur "Alat Kerja"

Status: **Analisa selesai, belum dieksekusi.** Dokumen ini dibuat 2026-10-01 agar pengerjaan penghapusan bisa dilakukan di sprint lain tanpa riset ulang.

## A. Ringkasan Fitur Existing

Master Alat Kerja = checklist peralatan **dibawa-pulang** milik teknisi (tangga, splicer, OPM, OTDR, VFL, bor, dll). Dibuat lewat migration `2026_08_01_000003`/`000004`.

Beda tegas dari dua konsep lain yang sekilas mirip — **jangan ikut disentuh**:
- `items` / material habis pakai (ditinggal di pelanggan) — modul Master Barang, tidak terkait.
- `InventorySerial` + `OwnershipMode::COMPANY_ASSET` (warehouse asset tracking, laptop/OTDR sebagai aset pinjaman) — model & tabel beda total, cuma kebetulan dilabeli "Alat Kerja" di UI Master Barang.

Satu komponen Blade checklist dipakai di 3 form laporan:
- Survey (`CustomerSurveyController` + `surveys/report.blade.php`)
- Pemasangan (`CustomerInstallationController` + `installations/report.blade.php`)
- Maintenance/C-REQ/O-REQ/INFR REQ — **satu controller & view yang sama** (`TaskMaintenanceController` + `tasks/maintenance-report.blade.php`), tidak ada percabangan logic per task type.

Data dibaca ulang di halaman Verifikasi Admin, Detail Task, Riwayat FOP, dan diagregasi (ranking alat terpakai + trend) di FOP Analytics Dashboard — ini **dependency nyata**, bukan modul utama BUG 11 tapi ikut kena.

## B. Inventori Kode — Checklist Penghapusan per Layer

### Migration
- [ ] `database/migrations/2026_08_01_000003_create_work_tools_table.php` — hapus file (atau migration baru `drop table` kalau sudah jalan di prod — lihat Risiko D.1)
- [ ] `database/migrations/2026_08_01_000004_create_task_work_tools_table.php` — hapus file

### Model
- [ ] `app/Models/WorkTool.php` — hapus file penuh
- [ ] `app/Models/TaskWorkTool.php` — hapus file penuh
- [ ] `app/Models/FopTask.php:179-186` — hapus method `workTools(): HasMany`

### Service
- [ ] `app/Services/TaskWorkToolService.php` — hapus file penuh (`sync`, `rowsFromRequest`, `normalizeRows`, `resolveTaskFor`, `resolveTaskForCustomer`, `rowsFor`, `surveyRowsForCustomer`, `displayRowsForTask`)

### Controller
- [ ] `app/Http/Controllers/Master/WorkToolController.php` — hapus file penuh
- [ ] `app/Http/Controllers/CustomerSurveyController.php` — hapus blok `$workToolService`/`$workTools`/`$workToolRows` (±baris 264-271, 286 compact, validasi `work_tools_ids`/`work_tools_manual` ±385-389, assembly ±437-442, panggilan `sync()` ±588)
- [ ] `app/Http/Controllers/CustomerInstallationController.php` — pola sama, 2 jalur (laporan awal ±405-427, ±516-524, ±704-715; device-retrieval/report kedua ±898-906, ±1066-1077)
- [ ] `app/Http/Controllers/TaskMaintenanceController.php` — pola sama (±145-182, ±249-257, ±383-417) — jalur bersama MTN/C-REQ/O-REQ/INFR REQ
- [ ] `app/Services/FopTaskProvisioningService.php` — cuma komentar yang menyebut `task_work_tools`, update teksnya
- [ ] `app/Services/CustomerVerificationDetailService.php` — hapus blok `$workToolService`/`$surveyWorkTools`/`$installationWorkTools` (±75-86, ±113-114 compact)

### View / Blade
- [ ] `resources/views/components/work-tool-checklist.blade.php` — hapus file penuh
- [ ] `resources/views/verifications/partials/work-tools.blade.php` — hapus file penuh
- [ ] `resources/views/master/work_tools/{index,create,edit}.blade.php` — hapus direktori penuh
- [ ] `resources/views/surveys/report.blade.php` — hapus Section 3 (±279-290)
- [ ] `resources/views/installations/report.blade.php` (±430-433, ±704-707)
- [ ] `resources/views/tasks/maintenance-report.blade.php` (±282-285) — **cek numbering Section 1/2/4 di sekitarnya setelah Section 3 dicabut**
- [ ] `resources/views/tasks/show.blade.php` (±731-741 blok "Alat Kerja Wajib", ±962 `resolveTaskFor`, ±1045 "Alat Kerja Dipakai")
- [ ] `resources/views/fop_tasks/history_detail.blade.php` (±415, ±605 "Alat Kerja Dipinjam")
- [ ] `resources/views/tasks/partials/creq-detail.blade.php` (±4, cek konteks — kemungkinan cuma komentar)
- [ ] `resources/views/customers/tabs/_survey.blade.php` (±65-73, ±238-257 — query `TaskWorkTool` langsung + tabel)
- [ ] `resources/views/customers/tabs/_installation.blade.php` (±14-18, ±248-264 — pola sama)
- [ ] `resources/views/verifications/admin.blade.php` (±296, ±320-321, ±651-652 — include partial + title)
- [ ] `resources/views/fop/analytics.blade.php` (±20, ±63 "Pemakaian Alat Kerja")
- [ ] `resources/views/fop/partials/analytics-delta-badge.blade.php` (±3, komentar)
- [ ] `resources/views/components/layout/sidebar.blade.php` (±165-171 menu item)
- [ ] `resources/views/layouts/app.blade.php` (±693-695 — **ada 2 implementasi sidebar di repo, keduanya punya menu Alat Kerja**)

### Route
- [ ] `routes/web.php:654-668` — hapus blok "Master Alat Kerja" (6 route) + import `WorkToolController` (baris ±54)

### Seeder
- [ ] `database/seeders/WorkToolSeeder.php` — hapus file
- [ ] `database/seeders/WorkToolFeatureSeeder.php` — hapus file
- [ ] `database/seeders/DatabaseSeeder.php:36,53` — hapus 2 pemanggilan
- [ ] `database/seeders/FopAnalyticsDummySeeder.php` — hapus `attachWorkTools()` dan pemanggilannya (±59, 152-155, 234, 240-255)

### RBAC / Permission
- [ ] `config/rbac.php:479-484` — hapus blok permission `work_tools` (view/create/update/delete)
- [ ] `config/rbac.php:31, 99, 228-231` — hapus referensi di grup Master Data + deskripsi
- [ ] Cleanup `role_permissions` existing yang sudah assign `work_tools.*` — **tidak auto-cascade** cuma dari ubah config, lihat Risiko D.3

### Analytics (FOP Dashboard) — collateral, bukan opsional
- [ ] `app/Http/Controllers/FopAnalyticsController.php` (±14,16,28,96,195-231,258,601,676-679,732-735) — section ranking "alat kerja terpakai", query join ke `task_work_tools`
- [ ] `tests/Feature/FopAnalyticsDashboardTest.php` — assersi terkait `WorkTool`/`TaskWorkTool`

### Test
- [ ] `tests/Feature/WorkToolChecklistTest.php` — hapus file penuh (spesifik fitur ini)
- [ ] `tests/Feature/CreqReportIncompleteOnDetailPagesTest.php` — **jangan hapus file**, cuma assersi "alat kerja hilang dari Detail Task" (test ini gabungan regresi tikor+material+alat kerja+kode roll)
- [ ] `tests/Feature/VerificationAdminInputDataVisibilityTest.php` — hapus assertSee "Alat Kerja Dicatat Surveyor"/"Dipakai Tim Pemasangan"
- [ ] `tests/Feature/SurveyReportMaterialLostWithoutFopTaskTest.php` — hapus baris WorkTool/`work_tools_ids` dari skenario
- [ ] `tests/Feature/MasterBarangPermissionGeneratedTest.php` — hapus assersi permission `work_tools` (±56,90,103,109) — cek apakah test perlu rename karena sebagian scope-nya hilang
- [ ] `tests/Feature/FopAnalyticsDashboardTest.php` — hapus bagian ranking alat kerja

### Dokumentasi (update manual, bukan hapus file)
- `docs/pendaftaran-pelanggan/database-schema.md`
- `docs/fop-task/README.md`
- `docs/task-teknisi/{README,business-logic,user-flow,flowchart}.md`
- `docs/customer-lifecycle/business-logic.md`
- `docs/TASKS.md` — tandai histori sprint asal fitur ini selesai dicabut

## C. Dampak per Modul

| Modul | Dampak |
|---|---|
| **Master Data** | Menu "Alat Kerja" + CRUD hilang total, tabel `work_tools` di-drop |
| **Survey** | Form laporan kehilangan Section 3 checklist; `work_tools_ids`/`work_tools_manual` dibuang dari validasi & store |
| **Pemasangan** | Sama, 2 jalur form (laporan awal + device-retrieval) kehilangan checklist; prefill dari survey (`surveyRowsForCustomer`) ikut hilang |
| **Maintenance/C-REQ/O-REQ/INFR REQ** | Satu form bersama (`TaskMaintenanceController`) kehilangan Section 3 — hapus sekali jalan untuk ke-4 tipe, tidak ada percabangan per task type |
| **Verifikasi Admin** | Partial `work-tools.blade.php` hilang dari halaman verifikasi registrasi |
| **FOP Analytics Dashboard** | Card "Pemakaian Alat Kerja" (ranking + trend) hilang dari `/fop/analytics` |
| **Detail Task & Riwayat FOP** | Checklist read-only di `tasks/show.blade.php` & `fop_tasks/history_detail.blade.php` hilang |

## D. Risiko & Dependency Tersembunyi

1. **Data existing hilang permanen.** `task_work_tools` berisi histori checklist yang sudah diisi teknisi di lapangan. Drop table = tidak ada arsip. **Putuskan dulu**: backup/export sebelum drop, atau terima hilang (komentar migration asli menyatakan "tidak ada angka yang dipakai siapa pun" — perlu konfirmasi user sebelum eksekusi).
2. **FK aman.** `task_work_tools.fop_task_id` cascadeOnDelete ke `fop_tasks`; tidak ada tabel lain yang FK ke `work_tools`/`task_work_tools`.
3. **Permission zombie.** Baris `role_permissions` yang sudah assign `work_tools.*` ke role (owner, admin, dll) tidak otomatis bersih cuma dari ubah `config/rbac.php` — perlu migration/command cleanup data permission, kalau tidak nyangkut sebagai permission tanpa feature.
4. **Urutan eksekusi wajib: controller/view dulu, baru model/migration.** `FopAnalyticsController` query langsung `TaskWorkTool::query()` — kalau model dihapus duluan sebelum controller diedit, `/fop/analytics` fatal error. Kerjakan sebagai satu PR, jangan dicicil lintas sprint.
5. **Section numbering.** `tasks/maintenance-report.blade.php` dipakai bersama 4 task type, ada Section 1/2/3/4 eksplisit di komentar — cabut Section 3 jangan sampai merusak referensi Section lain di view yang sama.
6. **`CreqReportIncompleteOnDetailPagesTest` JANGAN dihapus filenya** — ini test regresi gabungan (tikor + material + alat kerja + kode roll), cuma assersi alat kerja yang dicabut.
7. **Dua implementasi sidebar** (`components/layout/sidebar.blade.php` dan `layouts/app.blade.php`) sama-sama punya menu Alat Kerja — jangan lewatkan salah satu.

## E. False Positive — Tidak Perlu Disentuh

- `app/Enums/OwnershipMode.php` + `resources/views/master/items/{create,edit}.blade.php` — label "Aset Perusahaan (Alat Kerja)" untuk mode kepemilikan barang gudang (OTDR/laptop sebagai aset pinjaman). Model & tabel beda total (`InventorySerial`/`items`), bukan dependency ke `WorkTool`. **Jangan ikut dihapus.**
- `docs/plan/billing/*`, `docs/plan/warehouse/*`, `docs/plan/analisa-dashboard-*` — cuma menyebut "alat kerja" sebagai istilah umum dalam narasi, bukan deskripsi fitur ini.

## F. Rencana Eksekusi (saat dikerjakan nanti)

1. Konfirmasi ke user: data `task_work_tools` existing boleh hilang tanpa backup? (lihat D.1)
2. Satu PR, urutan: Controller & View → Service → Analytics (FopAnalyticsController) → Test → Model → Seeder → RBAC → Migration (paling akhir, karena paling destruktif & paling gampang verifikasi lewat `php artisan migrate:status`).
3. Jalankan `vendor/bin/pint --dirty` setelah semua edit PHP.
4. `npm run build` setelah ubah Blade (sidebar, komponen checklist dihapus).
5. Jalankan test yang terdampak per file (`php artisan test --compact tests/Feature/<File>.php`), bukan full suite.
6. Cleanup `role_permissions` row `work_tools.*` lewat command/migration data, bukan manual query lepas tangan.
7. Update `docs/TASKS.md` — pindahkan BUG 11 ke Done dengan ringkasan apa yang dihapus.
