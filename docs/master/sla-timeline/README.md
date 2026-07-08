# Master Timeline SLA

Status: **Implemented** (2026-07-08)

Master Timeline SLA menyimpan **batas waktu wajib mulai ditangani** untuk tiap jenis tiket (Survey, Pemasangan, MTN, C-Req, O-Req, INFR REQ, Relokasi, DEAC), dan nilainya **berbeda-beda per Paket Internet**. Modul ini dipakai FOP Dashboard, Antrean Survey, dan Verif Pemasangan sebagai sumber deadline/progress bar tiket.

Bukan durasi pengerjaan teknisi di lapangan — fitur SLA pengerjaan (`tasks.sla_minutes`, `Task::isOverSla()`) di luar scope modul ini dan tidak diubah.

## Fungsi Utama

1. Admin mengatur matrix batas waktu (Paket × Jenis Tiket) di satu halaman.
2. Saat tiket (`FopTask`) dibuat, sistem otomatis resolve & snapshot SLA sesuai paket internet customer.
3. `FopTask::slaDeadline()`/`slaTotalSeconds()` pakai snapshot ini utk hitung deadline & progress bar — anchor waktu (dari kapan dihitung) tetap ikut pola existing per jenis tiket, cuma durasinya yang jadi configurable per paket.

## File Terkait

- **Migration**: `database/migrations/2026_07_08_120000_create_package_sla_settings_table.php`, `2026_07_08_120001_add_handling_sla_hours_to_fop_tasks_table.php`
- **Seeder**: `database/seeders/PackageSlaSettingSeeder.php`
- **Model**: `app/Models/PackageSlaSetting.php`, `app/Models/InternetPackage.php` (`slaSettings()`, `getHandlingSla()`), `app/Models/FopTask.php` (`booted()`, `slaDeadline()`, `slaTotalSeconds()`)
- **Enum**: `app/Enums/TaskType.php` (`defaultHandlingSlaHours()`)
- **Controller**: `app/Http/Controllers/Master/SlaTimelineController.php`
- **View**: `resources/views/master/sla-timeline/index.blade.php`
- **Route**: `routes/web.php` — `master.sla-timeline.index` (GET, `permission:sla_timeline.view`), `master.sla-timeline.update` (PUT, `permission:sla_timeline.update`)
- **RBAC**: `config/rbac.php` (`allowed_actions.sla_timeline`), `database/seeders/SlaTimelineFeatureSeeder.php` (root Feature `sla_timeline`), permission `sla_timeline.view`/`sla_timeline.update` digenerate `PermissionGeneratorService`, di-assign per role di `database/seeders/RolePermissionSeeder.php`

## Dokumentasi Terkait

| Dokumen | Isi |
|---|---|
| [business-logic.md](business-logic.md) | Aturan anchor per jenis tiket, resolusi durasi & fallback, kenapa snapshot bukan live, kenapa `Task` teknisi tidak ikut. |
| [database-schema.md](database-schema.md) | ERD `package_sla_settings`, kolom `fop_tasks.handling_sla_hours`, relasi ke skema existing. |
| [flowchart.md](flowchart.md) | Alur admin atur matrix, alur resolve+snapshot saat tiket dibuat, alur hitung deadline. |
| [user-flow.md](user-flow.md) | Skenario pengguna: admin atur SLA, dampak otomatis ke FOP saat tiket dibuat. |

## Keputusan Desain Ringkas

| Topik | Keputusan |
|---|---|
| Cakupan jenis tiket | Semua 8 jenis (Survey, Pemasangan, MTN, C-Req, O-Req, INFR REQ, Relokasi, DEAC) |
| Anchor Survey / Pemasangan | Dipertahankan persis seperti sebelumnya (`customer.created_at` / survey selesai) — cuma durasinya jadi configurable |
| Anchor jenis lain | `created_at` tiket dibuat |
| Ganti paket di tengah tiket berjalan | **Snapshot** — SLA di-freeze saat tiket dibuat |
| SLA pengerjaan teknisi (`Task`) | Tidak ikut dikembangkan — di luar scope, dibiarkan apa adanya |
| UI pengaturan | Halaman matrix terpisah "Master Timeline SLA" (bukan tab di form edit paket) |
| Notifikasi/eskalasi otomatis | Belum dibuat, di luar scope sekarang |

Detail lengkap tiap keputusan ada di [business-logic.md](business-logic.md).

## Verifikasi yang Sudah Dilakukan

- `php artisan migrate` — 2 migration jalan sukses.
- `php artisan db:seed --class=PackageSlaSettingSeeder` — 216 baris ke-seed (27 paket aktif × 8 jenis tiket).
- `php -l` semua file PHP yang diubah — no syntax error.
- `php artisan route:list --name=sla-timeline` — 2 route kedaftar.
- Tinker: `InternetPackage::getHandlingSla()` resolve benar (24 jam Survey, 72 jam Pemasangan utk paket default).
- `php artisan db:seed --class=SlaTimelineFeatureSeeder` + `--class=RolePermissionSeeder` — Feature `sla_timeline` & 2 permission (`sla_timeline.view`, `sla_timeline.update`) ter-generate, ter-assign ke role: `owner`/`admin` (view+update), `atasan`/`noc`/`helpdesk`/`pop_admin` (view saja), `fop`/`teknisi`/`sales` (tidak dapat, sama seperti akses Master Paket).
- **Belum dites**: end-to-end lewat browser (buka `/master/sla-timeline`, edit sel, cek tersimpan; buat `FopTask` baru lewat alur normal dan cek `handling_sla_hours` ke-snapshot benar; login sbg role tanpa akses dan pastikan menu + route ke-block).
