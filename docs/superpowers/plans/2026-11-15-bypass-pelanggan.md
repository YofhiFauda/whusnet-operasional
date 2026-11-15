# Bypass Pelanggan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman `/bypass` — form registrasi yang langsung menjadikan pelanggan `active` tanpa verifikasi/survey/pemasangan/Tagihan Awal, untuk pelanggan legacy yang terlewat migrasi.

**Architecture:** Logika simpan registrasi (customer + alamat + layanan) diekstrak dari `CustomerController::store()` ke `CustomerRegistrationService::create()` supaya Registrasi biasa & Bypass memakai rumus yang sama. Bypass = `CustomerBypassController` tipis + `CustomerBypassRequest` + `CustomerBypassService` (status active, CID, log status, audit). Permission mengikuti pola Skip Survey: action baru `bypass` di sub-feature `customers.registration` → `customers.registration.bypass`.

**Tech Stack:** Laravel 13, PHPUnit 12, Blade + Alpine.

**Spec:** `docs/superpowers/specs/2026-11-15-bypass-pelanggan-design.md`

## Global Constraints

- Permission: `customers.registration.bypass` (penyesuaian dari `customers.bypass` di spec — format `{feature}.{action}` wajib, dan sub-feature `customers.registration` sudah ada di `view_autogrant_chain_boundary`).
- Seed hanya ke `owner` (otomatis lewat `*`) & `customer_service`.
- Status pelanggan `WorkflowTransition::ACTIVE`; `customer_services.service_status = 'aktif'`, `billing_status = 'active'`, `activation_date = registration_date` form.
- CID = `CustomerCidService::resolve()`; `mini_pop_id`/`distribution_id` null.
- Tidak membuat Task, FopTask, CustomerSurvey, Invoice apa pun.
- Catatan log status persis: `Aktif lewat Bypass (data migrasi terlewat)`.
- POP wajib dalam scope actor (`Pop::forUser()`).
- Enum, bukan string literal, untuk status.
- Komentar bahasa Indonesia menjelaskan *kenapa*.
- Jalankan hanya test terdampak (JANGAN full suite). `vendor/bin/pint --dirty --format agent` sebelum selesai.

## Review Focus

1. Registrasi biasa & Skip Survey tidak berubah perilaku setelah ekstraksi → jalankan `CustomerRegistrationTest`, `CustomerRegistrationSkipSurveyTest`, `CustomerRegistrationFabDocumentTest`, `CustomerRegistrationFotoRumahVisibilityTest`, `CustomerRegistrationDateDefaultTodayTest` di Task 2.
2. POP di luar scope lewat request yang dipalsukan → 422 (test di Task 3).
3. Kirim field `skip_survey=1` / data survey ke `/bypass` → diabaikan/ditolak, tidak membuat CustomerSurvey/Task (test di Task 3).
4. POP tanpa `cid_prefix` → CID null tetapi simpan tidak crash (test di Task 3).
5. Tagihan bulanan: generator tidak menerbitkan periode bulan aktivasi, menerbitkan bulan berikutnya (test di Task 3).

---

### Task 1: Permission `customers.registration.bypass`

**Files:**
- Modify: `app/Enums/ActionCode.php` (case `BYPASS = 'bypass'` setelah `SKIP_SURVEY`)
- Modify: `database/seeders/ActionSeeder.php` (entri `BYPASS`, name `Bypass`, description `Mendaftarkan pelanggan lama (terlewat migrasi) langsung aktif tanpa workflow`)
- Modify: `config/rbac.php` — `allowed_actions['customers.registration']` tambah `ActionCode::BYPASS->value`; `permission_name_overrides['customers.registration.bypass'] = 'Bypass Pelanggan (Langsung Aktif, Data Migrasi Terlewat)'`
- Modify: `database/seeders/FeatureSeeder.php` — nama sub-feature `customers.registration` jadi `Registrasi — Skip Survey & Bypass`
- Modify: `database/seeders/RolePermissionSeeder.php` — `customer_service` tambah `customers.registration.bypass`
- Test: `tests/Feature/CustomerBypassPermissionSeederTest.php`

- [ ] **Step 1: Failing test** — seed Feature/Action/Role/Permission/RolePermission; assert role `customer_service` & `owner` punya permission code `customers.registration.bypass`; role `sales`, `helpdesk`, `admin` TIDAK punya.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/CustomerBypassPermissionSeederTest.php` → FAIL.
- [ ] **Step 3:** Implement perubahan file di atas.
- [ ] **Step 4:** Test → PASS. Juga `tests/Feature/SalesSeederSkipSurveyScenarioTest.php` → PASS.

### Task 2: Ekstrak `CustomerRegistrationService::create()`

**Files:**
- Create: `app/Services/CustomerRegistrationService.php`
- Modify: `app/Http/Controllers/CustomerController.php` (`store()`)

**Interfaces:**
- Produces: `CustomerRegistrationService::create(array $validated, User $actor, string $status, array $files = []): Customer`
  - `$validated`: hasil validasi tanpa field survey/`skip_survey`; boleh berisi `jenis_kontrak`.
  - `$files`: `['foto_rumah' => ?UploadedFile, 'foto_kontrak' => ?UploadedFile, 'fab_document' => ?UploadedFile]`.
  - Menangani: normalisasi `sales_user_id`/`agent_id`, `created_by`/`updated_by`, `customer_code` via `Pop::generateRegistrationNumber()`, Person, Customer, foto, FAB, alamat, `customer_services` (mapping `service_status`/`billing_status` dari `$status` sama persis dengan `store()` sekarang), lalu `CustomerValidationService::validate()`. Dipanggil di dalam transaksi milik pemanggil.
  - Flash warning kelengkapan TETAP di controller (pakai hasil validate yang dikembalikan — service mengembalikan Customer; controller memanggil `CustomerValidationService::validate()` sendiri untuk pesan).

- [ ] **Step 1:** Jalankan test registrasi yang ada (lihat Review Focus #1) → catat baseline PASS.
- [ ] **Step 2:** Pindahkan logika; `store()` cuma: tentukan status, siapkan file, `DB::transaction` → `create()` + cabang Skip Survey (CustomerSurvey, Task, FopTask) tetap di controller.
- [ ] **Step 3:** Test baseline yang sama → PASS semua, tanpa ubah test.

### Task 3: Halaman & aksi Bypass

**Files:**
- Create: `app/Http/Requests/CustomerBypassRequest.php` (extends `CustomerRegistrationRequest`)
- Create: `app/Services/CustomerBypassService.php`
- Create: `app/Http/Controllers/CustomerBypassController.php`
- Modify: `routes/web.php` — blok static customers: `Route::middleware('permission:customers.registration.bypass')` → `GET /bypass` `customers.bypass.create`, `POST /bypass` `customers.bypass.store`
- Modify: `resources/views/customers/create.blade.php` — variabel `$bypassMode` (default false): judul `Bypass Pelanggan (Langsung Aktif)`, banner penjelasan, form `action` = `route('customers.bypass.store')`, sembunyikan blok Skip Survey, label `Tanggal Registrasi` → `Tanggal Aktivasi`
- Modify: `resources/views/layouts/app.blade.php` — submenu `Bypass Pelanggan` di bawah Registrasi, `@if(hasPermission('customers.registration.bypass'))`; tambahkan permission ke kondisi grup menu pelanggan
- Test: `tests/Feature/CustomerBypassTest.php`

**Interfaces:**
- Consumes: `CustomerRegistrationService::create()` (Task 2).
- Produces: `CustomerBypassService::register(array $validated, User $actor, array $files = []): Customer`
  - Dalam `DB::transaction`: `create($validated, $actor, WorkflowTransition::ACTIVE->value, $files)`; set `cid` = `CustomerCidService::resolve()` (null dibiarkan); `customer_services` diset `activated_by_name`, `activated_by_user_id`, `activation_time`; tulis `CustomerStatusLog` (`from_status` `registered` → `active`, note di Global Constraints) + `AuditLog` (`module` `Data Pelanggan`, `action` `activate_from_bypass`).
- `CustomerBypassRequest`: `authorize()` = `hasPermission('customers.registration.bypass')`; `rules()` = parent minus `skip_survey`, `nearest_odp`, `cable_estimation_meter`, `difficulty_level`, `survey_photo`, `requested_installation_date`, `foto_rumah` (prohibited), plus `pop_id` `Rule::in(Pop::forUser($user)->where('type','cabang')->pluck('id'))`.
- Controller: `create()` me-render `customers.create` dengan data sama seperti `CustomerController::create()` (ekstrak pengumpulan data ke private/helper bersama, jangan salin) + `bypassMode = true`; `store()` → service → redirect `customers.show` (fallback `customers.bypass.create` bila tanpa `customers.detail.view`), flash `Pelanggan {nama} langsung AKTIF lewat Bypass dengan CID {cid}`.

- [ ] **Step 1: Failing tests** di `CustomerBypassTest` (setup seperti `CustomerRegistrationSkipSurveyTest`):
  - `test_bypass_langsung_aktif_tanpa_task_survey_dan_tagihan_awal`: CS POST → customer status `active`, `cid` tidak null & diawali `cid_prefix` POP, `mini_pop_id`/`distribution_id` null, service `aktif`/`active`, `activation_date` = registration_date; `Task`, `FopTask`, `CustomerSurvey`, `Invoice` count 0; `CustomerStatusLog` note sesuai.
  - `test_role_tanpa_permission_bypass_ditolak`: sales GET & POST → 403.
  - `test_pop_di_luar_scope_ditolak`: CS dengan scope `selected_pop` POP A, POST pop B → error validasi `pop_id`, customer count 0.
  - `test_field_survey_dikirim_ke_bypass_diabaikan`: POST + `skip_survey=1` + data survey → tetap active, `CustomerSurvey`/`Task` 0.
  - `test_pop_tanpa_cid_prefix_tetap_tersimpan_cid_null`.
  - `test_tagihan_bulanan_mulai_periode_berikutnya`: activation 2026-11-10; `artisan invoices:generate-monthly --period=2026-11` (nama command dicek dari signature) → 0 invoice; `--period=2026-12` → 1 invoice BULANAN.
  - `test_halaman_bypass_tampil_untuk_cs`: GET 200, lihat teks `Bypass Pelanggan`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement file di atas.
- [ ] **Step 4:** Run `CustomerBypassTest` + test Review Focus #1 → PASS.
- [ ] **Step 5:** `npm run build` (Blade berubah) — via `docker compose run --rm assets` bila pakai container.

### Task 4: Dokumentasi & finalisasi

- [ ] `docs/TASKS.md`: entri `ADHOC-299` (Bypass Pelanggan) di tabel Ad-Hoc, status DONE, catat **Deploy:** `php artisan db:seed --class=ActionSeeder && php artisan db:seed --class=FeatureSeeder && php artisan db:seed --class=PermissionSeeder && php artisan db:seed --class=RolePermissionSeeder` (cek urutan/nama `PermissionSeeder` memanggil generator).
- [ ] `vendor/bin/pint --dirty --format agent`.
- [ ] Commit hanya bila user minta.
