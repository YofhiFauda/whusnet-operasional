# Design: Webhook "Siap Isolir" ke Web A + Eksekusi Isolir Otomatis

**Tanggal**: 2026-11-20
**Status**: Draft — menunggu approval

## Intent & Why

Pelanggan yang kasusnya sudah masuk status `ready_for_isolation` ("Siap Isolir" —
Admin sudah memutuskan "Eksekusi Isolir" lewat Monitoring Isolir) perlu diberi
tahu ke sistem eksternal **Web A**. Operator di Web A menekan tombol "Isolir"
di sana, dan tombol itu memicu eksekusi isolir sungguhan di Whusnet Operasional
— tanpa NOC harus login & klik manual di app ini.

Ini FULL-AUTO (keputusan user): Web A boleh langsung mengeksekusi, bukan cuma
mengonfirmasi niat lalu NOC yang klik beneran.

## Goals

- Begitu kasus isolir masuk `ready_for_isolation`, Whusnet mengirim webhook ke
  Web A berisi `case_number`, CID, nama pelanggan, paket, outstanding amount.
- Web A bisa POST balik ke Whusnet (token-authenticated) untuk mengeksekusi
  isolir pelanggan tersebut — status layanan beneran berubah jadi `suspended`,
  tercatat di audit log & riwayat kasus isolir, sama seperti isolir manual
  NOC.
- NOC tetap bisa isolir manual dari app seperti sekarang (dua jalur masuk,
  siapa cepat menang, idempotent).

## Non-Goals

- Restorasi/aktivasi-kembali (`activate`) lewat Web A — TIDAK dibangun
  sekarang. Web A cuma bisa EKSEKUSI ISOLIR, bukan mengaktifkan balik.
- Web A tidak diberi tahu soal kasus yang batal/ditoleransi setelah webhook
  "siap isolir" terkirim (mis. Admin berubah pikiran). Kalau tombol lama di
  Web A ditekan untuk kasus yang sudah tidak `ready_for_isolation`, endpoint
  cukup balas 422 jelas — menyinkronkan status tombol di sisi Web A itu di
  luar scope ini.
- Tidak ada tabel/admin form untuk daftar konsumen webhook — destination
  tetap hardcode di `config/webhooks.php`, konsisten dengan `website_b`/
  `customer_portal` yang sudah ada (keputusan rev.8 docs/api/keputusan.md).

## Constraints (dari kode yang sudah ada)

- Pola outbox (`WebhookOutbox` + `SendWebhookOutboxJob`, HMAC-SHA256, retry
  8x/backoff s.d. 6 jam) sudah established — webhook keluar WAJIB lewat jalur
  ini, bukan `Http::post()` langsung dari listener.
- `IsolationCaseService::recordEvent()` sudah mendukung actor nullable
  (`actor_type` kolom string biasa, bukan DB enum — migration
  `2026_11_19_160002_create_isolation_events_table.php` cuma comment
  `'user' | 'system'`, bebas nilai ketiga tanpa migrasi baru).
- `CustomerController::toggleSuspend()` adalah SATU-SATUNYA jalur yang
  mengubah `Customer.status`/`CustomerService.service_status` hari ini
  (CLAUDE.md). Endpoint baru ini jadi jalur KEDUA — harus berbagi logic yang
  sama (lewat service baru), bukan duplikasi kode yang bisa menyimpang.
- `toggleSuspend()` adalah TOGGLE (active<->suspended) — tidak aman dipakai
  ulang apa adanya untuk pemanggil idempotent/otomatis (retry jaringan dari
  Web A yang memanggil dua kali akan membalik isolir jadi aktif lagi kalau
  logicnya toggle). Endpoint baru harus directional ("pastikan suspended"),
  bukan toggle.
- **Fix bug 2026-11-20 di `toggleSuspend()` (baris ~386-474) WAJIB diikuti,
  bukan cuma gaya lama sebelum fix**: (a) `lockForUpdate()` pada baris
  `isolation_cases` dipindah KE DALAM transaksi yang sama dengan
  `$customer->update()` — row lock mencegah dua eksekusi hampir-bersamaan
  lolos gerbang berdua; (b) `markIsolationSucceeded()`/`markTrackingFailed()`
  dipanggil SETELAH transaksi commit, dibungkus try/catch, BUKAN di dalam
  transaksi yang sama — supaya kegagalan tracking kasus (mis.
  `NumberSequenceService` gagal) tidak pernah merollback aksi isolir yang
  sudah terjadi. Builder-check (2026-11-20) menandai draf awal desain ini
  melanggar kedua hal ini — diperbaiki di bagian Desain Teknis #2 di bawah.

## Approaches

### Outbound (kirim ke Web A)

**A. Event domain baru + listener baru (dipilih)** — tambah event
`IsolationCaseReadyForExecution` di-dispatch dari
`IsolationCaseService::setAdminDecision()` persis saat status dipindah ke
`READY_FOR_ISOLATION`. Listener baru `SendIsolationReadyWebhook` (pola sama
`SendInvoiceUpdatedWebhook` — sync, bukan `ShouldQueue`, cuma tulis baris
outbox) menyusun payload dan menulis `WebhookOutbox` destination baru
`web_a`.
_Alasan dipilih_: konsisten 100% dengan pola existing, tidak menyentuh
`IsolationCaseQueueChanged` (yang sengaja payload-nya minimal untuk badge
realtime, beda tujuan).

**B. Taruh langsung di `setAdminDecision()`** — ditolak, melanggar pemisahan
"Service tidak boleh tahu soal webhook", dan tidak reusable/testable
terpisah seperti listener.

### Inbound (Web A eksekusi balik)

**A. Endpoint API baru + service terpisah yang MENIRU struktur
`toggleSuspend()` (dipilih)** — `POST /api/v1/isolation-cases/execute`,
token bearer (pola sama `EnsureNetworkAssignmentWriteToken`), body
`{case_number}`. `IsolationExecutionService::suspendFromWebA()` berdiri
sendiri (TIDAK dipanggil oleh `toggleSuspend()`, dan sebaliknya), tapi
mengikuti persis struktur dua-fase `toggleSuspend()` pasca fix
2026-11-20 (lock di dalam transaksi, tracking kasus di luar transaksi
dengan try/catch) — lihat Keputusan #5 dan Desain Teknis #2.
_Alasan dipilih_: `toggleSuspend()` tetap satu-satunya ENTRY POINT UI
untuk NOC (tidak disentuh), dan jalur Web A yang beda kebutuhan
(tanpa session, tanpa arah aktivasi-kembali, actor berbeda) tidak
dipaksa lewat satu method generik yang jadi rawan regresi lintas-jalur.

**B. Endpoint baru panggil `toggleSuspend()` langsung (lewat HTTP internal
atau instansiasi controller)** — ditolak. `toggleSuspend()` TOGGLE (bisa
membalik ke aktif), butuh `auth()->user()` untuk permission check,
dan return type campur JSON/Redirect — tidak cocok dipanggil
non-interaktif.

## Keputusan (dari percakapan)

1. **Full-auto** — endpoint eksekusi langsung mengubah status layanan,
   tidak ada langkah konfirmasi manual NOC tambahan.
2. **Idempotent no-op** — kalau pelanggan sudah `status = suspended` saat
   endpoint dipanggil (double-press, retry jaringan, atau NOC sudah isolir
   manual duluan), balas `200` sukses `"sudah diisolir sebelumnya"` TANPA
   mengubah apa pun lagi. Tidak pernah toggle balik ke aktif.
3. **Kasus sudah tidak siap** (status bukan lagi di `readyForIsolationGroup()`
   DAN pelanggan masih `active` — berarti Admin menoleransi/membatalkan
   sebelum Web A menekan tombol) → `422` dengan pesan eksplisit, tidak
   dieksekusi.
4. **Atribusi aktor** — BUKAN user login palsu, BUKAN generik `'system'`.
   `actor_type` di riwayat kasus (`isolation_events.actor_type`) diisi
   literal `'external_web_a'`, `actor_id` tetap `null`. Audit log
   (`audit_logs.user_id`) ikut `null` (kolom sudah nullable). Jelas
   kebeda dari eksekusi NOC (`actor_type = 'user'`, `actor_id` terisi) dan
   dari proses deteksi otomatis internal (kalau nanti ada, pakai `'system'`).
5. **Tidak ada ekstraksi/refactor `toggleSuspend()`** — jalur Web A berdiri
   sendiri di service baru, meniru struktur (lock-in-transaction, tracking
   di luar transaksi), bukan memanggil atau dipanggil `toggleSuspend()`.
   Dipilih setelah builder-check menandai draf awal (satu service dipakai
   bersama) berisiko menyeret perbedaan kebutuhan (session/permission/arah
   aktivasi-kembali) jadi satu method yang terlalu umum.

## Desain Teknis

### 1. Outbound — destination baru `web_a`

`config/webhooks.php` tambah:
```php
'web_a' => [
    'url' => env('WEBHOOK_WEB_A_URL'),
    'secret' => env('WEBHOOK_WEB_A_SECRET'),
],
```

Event baru `app/Events/IsolationCaseReadyForExecution.php` — bukan
broadcast, murni domain event, bawa `IsolationCase $case` (sudah
`load(['customer', 'service.internetPackage'])` di titik dispatch).
Dispatch dari `IsolationCaseService::setAdminDecision()` tepat setelah
`$case->save()` untuk cabang EXECUTE, DI DALAM transaksi yang sama
(pola sama event lain di file ini yang dispatch di dalam
`DB::transaction()`).

**Guard re-dispatch (gap builder-check #5)**: dispatch HANYA kalau
`$fromStatus !== IsolationCaseStatus::READY_FOR_ISOLATION` (benar-benar
pindah KE status itu, bukan Admin menekan "Eksekusi" lagi saat kasus
sudah di status itu — mis. re-klik tidak sengaja). `setAdminDecision()`
sudah punya variabel `$fromStatus` sebelum status diubah; guard ini
cuma pengecekan tambahan sebelum baris dispatch, tidak mengubah logic
lain di method itu.

Listener baru `app/Listeners/SendIsolationReadyWebhook.php` (sync, pola
`SendInvoiceUpdatedWebhook`):
```php
payload = [
  'event_id' => uuid,
  'event' => 'isolation.ready_for_execution',
  'occurred_at' => now()->toIso8601String(),
  'case_number' => $case->case_number,
  'cid' => $customer->cid,
  'customer_name' => $customer->full_name,
  'package_name' => $service->internetPackage?->name,
  'outstanding_amount' => Money::decimalString($case->current_outstanding_amount),
]
```
Idempotency key: `"isolation:{$case->id}:ready:{$sequence}"` lewat
`WebhookOutbox::nextActivationNumber($case->customer_id, $eventName)`
(reuse, generik by design — WAJIB `$case->customer_id`, bukan `null`:
`nextActivationNumber()`/`maxDeliveredActivationNumber()` dikunci per
`customer_id`, `null` bikin guard "superseded delivery" di
`SendWebhookOutboxJob` tidak pernah berfungsi untuk baris ini, gap
builder-check #4).

```php
WebhookOutbox::create([
    'destination' => 'web_a',
    'event' => $eventName, // 'isolation.ready_for_execution'
    'event_id' => $eventId,
    'idempotency_key' => $idempotencyKey,
    'customer_id' => $case->customer_id,
    'payload' => $data,
    'status' => 'pending',
]);
```

Dispatch `SendWebhookOutboxJob` `afterCommit()` dibungkus try/catch —
pola sama persis dua listener existing.

`SendWebhookOutboxJob`:
- `configError()` tambah cabang `web_a` (url wajib `https://`, secret wajib
  isi — sama pola `website_b`/`customer_portal`).
- `match ($row->destination)` tambah `'web_a' => $this->sendHttpJson($row)`
  — TAPI `sendHttpJson()` saat ini hardcode `config('webhooks.website_b')`.
  Diubah jadi terima `$row->destination` dan ambil config dinamis
  (`config("webhooks.{$row->destination}")`) — aman karena skema HMAC
  (`X-Whusnet-Signature: t=...,v1=...`) sama untuk kedua tujuan, cuma beda
  url/secret.

### 2. Inbound — endpoint eksekusi

`config/webhooks.php` tambah token tulis terpisah:
```php
'web_a_write_token' => env('WEB_A_WRITE_TOKEN'),
```

Middleware baru `app/Http/Middleware/EnsureWebAWriteToken.php` — copy
persis pola `EnsureNetworkAssignmentWriteToken` (bearer + `hash_equals`),
alias `web_a.write` didaftar di `bootstrap/app.php` bareng alias lain.

Rate limiter baru di `AppServiceProvider`: `RateLimiter::for('web-a-isolation-execute', ...)`
pola sama `network-assignment-write`.

Route baru di `routes/api.php`, grup `Route::prefix('v1')` yang sudah ada:
```php
Route::middleware(['web_a.write', 'throttle:web-a-isolation-execute'])
    ->post('/isolation-cases/execute', [IsolationExecutionController::class, 'store'])
    ->name('api.isolation-cases.execute');
```

Controller baru `app/Http/Controllers/Api/IsolationExecutionController.php`:
- Validasi `case_number` (`required|string|exists:isolation_cases,case_number`
  — 422 kalau tidak ketemu, konsisten gaya validasi existing, BUKAN 404
  custom seperti `NetworkAssignmentException` karena di sini tidak ada
  kebutuhan membedakan 404 dari pola alert beruntun seperti itu).
- Load case + `customer` + `customerService`.
- Panggil `IsolationExecutionService::suspendFromWebA($case)`.
- Service melempar exception khusus (`IsolationExecutionException`) untuk
  2 kasus gagal (belum siap / status customer tidak valid) — controller
  tangkap, balas 422 dengan message dari exception.
- Sukses → `response()->json(['success' => true, 'case_number' => ..., 'status' => 'suspended', 'already_suspended' => bool])`.

Service baru `app/Services/IsolationExecutionService.php` — mengikuti
PERSIS struktur dua-fase `toggleSuspend()` pasca fix 2026-11-20 (lock di
dalam transaksi, tracking kasus di LUAR transaksi + try/catch), bukan
draf awal yang builder-check tandai bermasalah (gap #1 & #2):

```php
public function suspendFromWebA(Customer $customer): array
{
    if ($customer->status === 'suspended') {
        return ['already_suspended' => true]; // no-op, idempotent
    }

    $gateError = null;

    DB::transaction(function () use ($customer, &$gateError) {
        if (! $customer->customerService) {
            $gateError = 'Pelanggan ini tidak punya layanan aktif.';

            return;
        }

        // lockForUpdate() di SINI, di dalam transaksi yang sama dengan
        // $customer->update() — persis pola toggleSuspend() pasca fix
        // 2026-11-20. Mencegah dua eksekusi hampir-bersamaan (retry Web A
        // vs NOC klik manual) lolos gerbang berdua.
        $activeCase = IsolationCase::where('service_id', $customer->customerService->id)
            ->where('active_slot', 1)
            ->lockForUpdate()
            ->first();

        if (! $activeCase || ! in_array($activeCase->status, IsolationCaseStatus::readyForIsolationGroup(), true)) {
            $gateError = 'Kasus ini tidak lagi siap dieksekusi.';

            return;
        }

        if ($customer->status !== 'active') {
            $gateError = 'Layanan pelanggan ini tidak dalam status yang bisa diisolir.';

            return;
        }

        $oldStatus = $customer->status;
        $customer->update(['status' => 'suspended']);
        $customer->customerService->update(['service_status' => 'isolir']);

        AuditLog::create([
            'user_id' => null,
            'module' => 'customers',
            'action' => 'isolir',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'suspended', 'note' => 'Isolir dieksekusi otomatis via Web A'],
            'ip_address' => request()->ip(),
            'user_agent' => 'Web A (API)',
            'created_at' => now(),
        ]);

        CustomerStatusLog::create([
            'customer_id' => $customer->id,
            'from_status' => $oldStatus,
            'to_status' => 'suspended',
            'changed_by' => null,
            'note' => 'Isolir dieksekusi otomatis via Web A',
        ]);
    });

    if ($gateError) {
        throw new IsolationExecutionException($gateError);
    }

    // DI LUAR transaksi, try/catch — pola sama toggleSuspend(): kegagalan
    // tracking kasus (mis. NumberSequenceService gagal) TIDAK BOLEH
    // merollback isolir yang sudah commit di atas.
    try {
        app(IsolationCaseService::class)->markIsolationSucceeded(
            $customer->customerService,
            actor: null,
            actorType: 'external_web_a',
        );
    } catch (\Throwable $e) {
        report($e);
        app(IsolationCaseService::class)->markTrackingFailed(
            $customer->customerService,
            actor: null,
            wasIsolating: true,
            error: $e,
        );
    }

    return ['already_suspended' => false];
}
```

Catatan soal `createOrSyncCase()` (gap builder-check #3): dipanggil
internal oleh `markIsolationSucceeded()`. Untuk jalur Web A, kasusnya
SELALU sudah ada (gerbang di atas mensyaratkan `activeCase` ketemu dan
berstatus `readyForIsolationGroup()`), jadi `createOrSyncCase()` masuk
cabang "kasus sudah ada → sync invoice saja" (baris 135 file ini), bukan
cabang pembuatan kasus baru yang butuh `$actor`/`$actorType`. Cabang
pembuatan baru CUMA relevan untuk isolir di luar jalur Monitoring Isolir
(tidak pernah berlaku untuk Web A, karena Web A cuma dipanggil setelah
`ready_for_isolation` ada). Tidak perlu thread `$actorType` ke
`createOrSyncCase()`/`markManual()` — scope ditahan sesuai kebutuhan
nyata, bukan didesain untuk kemungkinan yang tidak akan terjadi di jalur
ini.

`CustomerController::toggleSuspend()` TIDAK diubah strukturnya secara
drastis. Cabang isolir-nya (baris ~397-440, DB::transaction dengan
lockForUpdate + gate + update + AuditLog + CustomerStatusLog) tetap di
tempatnya — TIDAK diekstrak paksa ke `IsolationExecutionService`, supaya
risiko regresi ke jalur NOC yang sudah di-fix hari ini (2026-11-20)
minimal. `IsolationExecutionService::suspendFromWebA()` adalah
IMPLEMENTASI PARALEL yang sengaja menduplikasi struktur (lock → gate →
update → audit, lalu tracking di luar transaksi) bukan dipanggil
keduanya dari satu tempat — alasan: `toggleSuspend()` juga menangani
arah aktivasi-kembali & permission-check berbasis session yang tidak
relevan untuk Web A, memaksakan satu method generik untuk dua pemanggil
beda-kebutuhan ini lebih berisiko mengundang bug lintas-jalur daripada
duplikasi ~30 baris yang terstruktur sama. Kalau nanti ada jalur ketiga
yang butuh "suspend murni" ini, BARU diekstrak jadi shared helper —
bukan sekarang, sesuai "jangan abstraksi sebelum dibutuhkan" (CLAUDE.md).
Arah aktivasi-kembali (`activate`) di `toggleSuspend()` TIDAK disentuh
sama sekali.

### 3. Perubahan signature kecil (non-breaking)

`IsolationCaseService::markIsolationSucceeded(CustomerService $service, ?User $actor = null, string $actorType = 'system'): IsolationCase`
— tambah parameter `$actorType` dengan default `'system'` (perilaku lama:
`$actor` ada → `'user'`, cuma dipakai eksplisit utk override jadi
`'external_web_a'`). `recordEvent()` disesuaikan: `actor_type` dihitung
`$actor ? 'user' : $actorType` bukan literal `$actor ? 'user' : 'system'`.
Semua pemanggil lama (`toggleSuspend()` via actor user asli) tidak
terdampak — tetap `'user'`.

`IsolationCaseService::markTrackingFailed(CustomerService $service, ?User $actor, bool $wasIsolating, \Throwable $error, string $actorType = 'system'): void`
— signature `$actor` saat ini `User $actor` (non-nullable, dipanggil
`toggleSuspend()` dengan `auth()->user()`); diubah jadi `?User $actor`
+ `$actorType` yang sama seperti di atas, supaya `suspendFromWebA()`
bisa memanggilnya dengan `actor: null, actorType: 'external_web_a'` saat
tracking gagal. Pemanggil lama tidak terdampak (tetap kirim `User`
asli → `'user'`).

## Yang diserahkan ke builder

- Nama kolom/struktur test (mengikuti pola file test existing untuk webhook
  outbound: `tests/Feature/InvoiceRecalculateEmitsPortalOutboxTest.php` jadi
  acuan gaya test outbound; test inbound ikuti pola test
  `NetworkAssignmentController`).
- Nama method privat internal di `IsolationExecutionController`/`Service`
  selama konsisten dengan nama di dokumen ini untuk field publik
  (payload keys, nama endpoint, nama config key) — itu KONTRAK dengan Web A,
  tidak boleh berubah tanpa approval user.
- `.env.example` entries: `WEBHOOK_WEB_A_URL`, `WEBHOOK_WEB_A_SECRET`,
  `WEB_A_WRITE_TOKEN`.
