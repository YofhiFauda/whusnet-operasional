# Webhook Isolir — Web A

**Status: resmi, sudah diimplementasikan (ADHOC-307, 2026-11-20).** Spec:
`docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md`. Plan:
`docs/superpowers/plans/2026-11-20-webhook-isolir-web-a.md`.

## Ringkasan

Bidirectional, dua arah berbeda:

| | |
|---|---|
| Arah keluar | Whusnet → Web A. Webhook `isolation.ready_for_execution`, dikirim begitu Admin menekan "Eksekusi" (`IsolationCaseService::setAdminDecision()`) dan kasus beneran pindah ke status `READY_FOR_ISOLATION`. |
| Arah masuk | Web A → Whusnet. `POST /api/v1/isolation-cases/execute` — Web A mengeksekusi isolir berdasarkan `case_number` dari webhook keluar. |
| Trigger keluar | `IsolationCaseReadyForExecution` event → listener `SendIsolationReadyWebhook` → `WebhookOutbox` (destination `web_a`) → `SendWebhookOutboxJob` (HMAC-signed, retry pola sama destination lain). |
| Payload keluar | `event_id`, `event`, `occurred_at`, `case_number`, `cid`, `customer_name`, `package_name`, `outstanding_amount`. |
| Auth masuk | Token bearer statis `WEB_A_WRITE_TOKEN` (middleware `web_a.write` → `EnsureWebAWriteToken`), `hash_equals()`, bukan DB lookup — pola sama `network_assignment.write`. |
| Rate limit masuk | `web-a-isolation-execute`, 20/menit per token+IP — pola sama `network-assignment-write`. |
| Eksekusi | `IsolationExecutionService::suspendFromWebA(IsolationCase)` — BERDIRI SENDIRI dari `CustomerController::toggleSuspend()` (tidak saling panggil), directional ("pastikan suspended", bukan toggle), **idempotent**: kalau pelanggan sudah `suspended`, balas 200 no-op, tidak pernah membalik ke aktif. Menerima `IsolationCase` (bukan `Customer`) supaya bisa menolak `case_number` basi (dibatalkan/digantikan kasus baru) — gerbang mengunci baris kasus DAN baris pelanggan yang sama persis di dalam satu transaksi (hasil review akhir, 2026-11-20). |
| Atribusi aktor | `actor_type = 'external_web_a'`, `actor_id = null` di `isolation_events` (bukan user login, bukan `'system'` generik) — lihat `markIsolationSucceeded()` yang sekarang menerima `actorType` opsional. |

## Kenapa berdiri sendiri dari `toggleSuspend()`

Jalur Web A tidak punya session user, tidak punya arah aktivasi-kembali (cuma
isolir, tidak pernah dipakai buat memulihkan), dan atribusi aktornya beda
kelas. Menyatukannya ke `toggleSuspend()` akan mencampur dua model otorisasi
(session user vs token bearer eksternal) dan dua arah aksi (toggle vs
directional) yang seharusnya terpisah. `IsolationExecutionService` **meniru**
struktur dua-fase `toggleSuspend()` (lock+gate di dalam transaksi database,
tracking kasus di luar transaksi via `markIsolationSucceeded()`) supaya race
condition yang sudah di-fix di jalur NOC (2026-11-20) tidak terulang di sini
— tapi tidak memanggil atau dipanggil oleh `toggleSuspend()`.

## Berkas

| Berkas | Isi |
|---|---|
| `app/Events/IsolationCaseReadyForExecution.php` | Event domain, dispatch dari `setAdminDecision()` |
| `app/Listeners/SendIsolationReadyWebhook.php` | Bentuk payload, tulis `WebhookOutbox`, dispatch job setelah commit |
| `app/Http/Middleware/EnsureWebAWriteToken.php` | Token bearer masuk |
| `app/Http/Controllers/Api/IsolationExecutionController.php` | Endpoint masuk |
| `app/Services/IsolationExecutionService.php` | Eksekusi isolir, idempotent, lock |
| `app/Exceptions/IsolationExecutionException.php` | Kasus tidak ketemu/tidak siap/layanan tidak valid → 422 |
| `config/webhooks.php` | `web_a.url`/`web_a.secret` (keluar), `web_a_write_token` (masuk) |

## Catatan implementasi (ruling dari eksekusi)

- `markTrackingFailed()` **tidak** dipanggil dari `suspendFromWebA()` kalau
  `markIsolationSucceeded()` gagal setelah isolir sukses — method itu bagian
  dari pekerjaan lain yang belum di-commit saat fitur ini dibangun. Kegagalan
  tracking cuma di-log (`report()` + `Log::error()`); aksi isolir yang sudah
  commit tetap sah. Kalau `markTrackingFailed()` sudah ada di masa depan,
  pertimbangkan menyambungkannya di sini juga.
