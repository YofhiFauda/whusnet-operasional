# Webhook "Siap Isolir" ke Web A + Eksekusi Otomatis Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Begitu kasus isolir Admin putuskan "Eksekusi" (status pindah ke `ready_for_isolation`), Whusnet kirim webhook ke Web A (case_number, CID, nama, paket, outstanding amount); Web A POST balik (token-authenticated) untuk mengeksekusi isolir sungguhan, idempotent, atribusi aktor jelas (`external_web_a`), tanpa menyentuh jalur NOC manual (`toggleSuspend()`) yang baru di-fix race condition-nya 2026-11-20.

**Architecture:** Outbound lewat pola outbox existing (`WebhookOutbox` + `SendWebhookOutboxJob`, HMAC-SHA256) — event domain baru dispatch dari `IsolationCaseService::setAdminDecision()`, listener baru tulis baris outbox destination `web_a`. Inbound lewat endpoint API baru (token bearer, pola `EnsureNetworkAssignmentWriteToken`) yang mendelegasikan ke service baru `IsolationExecutionService` — BERDIRI SENDIRI dari `toggleSuspend()` (tidak saling panggil), tapi meniru struktur dua-fase (lock+gate di dalam transaksi, tracking kasus di luar transaksi+try/catch) yang jadi fix bug race condition hari ini.

**Tech Stack:** Laravel 13, PHPUnit 12, pola existing `WebhookOutbox`/`SendWebhookOutboxJob`/token bearer middleware.

**Spec:** `docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md`

## Global Constraints

- Webhook keluar WAJIB lewat `WebhookOutbox` + `SendWebhookOutboxJob` — tidak ada `Http::post()` langsung dari listener manapun.
- Destination webhook tetap hardcode di `config/webhooks.php` (bukan tabel DB) — konsisten `website_b`/`customer_portal`.
- `toggleSuspend()` TIDAK diubah strukturnya dan TIDAK dipanggil/memanggil kode baru — jalur Web A berdiri sendiri.
- Endpoint eksekusi Web A WAJIB directional ("pastikan suspended"), BUKAN toggle — double-call tidak boleh membalik ke aktif.
- `lockForUpdate()` pada baris `isolation_cases` WAJIB di DALAM transaksi yang sama dengan `$customer->update()`.
- `markIsolationSucceeded()`/`markTrackingFailed()` WAJIB dipanggil DI LUAR transaksi suspend, dibungkus try/catch (kegagalan tracking tidak boleh merollback isolir yang sudah terjadi).
- Atribusi aktor Web A: `actor_type = 'external_web_a'`, `actor_id = null`, `audit_logs.user_id = null` — bukan user login palsu, bukan `'system'` generik.
- Webhook "siap isolir" cuma dikirim saat transisi BENAR-BENAR masuk `READY_FOR_ISOLATION` (`$fromStatus !== READY_FOR_ISOLATION`), bukan tiap kali `setAdminDecision(EXECUTE)` dipanggil.
- `vendor/bin/pint --dirty --format agent` setelah semua perubahan PHP. `npm run build` tidak diperlukan (tidak ada perubahan Blade/JS).
- Jangan jalankan full test suite (`php artisan test` tanpa filter) — hanya file/filter yang terdampak tiap task.

## Review Focus

- **Case belum punya `CustomerService` sama sekali** saat endpoint Web A dipanggil (data longgar/pelanggan sudah di non-aktifkan manual di luar jalur) — endpoint harus menolak 422, bukan crash null-pointer di `$customer->customerService->update()`.
- **`case_number` valid tapi milik kasus yang sudah `completed`/`cancelled`** (Web A memanggil pakai data webhook lama yang basi berhari-hari) — harus 422 "tidak lagi siap", bukan ikut tereksekusi karena enum group check longgar.
- **Dua request Web A hampir bersamaan untuk `case_number` yang sama** (retry jaringan ganda) — `lockForUpdate()` harus benar-benar mencegah keduanya lolos gerbang; satu sukses, satu lagi lihat status sudah `suspended` dan balas idempotent no-op, TIDAK dobel `AuditLog`/`IsolationEvent`.
- **`SendWebhookOutboxJob` menerima destination `web_a` dengan config kosong/url bukan https://** (env belum diisi di server) — harus gagal rapi (`failed`, tanpa retry 8x/6 jam), bukan exception tak tertangani atau retry sia-sia, sama seperti guard `website_b`/`customer_portal` yang sudah ada.
- **Token bearer Web A salah/kosong/header beda format** (`Authorization: Token ...` bukan `Bearer ...`) pada endpoint eksekusi — harus 401 cepat sebelum masuk logic apa pun, sama pola `EnsureNetworkAssignmentWriteToken`.

---

## Task 1: `IsolationCaseService` — actor type threading (`external_web_a`)

**Files:**
- Modify: `app/Services/IsolationCaseService.php` (`markIsolationSucceeded()`, `markTrackingFailed()`, `recordEvent()`)
- Test: `tests/Feature/IsolirQueueTest.php` (tambah kasus baru di file existing — jangan buat file baru untuk perubahan kecil ini)

**Interfaces:**
- Produces: `IsolationCaseService::markIsolationSucceeded(CustomerService $service, ?User $actor = null, string $actorType = 'system'): IsolationCase` — dipakai Task 4.
- Produces: `IsolationCaseService::markTrackingFailed(CustomerService $service, ?User $actor, bool $wasIsolating, \Throwable $error, string $actorType = 'system'): void` — dipakai Task 4.

- [ ] **Step 1: Tulis test gagal** — di `tests/Feature/IsolirQueueTest.php`, tambah:
```php
#[Test]
public function mark_isolation_succeeded_dengan_actor_null_tercatat_actor_type_custom(): void
{
    // arrange: customer service aktif dengan isolation case ready_for_isolation
    // act: app(IsolationCaseService::class)->markIsolationSucceeded($service, null, 'external_web_a')
    // assert: isolation_events terakhir (ISOLATION_SUCCEEDED) punya actor_type === 'external_web_a' dan actor_id === null
}
```
Isi arrange mengikuti pola setup kasus isolir yang sudah ada di file ini (cari `createOrSyncCase`/`setAdminDecision` di test lain pada file yang sama untuk pola factory).

- [ ] **Step 2: Jalankan test, pastikan gagal** (signature belum menerima argumen ke-3)
Run: `php artisan test --compact --filter=mark_isolation_succeeded_dengan_actor_null_tercatat_actor_type_custom`
Expected: FAIL (too many arguments / TypeError)

- [ ] **Step 3: Implement**
  - `recordEvent()`: ubah baris `'actor_type' => $actor ? 'user' : 'system',` jadi menerima parameter `$actorType` baru (default `'system'`) dan gunakan `$actor ? 'user' : $actorType`.
  - `markIsolationSucceeded(CustomerService $service, ?User $actor = null, string $actorType = 'system'): IsolationCase` — teruskan `$actorType` ke tiap `recordEvent()` call di dalamnya.
  - `markTrackingFailed(CustomerService $service, ?User $actor, bool $wasIsolating, \Throwable $error, string $actorType = 'system'): void` — ubah signature `User $actor` → `?User $actor`, tambah `$actorType`, teruskan ke `recordEvent()` internal.
  - Pemanggil lama (`CustomerController::toggleSuspend()`) tidak diubah — tetap kirim `auth()->user()` tanpa `$actorType`, default `'system'` tidak pernah dipakai karena `$actor` selalu truthy di jalur itu (`actor_type` tetap `'user'`).

- [ ] **Step 4: Jalankan test, pastikan lulus**
Run: `php artisan test --compact --filter=IsolirQueueTest`
Expected: PASS semua (termasuk test lama yang sudah ada di file, pastikan tidak regresi)

- [ ] **Step 5: Pint + commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Services/IsolationCaseService.php tests/Feature/IsolirQueueTest.php
git commit -m "feat(isolir): dukung actor_type kustom di markIsolationSucceeded/markTrackingFailed"
```

---

## Task 2: Outbound — event + listener + destination `web_a`

**Files:**
- Create: `app/Events/IsolationCaseReadyForExecution.php`
- Create: `app/Listeners/SendIsolationReadyWebhook.php`
- Modify: `app/Services/IsolationCaseService.php` (`setAdminDecision()` — dispatch event)
- Modify: `config/webhooks.php` (tambah `web_a`)
- Modify: `app/Jobs/SendWebhookOutboxJob.php` (`configError()`, `match` destination, `sendHttpJson()` jadi dinamis per destination)
- Modify: `.env.example` (tambah `WEBHOOK_WEB_A_URL`, `WEBHOOK_WEB_A_SECRET`)
- Test: `tests/Feature/IsolationReadyWebhookTest.php` (baru)

**Interfaces:**
- Consumes: `IsolationCaseService::setAdminDecision()` existing (Task ini cuma menambah dispatch, tidak mengubah return/signature).
- Produces: event `IsolationCaseReadyForExecution(IsolationCase $case)` — payload fields nanti dikonsumsi test Task ini saja (tidak ada task lain yang depend).
- Produces: `WebhookOutbox` row `destination = 'web_a'` dengan payload `{event_id, event: 'isolation.ready_for_execution', occurred_at, case_number, cid, customer_name, package_name, outstanding_amount}`.

- [ ] **Step 1: Tulis test gagal** — `tests/Feature/IsolationReadyWebhookTest.php`:
```php
#[Test]
public function admin_eksekusi_isolir_menulis_outbox_web_a(): void
{
    config(['webhooks.web_a.url' => 'https://web-a.test/hook', 'webhooks.web_a.secret' => 'secret']);
    // arrange: isolation case status WAITING_ISOLATION
    // act: app(IsolationCaseService::class)->setAdminDecision($case, IsolationAdminDecision::EXECUTE, $admin, null)
    // assert: WebhookOutbox::where('destination', 'web_a')->where('customer_id', $case->customer_id)
    //   ->first() ada, payload['case_number'] === $case->case_number,
    //   payload['cid'] === $customer->cid, payload['customer_name'] === $customer->full_name,
    //   payload['outstanding_amount'] sesuai Money::decimalString($case->current_outstanding_amount)
}

#[Test]
public function eksekusi_ulang_saat_sudah_ready_for_isolation_tidak_dobel_kirim(): void
{
    // arrange: case sudah READY_FOR_ISOLATION
    // act: setAdminDecision(EXECUTE) lagi
    // assert: jumlah WebhookOutbox destination web_a untuk case ini TIDAK bertambah
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**
Run: `php artisan test --compact tests/Feature/IsolationReadyWebhookTest.php`
Expected: FAIL (listener/event belum ada, `webhooks.web_a` config belum ada)

- [ ] **Step 3: Implement event**
`app/Events/IsolationCaseReadyForExecution.php` — plain `Dispatchable`, constructor `public function __construct(public IsolationCase $case) {}`. Bukan broadcast (beda dari `IsolationCaseQueueChanged`).

- [ ] **Step 4: Implement dispatch di `setAdminDecision()`**
Setelah `$case->save()` di cabang `EXECUTE` (dalam `DB::transaction` yang sama), tambah:
```php
if ($decision === IsolationAdminDecision::EXECUTE && $fromStatus !== IsolationCaseStatus::READY_FOR_ISOLATION) {
    $case->loadMissing(['customer', 'service.internetPackage']);
    IsolationCaseReadyForExecution::dispatch($case);
}
```
(Guard pakai `$fromStatus` yang sudah ada di method ini sebelum status diubah.)

- [ ] **Step 5: Implement listener**
`app/Listeners/SendIsolationReadyWebhook.php`, pola sama `SendInvoiceUpdatedWebhook` (bukan `ShouldQueue`, sync):
```php
public function handle(IsolationCaseReadyForExecution $event): void
{
    $case = $event->case;
    $customer = $case->customer;
    $eventId = (string) Str::uuid();
    $eventName = 'isolation.ready_for_execution';
    $sequence = WebhookOutbox::nextActivationNumber($case->customer_id, $eventName);
    $idempotencyKey = "isolation:{$case->id}:ready:{$sequence}";

    $payload = [
        'event_id' => $eventId,
        'event' => $eventName,
        'occurred_at' => now()->toIso8601String(),
        'case_number' => $case->case_number,
        'cid' => $customer->cid,
        'customer_name' => $customer->full_name,
        'package_name' => $case->service?->internetPackage?->name,
        'outstanding_amount' => Money::decimalString($case->current_outstanding_amount),
    ];

    $row = WebhookOutbox::create([
        'destination' => 'web_a',
        'event' => $eventName,
        'event_id' => $eventId,
        'idempotency_key' => $idempotencyKey,
        'customer_id' => $case->customer_id,
        'payload' => $payload,
        'status' => 'pending',
    ]);

    $this->dispatchAfterCommitSafely($row->id);
}
```
`dispatchAfterCommitSafely()` — copy persis method privat yang sama di `SendInvoiceUpdatedWebhook` (DB::afterCommit + try/catch + Log::error).

- [ ] **Step 6: Config + `.env.example`**
`config/webhooks.php` tambah:
```php
'web_a' => [
    'url' => env('WEBHOOK_WEB_A_URL'),
    'secret' => env('WEBHOOK_WEB_A_SECRET'),
],
```
`.env.example` tambah di dekat blok `WEBHOOK_WEBSITE_B_*`:
```
WEBHOOK_WEB_A_URL=
WEBHOOK_WEB_A_SECRET=
```

- [ ] **Step 7: `SendWebhookOutboxJob` — destination dinamis**
  - `configError()`: tambah cabang `web_a` (sama pola `website_b`/`customer_portal` — url wajib `https://`, secret wajib isi).
  - `match ($row->destination)`: tambah `'web_a' => $this->sendHttpJson($row),`.
  - `sendHttpJson(WebhookOutbox $row)`: ganti `config('webhooks.website_b')` jadi `config("webhooks.{$row->destination}")` — method ini sekarang dipakai dua destination (`website_b`, `web_a`), skema HMAC header (`X-Whusnet-Signature: t=...,v1=...`) identik untuk keduanya.

- [ ] **Step 8: Jalankan test, pastikan lulus**
Run: `php artisan test --compact tests/Feature/IsolationReadyWebhookTest.php`
Expected: PASS

- [ ] **Step 9: Regresi outbound existing**
Run: `php artisan test --compact tests/Feature/InvoiceRecalculateEmitsPortalOutboxTest.php`
Expected: PASS (memastikan `sendHttpJson()` yang diubah generik tidak merusak jalur `website_b` — test ini pakai `customer_portal` yang jalur kodenya beda method `sendPortalWebhook()`, tapi jalankan juga test API 1 kalau ada file khusus `website_b` — cari dengan `php artisan test --compact --filter=SendInstallationActivatedWebhooks` kalau filenya bernama begitu).

- [ ] **Step 10: Pint + commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Events/IsolationCaseReadyForExecution.php app/Listeners/SendIsolationReadyWebhook.php app/Services/IsolationCaseService.php config/webhooks.php app/Jobs/SendWebhookOutboxJob.php .env.example tests/Feature/IsolationReadyWebhookTest.php
git commit -m "feat(isolir): webhook outbound isolation.ready_for_execution ke Web A"
```

---

## Task 3: Inbound — token middleware + route + rate limiter

**Files:**
- Create: `app/Http/Middleware/EnsureWebAWriteToken.php`
- Modify: `bootstrap/app.php` (alias `web_a.write`)
- Modify: `app/Providers/AppServiceProvider.php` (`RateLimiter::for('web-a-isolation-execute', ...)`)
- Modify: `config/webhooks.php` (tambah `web_a_write_token`)
- Modify: `.env.example` (tambah `WEB_A_WRITE_TOKEN`)
- Modify: `routes/api.php` (route, controller placeholder dari Task 5 — buat route dulu, controller class di-reference tapi body diisi Task 5)
- Test: `tests/Feature/IsolationExecutionApiTest.php` (baru — test token saja di task ini, test logic eksekusi di Task 5)

**Interfaces:**
- Produces: middleware alias `web_a.write`, rate limiter `web-a-isolation-execute`, route bernama `api.isolation-cases.execute` di `POST /api/v1/isolation-cases/execute`.
- Consumes (forward-reference): `Api\IsolationExecutionController::store` — class dibuat di Task 5; Task ini cukup menulis route-nya, controller boleh berupa stub kosong yang mengembalikan `response()->json([], 501)` SEMENTARA supaya route bisa diuji tokennya tanpa menunggu Task 5 selesai. Task 5 mengisi body controller yang sesungguhnya.

- [ ] **Step 1: Tulis test gagal**
```php
#[Test]
public function token_salah_ditolak_401(): void
{
    config(['webhooks.web_a_write_token' => 'token-benar']);
    $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'ISO-TEST'], [
        'Authorization' => 'Bearer token-salah',
    ])->assertStatus(401);
}

#[Test]
public function tanpa_token_ditolak_401(): void
{
    config(['webhooks.web_a_write_token' => 'token-benar']);
    $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'ISO-TEST'])
        ->assertStatus(401);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**
Run: `php artisan test --compact tests/Feature/IsolationExecutionApiTest.php`
Expected: FAIL (route belum ada → 404, bukan 401)

- [ ] **Step 3: Implement middleware**
`app/Http/Middleware/EnsureWebAWriteToken.php` — copy persis struktur `EnsureNetworkAssignmentWriteToken`, pakai `config('webhooks.web_a_write_token')`.

- [ ] **Step 4: Alias + rate limiter + config + env**
  - `bootstrap/app.php`: tambah `'web_a.write' => EnsureWebAWriteToken::class,` di array alias yang sudah ada.
  - `AppServiceProvider.php`: tambah `RateLimiter::for('web-a-isolation-execute', function (Request $request) { return Limit::perMinute(20)->by($request->bearerToken() ?? $request->ip()); });` (angka sama `network-assignment-write`, cek nilai persisnya di file dan samakan).
  - `config/webhooks.php` tambah `'web_a_write_token' => env('WEB_A_WRITE_TOKEN'),`.
  - `.env.example` tambah `WEB_A_WRITE_TOKEN=`.

- [ ] **Step 5: Route + controller stub**
`routes/api.php`, di grup `Route::prefix('v1')` yang sudah ada:
```php
Route::middleware(['web_a.write', 'throttle:web-a-isolation-execute'])
    ->post('/isolation-cases/execute', [IsolationExecutionController::class, 'store'])
    ->name('api.isolation-cases.execute');
```
Buat `app/Http/Controllers/Api/IsolationExecutionController.php` stub minimal:
```php
class IsolationExecutionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'belum diimplementasi'], 501);
    }
}
```

- [ ] **Step 6: Jalankan test, pastikan lulus**
Run: `php artisan test --compact tests/Feature/IsolationExecutionApiTest.php`
Expected: PASS

- [ ] **Step 7: Pint + commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureWebAWriteToken.php bootstrap/app.php app/Providers/AppServiceProvider.php config/webhooks.php .env.example routes/api.php app/Http/Controllers/Api/IsolationExecutionController.php tests/Feature/IsolationExecutionApiTest.php
git commit -m "feat(isolir): endpoint eksekusi Web A — token auth + route (stub)"
```

---

## Task 4: `IsolationExecutionService::suspendFromWebA()`

**Files:**
- Create: `app/Exceptions/IsolationExecutionException.php`
- Create: `app/Services/IsolationExecutionService.php`
- Test: `tests/Feature/IsolationExecutionServiceTest.php` (baru)

**Interfaces:**
- Consumes: `IsolationCaseService::markIsolationSucceeded(CustomerService, ?User, string)` dan `markTrackingFailed(CustomerService, ?User, bool, \Throwable, string)` dari Task 1.
- Produces: `IsolationExecutionService::suspendFromWebA(Customer $customer): array{already_suspended: bool}` — dipakai Task 5. Melempar `IsolationExecutionException` (extends `\RuntimeException`, constructor `string $message`) kalau gerbang gagal.

- [ ] **Step 1: Tulis test gagal**
```php
#[Test]
public function suspend_from_web_a_sukses_untuk_kasus_ready_for_isolation(): void
{
    // arrange: customer active, customerService, isolation case READY_FOR_ISOLATION, active_slot=1
    $result = app(IsolationExecutionService::class)->suspendFromWebA($customer);

    $this->assertFalse($result['already_suspended']);
    $this->assertSame('suspended', $customer->refresh()->status);
    $this->assertSame('isolir', $customer->customerService->refresh()->service_status);
    $this->assertDatabaseHas('audit_logs', ['auditable_id' => $customer->id, 'action' => 'isolir', 'user_id' => null]);
    // assert isolation_events terakhir actor_type === 'external_web_a'
}

#[Test]
public function suspend_from_web_a_idempotent_kalau_sudah_suspended(): void
{
    // arrange: customer sudah status suspended
    $result = app(IsolationExecutionService::class)->suspendFromWebA($customer);
    $this->assertTrue($result['already_suspended']);
    // assert TIDAK ada audit_logs/isolation_events baru ditambahkan
}

#[Test]
public function suspend_from_web_a_menolak_kasus_belum_ready(): void
{
    // arrange: customer active, isolation case masih WAITING_ISOLATION
    $this->expectException(IsolationExecutionException::class);
    app(IsolationExecutionService::class)->suspendFromWebA($customer);
}

#[Test]
public function suspend_from_web_a_menolak_tanpa_customer_service(): void
{
    // arrange: customer active TANPA customerService
    $this->expectException(IsolationExecutionException::class);
    app(IsolationExecutionService::class)->suspendFromWebA($customer);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**
Run: `php artisan test --compact tests/Feature/IsolationExecutionServiceTest.php`
Expected: FAIL (class belum ada)

- [ ] **Step 3: Implement `IsolationExecutionException`**
```php
class IsolationExecutionException extends \RuntimeException {}
```

- [ ] **Step 4: Implement `IsolationExecutionService::suspendFromWebA()`**
Ikuti PERSIS struktur yang ditulis di spec §"Desain Teknis #2" (design doc) — lock `IsolationCase` di dalam `DB::transaction` bareng `$customer->update()`/`AuditLog::create()`/`CustomerStatusLog::create()`, gate error ditangkap lewat variabel referensi lalu dilempar sebagai `IsolationExecutionException` SETELAH transaksi selesai (supaya lock dilepas sebelum exception keluar), baru PANGGIL `markIsolationSucceeded()` DI LUAR transaksi dibungkus try/catch → `markTrackingFailed()` kalau gagal. Baca ulang `app/Http/Controllers/CustomerController.php:386-440` sebagai referensi struktur persis yang harus ditiru (lock, gate, transaksi, audit log, status log).

- [ ] **Step 5: Jalankan test, pastikan lulus**
Run: `php artisan test --compact tests/Feature/IsolationExecutionServiceTest.php`
Expected: PASS

- [ ] **Step 6: Pint + commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Exceptions/IsolationExecutionException.php app/Services/IsolationExecutionService.php tests/Feature/IsolationExecutionServiceTest.php
git commit -m "feat(isolir): IsolationExecutionService::suspendFromWebA idempotent + lock"
```

---

## Task 5: `Api\IsolationExecutionController` — isi controller sungguhan

**Files:**
- Modify: `app/Http/Controllers/Api/IsolationExecutionController.php` (ganti stub Task 3)
- Test: `tests/Feature/IsolationExecutionApiTest.php` (tambah kasus di file yang sama dengan Task 3)

**Interfaces:**
- Consumes: `IsolationExecutionService::suspendFromWebA(Customer $customer): array` dan `IsolationExecutionException` dari Task 4.

- [ ] **Step 1: Tulis test gagal** — tambah di `tests/Feature/IsolationExecutionApiTest.php`:
```php
#[Test]
public function eksekusi_sukses_mengubah_status_pelanggan(): void
{
    config(['webhooks.web_a_write_token' => 'token-benar']);
    // arrange: case READY_FOR_ISOLATION dengan case_number diketahui
    $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => $case->case_number], [
        'Authorization' => 'Bearer token-benar',
    ])->assertStatus(200)->assertJson(['success' => true, 'already_suspended' => false]);
}

#[Test]
public function eksekusi_case_number_tidak_ada_422(): void
{
    config(['webhooks.web_a_write_token' => 'token-benar']);
    $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'TIDAK-ADA'], [
        'Authorization' => 'Bearer token-benar',
    ])->assertStatus(422);
}

#[Test]
public function eksekusi_kasus_belum_ready_422(): void
{
    config(['webhooks.web_a_write_token' => 'token-benar']);
    // arrange: case masih WAITING_ISOLATION
    $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => $case->case_number], [
        'Authorization' => 'Bearer token-benar',
    ])->assertStatus(422);
}

#[Test]
public function eksekusi_sudah_suspended_200_idempotent(): void
{
    config(['webhooks.web_a_write_token' => 'token-benar']);
    // arrange: customer sudah suspended, case apapun
    $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => $case->case_number], [
        'Authorization' => 'Bearer token-benar',
    ])->assertStatus(200)->assertJson(['success' => true, 'already_suspended' => true]);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**
Run: `php artisan test --compact tests/Feature/IsolationExecutionApiTest.php`
Expected: FAIL (controller masih stub 501)

- [ ] **Step 3: Implement controller**
```php
public function store(Request $request): JsonResponse
{
    $data = $request->validate([
        'case_number' => ['required', 'string', 'exists:isolation_cases,case_number'],
    ]);

    $case = IsolationCase::where('case_number', $data['case_number'])->with('customer')->firstOrFail();

    try {
        $result = app(IsolationExecutionService::class)->suspendFromWebA($case->customer);
    } catch (IsolationExecutionException $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
    }

    return response()->json([
        'success' => true,
        'case_number' => $case->case_number,
        'status' => 'suspended',
        'already_suspended' => $result['already_suspended'],
    ]);
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**
Run: `php artisan test --compact tests/Feature/IsolationExecutionApiTest.php`
Expected: PASS semua (termasuk test token Task 3)

- [ ] **Step 5: Pint + commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/IsolationExecutionController.php tests/Feature/IsolationExecutionApiTest.php
git commit -m "feat(isolir): Api\\IsolationExecutionController eksekusi isolir dari Web A"
```

---

## Task 6: Dokumentasi + `docs/TASKS.md`

**Files:**
- Create: `docs/api/api-webhook-isolir-web-a/README.md`
- Create: `docs/api/api-webhook-isolir-web-a/business-logic.md`
- Modify: `docs/TASKS.md` (tambah baris `ADHOC-307`)

**Interfaces:** (tidak ada — dokumentasi, tidak ada test)

- [ ] **Step 1: `README.md`** — ikuti format singkat `docs/api/api-pop-distribusi/README.md` (atau `api-webhook-pemasangan/README.md`): ringkasan 1 paragraf, link ke `business-logic.md`, link ke spec `docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md`.

- [ ] **Step 2: `business-logic.md`** — isi:
  - Arah outbound: event `isolation.ready_for_execution`, trigger (`setAdminDecision` EXECUTE, transisi nyata ke `READY_FOR_ISOLATION`), payload (tabel field: `case_number`, `cid`, `customer_name`, `package_name`, `outstanding_amount`), signing (`X-Whusnet-Signature: t=...,v1=...`, HMAC-SHA256 atas `"{timestamp}.{body}"`), retry (8x, backoff s.d. 6 jam).
  - Arah inbound: `POST /api/v1/isolation-cases/execute`, header `Authorization: Bearer <WEB_A_WRITE_TOKEN>`, body `{case_number}`, response sukses (`200`, `already_suspended` bool), response gagal (`401` token salah, `422` case tidak ditemukan/belum siap).
  - Idempotensi: directional (bukan toggle), double-call aman.
  - Atribusi aktor: `actor_type = 'external_web_a'` di riwayat kasus, `audit_logs.user_id = null`.
  - Non-goals eksplisit: tidak ada endpoint restorasi/aktivasi-kembali untuk Web A.

- [ ] **Step 3: `docs/TASKS.md`** — tambah baris baru di tabel Ad-Hoc Improvements (setelah baris `ADHOC-306`, nomor urut `ADHOC-307`) merangkum: tujuan (webhook siap-isolir ke Web A + eksekusi otomatis full-auto), file utama yang disentuh, jumlah test baru/lulus (isi setelah Task 1-5 selesai dan semua test dijalankan), status `DONE (kode lokal; 2026-11-20)` atau sesuaikan tanggal riil penyelesaian.

- [ ] **Step 4: Commit**
```bash
git add docs/api/api-webhook-isolir-web-a/ docs/TASKS.md
git commit -m "docs(isolir): dokumentasi API webhook isolir Web A + catatan ADHOC-307"
```

---

## Self-Review Notes

- **Spec coverage**: Outbound (Task 2), inbound+auth (Task 3+5), service directional+idempotent+lock (Task 4), atribusi aktor (Task 1), dokumentasi (Task 6) — semua bagian "Desain Teknis" spec punya task. Non-goals (restorasi via Web A, tabel konsumen dinamis) sengaja tidak ada task — sesuai spec.
- **Konsistensi tipe**: `suspendFromWebA(Customer $customer): array{already_suspended: bool}` dipakai sama persis di Task 4 (definisi) dan Task 5 (pemanggil). `markIsolationSucceeded`/`markTrackingFailed` signature Task 1 dipakai sama di Task 4.
- **Review Focus** tercakup: customer tanpa `customerService` → test Task 4 `suspend_from_web_a_menolak_tanpa_customer_service`; case basi (bukan ready group) → test Task 4 `suspend_from_web_a_menolak_kasus_belum_ready` + Task 5 `eksekusi_kasus_belum_ready_422`; race dua request → gate+lock diimplementasikan Task 4 (test lock konkuren tidak ditulis eksplisit karena PHPUnit single-process tidak bisa mensimulasikan race asli — dicatat sebagai keterbatasan, bukan diabaikan); config `web_a` kosong/non-https → sudah dijaga `configError()` existing pattern yang di-generalize Task 2 (regresi test `website_b`/`customer_portal` existing harus tetap lulus, Step 9 Task 2); token salah/kosong → Task 3 Step 1-2.
