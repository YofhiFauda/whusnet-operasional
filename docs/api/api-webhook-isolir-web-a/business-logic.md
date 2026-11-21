# Business Logic — Webhook Isolir Web A

## Arah keluar: `isolation.ready_for_execution`

**Trigger.** `IsolationCaseService::setAdminDecision()`, keputusan `EXECUTE`,
**hanya** saat kasus beneran pindah status dari selain `READY_FOR_ISOLATION`
ke `READY_FOR_ISOLATION` (guard `$fromStatus !== IsolationCaseStatus::READY_FOR_ISOLATION`).
Re-klik "Eksekusi" saat kasus sudah di status itu **tidak** mengirim webhook
lagi — Web A tidak boleh dapat webhook dobel untuk kasus yang sama.

**Payload:**

| Field | Sumber |
|---|---|
| `event_id` | UUID baru per kiriman |
| `event` | `"isolation.ready_for_execution"` (literal) |
| `occurred_at` | ISO 8601, saat listener jalan |
| `case_number` | `IsolationCase::case_number` |
| `cid` | `Customer::cid` |
| `customer_name` | `Customer::full_name` |
| `package_name` | `CustomerService::internetPackage->name` |
| `outstanding_amount` | `IsolationCase::current_outstanding_amount`, format desimal string via `Money::decimalString()` |

**Pengiriman.** Lewat `WebhookOutbox` (destination `web_a`) +
`SendWebhookOutboxJob` — pola sama `website_b`: `X-Whusnet-Signature:
t={timestamp},v1={hmac}` (HMAC-SHA256 atas `"{timestamp}.{body}"`,
`config('webhooks.web_a.secret')`), retry 8x dengan backoff
1m/5m/30m/2h/6h (bawaan job, tidak diubah).

## Arah masuk: `POST /api/v1/isolation-cases/execute`

**Auth.** Header `Authorization: Bearer <token>`, dicocokkan
`hash_equals()` terhadap `config('webhooks.web_a_write_token')`
(`WEB_A_WRITE_TOKEN` di `.env`) — middleware `web_a.write`. Token
salah/kosong → `401`. Rate limit `web-a-isolation-execute`, 20/menit per
token+IP.

**Body:** `{"case_number": "TFOP-..."}` — wajib, harus ada di
`isolation_cases.case_number`.

**Response:**

| Status | Kondisi |
|---|---|
| `200` | Sukses. `{"success": true, "case_number": ..., "status": "suspended", "already_suspended": bool}`. `already_suspended = true` kalau pelanggan sudah `suspended` sebelum request ini (idempotent no-op, tidak ada perubahan). |
| `401` | Token salah atau tidak ada. |
| `422` | `case_number` tidak ditemukan (validasi Laravel bawaan), atau kasus ditemukan tapi tidak lagi di kelompok `readyForIsolationGroup()`, atau pelanggan tidak punya `customerService`. |

## Idempotensi

`IsolationExecutionService::suspendFromWebA()` **directional** ("pastikan
status pelanggan `suspended`"), bukan toggle. Dua kemungkinan double-call:

- Retry jaringan dari Web A (request sama dikirim dua kali).
- NOC sudah isolir manual duluan lewat `/customers` sebelum Web A sampai.

Keduanya aman: pelanggan yang sudah `suspended` membuat
`suspendFromWebA()` langsung balas `already_suspended: true` tanpa
menyentuh apa pun — **tidak pernah** membalik status ke aktif, karena arah
sebaliknya (restorasi) bukan tanggung jawab endpoint ini sama sekali.

## Atribusi aktor

Isolir yang dieksekusi Web A tercatat beda dari isolir manual NOC maupun
dari kasus yang eksekusinya gagal tracking:

- `isolation_events.actor_type = 'external_web_a'`, `actor_id = null` —
  lewat `markIsolationSucceeded($service, null, 'external_web_a')`.
- `audit_logs.user_id = null`, `user_agent = 'Web A (API)'` — bukan user
  login, bukan `'system'` generik (keputusan user saat brainstorming:
  Web A punya identitas sendiri di riwayat, bukan disamakan dengan NOC
  atau proses internal).

## Non-goals (sengaja tidak dibangun)

- **Tidak ada endpoint restorasi/aktivasi-kembali untuk Web A.** Kalau
  pelanggan perlu diaktifkan kembali, itu tetap lewat `/customers` (NOC),
  bukan lewat kontrak Web A — arah ini satu arah (isolir saja).
- **Tidak ada tabel konsumen webhook dinamis.** Destinasi `web_a`
  hardcoded di `config/webhooks.php`, sama seperti `website_b` — bukan
  didaftarkan via UI/DB.
