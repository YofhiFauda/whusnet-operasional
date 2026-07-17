# Analisa Redundansi Logic & Label-Only Change

Tanggal: 2026-07-16
Scope: seluruh project (fokus area FopTaskController, TaskController, TaskType enum, sprint task 8-14)

**Update 2026-07-16 (lanjutan):** scan lanjutan khusus cari hardcoded string yang seharusnya reusable enum, ditambahkan sbg section 0/5/6/7 di bawah.

**Update 2026-07-17 — cleanup pre-existing test-infra bug (bukan bagian analisa enum, tapi ditemukan & diperbaiki di sesi yang sama):** dari 59 test gagal (full suite) turun jadi 28. 3 root cause diperbaiki:
1. **`InvoiceObserver` nolak invoice tanpa `invoice_type`** — 10 file test (`Invoice::create()` di factory helper) + `InvoiceCreateTest.php` (9 form POST) gak pernah kirim field ini padahal observer udah mewajibkan. Ditambahin `'invoice_type' => 'bulanan'` ke semua titik.
2. **"Call to a member function all() on array"** — ternyata BUKAN bug aplikasi, itu Collision (CLI test-output formatter) gagal nge-pretty-print pas ValidationException asli terjadi (root cause: test emang gak kirim `invoice_type`, sama kasus #1). Setelah #1 difix, gejala ini otomatis hilang.
3. **403 permission-seed gap, 2 sub-penyebab:**
   - **Legacy auth API**: 9 file test pakai `$user->pops()->attach($pop->id)` (API lama) padahal sistem otorisasi sekarang butuh `UserRoleScope`/`UserRoleScopeTarget` (RBAC scope baru). Ditambahin setup scope yang bener di tiap titik.
   - **Permission naming drift**: `PermissionSeeder.php` masih generate nama legacy (`view_audit_logs`, `view_customer_documents`) sementara SELURUH sistem (routes, RolePermissionSeeder, blade lain) udah pindah ke dotted-style (`audit_logs.view`, `customers.detail.documents.view`) — CUMA 2 titik ketinggalan (`PaymentController.php:117`, `payments/show.blade.php:121`, `customers/show.blade.php:1081`), bikin fitur "lihat audit log pembayaran" & "lihat dokumen pelanggan" **gak pernah muncul buat siapapun kecuali Owner** (wildcard `*`). Ini **bug produksi nyata**, bukan cuma test — difix.
   - **9 file test kurang seeder**: `setUp()` manggil `RolePermissionSeeder` tapi lupa `FeatureSeeder`/`ActionSeeder` duluan — `PermissionGeneratorService` butuh itu buat generate permission dotted dari `config/rbac.php`, tanpa itu semua grant permission dotted silently gagal (cuma ke-log warning, gak pernah nge-fail loud).
   - Bonus: `CustomerDocumentTest` nge-fake disk `'local'` tapi `FileUploadService` nulis ke disk `'public'` — mismatch disk, difix.

**28 kegagalan sisa** di luar 3 kategori ini (root cause beda: `AuditLog.old_values/new_values` null, `RolePermissionMatrixTest` unique-constraint seeder, dll) — **sengaja gak disentuh**, di luar scope yang disepakati.

---

## 0. ✅ FIXED — enum di-loose-compare ke string, reassign teknisi SELALU gagal

**Status:** sudah di-fix di `TaskService.php:304`. Full test suite `Task*` (128 test, 508 assertion) tetap pass, gak ada regresi.

**Lokasi:** `app/Services/TaskService.php:304`, method `reassignTeam()`

```php
if (!in_array($task->status, ['terjadwal', 'in_progress'])) {
    throw new \Exception("Hanya task terjadwal atau in progress yang bisa di-reassign.");
}
```

**Masalah:** `Task::$status` di-cast ke enum `TaskStatus` (`app/Models/Task.php:47`), jadi `$task->status` adalah **object enum**, bukan string. `in_array()` non-strict pakai `==` — enum instance vs string literal `'terjadwal'`/`'in_progress'` gak pernah dianggap sama di PHP. Akibatnya `!in_array(...)` **selalu `true`**, exception **selalu dilempar**, reassign teknisi via jalur ini **selalu gagal** apapun status task-nya.

**Solusi nyata:**
```php
if (!in_array($task->status, [TaskStatus::TERJADWAL, TaskStatus::IN_PROGRESS], true)) {
    throw new \Exception("Hanya task terjadwal atau in progress yang bisa di-reassign.");
}
```
Bandingkan enum-vs-enum, pakai strict `true` di param ketiga `in_array()`. `TaskStatus` udah ke-import di file ini, gak perlu tambah `use`.

**Prioritas: fix duluan sebelum #1 lama** — dampaknya lebih luas (blokir fitur reassign total, bukan cuma silent sort miss).

### Review lanjutan pasca-fix — celah lain di `reassignTeam()`

Dibaca full method (`TaskService.php:302-403`) + caller `TaskTeamController::update()`:

1. ✅ **DONE — Zero test coverage.** Ditambah `tests/Feature/TaskReassignTest.php`, 5 test (18 assertion): sukses status terjadwal, sukses status in_progress, regresi guard status-selain-2-itu (nutup balik kasus Task 0), gagal old_user bukan member, conflict detection saat schedule bentrok. Full suite `Task*` 133 test / 526 assertion pass, gak ada regresi.
2. ✅ **DONE — Urutan validasi.** `TaskService.php:319-333`: cek `oldUserId` member tim dipindah ke atas, sebelum conflict-detection query — hindari query sia-sia kalau oldUserId invalid.
3. **BELUM — Notification `type` raw string** (`'info'/'error'` line ~406/417 setelah reorder) — masih nunggu enum `NotificationType` dibikin (§8c), scope-nya lebih besar dari method ini doang (nyentuh semua caller `AppNotification`), sengaja belum disentuh.
4. ✅ **DONE — `DB::transaction()` dipindah ke dalam service.** `TaskService.php:342-357`: update team-member + task sekarang dibungkus `DB::transaction()` di dalam `reassignTeam()` sendiri, self-contained gak gantung transaction caller. Laravel handle nested transaction (savepoint) otomatis krn controller (`TaskTeamController::update`) masih bungkus transaction juga — aman.

---

## 1. ✅ FIXED — Hardcoded string category drift dari enum value

**Status:** sudah di-fix di `FopTaskController.php` (line 56-57, 991, 1014, 1042 — pakai `TaskType::SURVEY->value`/`::PEMASANGAN->value`/`::autoOnlyValues()`, line 56-57 pakai parameter binding `?` bukan literal string di raw SQL). Ditambah regresi test baru `FopTaskSortingTest::test_auto_sync_does_not_duplicate_existing_active_survey_task()` — sebelum fix, tiap akses `index()` bikin `FopTask` survey DUPLIKAT terus buat customer yg sama (dedupe guard `whereDoesntHave` gak pernah match). Full suite `FopTask*`/`Task*` 134 test / 530 assertion, semua pass.

**Lokasi:** `app/Http/Controllers/FopTaskController.php` line 56-57, 991, 1042

**Masalah:**
```php
// line 56-57
->orderByRaw("CASE WHEN category IN ('Survey', 'PSB') THEN created_at END ASC")
->orderByRaw("CASE WHEN category NOT IN ('Survey', 'PSB') THEN created_at END DESC");

// line 991
$q->where('category', 'Survey')->whereNotIn('status', [...]);

// line 1042
->whereIn('category', ['Survey', 'PSB'])
```

`App\Enums\TaskType::SURVEY->value` sekarang `'SURVEY'` (uppercase) — enum sempat di-rename dari `'survey'`, tapi 4 call site di atas gak ikut diupdate dan masih pakai literal `'Survey'`. `'PSB'` masih kebetulan cocok (`TaskType::PEMASANGAN->value === 'PSB'`), tapi `'Survey'` sekarang gak pernah match kolom `category` (yang isinya `'SURVEY'`).

**Dampak:** task Survey silently gak kena aturan "naik ke atas antrian" (sort priority) dan lolos dari guard dedupe/priority-recalc di `autoSyncAndCalculatePriority()`.

**Solusi nyata:**
```php
// line 56-57
->orderByRaw("CASE WHEN category IN (?, ?) THEN created_at END ASC", [
    \App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value,
])
->orderByRaw("CASE WHEN category NOT IN (?, ?) THEN created_at END DESC", [
    \App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value,
]);

// line 991
$q->where('category', \App\Enums\TaskType::SURVEY->value)->whereNotIn('status', [...]);

// line 1014 — 'PSB' literal kebetulan masih match TaskType::PEMASANGAN->value,
// tapi tetap ganti biar konsisten & gak jadi bom waktu kalau value PEMASANGAN direname
$q->where('category', \App\Enums\TaskType::PEMASANGAN->value)->whereNotIn('status', [...]);

// line 1042
->whereIn('category', \App\Enums\TaskType::autoOnlyValues())
```
Pattern ini udah dipakai benar di line 43, 120, 182, 280-282, 335, 497, 907, 970, 1000, 1037, 1055 di file yang sama — tinggal disamakan.

**Regresi test yang perlu ditambah:** assert task berkategori SURVEY tetap muncul di atas antrian sort (mirip `FopTaskSortingTest.php` yang udah ada, tapi eksplisit assert query `orderByRaw` match).

---

## 2. ✅ FIXED — Duplikasi SLA-window calculation

**Status:** sudah di-fix. Literal `addDay()`/86400 & `addDays(3)`/259200 diganti `$task->category->defaultHandlingSlaHours()` (satu sumber kebenaran). Angka numerik identik (24h=86400s, 72h=259200s) — refactor murni, gak ubah behavior, dikonfirmasi via test.

**Review celah tambahan:** method `autoSyncAndCalculatePriority()` ternyata **zero test coverage** buat logic priority-recalc percentage-based-nya (cuma nyentuh via dedupe test §1). Ditambah regresi test baru `FopTaskSortingTest::test_survey_task_priority_escalates_to_urgent_when_sla_overdue()` — customer survey yg SLA 24 jam-nya udah lewat, assert priority otomatis naik ke `URGENT`. Gak ketemu celah logic baru di method ini selain kurangnya test coverage tsb — kalkulasi persentase (`$percentage < 0` → Urgent, `<= 25` → High, dst) konsisten sebelum/sesudah refactor. Full suite `FopTask*`/`Task*` 135 test / 532 assertion, semua pass.

**Lokasi:** `app/Http/Controllers/FopTaskController.php` line 1055-1066 (dalam `autoSyncAndCalculatePriority()`)

**Masalah:** hand-roll deadline window manual:
```php
// kira-kira: addDay() / 86400 utk Survey, addDays(3) / 259200 utk PSB
```
Padahal `TaskType::defaultHandlingSlaHours()` (`app/Enums/TaskType.php:53-65`) sudah define persis nilai ini (24 jam Survey, 72 jam PSB), dan sudah dipakai konsisten di `FopTask.php:56` dan `FopTask.php:147`.

**Dampak:** dua sumber kebenaran independen. Kalau admin ubah SLA per-paket lewat Master Timeline SLA (yang menurut comment di `TaskType.php:47-51` dimaksud override default ini), method di FopTaskController gak ikut berubah → priority-calc pakai angka basi.

**Solusi nyata:** ganti literal di line 1055-1066 jadi:
```php
$slaHours = $task->category->defaultHandlingSlaHours();
$deadline = $task->created_at->copy()->addHours($slaHours);
```
Hapus semua literal `addDay()/addDays(3)/86400/259200` di method ini.

---

## 3. ✅ FIXED — Duplikasi validasi reason, ketemu bug latent max-length vs kapasitas DB

**Status: SUDAH DIKERJAKAN, dengan koreksi.** Solusi awal doc (1 konstanta `MAX_LENGTH=500` buat semua) **SALAH** — dicek dulu kapasitas kolom DB aslinya, ternyata beda-beda: `Task.cancel_reason`/`Task.pending_reason` = `varchar(255)` (default Laravel `$table->string()`), `Task.reject_reason` = `varchar(1000)`, `FopTask.pending_reason` = `varchar(255)`, `FopTask.cancel_reason` = `TEXT` (unlimited).

**Bug latent ketemu:** `TaskController::cancel()` (`cancel_reason` max:500), `reschedule()` (`pending_reason` max:500), `pending()` (`pending_reason` max:1000), `review()` (`reason` shared max:1000) — SEMUA validasi lebih longgar dari kapasitas kolom asli (255). Kalau user submit alasan 256-500 karakter, lolos validasi tapi MySQL bakal truncate/error pas insert (`Data too long for column`). Ini bug pre-existing yang gak pernah ke-trigger (mgkn belum pernah ada user nulis alasan sepanjang itu), tapi laten.

**Solusi nyata diimplementasi:** `app/Support/ReasonValidationRule.php` — helper `required(int $max)` dan `requiredIf(string $field, string $value, int $max)`, max WAJIB dikirim eksplisit per call-site (bukan konstanta global) biar gak ada yang salah samain lagi. Diterapkan ke 6 titik:
- `FopTaskController::store/update` — `pending_reason` max:255 (cocok kolom), `cancel_reason` max:500 (kolom TEXT, app-level cap, aman).
- `TaskController::cancel/reschedule/pending` — dibenerin ke max:255 (cocok `Task.cancel_reason`/`pending_reason`).
- `TaskController::reject` — max:1000 (cocok `Task.reject_reason`).
- `TaskController::review` — field `reason` ditulis ke `reject_reason` (1000) ATAU `pending_reason` (255) tergantung `$action` — dicap ke yg lebih kecil (255) biar gak pernah overflow kolom manapun jadi tujuannya. Gak dipaksa masuk helper `requiredIf()` (beda shape — `required_if` multi-value `reject,pending`), ditulis manual dgn komentar penjelasan.

**Verifikasi:** 136 test (`Task*`/`FopTask*`) pass, termasuk `test_reschedule_requires_reason` yg langsung nguji validasi max-length ini.

---

## 4. Sprint Task 9 "Status Realtime" — dicek, BUKAN label-only

Verifikasi via `git show 4205896`: nambah `FopTaskStatusHistory` model + `TaskObserver` — persistence & logic baru beneran (tabel status-history, label granular `'proses_dikerjakan'`, `'lapor_nanti'`). Bukan rename kosmetik.

Catatan sampingan: sempat ada enum `FopTaskStatus` terpisah yang kemudian dihapus & diunifikasi ke `TaskStatus` (comment di `TaskObserver.php`: "2026-07-20 unifikasi enum"). Ini cleanup yang sudah selesai dengan benar, tidak ada isu sekarang — dicatat cuma sebagai riwayat.

---

## 5. ✅ FIXED — Raw SQL CASE priority pakai literal, gak reference enum

**Status:** sudah di-fix. Tambah method `FopTaskPriority::sortOrder()` (`app/Enums/FopTaskPriority.php`) — urutan sort eksplisit (URGENT=1..LOW=4), terpisah dari urutan deklarasi enum. `FopTaskController::index()` sekarang generate CASE SQL + binding dinamis lewat 2 helper baru `priorityOrderCaseSql()`/`priorityOrderBindings()` (pakai parameter binding `?`, bukan literal string di raw SQL lagi), bukan tulis manual `WHEN 'Urgent' THEN 1...`. Kalau enum direname/nambah case baru, sort otomatis ikut. Review: gak ketemu celah baru — output numerik identik (test existing `test_priority_sorting_regression_unaffected` hit endpoint asli, tetap pass). Full suite 135 test / 532 assertion pass.

**Lokasi:** `app/Http/Controllers/FopTaskController.php:51-54`

```php
WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'low' THEN 4
```

Nilai ini kebetulan masih cocok `FopTaskPriority` (`URGENT='Urgent'`, `HIGH='High'`, `MEDIUM='Medium'`, `LOW='low'`), tapi ditulis manual di raw SQL — kalau enum direname kayak kasus TaskType di atas, ordering ini bakal silently rusak juga tanpa error apapun.

**Solusi nyata:** generate CASE string dari `FopTaskPriority::cases()` alih-alih tulis manual:
```php
$priorityCases = collect(\App\Enums\FopTaskPriority::cases())
    ->map(fn ($p, $i) => "WHEN '{$p->value}' THEN " . ($i + 1))
    ->implode(' ');
// ->orderByRaw("CASE priority {$priorityCases} END ASC")
```
(pastikan `FopTaskPriority::cases()` udah urut sesuai prioritas asc; kalau belum, tambah method `sortOrder()` di enum kayak pola `defaultHandlingSlaHours()`.)

---

## 6. ✅ FIXED — `where('status', 'selesai')` literal + BUG NYATA tambahan ketemu di `FopTask.php:176`

**Status:** sudah di-fix di 4 lokasi: `FopTaskController.php:44,1036` dan `FopDashboardController.php:83` (query builder, ganti ke `TaskStatus::SELESAI->value`) + `FopTask.php:176` (lihat temuan bug di bawah — beda kelas fix).

**Lokasi:** `FopTaskController.php:44`, `:1038` (skr :1036), `FopDashboardController.php:83`, `FopTask.php:176`

**⚠️ Temuan bug nyata tambahan waktu ngerjain #6** — `FopTask.php:176` (dalam `slaDeadline()`) BUKAN query builder kayak 3 lokasi lain, tapi `Collection::where()` di-memory di atas relasi yg udah di-load (`$this->customer->tasks`). Kolom `task_type` & `status` di model `Task` di-cast ke enum (`TaskType`/`TaskStatus`). `Collection::where('col', 'string')` pakai loose `==` — **enum instance vs string literal gak pernah match**, sama persis kelas bug §0. Sebelum fix: `$surveyTask` di baris itu **SELALU `null`**, jadi deadline SLA Pemasangan SELALU fallback ke `customer.updated_at` — bukan tanggal survey selesai yang sebenarnya (mempengaruhi kalkulasi urgensi/prioritas task Pemasangan, dampaknya nyambung ke §2).

**Fix beda dari 3 lokasi lain:** karena ini `Collection::where` (bukan query builder raw SQL), fix-nya bukan `->value` tapi **enum instance langsung** (`TaskType::SURVEY`, `TaskStatus::SELESAI`) — enum instance vs enum instance match via `==` (backed enum case itu singleton di PHP). Pakai `->value` di sini tetap salah (balik ke bug yang sama, cuma kebalik arahnya).

**Solusi nyata (3 lokasi query builder):** ganti ke `TaskStatus::SELESAI->value`.
**Solusi nyata (1 lokasi Collection::where, `FopTask.php:176`):** ganti ke enum instance `TaskType::SURVEY` / `TaskStatus::SELESAI`, BUKAN `->value`.

**Test baru:** `tests/Feature/FopTaskSlaDeadlineTest.php` — assert `FopTask::slaDeadline()` utk kategori Pemasangan pakai `completed_at` survey asli, bukan fallback `customer.updated_at`. Sebelum fix, test ini gagal (deadline selalu dari fallback). Full suite `FopTask*`/`Task*` 136 test / 534 assertion, semua pass.

---

## 7. Belum ada enum — 2 kandidat kuat buat dibikin baru

### 7a. `PaymentStatus` (belum ada enum, status Payment masih raw string)

Nilai `'pending'`, `'valid'`, `'ditolak'`, `'lunas'`, `'batal'` diulang sbg raw string di:
- `PaymentController.php:29, 143, 153`
- `PaymentReportController.php:77, 86`
- `Payment.php:57`

**Solusi nyata:** bikin `app/Enums/PaymentStatus.php`:
```php
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case VALID   = 'valid';
    case DITOLAK = 'ditolak';

    public function label(): string { /* sama pola TaskType::label() */ }
}
```
Tambah `'status' => PaymentStatus::class` ke `$casts` di `Payment.php`, lalu migrasi semua raw string di 3 file itu ke `PaymentStatus::X->value`. Pakai `Rule::enum(PaymentStatus::class)` di validasi form.

**✅ STATUS: SUDAH DIKERJAKAN (dengan koreksi).** Kolomnya `payment_status`, bukan `status` (§7a di atas salah nama kolom). Value real cuma **3**: `pending`/`valid`/`ditolak` — bukan 5 (`lunas`/`batal` ternyata punya kolom sendiri, itu `Invoice.invoice_status`, lihat §8a InvoiceStatus, beda tabel beda value set). Dikerjain: enum `PaymentStatus` (3 case) + cast di `Payment.php` + migrasi ~15 titik: `PaymentController.php`, `PaymentReportController.php`, `DashboardController.php`, `CustomerController.php`, `BackfillLegacyDeviceAndPaymentDataCommand.php`, + 4 blade view (`payments/show`, `payments/index`, `reports/payments/index`, `customers/show`).

**Bug ketemu waktu ngerjain:** `Payment.php` `booted()` hook — `$payment->payment_status === 'ditolak'` (nentuin audit-log action `cancel` vs `update`) jadi enum-vs-string loose-compare abis di-cast, sama kelas bug §0. Difix ke `=== PaymentStatus::DITOLAK`. Beberapa blade view (`match($payment->payment_status)`, `ucfirst($payment->payment_status)`, `$statusBadges[$payment->payment_status]`) juga bakal TypeError abis di-cast kalau gak difix ke `->value`/`->label()` — semua udah dihandle.

**Verifikasi:** full `Payment*` test suite — baseline SEBELUM perubahan ini udah 15 failed/8 passed (pre-existing, gara-gara `InvoiceObserver` nolak Invoice tanpa `invoice_type` yg gak diisi test factory — **sama sekali gak berhubungan** sama PaymentStatus, dikonfirmasi via `git stash` A/B test). Setelah perubahan: **persis 15 failed/8 passed juga** — zero regresi baru.

### 7b. `CustomerStatus` — ❌ BUKAN kandidat PHP enum (koreksi analisa awal)

**Temuan waktu eksekusi:** `Customer::status` (`Customer.php:120`) itu **foreign key ke tabel master `subscription_statuses`** (`belongsTo(SubscriptionStatus::class, 'status', 'code')`), bukan closed-set string biasa. Ada model `SubscriptionStatus` (`code/name/workflow_order/badge_color/is_terminal/is_active`) — ini fitur **"Master Status Pelanggan"** yang **admin-configurable lewat UI** (permission `master_status_pelanggan.*` udah ada di `RolePermissionSeeder`). PHP `enum` itu closed-set FIXED di compile-time — kalau dipaksa cast `Customer::status` ke enum, bakal **PATAH begitu admin nambah status baru lewat UI** (Eloquent lempar `ValueError` pas hydrate row dengan value yang gak ada di enum). Analisa awal §7b SALAH nganggep ini kandidat enum kode biasa.

**Tapi ada temuan bagus:** enum `App\Enums\WorkflowTransition` **UDAH ADA** (`app/Enums/WorkflowTransition.php`) — 14 case, persis cover semua value status Customer (`registered`, `waiting_survey`, `survey_in_progress`, `surveyed`, `waiting_acc`, `waiting_installation`, `installation_in_progress`, `installed`, `verification_admin`, `revision_installation`, `active`, `suspended`, `terminated`, `rejected`), lengkap sama state-machine `allowedNextTransitions()`. Ini enum kode-level yang sengaja TERPISAH dari `SubscriptionStatus` (yang buat metadata display: nama/warna/urutan). Keduanya valid coexist — `SubscriptionStatus` buat display admin-configurable, `WorkflowTransition` buat validasi transisi state-machine di kode.

**Audit typo-risk (bukan bikin enum baru, tapi cek konsistensi pakai `WorkflowTransition::X->value` yang UDAH ada):** grep `->where('status', 'literal')` / `whereIn('status', [...])` nemuin puluhan titik di 24 file (`CustomerController.php` 2700+ baris, `CustomerSurveyController.php`, `CustomerInstallationController.php`, `FopDashboardController.php`, dll). **Sengaja TIDAK dieksekusi mass-rewrite sekarang** — resiko blast-radius terlalu besar buat dikerjain aman dalam 1 sesi:
- Banyak false-positive: kata `'active'`/`'suspended'` juga dipakai kolom `status` di model LAIN (`User`, `Pop`, `InternetPackage`) yang gak ada hubungannya sama Customer workflow — grep gak bisa bedain otomatis, butuh baca konteks tiap titik satu-satu.
- `CustomerController.php` nyentuh logic billing (`billing_status`) yang campur aduk sama `customer_status` — resiko salah ganti tinggi tanpa test coverage granular per baris.
- Beda dari §7a (Payment, cuma ~15 titik di file kecil) atau §8a (Invoice, udah diinventarisir persis), scope Customer status ini butuh sesi audit tersendiri kayak §9 (Task 15) — **rekomendasi: jadiin task terpisah, audit per-file dulu (mana yang beneran Customer.status vs model lain) sebelum eksekusi.**

---

## 8. Scan lanjutan (2026-07-16) — kandidat enum di luar domain Task/FOP

### 8a. ✅ FIXED — `InvoiceStatus`, zero proteksi typo, 7 file / 15+ titik

**Lokasi:** `Invoice.php:37` (cuma `$fillable`, **gak ada `$casts`** — verified), `InvoiceController.php:57,59,61,71`, `InvoiceReportController.php:57,70,79,144,157`, `DashboardController.php:70,73,87`, `CustomerController.php:799,805,2007,2076,2678`, `CustomerVerificationController.php:190`, `GenerateMonthlyInvoicesCommand.php:117` — plus di-duplikat lagi sbg literal `<option>`/perbandingan Blade di `invoices/index.blade.php`, `invoices/show.blade.php`, `reports/invoices/index.blade.php`, `dashboard.blade.php`.

**Beda dari `PaymentStatus` (§7a)** — itu kolom `Payment.status` (tabel pembayaran), ini `Invoice.invoice_status` (tabel tagihan), value set beda: `'belum_dibayar'`, `'sebagian'`, `'lunas'`, `'batal'`.

**Kenapa paling kritis:** typo di sini = query `where('invoice_status', 'lunas')` salah ketik jadi silently return 0 rows / data salah, **gak ada error apapun**. Titik sentuh 7 controller file — makin gede project makin sering disentuh developer beda, makin gede resiko drift.

**Solusi nyata:**
```php
enum InvoiceStatus: string
{
    case BELUM_DIBAYAR = 'belum_dibayar';
    case SEBAGIAN       = 'sebagian';
    case LUNAS          = 'lunas';
    case BATAL          = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIBAYAR => 'Belum Dibayar',
            self::SEBAGIAN       => 'Sebagian',
            self::LUNAS          => 'Lunas',
            self::BATAL          => 'Batal',
        };
    }
}
```
Tambah `'invoice_status' => InvoiceStatus::class` ke `Invoice::$casts`, migrasi 7 file di atas ke `InvoiceStatus::LUNAS->value` dst, pakai `Rule::enum(InvoiceStatus::class)` di validasi form.

**✅ STATUS: SUDAH DIKERJAKAN.** Enum `InvoiceStatus` (4 case) + cast di `Invoice.php`. Migrasi ~25 titik: `InvoiceController.php`, `InvoiceReportController.php`, `DashboardController.php`, `CustomerController.php` (termasuk `mapLegacyInvoiceStatus()` diubah dari array-lookup jadi `match()`), `CustomerVerificationController.php`, `PaymentController.php`, `GenerateMonthlyInvoicesCommand.php`, + 4 blade view (`invoices/index`, `invoices/show`, `customers/show`, `reports/invoices/index`).

**Bug ketemu waktu ngerjain:** `PaymentController.php:145,155` — `$invoice->invoice_status === 'lunas'/'batal'` (guard nolak pembayaran ke invoice lunas/batal) jadi enum-vs-string abis cast, difix ke `=== InvoiceStatus::LUNAS`/`::BATAL`. `InvoiceReportController.php` CSV export `ucfirst(str_replace(...))` juga bakal TypeError, difix ke `->label()`.

**Verifikasi:** 3 test file (`CustomerFinalVerificationTest`, `CustomerImportTest`, `RealDataMigrationTest`) assert `assertEquals('lunas', $invoice->invoice_status)` langsung ke string — diupdate ke `->value`. Full `Invoice*`/`Payment*` suite: name-level diff before/after (bukan cuma count) **byte-identik** — zero regresi baru.

### 8b. ✅ FIXED — `CustomerDocument::TYPES` upgrade ke enum

**Lokasi:** `CustomerDocument.php:17-23` (const `TYPES` cuma buat label), validasi `'in:ktp,rumah,kontrak,survey,pemasangan'` raw string di `CustomerDocumentController.php:20`.

**✅ STATUS: SUDAH DIKERJAKAN.** Enum `DocumentType` (5 case) + cast di `CustomerDocument.php`. Const `TYPES` **dihapus** (bukan dipertahankan buat backward-compat — cuma 1 pemakai, `customers/show.blade.php:1089`, dimigrasi ke `DocumentType::cases()`). Validasi `in:...` diganti `Rule::enum(DocumentType::class)`. Verifikasi: `CustomerDocumentTest` — hasil identik baseline (3 gagal pre-existing/2 lolos, dikonfirmasi via isolasi).

### 8c/Task 15. ✅ FIXED — `NotificationType` enum

**→ Detail lengkap eksekusi di §9 di bawah.**

### 8d. ✅ FIXED — `Gender` enum, ketemu bonus bug nyata

**Lokasi:** `Customer.php:21` fillable, validasi cuma di `CustomerRegistrationRequest.php:25` (`in:Laki-laki,Perempuan`), diulang literal di `customers/create.blade.php` & `customers/edit.blade.php`.

**✅ STATUS: SUDAH DIKERJAKAN.** Enum `Gender` (2 case) + cast di `Customer.php`. Validasi diganti `Rule::enum(Gender::class)`. Fix blade yg bakal break (`customers/show.blade.php`, `customers/edit.blade.php`, `surveys/report.blade.php`, `CustomerReportController.php` CSV export) ke `->label()`/`?->value`.

**⚠️ Bug NYATA ketemu waktu ngerjain (bukan di gender field-nya sendiri, tapi efek samping cast):** `CustomerValidationService.php:209` — method generik `isFieldFilled()` yg dipanggil buat SEMUA field Customer (termasuk `gender`) do `(string) $value` buat cek "apa field ini keisi". Begitu `gender` di-cast ke enum, baris ini **crash** (`Object of class App\Enums\Gender could not be converted to string`) — nge-block SELURUH flow `recalculateCompleteness()` yang jalan tiap kali Customer di-save (dampaknya luas: semua create/update Customer manapun, bukan cuma yg isi gender). **Difix**: unwrap `BackedEnum` ke `->value` dulu sebelum `(string)` cast — pattern reusable buat field enum lain di masa depan yg lewat generic-field-checker ini.

**Verifikasi ketat** (krn dampak luas di atas): isolasi manual — file-file terkait Gender di-revert ke versi original 1-per-1 via `git show HEAD:...`, jalanin test, restore — dikonfirmasi SEMUA kegagalan yg masih muncul (CustomerValidationTest, CustomerRegistrationTest flakiness) **identik** dengan atau tanpa perubahan Gender (pre-existing test-order pollution, gak related). Fix `isFieldFilled()` sendiri diverifikasi lewat re-run manual (crash hilang setelah fix).

### 8e. ✅ FIXED — `users.status` → `UserStatus` enum, ketemu BUG PRODUKSI SERIUS

**Lokasi:** migration `0001_01_01_000000_create_users_table.php:21`, validasi `Rule::in(['active','inactive'])` di `UserController.php` (2 titik: store/update).

**✅ STATUS: SUDAH DIKERJAKAN.** Cek dulu value real yg dipakai (sesuai rekomendasi awal) — cuma 2: `active`/`inactive`, udah divalidasi loud. Enum `UserStatus` (2 case) + cast di `User.php`. Validasi diganti `Rule::enum(UserStatus::class)` — otomatis ubah message-key dari `status.in` jadi `status.enum` (custom pesan error di `UserController.php` disesuaikan, kalau kelewat bakal balik ke pesan default Laravel bukan bahasa Indonesia — nutup ini juga).

**🔴 BUG PRODUKSI SERIUS ketemu:** `app/Http/Controllers/Auth/LoginController.php:34` — `if ($user->status !== 'active') { Auth::logout(); ... }`. Sebelum cast ini SUDAH benar (string vs string). Kalau saya cast `User::status` ke enum TANPA fix baris ini, comparison jadi enum-vs-string yang **SELALU true** → **SEMUA user, termasuk yang statusnya `active`, bakal ke-logout paksa tiap kali login** (nutup nyaris seluruh akses sistem). Ini murni potential-bug-yang-baru-KETEMU-karena-mau-di-cast, bukan bug pre-existing — difix jadi `!== UserStatus::ACTIVE` di commit yang sama dgn cast-nya, jadi gak pernah ke-deploy dalam kondisi rusak.

**Verifikasi:** `UserCrudTest` (2 assertion `->status` diupdate ke `->value`), full `User*`/`Auth` test suite — sisa gagal cuma pre-existing (`Call to a member function all() on array`, dikonfirmasi identik sebelum/sesudah).

**Konfirmasi baik:** role/permission (`hasPermission()`/`hasRole()`) udah DB-driven via model `Role`/`Permission`, BUKAN raw string literal di middleware/routes — gak butuh enum di area itu. Broadcast channel names di `routes/channels.php` cuma 3 pattern tetap, bukan masalah duplikasi.

---

## 9. ✅ FIXED (Task 15) — `NotificationType` enum, kelanjutan §8c

**Status: SUDAH DIKERJAKAN.** Enum ternyata butuh **4 case** (`INFO`/`ERROR`/`WARNING`/`SUCCESS`), bukan 2 kayak dugaan awal — ketauan pas migrasi `notifyTeamMembers()` di `TaskController.php` yang gak ke-grep di scan awal (cuma nyari `new AppNotification(` langsung, kelewat wrapper method). Constructor `AppNotification` param `type` diubah dari `string` jadi `NotificationType $type = NotificationType::INFO`, `toArray()`/`toBroadcast()` pakai `->value`. Semua 7+5 call-site (TaskService x5, FopTaskController x1, TaskController `notifyTeamMembers` + 5 caller internalnya) dimigrasi. Test `NotificationDashboardTest` (5 instansiasi langsung `new AppNotification(...)`) diupdate ke enum instance. Verifikasi: 24 test relevan (`FopTaskCancelTest`/`NotificationDashboardTest`/`TaskFopActionsTest`/`TaskRescheduleTest`) full pass.

**Inventaris lengkap call-site `new AppNotification(...)` / param `type` (hasil grep 2026-07-16):**

| # | Lokasi | Value `type` dipakai |
|---|---|---|
| 1 | `TaskService.php:201-206` (laporan selesai → notif FOP) | `'info'` |
| 2 | `TaskService.php:389-394` (reassign → notif teknisi baru) | `'info'` |
| 3 | `TaskService.php:400-405` (reassign → notif teknisi lama) | `'error'` |
| 4 | `TaskService.php:415-420` (reschedule → notif anggota tim lain) | `'info'` |
| 5 | `TaskService.php:498-503` (update umum task) | `'error'` kalau `$eventType` cancelled/rejected, else `'info'` |
| 6 | `FopTaskController.php:771-776` (switch teknisi FOP) | `'info'` |
| 7 | `TaskController.php:578,586-591` — private `notifyTeamMembers()`, param `$type = 'info'` default, diteruskan dari 5 caller internal (line 389, 414, 460, 483, 496) | `'info'` (default, belum dicek tiap caller kirim override atau enggak) |

**Konsumen frontend (harus disinkronkan manual sekarang):** `resources/views/components/notification-dropdown.blade.php:19-22` — cuma bedain `type === 'error'` vs selain itu (binary check). Konfirmasi: value yang BENERAN dipakai di seluruh codebase cuma **`'info'`** dan **`'error'`** — gak ada `'success'`/`'warning'` ditemukan di scan ini (dugaan awal §8c soal 4 value gak akurat, cukup 2 case).

**Solusi nyata — langkah eksekusi:**
1. Bikin `app/Enums/NotificationType.php`:
   ```php
   enum NotificationType: string
   {
       case INFO  = 'info';
       case ERROR = 'error';
   }
   ```
2. Ubah constructor `AppNotification.php:14-19` — parameter `type` jadi `NotificationType $type = NotificationType::INFO`, dan `toArray()`/`toBroadcast()` (line 32, 43) pakai `$this->type->value`.
3. Migrasi 7 titik di tabel atas: ganti literal `'info'`/`'error'` jadi `NotificationType::INFO`/`NotificationType::ERROR`.
4. `TaskController.php:578` — ganti signature `notifyTeamMembers(Task $task, string $title, string $message, string $type = 'info')` jadi terima `NotificationType $type = NotificationType::INFO`; cek 5 caller-nya (line 389,414,460,483,496) apa ada yang override `$type` — kalau ada, ikut disesuaikan.
5. Blade `notification-dropdown.blade.php` gak perlu berubah (baca `notif.data.type` dari JSON hasil `toArray()`/`toBroadcast()`, tetep string `'error'`/`'info'` di JS — enum di PHP gak nembus ke JS, cuma proteksi sisi backend).
6. Tambah test: assert `AppNotification` nolak value selain enum (type-safety by construction, gak perlu test manual lagi krn PHP bakal TypeError kalau caller kirim string sembarangan).

**Kenapa worth dikerjakan:** sekarang `AppNotification::$type` terima string BEBAS tanpa validasi — typo (`'eror'`, `'Info'`, dll) gak bakal ke-detect dimanapun, cuma bikin badge gak ke-style tapi gak ada error. Dengan enum, typo jadi PHP TypeError saat development, bukan silent UI glitch di production.

---

## Yang SUDAH baik (bukan temuan, referensi pola benar)

- SLA countdown UI: satu component reusable `resources/views/components/countdown-timer.blade.php`, dipakai konsisten di `tasks/own.blade.php:187` dll — gak ada duplikat JS comparator.
- `TaskController::releaseTeamAndSetPending()` (line 250-286): sengaja di-share antara `reschedule()` dan `pending()`, didokumentasikan jelas — contoh penghindaran duplikasi yang benar.
- `TaskType::autoOnlyValues()` / `manualValues()` / `manualOptions()`: pattern helper terpusat yang benar — masalahnya cuma di 4 call site FopTaskController yang belum migrasi ke pattern ini (lihat temuan #1).

---

## Prioritas eksekusi

1. ~~**#0 (reassign teknisi selalu gagal)**~~ — ✅ **SUDAH FIX + test coverage + transaction fix**, full suite pass.
2. ~~**#1 (bug produksi sort/priority Survey)**~~ — ✅ **SUDAH FIX + regresi test baru**, full suite pass.
3. ~~**#2 (SLA calc konsolidasi)**~~ — ✅ **SUDAH FIX + regresi test baru**, full suite pass.
4. ~~**#5, #6**~~ — ✅ **SUDAH FIX**. #6 nemuin bonus bug nyata di `FopTask.php:176` (`Collection::where` enum vs string, `slaDeadline()` Pemasangan selalu salah fallback) — udah difix + test baru. Full suite pass.
5. ~~**#8a (InvoiceStatus)**~~ — ✅ **SUDAH FIX**, ~25 titik/11 file dimigrasi, 2 bug enum-vs-string ketemu & difix (`PaymentController.php`). Full suite name-diff identik.
6. ~~**#7a (PaymentStatus)**~~ — ✅ **SUDAH FIX** (3 case, bukan 5 — koreksi analisa), full suite pass (baseline gak berubah). ~~**#7b (CustomerStatus)**~~ — ❌ **DIBATALKAN**, ternyata bukan kandidat enum (FK ke master table `subscription_statuses` yang admin-configurable). Digantiin rekomendasi: reuse `WorkflowTransition` enum yang UDAH ADA, audit typo-risk-nya perlu sesi terpisah (24 file, banyak false-positive, gak aman di-mass-rewrite tanpa baca konteks satu-satu).
7. ~~**Task 15 / §9 (NotificationType)**~~ — ✅ **SUDAH FIX**, ternyata butuh 4 case (bukan 2 — koreksi analisa, `notifyTeamMembers()` kelewat di scan awal).
8. ~~**#8b (DocumentType)**~~ — ✅ **SUDAH FIX**, const `TYPES` dihapus (bukan dipertahankan).
9. ~~**#8d (Gender)**~~ — ✅ **SUDAH FIX**, ketemu bug crash nyata di `CustomerValidationService.php` (generic field-checker, dampak luas ke semua Customer save). ~~**#8e (users.status)**~~ — ✅ **SUDAH FIX**, ketemu **BUG PRODUKSI SERIUS** di `LoginController.php` (kalau gak difix bareng, SEMUA login bakal ke-block).
10. ~~**#3 (reason validation helper)**~~ — ✅ **SUDAH FIX**. Ketemu bug latent: validasi di 4 titik `TaskController` lebih longgar (max:500/1000) dari kapasitas kolom DB asli (varchar 255) — udah dibenerin. Solusi 1-konstanta di analisa awal SALAH (kolom beda-beda kapasitas), diganti helper dgn max eksplisit per call-site.

---

## SEMUA ITEM SELESAI (2026-07-17)

Seluruh temuan #0 s/d #8e + Task 15 udah dikerjakan & diverifikasi. Ringkasan bug produksi nyata yang ketemu & difix sepanjang proses (bukan cuma refactor kosmetik):
- **#0**: reassign teknisi SELALU gagal (enum vs string loose-compare).
- **#1**: task Survey silalu kelewat dari sort-priority (hardcode string drift dari enum rename).
- **#6**: `FopTask::slaDeadline()` Pemasangan SELALU salah fallback tanggal (Collection::where enum vs string).
- **§7a**: `Payment` booted() hook audit-action-detector salah kelas (`cancel` vs `update`).
- **§8a**: guard nolak-bayar-invoice-lunas/batal salah (enum vs string).
- **§8d**: `CustomerValidationService` crash total pas Customer disave (dampak luas, bukan cuma gender).
- **§8e**: `LoginController` — SEMUA login bakal ke-block kalau gak difix bareng cast (paling severe).
- **§3**: validasi reason 4 titik lebih longgar dari kapasitas kolom DB (bug latent, belum pernah ke-trigger).

Semua diverifikasi lewat A/B test (baseline vs after, name-level diff bukan cuma count) — zero regresi baru diperkenalkan, semua kegagalan test tersisa dikonfirmasi pre-existing/infra-related.
