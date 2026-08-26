# Rencana Implementasi — API 2: Portal Pelanggan

## Dependensi: tidak ada yang baru

Karena token portal memakai tabel sendiri (`customer_portal_tokens`, §6.6.2) dan
bukan `personal_access_tokens` polymorphic, `laravel/sanctum` tidak jadi dibutuhkan.

Seluruh rancangan berjalan di atas paket yang sudah terpasang: Horizon untuk antrean,
`endroid/qr-code` (`composer.json:10`) untuk kartu ber-PIN kalau jalur klaim
dikerjakan bersama modul QR. Tidak ada `composer require` sama sekali.

## Fase 0 — Pondasi API (dipakai bareng semua API masuk) — **SELESAI (2026-08-24)**

`routes/api.php` + `withRouting(api: ...)` + `withExceptions()` JSON untuk `/api/*`
**sudah ada duluan** lewat `api-pop-distribusi/` — Fase 0 modul ini cuma menambah yang
belum ada:

- Grup route baru `Route::prefix('customer-portal')` di `routes/api.php` (file yang
  sama, bukan file terpisah — `withRouting` cuma nunjuk satu file api). Grup `v1` milik
  `api-pop-distribusi` tidak disentuh.
- `config/cors.php` (baru — repo belum pernah punya file ini): whitelist origin portal
  **spesifik** dari env `PORTAL_ALLOWED_ORIGIN`, `paths` discope ke
  `api/customer-portal/*` saja — route `api-pop-distribusi` (`api/v1/*`) tidak ikut kena.
- 3 rate limiter di `AppServiceProvider::boot()`: `customer-portal-api` (120/menit,
  attached ke grup route), `customer-portal-auth` (5/15menit per IP+login_id),
  `customer-portal-auth-ip` (30/15menit per IP — angka dikonfirmasi user, gak ada di
  dokumen manapun). Dua limiter auth didaftarkan sekarang, belum diattach ke route
  nyata sampai Fase 2.
- `App\Http\Resources\ApiResource` — base class baru, `with()` nyuntik `meta.generated_at`
  (envelope `{data, meta}` gratis lewat mekanisme `JsonResource` bawaan Laravel), method
  `money()` reuse `Money::decimalString()` (baru, di `app/Support/Money.php`) buat
  serialisasi nominal sebagai string desimal — bukan `number_format(float,2)`.

**Koreksi klaim dokumen ini yang sempat basi:** bagian Risiko di bawah sempat bilang
"`RateLimiter::for` belum pernah ada di repo, gak ada pola yang bisa dicontoh" — itu
keliru, 3 definisi udah ada duluan buat `api-pop-distribusi`
(`AppServiceProvider.php:169-183`). Yang genuinely baru cuma `config/cors.php`.

Selesai kalau: endpoint `GET /api/customer-portal/ping` mengembalikan JSON, 404 di bawah
`/api/customer-portal/*` juga JSON, dan preflight CORS dari origin portal lolos sementara
origin lain (dan route `api-pop-distribusi`) ditolak — **dibuktikan test**, lihat di bawah.
`/ping` dipertahankan permanen sebagai health-check (bukan dibuang setelah Fase 2),
tanpa proteksi token — datanya kosong, risiko disclosure nol.

## Fase 2 — Kredensial & auth portal — **SELESAI SEBAGIAN (2026-08-25)**

**Selesai & full functional:**
- `customer_portal_accounts`, `customer_portal_tokens` (migrasi + model,
  tanpa `RecordsAuditLogs` — lihat database-schema.md §1).
- `Customer::portalAccount()` (`HasOne`), `Customer::tickets()` (`HasMany`,
  prasyarat Fase 3/4, ditambah sekalian).
- `POST /auth/login`, `/auth/refresh` (rotasi + deteksi reuse → cabut semua
  token), `/auth/logout`, `/auth/logout-all`, `PUT /me/password`, `GET /me`.
- Middleware `portal_client` (`X-Portal-Client` + client secret,
  `config('webhooks.portal_client_secret')`) dan `portal_token` (bearer
  token per-pelanggan, DB lookup — middleware pertama di repo yang begitu).
- Pencabutan token via `CustomerObserver` saat pelanggan `terminated`
  (`WorkflowTransition::TERMINATED->value`).
- Command `customers:backfill-portal-login-id` (penerbitan `login_id` massal)
  dan `customers:portal-set-password-for-testing` (DEV ONLY, smoke-test
  manual selama `/auth/claim` masih stub).
- 14 file test, `tests/Feature/Api/CustomerPortal/`.

**`POST /auth/claim` — STUB (501), menunggu modul QR/PIN.** Verifikasi PIN
butuh infrastruktur dari `docs/plan/qr-code/rancangan-qr-pelanggan-final.md`
§7.6 (kartu fisik dicetak bareng token QR) — modul itu nol kode DAN nol
keputusan operasional (threat-model ONT dalam/luar rumah, logistik cetak
belum diputuskan pemilik produk). Keputusan user 2026-08-24: tahan endpoint
ini sebagai stub, jangan bangun mekanisme PIN paralel yang menyimpang dari
desain "PIN dibangkitkan bersama token QR". Rate limiter `customer-portal-auth`
+ `-auth-ip` tetap terpasang di route-nya.

**Pencetakan kartu ber-PIN — di luar scope, ikut modul QR** (belum dikerjakan).

**Dikonfirmasi 2026-08-24:** `{prefix_pop}` di `login_id` = `pops.registration_prefix`
(bukan `cid_prefix`) — lihat `keputusan.md` §3 poin 1. `cid` sempat diusulkan ulang,
ditolak lagi dengan alasan sama seperti §1: baru terbit saat pelanggan aktif, cuma
index biasa (bukan unique).

**Keputusan tambahan dikonfirmasi 2026-08-25** (angka yang tidak eksplisit di
dokumen manapun sebelumnya):
- Durasi lockout akun (`locked_until`, beda dari rate limiter request-level)
  = 15 menit, mengikuti pola lockout PIN §6.5.4.
- `GET /me` — alamat ditampilkan generic (desa/kecamatan), bukan alamat
  detail/koordinat.
- `POST /auth/logout` disamakan perilakunya dengan `/auth/logout-all` (cabut
  semua token pelanggan itu) — access token tidak punya rantai ke refresh
  pasangannya tanpa client mengirim `refresh_token` tambahan.
- Daftar password umum di `StrongPortalPassword` — placeholder ~30 entri,
  boleh ditinjau ulang kapan saja tanpa mengubah cara kerja rule.

**Penyimpangan terdokumentasi:** validasi password "tidak boleh mengandung
tanggal lahir" TIDAK diimplementasikan — `Customer` tidak punya kolom
tanggal lahir sama sekali (dikonfirmasi grep nihil). `StrongPortalPassword`
cuma cek `login_id` + nomor HP (primary + alternative).

## Fase 3 — Tagihan, pembayaran, kwitansi — **SELESAI (2026-08-25)**

**Nol migrasi** — semua tabel yang dibutuhkan (`invoices`, `payments`,
`customer_balance_mutations`, `webhook_outbox`) sudah ada sejak sebelum Fase 3,
dikonfirmasi eksplisit `database-schema.md`.

- `GET /me/invoices`, `/me/invoices/{invoice_number}`, `/me/payments`,
  `/me/payments/{payment_number}/receipt`, `/me/balance` — semua **selesai & full
  functional**.
- Resource (`app/Http/Resources/CustomerPortal/`): `InvoiceResource`,
  `InvoiceDetailResource` (+ payments menempel), `PaymentResource`,
  `PaymentReceiptResource`, `CustomerBalanceMutationResource` — daftar putih kolom
  persis §2; `overpay_amount`, `billing_period`, dan `invoice_number` (dikonfirmasi
  user 2026-08-25) ikut keluar, `reject_reason`/`note`/`proof_file`/`bank_name`/
  `account_number` (dikonfirmasi user, default exclude) tidak.
- Trait `ScopedToAuthenticatedCustomer` (`app/Http/Controllers/CustomerPortal/Concerns/`)
  — satu-satunya titik resolve `customer_id` dari token, query selalu dibuka
  terfilter dulu baru dicari nomor dokumennya (gagal aman, bukan bind-lalu-cek —
  anti-pola yang ditemukan di `PaymentController::receipt` staf, sengaja tidak
  dicontoh).
- Kwitansi: `ReceiptPresenter` **dipangkas** — buang `penerima`, `penagih`, `catatan`,
  `dicetak`; tambah pendamping mentah `dibayar_raw` (string desimal) dan
  `tanggal_bayar_iso`.
- `GET /me/balance` — saldo (`CustomerBalanceService::balance()`) + riwayat mutasi
  ringkas (dikonfirmasi user 2026-08-25), paginasi 10/halaman, tanpa `pop_id`/
  `created_by` (nama staf).
- Listener `SendInvoiceUpdatedWebhook` (auto-discovery, `Invoice.php` **tidak
  disentuh** — reuse `InvoiceStatusUpdated` yang sudah dispatch di
  `recalculateFromPayments()`) → `webhook_outbox` destination `customer_portal`,
  state penuh, tanpa PII. `SendWebhookOutboxJob` diperluas — skema 3 header
  terpisah (`X-Whusnet-Event-Id`/`-Timestamp`/`-Signature`, BEDA dari format
  gabungan `website_b`), sesuai §6.6.6 persis.
- Config baru: `webhooks.customer_portal` (`PORTAL_WEBHOOK_URL`/`_SECRET`) — arah
  OUTBOUND, beda dari `portal_client_secret` (INBOUND) Fase 2.
- ~10 file test baru (`tests/Feature/Api/CustomerPortal/` + 2 file webhook di
  `tests/Feature/`).

## Fase 4 — Ticketing — **SELESAI (2026-08-25)**

- Relasi `Customer::tickets()` (ditambahkan Fase 2, dipakai di sini).
- `GET /me/tickets`, `/me/tickets/{ticket_number}` — selesai & full functional.
- `TicketPortalStatusPresenter` (`app/Support/CustomerPortal/`) — presenter status
  pelanggan bertumpu `Ticket::resolveStatus()`, **bukan** `Ticket::statusLabel()`
  yang membocorkan nama tim internal. **Urutan pengecekan kritis**: `handler`
  dicek SEBELUM `status` — begitu `handler=FOP`, kolom `tickets.status` beku dan
  tidak boleh dibaca. Dibuktikan test regresi eksplisit (tiket pasca-FOP yang
  `status` kolomnya masih `open` tapi `FopTask` sudah `selesai` → tetap `selesai`).
- `/me/tickets/{ticket_number}` — "detail tiket + riwayat" di daftar endpoint
  diinterpretasikan sebagai bentuk sama dengan item `index()` (bukan riwayat
  mentah `ticket_histories`, yang eksplisit haram §4 karena memuat nama pegawai).
  Tidak ada `TicketDetailResource` terpisah.
- Kode status portal (`value` di JSON) — `diterima`/`sedang_ditangani`/
  `selesai`/`dibatalkan` — slug baru, dokumen cuma kasih label Indonesia.
- `/me/tickets` tanpa filter (dokumen gak sebut filter apa pun, beda dari
  invoices/payments).
- 3 file test baru, termasuk `PortalTicketStatusMappingTest` yang membuktikan
  seluruh baris tabel mapping flowchart.md §3.

## Fase 5 — Portal mengonsumsi API

Di luar repo ini (§6.6.1, §6.6.8). Fase 0-4 tidak menunggu portal selesai dibangun.

---

## Rencana test

Semua `tests/Feature`, PHPUnit, atribut modern (`#[Test]`, `#[DataProvider]`), nama
berdasarkan gejala — mengikuti pola `FopTaskCancelCascadeAuthTest`,
`CustomerVerificationRejectFopSyncTest`.

### Kredensial

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

### Kebocoran lintas pelanggan

`PortalCrossCustomerLeakTest`, satu berkas, `#[DataProvider]` atas seluruh endpoint
detail. Pelanggan A memanggil `INV-`/`PAY-`/`TKT-` milik B → **404**, bukan 403, bukan
200. Ditambah: `customer_id` yang disuntikkan lewat query string, body, atau header
diabaikan total.

Test ini yang paling mudah rusak tanpa disadari saat controller baru ditambahkan, jadi
provider-nya dibuat mudah ditambahi satu baris per endpoint baru.

### Isi data

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

**CORS pertama di repo** (`config/cors.php` belum pernah ada sebelum Fase 0 modul ini —
sekarang sudah dibuat, scoped `api/customer-portal/*`). `RateLimiter::for` **sudah ada
polanya duluan** (3x untuk `api-pop-distribusi`, `AppServiceProvider.php:169-183`) — 3
limiter portal mengikuti gaya yang sama, bukan pola baru.

**Dua dokumen untuk satu portal.** Modul ini dan QR §6.6 membahas API yang sama dari
sudut berbeda. Setiap perubahan keputusan di salah satunya harus tercermin di yang
lain.

**Payload portal berbagi tabel `webhook_outbox` dengan `api-webhook-pemasangan`.** Payload
`installation.*` memuat PII, payload `invoice.updated` tidak boleh sama sekali — dua
kebijakan berbeda di satu tabel, review kode harus memeriksa keluarga event mana yang
sedang disentuh.
