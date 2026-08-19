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

### Fase 1 — Webhook Pemasangan

- `webhook_endpoints`, `webhook_outbox`.
- **`App\Events\InstallationActivated`** — event baru, belum ada di repo.
- Dispatch event itu dari `CustomerInstallationController::storePemasangan()`, sebagai
  pernyataan **terakhir sebelum `DB::commit()` (`:752`)**. Satu baris; ini satu-satunya
  sentuhan ke controller.
- `InstallationWebhookPresenter` — **muat ulang relasi**, jangan pakai
  `customerDevice`/`customerTechnicalDetail` yang sudah ter-cache di `$customer`.
- Listener `QueueInstallationWebhook` → INSERT outbox di dalam transaksi; job pengirim
  `afterCommit`.
- Job pengirim: HMAC atas payload tersimpan, backoff 1m/5m/30m/2j/6j maks 8x,
  penonaktifan endpoint setelah gagal beruntun.
- `idempotency_key` = `installation:{customer_installations.id}`.
- Halaman admin untuk mendaftarkan endpoint (Owner saja), secret ditampilkan sekali.
- Perintah purge terjadwal: `delivered` 90 hari, `failed` **tidak** ikut.

**Tidak menyentuh `CustomerInstallationController` sama sekali.** Kalau muncul dorongan
mengubahnya, berhenti — berarti listener tidak terpanggil dan penyebabnya harus dicari
dulu, bukan ditambal dengan pemanggilan langsung di controller.

---

## Runbook: menyambungkan pemasangan ke Telegram lewat gateway

Pertanyaan praktisnya: apa saja yang harus ada supaya data pemasangan (nama, cabang
POP, desa, paket, SN, ODP) muncul di grup Telegram.

### Yang penting diketahui lebih dulu: API 1 tidak butuh Fase 0

Webhook pemasangan **murni keluar**. Ia tidak menerima request dari siapa pun, jadi
tidak butuh `routes/api.php`, tidak butuh envelope JSON, tidak butuh CORS, tidak butuh
rate limiter. Seluruh Fase 0 adalah prasyarat **API 2 saja**.

Artinya bagian ini bisa dikerjakan dan dipakai duluan, terpisah dari portal.

### Rantai lengkapnya

```
Teknisi tekan AKTIVASI di Laporan Pemasangan (step 6, kirim form step 5)
      │
      ▼  (di dalam transaksi, setelah semua penulisan selesai)
InstallationActivated  ──►  Listener  ──►  INSERT webhook_outbox
      │                                          │
      ▼  COMMIT                                  │
Worker Horizon (queue 'webhook')  ◄──────────────┘
      │  POST + X-Whusnet-Signature
      ▼
Gateway (OpenClaw)
      ├─ verifikasi HMAC
      ├─ dedupe event_id
      ├─ rangkai kalimat (LLM atau template)
      └─ kirim ke Telegram Bot API
                │
                ▼
          Grup Telegram
```

Batasnya tegas: **Whusnet berhenti di panah "POST".** Tidak ada kode Telegram di repo
ini untuk jalur pemasangan.

### Sisi Whusnet — yang harus dibangun

| Komponen | Catatan |
|---|---|
| Migrasi `webhook_endpoints`, `webhook_outbox` | Lihat `database-schema.md` |
| `App\Events\InstallationActivated` | **Baru** — belum ada di repo |
| Dispatch di `storePemasangan()` | Satu baris, **terakhir sebelum `DB::commit()` (`:752`)** |
| `App\Services\Webhooks\InstallationWebhookPresenter` | Satu sumber isi payload; **muat ulang relasi** |
| `App\Listeners\QueueInstallationWebhook` | Merakit payload + INSERT outbox |
| `App\Jobs\DeliverWebhook` | HMAC, backoff, catat hasil ke baris outbox |
| Halaman admin `webhook_endpoints` | Owner saja; secret tampil sekali |
| `php artisan webhook:test {endpoint}` | Kirim payload contoh tanpa pemasangan sungguhan |
| `php artisan webhook:purge` | `delivered` 90 hari; `failed` tidak ikut |
| Supervisor Horizon queue `webhook` | Ikuti preseden queue `kwitansi` di `config/horizon.php:228` |

**Pendaftaran listener pakai auto-discovery.** Repo tidak punya `EventServiceProvider`
dan `app/Listeners` belum ada — Laravel 11+ menemukan listener dari type-hint
`handle()`. Buat direktorinya, type-hint eventnya, selesai. Kalau nanti terasa perlu
mendaftarkan manual, itu sinyal ada yang salah dengan namespace-nya, bukan alasan
menambah provider.

Listener **hanya merakit payload dan menulis baris outbox** — tidak memanggil HTTP apa
pun. Ia berjalan di dalam transaksi; HTTP di sana menggantung transaksi selama gateway
lambat, dan rollback berarti gateway sudah diberi tahu soal pemasangan yang batal.

**Presenter harus membaca relasi yang segar.** Objek `$customer` di `storePemasangan()`
sudah lama dimuat; `customerDevice` dan `customerTechnicalDetail` yang sempat ter-cache
akan mengembalikan nilai **sebelum** Aktivasi. Gejalanya jahat karena tidak selalu
muncul: pemasangan pertama (relasi belum pernah dimuat) terlihat benar, koreksi SN
justru mengirim nilai lama.

### Sisi Telegram — yang disiapkan sekali

1. Buat bot lewat **@BotFather** → dapat bot token.
2. Masukkan bot ke grup operasional yang dituju.
3. Ambil `chat_id` grup: kirim satu pesan ke grup, lalu panggil
   `https://api.telegram.org/bot<token>/getUpdates` dan baca `message.chat.id`
   (grup bernilai negatif).
4. **Bot token dan `chat_id` disimpan di gateway, bukan di Whusnet.**

Perhatikan: `TELEGRAM_BOT_TOKEN` yang sudah ada di repo dipakai notifikasi task
teknisi (`TaskService`, `SendTaskNotificationJob`). Itu jalur lain dengan pembaca lain.
Pakai bot terpisah, atau minimal grup terpisah — mencampurnya membuat pencabutan satu
token mematikan dua alur yang tidak berhubungan.

### Sisi gateway — kontrak minimum

```
POST /webhooks/whusnet
Header: X-Whusnet-Signature: t=<unix>,v1=<hex>
```

1. Hitung ulang HMAC-SHA256 atas `"{t}.{raw body}"` dengan secret endpoint,
   bandingkan `hash_equals`. Tolak kalau tidak cocok.
2. Tolak kalau `|now - t| > 300` detik.
3. Sudah pernah lihat `event_id` ini? Balas 200, jangan proses ulang.
4. Ada `idempotency_key` yang sama dari event sebelumnya? Ini **koreksi**, bukan
   pemasangan baru.
5. Rangkai pesan, kirim ke Telegram.
6. **Balas 2xx secepatnya**, sebelum memanggil Telegram. Kalau Telegram lambat dan
   gateway menahan respons, Whusnet menganggapnya gagal lalu mengulang — dan pesan
   yang sebenarnya terkirim jadi dobel.

Bentuk pesan yang cukup untuk grup operasional:

```
🔧 Pemasangan Diaktivasi
C1X4ARQ000631 · POP Jetis · Joresan
Paket : Home 20 Mbps
SN    : ZTEGC1234567
ODP   : ODP-JTS-04 port 3
```

Judulnya sengaja bukan "Pemasangan Selesai". Saat Aktivasi ditekan, pemasangan masih
`in_progress` — Laporan Speedtest belum diisi dan verifikasi admin belum jalan. Kalimat
"selesai" di grup operasional akan dibaca sebagai layanan sudah beres, lalu jadi
pertanyaan saat ternyata belum.

Kalau `idempotency_key` yang sama datang lagi, gateway sebaiknya menandainya sebagai
koreksi (mis. "🔁 Data pemasangan diperbarui") daripada mengirim pesan identik kedua.

Nama pelanggan sengaja tidak ikut. Payload memuatnya karena provisioning butuh, tapi
grup Telegram menyimpan riwayat dan anggota yang ditambahkan belakangan bisa
membacanya ke belakang. Kalau nama memang diperlukan, itu keputusan sadar yang diambil
di gateway.

### Urutan menyalakan

1. Bangun komponen sisi Whusnet, jalankan test.
2. Daftarkan endpoint **percobaan** dulu — layanan penampung seperti `webhook.site` —
   lalu `php artisan webhook:test`. Ini membuktikan payload, signature, dan retry
   bekerja tanpa menunggu gateway siap dan tanpa pemasangan sungguhan.
3. Bangun gateway, arahkan ke sana, ulangi `webhook:test`.
4. Arahkan ke grup Telegram **percobaan** dulu, bukan grup operasional. Aktivasi bisa
   ditekan berulang untuk satu pemasangan; berapa banyak pesan yang benar-benar muncul
   baru ketahuan setelah dipakai teknisi sungguhan.
5. Pindah ke grup operasional setelah jumlah pesannya terbukti masuk akal.
6. Hapus endpoint percobaan. Endpoint penampung publik menyimpan payload ber-PII.

### Kalau pesan tidak muncul

Urutan periksa, dari yang paling sering:

| Gejala | Periksa |
|---|---|
| Tidak ada baris outbox sama sekali | Listener tidak terpanggil — cek namespace & type-hint |
| Baris ada, `status=pending` menumpuk | Worker queue `webhook` tidak jalan |
| `status=failed`, `response_status=401/403` | Signature ditolak gateway — cek secret, dan pastikan gateway memverifikasi **raw body** |
| `delivered` tapi grup sunyi | Masalah di gateway/Telegram, bukan di Whusnet |
| Pesan dobel | Gateway tidak dedupe `event_id`, atau membalas 2xx setelah memanggil Telegram |

Halaman riwayat pengiriman adalah tempat menjawab semua ini. Grup chat yang sunyi
bukan bukti tidak ada pemasangan.

---

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

---

## Rencana test

Semua `tests/Feature`, PHPUnit, atribut modern (`#[Test]`, `#[DataProvider]`), nama
berdasarkan gejala — mengikuti pola `FopTaskCancelCascadeAuthTest`,
`CustomerVerificationRejectFopSyncTest`.

### Webhook pemasangan

| Nama | Menguji |
|---|---|
| `WebhookActivationPayloadTest` | Tombol Aktivasi menerbitkan `installation.activated` dengan keenam data terisi; nominal berupa string |
| `WebhookSnOdpFallbackChainTest` | SN terbaca dari `customer_devices`, ODP dari `customer_technical_details` — pembagian yang memang dihasilkan `storePemasangan()` |
| `WebhookPresenterReadsFreshRelationsTest` | Koreksi SN lalu Aktivasi lagi → payload membawa SN **baru**, bukan yang ter-cache |
| `WebhookNoEventWhenGateFailsTest` | Aktivasi ditolak (foto/material kurang) → nol baris outbox |
| `WebhookSignatureVerificationTest` | Signature valid; body diubah → invalid; `t` lewat 5 menit → ditolak |
| `WebhookSecretIsRecoverableTest` | Secret tersimpan bisa dibaca kembali untuk menandatangani — menangkap `Hash::make()` yang salah pasang |
| `WebhookRetryUsesStoredPayloadTest` | Percobaan ke-2..8 memakai `event_id` identik dan payload tersimpan, bukan dirakit ulang |
| `WebhookRepeatedActivationSharesIdempotencyKeyTest` | Aktivasi ditekan tiga kali (koreksi + revisi) → tiga `event_id` berbeda, satu `idempotency_key` sama |
| `WebhookEndpointDisabledAfterFailuresTest` | `attempts` sampai 8 → `failed`; `consecutive_failures` naik; endpoint mati di ambang; reset saat sukses |
| `WebhookFailedRowsSurvivePurgeTest` | Purge 90 hari menghapus `delivered`, menyisakan `failed` |
| `WebhookNotSentBeforeCommitTest` | Rollback transaksi `storePemasangan()` → tidak ada pengiriman |
| `WebhookPopScopeTest` | Endpoint ber-`pop_id` tidak menerima pelanggan cabang lain |

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
repo ini, kebocoran di sini tidak berhenti di batas aplikasi. Karena itu endpoint
didaftarkan manual, wajib HTTPS, punya `pop_id`, dan log-nya di-purge. Payload portal
sebaliknya tidak boleh memuat PII sama sekali — dua kebijakan berbeda di satu tabel
outbox, jadi review kode harus memeriksa keluarga event mana yang sedang disentuh.

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
