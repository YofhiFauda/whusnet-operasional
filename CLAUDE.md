# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Panduan kerja AI di repo ini. Bahasa kerja: Indonesia (kode, komentar, commit, dokumentasi).

## Project

**Whusnet Operasional** — sistem operasional + billing ISP internal. Master data pelanggan jadi pusat sistem.

Dua sumbu yang saling silang:

```
Billing   : Pelanggan → Paket → Layanan Aktif → Tagihan → Pembayaran → Laporan
Lapangan  : Ticketing → FOP Task → Task Teknisi → Verifikasi → Aktivasi Layanan
```

Fase MVP sudah lewat. Sprint aktif ada di `docs/TASKS.md` bagian "Status Project Saat Ini" — **baca itu dulu sebelum mulai task apa pun.**

## Tech Stack

- PHP 8.3, Laravel 13, PHPUnit 12 (bukan Pest), Pint
- Blade server-rendered + **Alpine.js 3 via CDN** (`layouts/app.blade.php`) — bukan SPA, tidak ada build step untuk Alpine
- Tailwind CSS 4 (`@tailwindcss/vite`) + Vite 7 untuk `resources/css/app.css` & `resources/js/app.js`
- Laravel Reverb + laravel-echo + pusher-js — realtime dashboard FOP & task teknisi
- Laravel Horizon — queue worker
- `spatie/simple-excel` — import/export pelanggan
- DB default `sqlite` (`.env.example`); test pakai sqlite `:memory:`
- Git: kerja di `dev`, PR ke `master`

## Perintah

```bash
composer dev          # serve + queue:listen + pail + vite (concurrently)
composer test         # config:clear && artisan test
php artisan test --filter=TicketingTest
vendor/bin/pint       # WAJIB sebelum commit
php artisan migrate && php artisan db:seed
php artisan reverb:start
php artisan horizon
```

## Arsitektur

### Pembagian layer
- **Controller** — tipis: validasi request, cek scope, delegasi ke Service, return view/JSON.
- **Service** (`app/Services/`) — **semua business logic ada di sini.** Jangan taruh logic di controller atau model.
- **Observer** (`app/Observers/`) — invariant + jejak audit yang harus jalan dari *semua* jalur masuk (controller, artisan, import, tinker). Lihat "Observer" di bawah.
- **Policy** (`app/Policies/TaskPolicy.php`) — otorisasi per-aksi task, granular (21 ability).
- **Event** (`app/Events/`) — broadcast realtime.
- **Enum** (`app/Enums/`) — semua status/tipe. **Jangan pakai string literal.**
- **Helper** — `app/Helpers/FormatHelper.php`, `helpers.php`, `app/Support/IndonesianDate.php`, `app/Support/ReasonValidationRule.php`.

### Service
| Service | Tanggung jawab |
|---|---|
| `TicketService` | Semua transisi status tiket: `create()` (snapshot pelanggan + lampiran, **tanpa** bikin FopTask) → `close()`/`cancel()`/`escalateToNoc()`/`onCheckNoc()`/`escalateToFop()`/`returnToHelpdesk()`. FopTask cuma kebentuk di `escalateToFop()` atau submit dari halaman Task FOP |
| `TaskService` | task teknisi: `create/update/start/complete/setPending/cancel/reassignTeam/detectConflicts` + sync balik ke FopTask |
| `FopTaskTeamService` | `rebuildTeamsForDate()` — tim harian FOP, dipanggil tiap jadwal berubah |
| `CustomerWorkflowService` | transisi status pelanggan (`WorkflowTransition`) |
| `CustomerValidationService` | kelengkapan data pelanggan |
| `EffectiveAccessService` | resolusi permission + POP scope efektif (**cached**) |
| `UserScopeManagementService` | CRUD user role scope |
| `RoleManagementService`, `PermissionGeneratorService` | RBAC dinamis dari `features` × `actions` |
| `FileUploadService` | evidence, dokumen, foto |
| `TelegramBotService` | notifikasi teknisi (opsional, pelengkap in-app notif) |

### Enum — jangan bikin string baru
- `TaskStatus`: `draft`, `terjadwal`, `in_progress`, `selesai`, `dibatalkan`, `pending`
- `TaskType`: `SURVEY`, `PSB`, `MTN`, `DEAC`, `C-REQ`, `O-REQ`, `INFR REQ`. `SURVEY`/`PSB`/`DEAC` = `autoOnlyValues()` — gak bisa dipilih manual, `DEAC` cuma lewat tombol "Ambil Alat" di List Putus Langganan. `RELOKASI` dihapus permanen dari sistem.
- `TicketHandler`: `helpdesk`, `noc`, `fop` — siapa yang lagi pegang tiket. Beku permanen begitu `fop`.
- `TicketHandlingStatus`: `open`, `closed`, `cancelled` — status internal tiket, cuma bermakna selama `handler` ≠ `fop`.
- `TicketHistoryAction`: `dibuat`, `dieskalasi`, `dicek_noc`, `diselesaikan`, `dikembalikan`, `dibatalkan`
- `TicketBucket`: `masuk`, `diproses`, `selesai`, `dibatalkan` — **klasifikasi, bukan route** (route `/tickets/{bucket}` sudah dihapus).
  → **Tiap `TaskStatus` baru wajib dipetakan ke bucket.** Ada test yang sengaja gagal kalau lupa.
- `InvoiceStatus`: `belum_dibayar`, `sebagian`, `lunas`, `batal`
- `WorkflowTransition` (14 state): `registered` → `waiting_survey` → `survey_in_progress` → `surveyed` → `waiting_acc` → `waiting_installation` → `installation_in_progress` → `installed` → `verification_admin` → `active`; cabang `revision_installation`, `rejected`, `suspended`, `terminated`
- Lain: `ScopeType`, `InvoiceType`, `PaymentStatus`, `FopTaskPriority`, `NotificationType`, `DocumentType`, `FeatureType`, `ActionCode`, `Gender`, `UserStatus`

## Sinkronisasi Ticket ↔ FopTask ↔ Task

Bagian paling rawan di repo ini. Tiga entitas, tiga nomor, sinkron dua arah.

```
Ticket (TKT-YYYY-NNNN)          FopTask TIDAK auto-dibuat saat submit!
  handler=HELPDESK, status=OPEN
       │
       ├─ close()/cancel()  → selesai/batal TANPA pernah nyentuh FOP
       │
       ├─ escalateToNoc()   → handler=NOC, noc_checked_at=NULL ("Pending NOC",
       │                       Helpdesk MASIH boleh act)
       │      └─ onCheckNoc() → noc_checked_at terisi, Helpdesk lepas kendali
       │
       └─ escalateToFop()   → SATU-SATUNYA titik FopTask kebentuk
             └─ syncToFopTask() → FopTask (TFOP-YYYY-NNNN, status DRAFT)
                                    ├─ ticket.fop_task_id → FopTask
                                    ├─ ticket.handler = FOP  (TERMINAL)
                                    └─ fop_task.task_id → Task (TASK-YYYY-NNNN)
                                          └─ TaskService::syncToFopTask()
                                               sync teknisi + task_date balik ke FopTask
                                               lalu FopTaskTeamService::rebuildTeamsForDate()
                                               untuk tanggal lama DAN tanggal baru
```

Aturan:

1. **Tiket PUNYA kolom status sendiri** — `handler` (`TicketHandler`) + `status` (`TicketHandlingStatus`) + `noc_checked_at`. Selama `handler` ≠ FOP, status tiket **tidak** diturunkan dari FopTask. Jangan balik lagi ke asumsi lama "tiket gak punya status".
2. **`handler=FOP` itu terminal buat sisi Ticketing** — `assertTicketStillOpen()` nolak semua aksi Ticketing begitu sampai sini. Pembatalan pasca-FOP wajib lewat `/fop-tasks`.
3. **Window "Pending NOC"** — `Ticket::holderRoles()` adalah SATU-SATUNYA sumber "siapa yang boleh act": handler=NOC + `noc_checked_at` NULL ⇒ `['helpdesk','noc']`; setelah di-Oncheck ⇒ `['noc']`. Dipakai bareng `TicketService::assertActorOwnsTicket()` (otorisasi asli) dan `Ticket::actionFlagsFor()` (gerbang tombol). Jangan duplikasi logic ini di tempat ketiga.
4. **`TFOP-` digenerate di dua tempat** — `TicketService::generateFopTaskNumber()` dan `FopTaskController::generateTaskNumber()`. Format wajib identik, keduanya nulis ke deret yang sama.
5. **`fop_task.tugas` = `"{display_id}_{full_name}"`** (mis. `C1X4ARQ000631_Masudah Yuni Fitri`) — identitas pelanggan konsisten seluruh sistem, bukan label tipe tiket generik.
6. **`fop_task.notes` cuma pointer pendek** (`"Ticket TKT-… — dikirim oleh …"`). Jangan salin `catatan_teknis` ke sini — itu bikin dua sumber kebenaran yang gampang menyimpang.
7. **Riwayat pembatalan: satu aksi, dua riwayat, satu penulis per sisi.**
   - Sisi Ticket (`ticket_histories`) → **hanya** `FopTaskObserver`.
   - Sisi FOP (`fop_task_status_history`) → jalur `/tasks` ditulis `TaskObserver`; jalur `/fop-tasks` ditulis `FopTaskController::update()` (karena `TaskObserver` early-return begitu FopTask sudah `dibatalkan`).
   - **Jangan pindah-pindah penulisnya** — langsung jadi riwayat dobel.
   - Pembatalan **pra-FOP** (`TicketService::cancel()`) cuma nulis `ticket_histories` — belum ada FopTask buat dicatat sisi lainnya. Prinsip "dua riwayat" cuma berlaku pasca-FOP.
8. **Tiap halaman Ticketing punya permission sendiri** — `tickets.selesai.view`, `tickets.dibatalkan.view`, `noc_worksheet.masuk.view`, `noc_worksheet.diproses.view`, `noc_dashboard.view`. Jangan tambah halaman baru yang numpang `tickets.view` generik.

Sebelum menyentuh alur ini, baca `docs/ticketing/business-logic.md` dan `docs/fop-task/analisa-sync-execution-task.md`.

## RBAC

Roles (`RoleSeeder`, `is_system`): `owner`, `atasan`, `admin`, `noc`, `helpdesk`, `fop`, `teknisi`, `sales`, `pop_admin`.

### Permission

Format `{feature_code}.{action_code}` lowercase — `customers.view`, `customers.detail.devices.view_sensitive`. Di-generate dari tabel `features` × `actions` (`PermissionGeneratorService`), **bukan hardcode**.

Resolusi di `EffectiveAccessService::userCan()`, urutan:
1. exact match
2. wildcard global `*`
3. feature wildcard `customers.*`
4. nested wildcard `customers.import.*`
5. prefix match — punya `customers.import.view` ⇒ lolos cek `customers.import`

Enforcement route pakai alias middleware `permission` (satu-satunya alias di `bootstrap/app.php`):

```php
Route::middleware('permission:customers.view')->group(...);
Route::middleware('permission:roles.view|roles.update')->group(...);  // pipe = OR
```

`*` mem-bypass middleware sepenuhnya. `hasFullAccess()` = `hasPermission('*')`.

### POP Scope

`ScopeType` cuma **3**: `all_pop`, `selected_pop`, `pop_tree`. Tidak ada `assigned_only`/`own_created` sebagai ScopeType — pembatasan per-user ditangani di query (mis. `task.view.own`, `TaskPolicy::viewOwn`).

**Ada dua jalur scoping di codebase — sadari bedanya:**
- `EffectiveAccessService::getAllowedPopIds($user)` — dari `user_role_scopes` + `user_role_scope_targets`, dukung `pop_tree` (rekursif ke Mini POP). **Ini jalur yang benar.**
- `$user->pops()` — pivot `user_pops` langsung, dipakai di beberapa controller lama & `routes/channels.php`. Tidak paham `pop_tree`.

Kalau nulis kode baru, pakai `EffectiveAccessService`.

**`getAllowedPopIds()` mengembalikan array kosong untuk ALL_POP.** Array kosong itu ambigu — bisa berarti akses penuh, bisa berarti scope belum di-setup. Jangan tafsirkan sendiri; pakai `hasAllPopAccess()` yang sudah menangani deny-by-default.

Permission & scope **di-cache**. Setelah mengubah role/permission/scope, panggil `EffectiveAccessService::clearCache($user)`.

### Larangan keras
1. **Dilarang bikin role per cabang** (`NOC Ponorogo`, `Teknisi Siman`). Role global, batasi lewat scope.
2. **Dilarang kasih permission langsung ke user** tanpa lewat matrix role.
3. **Setiap query pelanggan/task/invoice/laporan wajib lewat POP scope.** Query tanpa scope = kebocoran data lintas cabang → berhenti dan tanya.
4. Teknisi tak boleh catat pembayaran. `pop_admin` tak boleh lihat pelanggan luar scope. Helpdesk tak boleh ubah nominal tagihan terbit. Sales tak boleh akses laporan keuangan.

## Business Rules

### Pelanggan
Boleh disimpan walau belum lengkap. Draft → Perlu Dilengkapi → Lengkap → Siap Billing. Belum lengkap **tidak boleh** masuk billing aktif.

Wajib untuk siap billing: nama lengkap, nomor HP, alamat lengkap, desa, kecamatan, kota/kabupaten, POP, paket internet, harga bulanan, tanggal aktivasi, tanggal jatuh tempo, status layanan.

### Billing
Tagihan turunan dari Pelanggan Aktif + Paket Aktif + Harga Layanan + Periode — bukan dibuat dari nol. Harga diambil dari `customer_services`. Tidak boleh dobel per periode (ada unique index, lihat migration `add_duplicate_guard_indexes_to_invoices_and_payments`). Tagihan lunas tidak dihapus sembarangan.

### Pembayaran
Wajib terhubung invoice + pelanggan + POP. Penuh → `lunas`; kurang → `sebagian`; ditolak → tidak boleh jadi `lunas`. Semua perubahan masuk audit log.

`PaymentObserver::creating()` menolak nominal ≤ 0 dari **semua** jalur masuk — data legacy punya baris "pembayaran" `BAYAR=0` yang sebenarnya placeholder log aktivasi. Jangan lemahkan guard ini.

### SLA — dua konsep, jangan dicampur
- **Handling SLA** — kecepatan FOP respons/assign tiket. Kolom `handling_sla_hours` di `fop_tasks`.
- **SLA Pengerjaan** — durasi teknisi kerjakan task, dihitung di `TaskService` / `TaskReport`.

`PackageSlaSetting` untuk SLA paket. **Bukan** untuk SLA pengerjaan teknisi.

### Penomoran & ID
- `TKT-{tahun}-{4 digit}`, `TFOP-{tahun}-{4 digit}`, `TASK-{tahun}-{4 digit}`
- CID pelanggan digenerate per-POP: prefix di tabel `pops` + `PopSequence`. Lihat `docs/ID_NUMBERING_RULES.md` dan `docs/master/pop/business-logic.md`.
- Data legacy multi-cabang (jetis_db, sand_db) punya risiko tabrakan ID (PE/RQ/IDBIAYA). **ID legacy wajib di-namespace per cabang.**

### File & lampiran
Lampiran tiket disimpan di disk **`local` (privat)**, bukan `public` — isinya bisa memuat data pelanggan. Akses hanya lewat controller yang mengecek permission + POP scope (`TicketController::download()`). Jangan pindahkan ke disk public atau bikin URL yang bisa ditebak.

## Testing

- ~90 file `tests/Feature`, 4 `tests/Unit`. **Fitur/perbaikan baru wajib ada test.**
- `RefreshDatabase`, sqlite `:memory:`, `QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=null`, locale `id` / `Asia/Jakarta`.
- Pakai atribut PHPUnit modern: `#[DataProvider]`, `#[Test]` — bukan anotasi docblock.
- `Tests\TestCase::loginAsAdmin()` — helper login sebagai Owner (auto-seed `RoleSeeder` kalau perlu).
- Test regresi diberi nama sesuai gejalanya, bukan sesuai kelas (`FopTaskCancelCascadeAuthTest`, `CustomerVerificationRejectFopSyncTest`). Ikuti pola itu.

## Konvensi Kode

- **Komentar bahasa Indonesia yang menjelaskan *kenapa*, bukan *apa*.** Repo ini komentarnya panjang dan argumentatif di titik-titik rawan (observer, sync, guard). Waktu menyentuh area itu, ikuti gaya yang sama — jelaskan keputusan dan konsekuensi kalau dilanggar.
- **Urutan route: static dulu, dynamic belakangan.** `routes/web.php` menandai ini eksplisit (`// Customers Management - Static Routes First` … `- Dynamic Routes Last`). Route `{id}` yang naik ke atas akan menelan route statis.
- **Redirect setelah simpan pakai pola PRG.** Handler `POST`/`PUT`/`DELETE` selalu redirect (jangan render view langsung — refresh = double-submit). Create/update satu record → halaman Detail (`*.show`); list/board hanya untuk aksi list-oriented (import massal, papan FOP). Aturan + peta lengkap: `docs/PRG_REDIRECT_CONVENTION.md`.
- Sederhana, tidak overengineered. Hindari abstraksi sebelum dibutuhkan, otomatisasi sebelum flow manual stabil, tabel baru kalau kolom cukup, campur banyak modul dalam satu task.
- Jalankan `vendor/bin/pint` sebelum commit.

## Cara Kerja Task

1. **Scope check** — sprint berapa (`docs/TASKS.md`), modul apa, file mana disentuh, file mana haram disentuh, acceptance criteria apa.
2. **Rencana singkat** — tujuan, langkah, dependency, risiko, cara test.
3. **Coding** — setelah 1 & 2 jelas.
4. **Test** — tulis + jalankan.
5. **Update `docs/TASKS.md`** — task selesai ke Done, berikutnya ke In Progress, risiko ke Notes/Blocked.

Jangan loncat sprint, jangan ubah file di luar scope task aktif, jangan berasumsi tanpa konfirmasi.

## Dokumentasi

Modul punya struktur seragam: `README.md`, `business-logic.md`, `database-schema.md`, `flowchart.md`, `user-flow.md`, kadang `bug.md` + `archive/`. **Baca sesuai kebutuhan task, jangan baca semua.**

| Kebutuhan | Lokasi |
|---|---|
| Sprint & task aktif | `docs/TASKS.md` |
| Konteks produk, PRD | `docs/PROJECT_CONTEXT.md`, `docs/Website_Billing_ISP_PRD.md` |
| Aturan bisnis umum | `docs/BUSINESS_RULES.md`, `docs/DEFINITION_OF_DONE.md` |
| Skema DB | `docs/database-schema.md`, `docs/DATABASE_RULES.md` |
| RBAC | `docs/rbac/` (+ `field-lock-verifikasi.md`) |
| Ticketing | `docs/ticketing/` |
| FOP Task | `docs/fop-task/` (+ `analisa-sync-execution-task.md`, `analisa-auto-team.md`, `fop-dashboard.md`) |
| Task teknisi | `docs/task-teknisi/` |
| Billing & pembayaran | `docs/billing-pembayaran/` |
| Lifecycle pelanggan | `docs/customer-lifecycle/` |
| Pendaftaran, data pelanggan, dashboard | `docs/pendaftaran-pelanggan/`, `docs/data-pelanggan/`, `docs/dashboard/` |
| Master data | `docs/master/` (pop, distribution, internet-package, wilayah, sla-timeline, status-pelanggan) |
| Import & migrasi legacy | `docs/IMPORT_SPEC.md`, `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`, `docs/ANALISA_KELENGKAPAN_MIGRASI_jetis_db.MD` |
| Penomoran ID | `docs/ID_NUMBERING_RULES.md` |

## Berhenti & Tanya Kalau

1. Requirement ambigu.
2. Task menyentuh modul di luar sprint aktif.
3. Konflik antara dokumen dan instruksi user.
4. Perubahan berpotensi merusak data: hapus history, hapus invoice lunas, migrasi destruktif, rollback import.
5. Muncul dorongan bikin role per cabang.
6. Muncul dorongan kasih permission langsung ke user tanpa matrix role.
7. Ada query data tanpa pembatasan POP scope.
8. Perlu mengubah pembagian penulis riwayat Ticket/FopTask (risiko riwayat dobel).

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
