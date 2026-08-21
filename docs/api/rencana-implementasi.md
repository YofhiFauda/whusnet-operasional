# Rencana Implementasi — API Eksternal

## Dependensi: tidak ada yang baru

Revisi ini **menghapus gerbang persetujuan Sanctum.** Karena token portal memakai
tabel sendiri (`customer_portal_tokens`, §6.6.2) dan bukan
`personal_access_tokens` polymorphic, `laravel/sanctum` tidak jadi dibutuhkan.

Seluruh rancangan berjalan di atas paket yang sudah terpasang: Horizon untuk antrean,
`endroid/qr-code` (`composer.json:10`) untuk kartu ber-PIN kalau jalur klaim dikerjakan
bersama modul QR. Tidak ada `composer require` sama sekali.

Yang berubah dari revisi pertama dokumen ini dan alasannya, ringkas: menumpang tabel
token bersama staf ditolak karena mencampur dua populasi kredensial; token bearer
30 hari tanpa rotasi ditolak karena token bocor hidup sebulan penuh tanpa sinyal.

---

## Fase

### Fase 0 — Pondasi API

Tanpa ini, fase mana pun akan membangun pola sendiri-sendiri lalu menyimpang.

- Buat `routes/api.php`, daftarkan di `bootstrap/app.php` (`withRouting(api: ...)`).
- Isi `withExceptions()` supaya request `/api/*` mendapat JSON — saat ini kosong
  (`bootstrap/app.php:20-22`), jadi galat balik sebagai HTML.
- `config/cors.php`: whitelist origin portal **spesifik**, hanya untuk grup
  `/api/customer-portal/*`. Belum ada berkas ini di repo.
- Daftarkan rate limiter pertama di repo: `customer-portal-auth`,
  `customer-portal-auth-ip`, `customer-portal-api`.
- Buat `App\Http\Resources` (direktori belum ada) + base Resource yang menetapkan
  envelope `{data, meta}` dan **serialisasi nominal sebagai string desimal**.

Selesai kalau: satu endpoint `GET /api/v1/ping` mengembalikan JSON, 404 di bawah
`/api/*` juga JSON, dan preflight CORS dari origin portal lolos sementara origin lain
ditolak.

### Fase 1 — Webhook Pemasangan — **SUDAH DIIMPLEMENTASIKAN** (2026-08-20)

File nyata: `app/Events/InstallationActivated.php`,
`app/Listeners/SendInstallationActivatedWebhooks.php`,
`app/Services/Webhooks/InstallationWebhookPresenter.php`,
`app/Jobs/SendWebhookOutboxJob.php`, `app/Models/WebhookOutbox.php`,
`app/Console/Commands/PruneWebhookOutbox.php`,
`database/migrations/2026_08_20_100000_create_webhook_outbox_table.php`,
`config/webhooks.php`. Test: `tests/Feature/InstallationActivatedWebhookTest.php`,
`tests/Feature/SendWebhookOutboxJobTest.php`.

**Penyimpangan dari rencana di bawah, ditemukan saat implementasi:**
- Dispatch job **tidak** memakai `->afterCommit()` langsung dari
  `PendingDispatch` — dibungkus `DB::afterCommit(fn () => ...try/catch...)` di
  `SendInstallationActivatedWebhooks::dispatchAfterCommitSafely()`. Alasan:
  di queue `sync` (dipaksa `phpunit.xml`, bisa juga jadi konfigurasi
  environment tertentu), `->afterCommit()` mengeksekusi job **inline** saat
  `DB::commit()` dipanggil — kalau job melempar, exception itu naik balik ke
  `storePemasangan()`'s try/catch dan memicu `DB::rollBack()` padahal commit
  sudah sukses. Kegagalan kirim webhook tidak boleh pernah menggagalkan alur
  yang memicunya (itu inti dari pola outbox), jadi dispatch dibungkus
  try/catch eksplisit di titik ini, bukan diserahkan ke Laravel apa adanya.
- Job class **tidak** mendeklarasikan properti `public bool $afterCommit`
  — trait `Queueable` sudah mendeklarasikan properti itu dengan tipe
  berbeda; menimpanya bikin fatal error "incompatible property definition"
  (persis kelas masalah `$queue` yang diperingatkan komentar
  `MatchPaymentReceipt`, ternyata berlaku juga ke `$afterCommit`).
- `WebhookOutbox::nextActivationNumber()`/`maxDeliveredActivationNumber()`
  menghitung nomor aktivasi di PHP (bukan raw SQL `SUBSTRING_INDEX`/`CAST AS
  UNSIGNED` MySQL-only yang diusulkan draf awal) — supaya portable ke sqlite
  yang dipakai test (CLAUDE.md: DB default repo ini sqlite).
- `login_id` **di-omit** dari payload fase ini (`customer_portal_accounts`
  API 2 belum ada) — payload cuma bawa `cid`. Ditambahkan sebagai perubahan
  payload versi baru begitu API 2 jadi.
- `perangkat.olt` = gabungan `olt_number`/`olt_slot`/`olt_port` dipisah `/`
  (skip bagian kosong), fallback `customers.olt_code` — bukan `olt_number`
  polos seperti draf awal (keputusan user saat implementasi).
- **Ditambahkan (2026-08-20, setelah testing manual lewat Cloudflare Tunnel)**:
  `SendWebhookOutboxJob` menolak kirim ke `website_b` kalau `url` bukan
  `https://` atau `secret` kosong — baris langsung `failed`, TANPA masuk
  siklus retry 8x. Salah konfigurasi bukan kegagalan jaringan sesaat yang
  bisa sembuh lewat retry; menghabiskan 8 percobaan (sampai 6 jam) untuk
  sesuatu yang pasti gagal lagi cuma menunda orang sadar `.env` salah isi.

---

- `config/webhooks.php` + entri `.env` (`WEBHOOK_WEBSITE_B_URL`,
  `WEBHOOK_WEBSITE_B_SECRET`, `TELEGRAM_EXTERNAL_BOT_TOKEN`,
  `TELEGRAM_EXTERNAL_CHAT_ID`) — **bukan** tabel `webhook_endpoints` maupun halaman
  admin (dicabut rev. 8, `keputusan.md`).
- `webhook_outbox`, kolom `destination` (string tetap) menggantikan FK endpoint.
- **`App\Events\InstallationActivated`** — event baru, membawa `Customer` dan memakai
  `SerializesModels`, mengikuti bentuk event instalasi lain di `app/Events/`.
- **Satu baris dispatch di `CustomerInstallationController::storePemasangan()`**, di
  dalam transaksi, sebelum `DB::commit()` (`:751`).
- `InstallationWebhookPresenter` + listener → satu baris outbox **per tujuan** (2
  pemanggilan eksplisit: `website_b` dan `telegram_external`) di dalam transaksi; job
  pengirim `afterCommit`.
- **Dua transport**: `http_json` (Website B — HMAC, JSON) dan `telegram`
  (Telegram Eksternal — bot token & chat id dari `config('webhooks.telegram_external')`,
  **bukan** dari `config('services.telegram.*')`).
- Presenter punya dua renderer di atas satu sumber data: `toJson()` dan
  `toTelegramText()`.
- Aturan khusus `telegram`: lewati kalau teks identik dengan kiriman terakhir yang
  berhasil (tandai `skipped`), dan cantumkan nomor aktivasi saat data berubah.
- Job pengirim: backoff 1m/5m/30m/2j/6j maks 8x. Gagal beruntun dicatat (cache/log) dan
  dialertkan manual ke Owner — **tidak ada endpoint untuk dinonaktifkan**, tujuan
  tetap satu. Sukses `http_json` = 2xx; sukses `telegram` = `ok: true` di body.
- `idempotency_key` = `installation:{customer_id}:activation:{n}`.
- Perintah purge terjadwal: `delivered` 90 hari, `failed` **tidak** ikut.

**Ini satu-satunya fase yang mengubah kode alur pemasangan.** Perubahannya sengaja
dibuat sekecil mungkin — satu dispatch, tanpa menyentuh validasi, penyimpanan, gerbang
kelengkapan, maupun perilaku redirect `activated=1`. Kalau muncul dorongan mengubah
hal lain di `storePemasangan()`, berhenti: itu di luar cakupan webhook.

**`InstallationActivated` adalah satu-satunya event yang didengarkan webhook.** Jangan
memasang listener pada event instalasi lain yang sudah ada di `app/Events/` — mereka
melayani dashboard realtime FOP, disiarkan dari titik yang tidak punya gerbang
kelengkapan data, dan artinya bisa berubah tanpa ada yang ingat webhook ikut
mendengarkan. Satu event, satu pemicu, satu listener.

**Telegram Internal tidak disentuh.** Enam pemanggilan `TelegramBotService` inline
(`CustomerInstallationController`, `CustomerSurveyController`, `CustomerController`,
`CustomerVerificationController`, `SendTaskNotificationJob`, `CheckCountdownStatus`)
tetap apa adanya. Jangan memindahkannya ke outbox "sekalian" — itu task tersendiri
yang menyentuh empat modul.

**Catatan status kode:** wizard dua-submit (`storePemasangan`/`storeSpeedtest`) beserta
`InstallationSpeedtestActivationGateTest` masih **belum ter-commit** di branch `dev`
saat dokumen ini ditulis. Rujukan barisnya bisa bergeser; verifikasi ulang sebelum
implementasi.

### Fase 2 — Kredensial & auth portal

- `customer_portal_accounts`, `customer_portal_tokens`.
- `Customer::portalAccount()` (`HasOne`).
- Penerbitan `login_id` untuk pelanggan yang sudah ada + pencetakan kartu ber-PIN.
- `POST /auth/claim`, `/auth/login`, `/auth/refresh`, `/auth/logout`,
  `/auth/logout-all`, `PUT /me/password`, `GET /me`.
- Middleware `X-Portal-Client` + client secret.
- Pencabutan token via `CustomerObserver` saat pelanggan `terminated`.

**Detail yang masih perlu dipastikan sebelum menulis migrasi:** `{prefix_pop}` di
`login_id` merujuk `pops.registration_prefix` atau `pops.cid_prefix`? Keduanya ada
(`app/Models/Pop.php:18-35`) dan §6.6.2 tidak menyebut kolomnya. Contoh di sana
(`PNG-RQ000631`) berpasangan dengan `customer_code` berawalan RQ, yang menyiratkan
prefix registrasi — tapi ini harus dikonfirmasi, bukan ditebak: salah pilih berarti
seluruh kartu pelanggan tercetak dengan login ID yang tidak cocok.

### Fase 3 — Tagihan, pembayaran, kwitansi

- `GET /me/invoices`, `/me/invoices/{invoice_number}`, `/me/payments`,
  `/me/payments/{payment_number}/receipt`, `/me/balance`.
- Resource dengan daftar putih kolom; `overpay_amount` dan `billing_period` ikut
  keluar, `reject_reason`/`note`/`proof_file` tidak.
- Trait/base `ScopedToAuthenticatedCustomer`.
- Kwitansi: `ReceiptPresenter` **dipangkas** — buang `penerima`, `penagih`, `catatan`;
  tambahkan pendamping mentah `dibayar_raw` (string desimal) dan `tanggal_bayar_iso`.
- Event `invoice.updated` ke outbox dari `Invoice::recalculateFromPayments()`, state
  penuh, tanpa PII.

### Fase 4 — Ticketing

- Relasi `Customer::tickets()`.
- `GET /me/tickets`, `/me/tickets/{ticket_number}`.
- Presenter status pelanggan bertumpu `Ticket::resolveStatus()` —
  **bukan** `Ticket::statusLabel()`, yang membocorkan nama tim internal.

### Fase 5 — Portal mengonsumsi API

Di luar repo ini (§6.6.1, §6.6.8). Fase 0-4 tidak menunggu portal selesai dibangun.

### Fase 6 — Callback Hasil Provisioning (arah balik) — belum resmi, menunggu jawaban

**Tidak dikerjakan sebelum dua pertanyaan di `keputusan.md` §8 (#6, #7) dijawab
pemilik produk.** Kontrak lengkap ada di `business-logic.md` bagian "Callback Hasil
Provisioning"; skema di `database-schema.md` §5. Dicatat di sini sebagai daftar kerja
supaya begitu dijawab, fase ini tinggal dieksekusi tanpa dirancang ulang.

- `installation_provisioning_callbacks`.
- Token bearer callback hardcode di `.env` (`WEBHOOK_WEBSITE_B_CALLBACK_TOKEN`,
  terpisah dari secret HMAC arah keluar) — bukan tabel token, ikut rev. 8.
- `POST /api/v1/installations/provisioning-callback` — berlaku hanya untuk tujuan
  `http_json` (Website B).
- Validasi `event_id`+`idempotency_key` terhadap `webhook_outbox` — tak ditemukan
  → 404. Duplikat identik → 200 diam-diam. Percobaan menimpa hasil final → 409
  `rejected`.
- Mirror ringkas ke task (bentuknya menunggu jawaban #6).

---

## Rencana test

Semua `tests/Feature`, PHPUnit, atribut modern (`#[Test]`, `#[DataProvider]`), nama
berdasarkan gejala — mengikuti pola `FopTaskCancelCascadeAuthTest`,
`CustomerVerificationRejectFopSyncTest`.

### Webhook pemasangan

| Nama | Menguji |
|---|---|
| `WebhookInstallationActivatedPayloadTest` | Tekan Aktivasi → satu event terbit dengan keenam data terisi (nama, POP, desa, paket, SN, ODP); nominal berupa string |
| `WebhookNotFiredOnInstallationStartTest` | Tombol Mulai Pemasangan **tidak** menerbitkan event — kalau ia ikut menembak, SN/ODP terkirim kosong |
| `WebhookNotFiredByAdminModalTest` | `store()` legacy (modal admin) tidak memicu webhook; hanya wizard teknisi |
| `WebhookNotFiredOnSpeedtestCompletionTest` | Menyelesaikan pemasangan lewat `storeSpeedtest()` tidak menghasilkan baris outbox apa pun — `installation.activated` satu-satunya event |
| `WebhookFanOutTwoTransportsTest` | Satu Aktivasi → dua baris outbox (`http_json` + `telegram`), data identik, dirender berbeda |
| `WebhookTelegramUsesExternalCredentialsTest` | Transport `telegram` memakai `bot_token`/`chat_id` dari `config('webhooks.telegram_external')`, **bukan** `config('services.telegram.*')` — penjaga langsung atas pemisahan internal/eksternal |
| `WebhookTelegramSkipsUnchangedTest` | Aktivasi ditekan lagi tanpa perubahan data → baris `skipped`, tidak ada pesan terkirim; Website B **tetap** menerima |
| `WebhookInternalTelegramUntouchedTest` | Enam pemanggilan `TelegramBotService` inline tetap berjalan seperti sebelumnya dan tidak menghasilkan baris outbox |
| `WebhookTelegramExcludesSensitiveFieldsTest` | Teks Telegram Eksternal tidak memuat nomor HP, alamat, NIK, koordinat, maupun kredensial perangkat |
| `WebhookSnOdpFallbackChainTest` | SN/ODP terbaca dari `customer_technical_details` saat `customer_devices.odp` kosong — kondisi normal setelah pemasangan |
| `WebhookSignatureVerificationTest` | Signature valid; body diubah → invalid; `t` lewat 5 menit → ditolak |
| `WebhookSecretIsRecoverableTest` | Secret tersimpan bisa dibaca kembali untuk menandatangani — menangkap `Hash::make()` yang salah pasang |
| `WebhookRetryUsesStoredPayloadTest` | Percobaan ke-2..8 memakai `event_id` identik dan payload tersimpan, bukan dirakit ulang |
| `WebhookRepeatedActivationUpsertTest` | Aktivasi ditekan dua kali (ralat SN, lalu alur revisi) → dua event, `event_id` beda, `idempotency_key` naik `activation:1` → `activation:2` |
| `WebhookMaxAttemptsMarksFailedTest` | `attempts` sampai 8 → baris outbox `failed`; tidak dicoba lagi setelahnya |
| `WebhookFailedRowsSurvivePurgeTest` | Purge 90 hari menghapus `delivered`, menyisakan `failed` |
| `WebhookNotSentBeforeCommitTest` | Rollback transaksi `storePemasangan()` (`:661`-`:751`) → tidak ada pengiriman, tidak ada baris outbox tertinggal |

`WebhookEndpointDisabledAfterFailuresTest` dan `WebhookPopScopeTest` (rev. ≤7) **dicabut
dari rencana test** — keduanya menguji perilaku `webhook_endpoints` (auto-disable,
`pop_id` per endpoint) yang tidak lagi ada sejak rev. 8. Kalau routing per cabang
dibangun kembali nanti, test itu dirancang ulang saat itu, bukan dipertahankan sebagai
test yang menguji sesuatu yang tidak ada.

### Portal — kredensial

| Nama | Menguji |
|---|---|
| `PortalLoginIdUniquenessTest` | Dua pelanggan beda POP dengan `customer_code` sama menghasilkan `login_id` berbeda, dan login masing-masing menerbitkan token untuk pelanggan yang benar |
| `PortalLoginIpSweepThrottleTest` | 21 percobaan dari satu IP ke 21 login ID berbeda → 429. Ini yang gagal kalau limiter cuma keyed per-login_id |
| `PortalLockoutSurvivesCacheFlushTest` | `Cache::flush()` setelah 5 kegagalan tidak membuka kunci — `locked_until` di DB yang menahan |
| `PortalUnclaimedAccountLooksIdenticalTest` | Pelanggan tanpa akun portal → 401 dengan pesan **sama persis** seperti password salah |
| `PortalClaimRequiresPinTest` | Klaim butuh PIN benar; akun yang sudah diklaim ditolak |
| `PortalRefreshTokenReuseRevokesChainTest` | Refresh dipakai dua kali → seluruh rantai turunan dicabut |
| `PortalPasswordChangeRevokesOtherTokensTest` | Token lain mati, sesi pemanggil hidup; `current_password` wajib |
| `PortalPasswordNeverInAuditLogTest` | Ganti password tidak menulis hash ke `audit_logs` — penjaga langsung atas alasan kenapa kredensial tidak ditaruh di `customers` |

### Portal — kebocoran lintas pelanggan

`PortalCrossCustomerLeakTest`, satu berkas, `#[DataProvider]` atas seluruh endpoint
detail. Pelanggan A memanggil `INV-`/`PAY-`/`TKT-` milik B → **404**, bukan 403, bukan
200. Ditambah: `customer_id` yang disuntikkan lewat query string, body, atau header
diabaikan total.

Test ini yang paling mudah rusak tanpa disadari saat controller baru ditambahkan, jadi
provider-nya dibuat mudah ditambahi satu baris per endpoint baru.

### Portal — isi data

| Nama | Menguji |
|---|---|
| `PortalInvoiceFieldWhitelistTest` | `created_by`, `old_*`, `pop_id`, `id` tidak muncul |
| `PortalPaymentHidesStaffAndInternalNotesTest` | `received_by`, `collected_by`, `note`, `reject_reason`, `proof_file` tidak muncul |
| `PortalReceiptHidesStaffNamesTest` | Kwitansi API tidak memuat `penerima`, `penagih`, `catatan` — menangkap "kembalikan presenter apa adanya" |
| `PortalOverpayVisibleTest` | `overpay_amount` dan `billing_period` keluar di daftar pembayaran |
| `PortalRejectedPaymentVisibleWithoutReasonTest` | Pembayaran ditolak tetap tampil, `status_valid=false`, alasannya tidak |
| `PortalMoneyIsDecimalStringTest` | Semua nominal berupa string `"150000.00"`, bukan float |
| `PortalTicketPostFopResolvedStatusTest` | Tiket `handler=fop` dengan FopTask selesai tampil **Selesai**, bukan "Sedang Ditangani" selamanya |
| `PortalTicketOrphanStatusTest` | Tiket orphan tampil "Sedang Ditangani", bukan "Terputus" |
| `PortalTicketHidesInternalNotesTest` | `catatan_teknis`, `handler` mentah, nomor TFOP/TASK, nama aktor tidak keluar |
| `PortalCollectorPaymentAppearsTest` | Pembayaran yang dicatat kolektor langsung terlihat di portal tanpa job apa pun |
| `InvoiceRecalculateEmitsOutboxTest` | Bayar, bayar sebagian, **dan tolak pembayaran** ketiganya menerbitkan `invoice.updated` dengan state penuh tanpa PII |

---

## Risiko

**Kredensial di sistem yang sama dengan operasional.** Pemisahan yang melindungi bukan
pemisahan aplikasi (portal memang terpisah), melainkan pemisahan tabel dan token.
Kalau di tengah implementasi muncul dorongan "sekalian saja taruh di `customers`,
lebih gampang", baca ulang bagian audit log di `database-schema.md` — jalur
kebocorannya sudah ditelusuri sampai nomor barisnya.

**Payload webhook pemasangan keluar organisasi.** Berbeda dari seluruh fitur lain di
repo ini, kebocoran di sini tidak berhenti di batas aplikasi. Karena itu URL tujuan
wajib HTTPS dan hardcode (`.env`, sejak rev. 8 tidak lagi lewat form admin), dan
log-nya di-purge. Payload portal sebaliknya tidak boleh memuat PII sama sekali — dua
kebijakan berbeda di satu tabel outbox, jadi review kode harus memeriksa keluarga
event mana yang sedang disentuh.

**Konsumen tunggal per transport — sadar, bukan lupa.** Sejak rev. 8 tidak ada lagi
`pop_id` per tujuan atau tabel untuk menampung konsumen kedua. Kalau ada permintaan
"Website C juga butuh" atau "cabang X butuh tujuan beda", itu bukan tambahan baris
data seperti rancangan lama — perlu keputusan sadar (tambah entri config + kode
eksplisit, atau bangun kembali tabel dinamis kalau jumlah konsumen mulai banyak).
Jangan diam-diam menambal dengan `if` bercabang di listener sampai jumlahnya lebih
dari dua atau tiga.

**Rate limit dan CORS pertama di repo.** Belum pernah ada `RateLimiter::for` maupun
`config/cors.php` di codebase ini, jadi tidak ada pola yang bisa dicontoh dan tidak
ada test yang menjaganya. Angkanya ditulis di `README.md` modul ini supaya tidak jadi
angka ajaib di satu berkas.

**Dua sumber SN/ODP yang tidak sinkron.** Rantai fallback menutupi gejalanya, bukan
sebabnya. Kalau kelak `customer_devices` dirapikan supaya ikut menyimpan `odp`, webhook
tetap benar — tapi rantai itu harus ditinjau ulang, bukan dibiarkan sebagai sisa yang
tidak ada yang berani sentuh.

**Dua dokumen untuk satu portal.** Modul ini dan QR §6.6 membahas API yang sama dari
sudut berbeda. Setiap perubahan keputusan di salah satunya harus tercermin di yang
lain, atau implementer akan mengikuti yang kebetulan ia buka duluan. Kalau beban
sinkronisasi itu mulai terasa, gabungkan — jangan biarkan keduanya menyimpang diam-diam.
