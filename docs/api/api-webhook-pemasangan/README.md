# API 1 — Webhook Pemasangan

**Status: sudah diimplementasikan** (2026-08-20). Lihat "File nyata" di
`rencana-implementasi.md`.

## Ringkasan

Whusnet **mendorong** (push) satu event, `installation.activated`, ke dua tujuan
tetap — Website B (`http_json`, HMAC-signed) dan Telegram Eksternal (`telegram`,
bot API) — setiap kali teknisi menekan tombol **"Aktivasi Laporan Speedtest"**.

| | |
|---|---|
| Arah | **Keluar** — Whusnet yang memulai koneksi (client), Website B yang menerima (server) |
| Trigger | Tombol "Aktivasi Laporan Speedtest", `CustomerInstallationController::storePemasangan()` |
| Event | `installation.activated` — satu-satunya, tidak ada yang lain |
| Tujuan | `website_b` (`http_json`) + `telegram_external` (`telegram`), hardcode di `config/webhooks.php` |
| Auth | HMAC-SHA256 per-request (`X-Whusnet-Signature`); Telegram: bot token |
| Retry | 1m → 5m → 30m → 2j → 6j, maksimal 8 percobaan |

## Berkas di folder ini

| Berkas | Isi |
|---|---|
| `business-logic.md` | Kontrak lengkap: kenapa titik pemicu ini, bentuk payload, aturan idempotensi, keamanan |
| `database-schema.md` | `webhook_outbox` (tabel utama, dipakai bareng `api-portal-pelanggan`) + config tujuan |
| `flowchart.md` | Diagram alur: tombol Aktivasi → outbox → pengiriman → retry |
| `keputusan.md` | Kenapa webhook bukan REST, kenapa hardcode bukan tabel dinamis, alternatif yang ditolak |
| `rencana-implementasi.md` | Status implementasi, penyimpangan dari rencana awal, daftar test |
| `panduan-konsumen.md` | **Untuk diserahkan ke Website B** — satu-satunya berkas di seluruh modul API yang keluar organisasi |

## Fase belum resmi (dirancang, belum dikerjakan)

Callback hasil provisioning (Website B lapor balik ke Whusnet) — lihat
`business-logic.md` bagian "Callback Hasil Provisioning" dan `keputusan.md` §4
"arah balik". Menunggu jawaban pemilik produk atas 2 pertanyaan di `keputusan.md`
§8.
