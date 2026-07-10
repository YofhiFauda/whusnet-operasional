# Analisa & Field-Lock RBAC: Registrasi → Survey → Pemasangan → Verifikasi Admin

**Tanggal:** 2026-07-08

## Latar Belakang

Pertanyaan: apakah RBAC sudah cukup di 4 titik siklus hidup pelanggan (Registrasi, Laporan Survey, Laporan Pemasangan, Verifikasi Admin), khususnya untuk kebutuhan berikut — begitu data survey/pemasangan sudah diisi dan masuk tahap Verifikasi Admin, dokumen-dokumen tersebut hanya boleh diperbarui oleh role tertentu (bukan role yang biasa mengisi di lapangan).

## Temuan Analisa

Sistem RBAC berbasis custom permission (`roles`, `role_permissions`, `user_role_scopes`), permission format `{feature}.{action}` di-generate dari `config/rbac.php`, dicek via `User::hasPermission()` + middleware `permission:xxx` (`app/Http/Middleware/CheckPermission.php`). Tidak ada Policy class untuk 4 fitur ini — semua authorize ad-hoc via `abort_unless(auth()->user()->hasPermission(...))`.

| Fitur | Permission gate | Lokasi | Status sebelum perbaikan |
|---|---|---|---|
| Registrasi Pelanggan | `customers.create` (route), `customers.update` (route, membungkus edit/update/destroy) | `routes/web.php:78-97` | OK — route sudah membungkus edit/update, temuan awal "tidak ada guard" keliru (cross-check ulang ke `routes/web.php` membuktikan grup middleware sudah ada) |
| Laporan Survey | `customers.detail.survey.view\|update` + cek keanggotaan tim task | `CustomerSurveyController.php` | Permission ada, **tapi `store()` tidak cek status pelanggan** — bisa dipanggil ulang meski pelanggan sudah lewat tahap survey |
| Laporan Pemasangan | `customers.detail.installation.view\|update` + cek keanggotaan tim task | `CustomerInstallationController.php` | Sama — `store()` tidak cek status pelanggan |
| Verifikasi Admin | `customers.detail.installation.validate` | `CustomerVerificationController.php` | OK — semua endpoint sudah gated, dan `finalVerify()` tidak menyentuh field survey/pemasangan sama sekali |

### Gap yang ditemukan (sebelum perbaikan)

`CustomerSurveyController::store()` dan `CustomerInstallationController::store()` hanya mengecek permission `*.update`, tanpa mengecek status pelanggan saat ini. Method `report()` di kedua controller sudah punya guard status (mis. survey hanya bisa diakses saat `survey_in_progress`), tapi guard itu **tidak diulang di `store()`** — jadi endpoint POST bisa langsung dipanggil (mis. replay request lama, atau resubmit form) untuk pelanggan yang statusnya sudah lewat tahap survey/pemasangan (sudah `verification_admin`, `active`, dst), selama user masih punya permission `*.update`. Ini berarti data yang sudah diverifikasi Admin bisa diubah ulang oleh role teknisi/FOP biasa — bertentangan dengan requirement "field yang sudah diverifikasi hanya boleh diubah role tertentu".

## Perbaikan yang Diimplementasikan

Ditambahkan guard status di awal `store()` pada kedua controller, memakai permission `*.validate` (permission yang sama dipakai `CustomerVerificationController`, sehingga role Admin/Verifikator yang berhak mem-validasi otomatis juga berhak melakukan koreksi data pasca-verifikasi — tanpa perlu permission baru):

- `app/Http/Controllers/CustomerSurveyController.php` — `store()`: hanya boleh jalan jika `customer->status === 'survey_in_progress'` **atau** user punya `customers.detail.survey.validate`.
- `app/Http/Controllers/CustomerInstallationController.php` — `store()`: hanya boleh jalan jika `customer->status` in `['installation_in_progress', 'revision_installation']` **atau** user punya `customers.detail.installation.validate`.

Efeknya:
- Teknisi/FOP (role lapangan, biasanya cuma punya permission `*.update`) hanya bisa mengisi/mengubah laporan survey & pemasangan selama tahap itu sedang berjalan (sesuai status workflow) — begitu pelanggan sudah diproses ke tahap berikutnya (via `CustomerVerificationController::processToTeam` / `finalVerify` / dst), endpoint `store()` menolak (403) request dari role tanpa permission `validate`.
- Role dengan permission `validate` (Admin/Owner/Admin Pusat — role yang juga berwenang di Verifikasi Admin) tetap bisa melakukan koreksi data survey/pemasangan kapan pun, termasuk pasca-verifikasi.
- Jalur revisi resmi tetap berfungsi normal: `CustomerVerificationController::revisi()` mengubah status pelanggan kembali ke `revision_installation` dan `installation_status` ke `in_progress`, sehingga teknisi lapangan otomatis diizinkan lagi mengisi ulang laporan pemasangan tanpa perlu permission `validate`.

Tidak menambah permission/config baru — memakai permission `.validate` yang sudah ada di `config/rbac.php` (`customers.detail.survey` & `customers.detail.installation` sudah punya action `VALIDATE`) dan sudah di-assign ke role admin via `database/seeders/RolePermissionSeeder.php`.

## Verifikasi

`php artisan test tests/Feature/CustomerSurveyTest.php tests/Feature/CustomerInstallationTest.php` — 5 test lulus, tidak ada regresi pada flow existing (technician can fill survey/installation, unauthorized rejected).

## Rekomendasi Lanjutan (belum diimplementasikan, di luar scope perbaikan ini)

- `CustomerController::update()` sudah aman di level route (`permission:customers.update`), tapi tidak ada guard status atau permission check inline (beda pola dengan `destroy()`/`assignSurvey()` yang double-check di controller). Kalau mau konsisten defense-in-depth, tambahkan `abort_unless(auth()->user()->hasPermission('customers.update'), 403)` di awal `edit()`/`update()`.
- Belum ada Policy class formal (`app/Policies`) untuk `Customer`, `CustomerSurvey`, `CustomerInstallation` — semua authorize masih ad-hoc via `hasPermission()`. Kalau field-lock logic makin kompleks (lebih dari status + 1 permission), pertimbangkan pindah ke Laravel Policy supaya lebih terpusat & testable.
