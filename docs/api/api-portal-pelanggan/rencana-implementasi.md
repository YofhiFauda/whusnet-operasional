# Rencana Implementasi — API 2: Portal Pelanggan

## Dependensi: tidak ada yang baru

Karena token portal memakai tabel sendiri (`customer_portal_tokens`, §6.6.2) dan
bukan `personal_access_tokens` polymorphic, `laravel/sanctum` tidak jadi dibutuhkan.

Seluruh rancangan berjalan di atas paket yang sudah terpasang: Horizon untuk antrean,
`endroid/qr-code` (`composer.json:10`) untuk kartu ber-PIN kalau jalur klaim
dikerjakan bersama modul QR. Tidak ada `composer require` sama sekali.

## Fase 0 — Pondasi API (dipakai bareng semua API masuk)

Belum dikerjakan. Sama seperti yang dibutuhkan `api-pop-distribusi/`:

- Buat `routes/api.php`, daftarkan di `bootstrap/app.php` (`withRouting(api: ...)`).
- Isi `withExceptions()` supaya request `/api/*` mendapat JSON.
- `config/cors.php`: whitelist origin portal **spesifik**, hanya untuk grup
  `/api/customer-portal/*`.
- Daftarkan rate limiter: `customer-portal-auth`, `customer-portal-auth-ip`,
  `customer-portal-api`.
- Buat `App\Http\Resources` (direktori belum ada) + base Resource yang menetapkan
  envelope `{data, meta}` dan **serialisasi nominal sebagai string desimal**.

Selesai kalau: satu endpoint `GET /api/v1/ping` mengembalikan JSON, 404 di bawah
`/api/*` juga JSON, dan preflight CORS dari origin portal lolos sementara origin lain
ditolak.

## Fase 2 — Kredensial & auth portal

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
prefix registrasi — tapi ini harus dikonfirmasi, bukan ditebak.

## Fase 3 — Tagihan, pembayaran, kwitansi

- `GET /me/invoices`, `/me/invoices/{invoice_number}`, `/me/payments`,
  `/me/payments/{payment_number}/receipt`, `/me/balance`.
- Resource dengan daftar putih kolom; `overpay_amount` dan `billing_period` ikut
  keluar, `reject_reason`/`note`/`proof_file` tidak.
- Trait/base `ScopedToAuthenticatedCustomer`.
- Kwitansi: `ReceiptPresenter` **dipangkas** — buang `penerima`, `penagih`, `catatan`;
  tambahkan pendamping mentah `dibayar_raw` (string desimal) dan `tanggal_bayar_iso`.
- Event `invoice.updated` ke `webhook_outbox` (tabel sama dengan `api-webhook-pemasangan`, lihat
  `../api-webhook-pemasangan/database-schema.md`) dari `Invoice::recalculateFromPayments()`, state
  penuh, tanpa PII.

## Fase 4 — Ticketing

- Relasi `Customer::tickets()`.
- `GET /me/tickets`, `/me/tickets/{ticket_number}`.
- Presenter status pelanggan bertumpu `Ticket::resolveStatus()` —
  **bukan** `Ticket::statusLabel()`, yang membocorkan nama tim internal.

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

**Rate limit dan CORS pertama di repo.** Belum pernah ada `RateLimiter::for` maupun
`config/cors.php` di codebase ini, jadi tidak ada pola yang bisa dicontoh.

**Dua dokumen untuk satu portal.** Modul ini dan QR §6.6 membahas API yang sama dari
sudut berbeda. Setiap perubahan keputusan di salah satunya harus tercermin di yang
lain.

**Payload portal berbagi tabel `webhook_outbox` dengan `api-webhook-pemasangan`.** Payload
`installation.*` memuat PII, payload `invoice.updated` tidak boleh sama sekali — dua
kebijakan berbeda di satu tabel, review kode harus memeriksa keluarga event mana yang
sedang disentuh.
