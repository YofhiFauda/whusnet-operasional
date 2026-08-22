# Rencana Implementasi — API 1: Webhook Pemasangan

## Fase 1 — Webhook Pemasangan — **SUDAH DIIMPLEMENTASIKAN** (2026-08-20)

File nyata: `app/Events/InstallationActivated.php`,
`app/Listeners/SendInstallationActivatedWebhooks.php`,
`app/Services/Webhooks/InstallationWebhookPresenter.php`,
`app/Jobs/SendWebhookOutboxJob.php`, `app/Models/WebhookOutbox.php`,
`app/Console/Commands/PruneWebhookOutbox.php`,
`database/migrations/2026_08_20_100000_create_webhook_outbox_table.php`,
`config/webhooks.php`. Test: `tests/Feature/InstallationActivatedWebhookTest.php`,
`tests/Feature/SendWebhookOutboxJobTest.php`.

Dependensi: **tidak ada yang baru**. Berjalan di atas paket yang sudah terpasang
(Horizon untuk antrean). Tidak ada `composer require`.

**Penyimpangan dari rencana awal, ditemukan saat implementasi:**

- Dispatch job **tidak** memakai `->afterCommit()` langsung dari `PendingDispatch` —
  dibungkus `DB::afterCommit(fn () => ...try/catch...)` di
  `SendInstallationActivatedWebhooks::dispatchAfterCommitSafely()`. Alasan: di queue
  `sync` (dipaksa `phpunit.xml`, bisa juga jadi konfigurasi environment tertentu),
  `->afterCommit()` mengeksekusi job **inline** saat `DB::commit()` dipanggil —
  kalau job melempar, exception itu naik balik ke `storePemasangan()`'s try/catch
  dan memicu `DB::rollBack()` padahal commit sudah sukses. Kegagalan kirim webhook
  tidak boleh pernah menggagalkan alur yang memicunya.
- Job class **tidak** mendeklarasikan properti `public bool $afterCommit` — trait
  `Queueable` sudah mendeklarasikan properti itu dengan tipe berbeda; menimpanya
  bikin fatal error "incompatible property definition" (persis kelas masalah
  `$queue` yang diperingatkan komentar `MatchPaymentReceipt`, ternyata berlaku juga
  ke `$afterCommit`).
- `WebhookOutbox::nextActivationNumber()`/`maxDeliveredActivationNumber()`
  menghitung nomor aktivasi di PHP (bukan raw SQL `SUBSTRING_INDEX`/`CAST AS
  UNSIGNED` MySQL-only yang diusulkan draf awal) — supaya portable ke sqlite yang
  dipakai test.
- `login_id` **di-omit** dari payload fase ini (`customer_portal_accounts` di
  `api-portal-pelanggan` belum ada) — payload cuma bawa `cid`. Ditambahkan sebagai perubahan
  payload versi baru begitu `api-portal-pelanggan` jadi.
- `perangkat.olt` = gabungan `olt_number`/`olt_slot`/`olt_port` dipisah `/` (skip
  bagian kosong), fallback `customers.olt_code` — bukan `olt_number` polos seperti
  draf awal.
- **Ditambahkan (2026-08-20, setelah testing manual lewat Cloudflare Tunnel)**:
  `SendWebhookOutboxJob` menolak kirim ke `website_b` kalau `url` bukan `https://`
  atau `secret` kosong — baris langsung `failed`, TANPA masuk siklus retry 8x. Salah
  konfigurasi bukan kegagalan jaringan sesaat yang bisa sembuh lewat retry.

**Cakupan implementasi:**

- `config/webhooks.php` + entri `.env` (`WEBHOOK_WEBSITE_B_URL`,
  `WEBHOOK_WEBSITE_B_SECRET`, `TELEGRAM_EXTERNAL_BOT_TOKEN`,
  `TELEGRAM_EXTERNAL_CHAT_ID`) — bukan tabel `webhook_endpoints` maupun halaman
  admin.
- `webhook_outbox`, kolom `destination` (string tetap) menggantikan FK endpoint.
- `App\Events\InstallationActivated` — event baru, membawa `Customer`, memakai
  `SerializesModels`.
- Satu baris dispatch di `CustomerInstallationController::storePemasangan()`, di
  dalam transaksi, sebelum `DB::commit()` (`:751`).
- `InstallationWebhookPresenter` + listener → satu baris outbox **per tujuan** (2
  pemanggilan eksplisit: `website_b` dan `telegram_external`) di dalam transaksi;
  job pengirim `afterCommit`.
- Dua transport: `http_json` (Website B — HMAC, JSON) dan `telegram` (Telegram
  Eksternal — bot token & chat id dari `config('webhooks.telegram_external')`).
- Presenter punya dua renderer di atas satu sumber data: `toJson()` dan
  `toTelegramText()`.
- Aturan khusus `telegram`: lewati kalau teks identik dengan kiriman terakhir yang
  berhasil (tandai `skipped`), dan cantumkan nomor aktivasi saat data berubah.
- Job pengirim: backoff 1m/5m/30m/2j/6j maks 8x. Gagal beruntun dicatat (cache/log)
  dan dialertkan manual ke Owner.
- `idempotency_key` = `installation:{customer_id}:activation:{n}`.
- Perintah purge terjadwal: `delivered` 90 hari, `failed` **tidak** ikut.

**Ini satu-satunya fase yang mengubah kode alur pemasangan.** Perubahannya sengaja
dibuat sekecil mungkin — satu dispatch, tanpa menyentuh validasi, penyimpanan,
gerbang kelengkapan, maupun perilaku redirect `activated=1`.

**`InstallationActivated` adalah satu-satunya event yang didengarkan webhook.**
Jangan memasang listener pada event instalasi lain yang sudah ada di `app/Events/`.

**Telegram Internal tidak disentuh.** Enam pemanggilan `TelegramBotService` inline
tetap apa adanya.

---

## Fase 6 — Callback Hasil Provisioning (arah balik) — belum resmi, menunggu jawaban

**Tidak dikerjakan sebelum dua pertanyaan di `keputusan.md` §7 dijawab pemilik
produk.** Kontrak lengkap ada di `business-logic.md` bagian "Callback Hasil
Provisioning"; skema di `database-schema.md` §3.

- `installation_provisioning_callbacks`.
- Token bearer callback hardcode di `.env` (`WEBHOOK_WEBSITE_B_CALLBACK_TOKEN`,
  terpisah dari secret HMAC arah keluar) — bukan tabel token.
- `POST /api/v1/installations/provisioning-callback` — berlaku hanya untuk tujuan
  `http_json` (Website B).
- Validasi `event_id`+`idempotency_key` terhadap `webhook_outbox` — tak ditemukan
  → 404. Duplikat identik → 200 diam-diam. Percobaan menimpa hasil final → 409
  `rejected`.
- Mirror ringkas ke task (bentuknya menunggu jawaban).
- **Prasyarat di luar fase ini:** butuh `routes/api.php` (belum ada — API 1 murni
  outbound, belum pernah butuh route masuk).

---

## Rencana test

Semua `tests/Feature`, PHPUnit, atribut modern (`#[Test]`, `#[DataProvider]`), nama
berdasarkan gejala.

| Nama | Menguji | Status |
|---|---|---|
| `InstallationActivatedWebhookTest` | Payload lengkap, fan-out 2 tujuan, event cuma di `storePemasangan()`, idempotency naik, skip Telegram, rollback → nol baris | **Ada** |
| `SendWebhookOutboxJobTest` | HMAC valid, kirim Telegram, retry, guard HTTPS, superseded, max attempts, purge | **Ada** |

Test dari rancangan awal yang **tidak** dibuat karena fitur yang diuji sudah dicabut
sejak rev. 8: `WebhookEndpointDisabledAfterFailuresTest` dan `WebhookPopScopeTest`
(auto-disable & `pop_id` per endpoint — tidak ada lagi tabel `webhook_endpoints`).

---

## Risiko

**Payload webhook pemasangan keluar organisasi.** Berbeda dari seluruh fitur lain di
repo ini, kebocoran di sini tidak berhenti di batas aplikasi. Karena itu URL tujuan
wajib HTTPS dan hardcode, dan log-nya di-purge.

**Konsumen tunggal per transport — sadar, bukan lupa.** Tidak ada lagi `pop_id` per
tujuan atau tabel untuk menampung konsumen kedua. Kalau ada permintaan "Website C
juga butuh" atau "cabang X butuh tujuan beda", itu bukan tambahan baris data seperti
rancangan lama — perlu keputusan sadar.

**Dua sumber SN/ODP yang tidak sinkron.** Rantai fallback menutupi gejalanya, bukan
sebabnya.
