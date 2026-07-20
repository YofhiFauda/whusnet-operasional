# AGENTS.md

## Project Name
Website Billing ISP Berbasis Master Data Pelanggan

## Main Product Goal
Bangun website billing ISP internal yang menjadikan master data pelanggan lengkap sebagai pusat sistem.

Prinsip utama sistem:

Pelanggan
→ Paket Internet
→ Layanan Aktif
→ Tagihan
→ Pembayaran
→ Laporan

Billing tidak boleh berdiri sendiri tanpa data pelanggan.

## Required Reading Before Any Task
Sebelum mengerjakan task apa pun, AI wajib membaca file berikut:

1. `docs/PROJECT_CONTEXT.md`
2. `docs/MVP_SCOPE.md`
3. `docs/IMPLEMENTATION_PLAN.md`
4. `docs/TASKS.md`
5. `docs/ACCEPTANCE_CRITERIA.md`
6. `docs/DATABASE_CONCEPT.md`
7. `docs/PROMPTS.md`
8. `docs/TOMORROW_START.md`
9. `docs/Sprint 11 — Advanced Hierarchical RBAC Planning & Documentation.md`
10. `docs/RBAC_MATRIX.md`
11. `docs/analisa-rbac-dinamis-whusnett.md`
12. `docs/AGENT_EXECUTION_GUIDEE.md`

Jika tersedia, baca juga PRD asli di:
- `docs/PRD.md`
- atau file PRD asli dari user.

## Main Development Rule
AI hanya boleh mengerjakan task yang sedang aktif di `docs/TASKS.md`.

AI tidak boleh:
- loncat sprint,
- membuat fitur di luar MVP,
- membuat asumsi sendiri tanpa konfirmasi,
- mengerjakan modul berikutnya sebelum modul saat ini selesai,
- mengubah file yang tidak berhubungan dengan task aktif.

## MVP Development Order
Urutan development wajib:

1. Login    
2. User Management
3. Role
4. Permission
5. RBAC dasar
6. POP/Cabang
7. Assign user ke POP
8. Master Paket Internet
9. Input Manual Pelanggan
10. Import Excel/CSV Pelanggan Lama
11. Validasi Kelengkapan Data Pelanggan
12. Aktivasi Layanan Pelanggan
13. Tagihan Manual
14. Pembayaran
15. Dashboard
16. Laporan Sederhana
17. Audit Log
18. Data teknis pelanggan setelah billing dasar stabil

## Features Not Allowed in MVP
Fitur berikut tidak boleh dibuat pada tahap MVP:

- Integrasi MikroTik
- Payment gateway
- Auto suspend pelanggan
- Auto generate tagihan bulanan kompleks
- WhatsApp notification
- Ticketing gangguan kompleks
- Monitoring OLT/SNMP
- Inventory perangkat kompleks
- Aplikasi mobile teknisi
- Multi-company
- Sistem akuntansi kompleks
- Integrasi otomatis router/OLT

Jika user meminta fitur di atas, AI wajib menjawab:

"Fitur ini termasuk post-MVP. Berdasarkan scope MVP, fitur ini belum dikerjakan sekarang. Apakah Anda ingin tetap memasukkannya atau tetap mengikuti urutan MVP?"

## Current Product Logic
Sistem harus mengikuti logika:

1. POP/Cabang dibuat lebih dahulu.
2. User dan hak akses dibuat dengan RBAC.
3. Paket internet dibuat sebagai master layanan.
4. Pelanggan dimasukkan manual atau import Excel/CSV.
5. Sistem memvalidasi kelengkapan data pelanggan.
6. Pelanggan yang belum lengkap tetap boleh disimpan.
7. Pelanggan yang belum lengkap tidak boleh masuk billing aktif.
8. Pelanggan lengkap dapat diubah menjadi siap billing.
9. Tagihan dibuat berdasarkan pelanggan aktif dan paket aktif.
10. Pembayaran harus terhubung ke invoice dan pelanggan.
11. Status invoice berubah berdasarkan pembayaran.
12. Semua perubahan penting dicatat di audit log.

## Task Execution Protocol
Setiap menjalankan task, AI wajib melakukan langkah berikut:

### 1. Scope Check
Sebelum coding, jawab:

- Task ini masuk sprint berapa?
- Modul apa yang disentuh?
- Requirement PRD mana yang relevan?
- File apa saja yang akan dibuat/diubah?
- File apa saja yang tidak boleh disentuh?
- Acceptance criteria apa yang harus terpenuhi?

### 2. Implementation Plan
AI wajib membuat rencana singkat:

- Tujuan task
- Langkah pengerjaan
- Dependency
- Risiko
- Cara test

### 3. Coding
AI hanya boleh coding setelah scope check dan implementation plan jelas.

### 4. Review
Setelah coding, AI wajib menjelaskan:

- File yang dibuat/diubah
- Alasan perubahan
- Cara test manual
- Status acceptance criteria
- Apakah ada risiko atau TODO

### 5. Update Task
Setelah selesai, AI wajib mengupdate `docs/TASKS.md`:

- Task selesai dipindah ke Done
- Task berikutnya dipindah ke In Progress
- Catatan risiko dimasukkan ke Blocked atau Notes

## Coding Style Rules
Gunakan struktur kode yang sederhana, mudah dibaca, dan sesuai kebutuhan MVP.

Hindari:
- overengineering,
- membuat service terlalu kompleks sebelum dibutuhkan,
- membuat fitur otomatis sebelum flow manual stabil,
- membuat tabel yang tidak diperlukan MVP,
- mencampur banyak modul dalam satu task.

## DATABASE RULES
Database harus mengikuti konsep:

- users
- roles
- permissions
- role_permissions
- pops
- user_pops
- user_role_scopes
- user_role_scope_targets
- internet_packages
- customers
- customer_addresses
- customer_services
- customer_surveys
- customer_installations
- customer_devices
- customer_documents
- invoices
- payments
- import_batches
- import_errors
- audit_logs

Jangan membuat tabel post-MVP seperti:
- mikrotik_routers
- olt_devices
- snmp_logs
- payment_gateway_transactions
- whatsapp_notifications
- technician_mobile_sessions

kecuali user secara eksplisit memutuskan masuk post-MVP.

## RBAC RULES (Advanced Hierarchical RBAC)
Minimal role seeder wajib:
1. Owner
2. Atasan
3. Admin
4. NOC
5. Helpdesk
6. FOP
7. Teknisi
8. Sales
9. Admin POP

Aturan Utama Advanced RBAC:
1. **Pemisahan Role & Scope:** Role menentukan *kapabilitas fungsi* (aksi apa yang bisa dilakukan) sedangkan Scope menentukan *wilayah data* (data POP/cabang mana yang bisa dilihat).
2. **Larangan Role per Cabang:** Dilarang keras membuat role baru per cabang (seperti `NOC Ponorogo`, `Teknisi Siman`). Role cukup didefinisikan satu kali secara global, dan batasi wilayah datanya menggunakan User Scope.
3. **Format Permission:** Seluruh permission wajib berformat string lowercase `{feature_code}.{action_code}` (e.g., `customers.view`, `customers.detail.devices.view_sensitive`).
4. **Definisi User Scope Types:**
   - `all_pop`: Akses data di seluruh POP tanpa batas (nasional).
   - `selected_pop`: Akses terbatas hanya pada daftar POP yang dipilih di target scope.
   - `pop_tree`: Akses pada POP utama beserta seluruh Mini POP (sub-POP) di bawahnya secara hierarkis.
   - `assigned_only`: Hanya mengakses data tugas/pelanggan yang ditugaskan ke ID user tersebut.
   - `own_created`: Hanya mengakses data pelanggan yang didaftarkan oleh ID user tersebut.
5. **Larangan Hak Akses Khusus:**
   - Teknisi tidak boleh mencatat pembayaran / keuangan.
   - Admin POP tidak boleh melihat data pelanggan di luar POP scopenya.
   - Helpdesk tidak boleh mengubah nominal tagihan terbit.
   - Sales tidak boleh mengakses laporan keuangan/pembayaran.

## Customer Data Rules
Pelanggan boleh disimpan walaupun belum lengkap.

Status kelengkapan data:

1. Draft
2. Perlu Dilengkapi
3. Lengkap
4. Siap Billing

Pelanggan hanya bisa masuk billing jika field wajib berikut terisi:

- Nama lengkap
- Nomor HP
- Alamat lengkap
- Desa/Kelurahan
- Kecamatan
- Kota/Kabupaten
- POP/Cabang
- Paket internet
- Harga bulanan
- Tanggal aktivasi
- Tanggal jatuh tempo
- Status layanan

## Billing Rules
Tagihan tidak boleh dibuat manual dari nol.

Tagihan harus berasal dari:

Pelanggan Aktif
+ Paket Aktif
+ Harga Layanan
+ Periode Tagihan

Aturan:

- Tagihan hanya bisa dibuat untuk pelanggan aktif atau siap billing.
- Tagihan mengambil harga dari layanan pelanggan.
- Tagihan memiliki periode.
- Tagihan memiliki tanggal jatuh tempo.
- Tagihan tidak boleh dobel untuk periode yang sama.
- Tagihan lunas tidak boleh dihapus sembarangan.

## Payment Rules
Pembayaran wajib terhubung ke:

- invoice
- pelanggan
- POP/cabang

Aturan:

- Jika nominal bayar sama dengan total tagihan, invoice menjadi lunas.
- Jika nominal bayar kurang dari total tagihan, invoice menjadi dibayar sebagian.
- Jika pembayaran ditolak, invoice tidak boleh berubah menjadi lunas.
- Perubahan pembayaran wajib masuk audit log.

## Output Format After Every Task
Setiap selesai task, AI wajib menjawab dengan format:

```md
## Task Selesai
Nama task:

## File Diubah
- file 1
- file 2

## Alasan Perubahan
Penjelasan singkat.

## Cara Test
1. ...
2. ...
3. ...

## Acceptance Criteria
- [x] Kriteria 1
- [x] Kriteria 2
- [ ] Kriteria belum selesai

## Risiko / Catatan
Catatan jika ada.

## Next Task
Task berikutnya sesuai `docs/TASKS.md`.
```

## Stop Condition
AI wajib berhenti dan bertanya jika:
1. Requirement ambigu.
2. Task menyentuh modul di luar sprint aktif.
3. Ada conflict antara PRD/desain dan instruksi user.
4. Ada kebutuhan membuat fitur post-MVP.
5. Perubahan berpotensi merusak data penting (misal menghapus history saat user di-delete).
6. **Mencoba membuat role per cabang** (seperti NOC Ponorogo, dsb).
7. **Memberi permission langsung ke user** tanpa melalui matrix role (kecuali fitur override terverifikasi).
8. **Ada potensi kebocoran data POP scope** (seperti query data tanpa pembatasan scope POP).

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
