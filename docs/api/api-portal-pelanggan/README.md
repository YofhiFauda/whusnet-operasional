# API 2 — Portal Pelanggan

**Status: rancangan, belum ada kode.**

## Ringkasan

Aplikasi Portal Pelanggan — **terpisah, domain berbeda, tanpa kredensial DB
operasional** — mengonsumsi REST API `/api/customer-portal/*` atas nama satu
pelanggan yang login. Whusnet juga mendorong satu webhook (`invoice.updated`) ke
portal supaya ia tahu ada pembayaran baru tanpa harus di-refresh manual.

| | |
|---|---|
| Arah | **Masuk** — portal yang menarik (REST), Whusnet cuma dorong 1 event kabar |
| Prefix | `/api/customer-portal/*` (§6.6.4, kontrak yang dipegang tim portal — jangan diseragamkan) |
| Identitas | `customer_portal_accounts.login_id` = `{prefix_pop}-{customer_code}` |
| Auth | Client secret portal **+** bearer token pelanggan (dua lapis) |
| Webhook pelengkap | `invoice.updated`, dipicu `Invoice::recalculateFromPayments()`, tanpa PII |

Mengikuti kontrak yang sudah ditetapkan di
`docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6 (baris 804-1060), sebagian
**dikonfirmasi pemilik produk**. Berkas di folder ini merinci dan melengkapinya,
tidak menggantikannya. Kalau ada beda, §6.6 yang menang.

## Berkas di folder ini

| Berkas | Isi |
|---|---|
| `business-logic.md` | Autentikasi, 4 kebutuhan fitur, kepemilikan data, **kontrak request/response tiap endpoint** (§"Kontrak endpoint") |
| `database-schema.md` | `customer_portal_accounts`, `customer_portal_tokens` — plus rujukan ke `webhook_outbox` (didefinisikan di `../api-webhook-pemasangan/database-schema.md`, dipakai bareng) |
| `flowchart.md` | Diagram klaim akun, login, refresh token, pengambilan data, status tiket, kwitansi |
| `keputusan.md` | Alternatif yang ditolak (login `cid`, password di `customers`, Sanctum, dll), temuan review |
| `rencana-implementasi.md` | Fase 2-5, rencana test |
