# Modul API Eksternal

Rancangan lapisan API supaya Whusnet Operasional bisa diremote dan diintegrasikan
dengan sistem lain. **Dokumen ini rancangan, bukan catatan fitur yang sudah jalan.**
Belum ada satu baris kode API di repo per 2026-08-18.

| Berkas | Isi |
|---|---|
| `README.md` (ini) | Prinsip lintas-API, versioning, envelope, auth, rate limit, larangan keras |
| `business-logic.md` | Kontrak lengkap API 1 (webhook pemasangan) & API 2 (portal pelanggan) |
| `database-schema.md` | Tabel & kolom baru beserta alasannya |
| `flowchart.md` | Alur webhook dan alur auth portal |
| `rencana-implementasi.md` | Fase, gerbang persetujuan, rencana test |

## Hubungan dengan `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6

**Baca §6.6 lebih dulu kalau Anda menyentuh API 2.** Bagian itu (baris 804-1060) sudah
menetapkan kontrak Portal Pelanggan untuk keempat fitur yang sama, dan sebagiannya
**dikonfirmasi pemilik produk** — bukan usulan terbuka:

- Portal adalah **aplikasi terpisah di domain berbeda**, tanpa kredensial DB
  operasional (§6.6.1).
- Login ID = `{prefix_pop}-{customer_code}`, bukan `cid` (§6.6.2).
- Kredensial portal di tabel sendiri, PIN jadi kunci aktivasi (§6.6.5).
- Notifikasi pembayaran lewat outbox + webhook, dipicu dari
  `Invoice::recalculateFromPayments()` (§6.6.6).
- Pemetaan status tiket ke bahasa pelanggan (§6.6.7).

Dokumen ini berdiri sebagai modul sendiri karena API 1 (webhook pemasangan) sama
sekali tidak dibahas di sana, dan karena pondasi API (`routes/api.php`, envelope,
exception JSON, rate limiter) melayani keduanya. **Tapi ia tidak mengontradiksi
§6.6.** Di setiap titik yang §6.6 sudah putuskan, dokumen ini mengikuti dan menunjuk
ke sana. Kalau Anda menemukan perbedaan, §6.6 yang menang dan dokumen ini yang salah.

## Titik berangkat: sistem ini belum punya API

Fakta yang harus dipahami sebelum membaca sisanya:

- `routes/api.php` **tidak ada**. `bootstrap/app.php:9-14` cuma mendaftarkan `web`,
  `commands`, `channels`, dan `health`.
- Tidak ada Sanctum maupun Passport (`composer.json:8-17`).
- `config/auth.php:40-45` cuma punya guard `web` → provider `users` → `App\Models\User`.
- `App\Http\Resources` **tidak ada**. Tidak ada satu pun API Resource di repo.
- Tidak ada rate limiter — `RateLimiter::for` nol hasil di seluruh `app/`.
- Tidak ada `config/cors.php`.
- `withExceptions()` di `bootstrap/app.php:20-22` **kosong**.

Route ber-prefix `/api/` yang sudah ada di `routes/web.php` (mis. `:580` pipeline FOP,
`:670-673` lookup tiket, `:700-703` detail tiket JSON) adalah **AJAX internal beraut
session** untuk Blade + Alpine. Bentuk responsnya ad-hoc, tanpa versi, tanpa envelope.
Bukan pondasi yang bisa dipakai ulang untuk konsumen luar, dan rancangan ini tidak
menyentuhnya.

## Dua API, dua model keamanan

| | API 1 — Webhook Pemasangan | API 2 — Portal Pelanggan |
|---|---|---|
| Prefix | — (keluar) | `/api/customer-portal/*` |
| Arah | **Keluar** (Whusnet mendorong) | **Masuk** (portal menarik) |
| Lawan bicara | Sistem lain (NMS/provisioning) | Portal, atas nama satu pelanggan |
| Auth | HMAC-SHA256 per-request | Client secret portal **+** bearer token pelanggan |
| Identitas | `webhook_endpoints` terdaftar manual | `customer_portal_accounts.login_id` |
| Pembatas data | `pop_id` opsional per endpoint | `customer_id` pemilik token |

Model keamanan yang berbeda ini disengaja. Untuk integrasi mesin-ke-mesin, token
bearer berumur panjang adalah satu rahasia statis yang, kalau bocor dari log atau
proxy, langsung memberi akses penuh; signature per-request tidak menaruh rahasia di
dalam permintaan sama sekali.

Portal justru butuh **dua lapis** (§6.6.2): client secret membuktikan "ini portal
resmi" dan berfungsi sebagai tuas darurat — cabut secret, seluruh portal mati
seketika tanpa menyentuh akun pelanggan mana pun; token pelanggan membuktikan "ini
pelanggan X". Portal tidak pernah memegang kunci yang bisa membaca semua pelanggan.

## Prinsip lintas-API

**1. Versioning.** API 1 dan pondasi memakai `/api/v1/`. API 2 memakai
`/api/customer-portal/*` sesuai §6.6.4 — prefix itu sudah jadi kontrak yang dipegang
tim portal, jadi jangan diseragamkan jadi `/api/v1/portal` hanya demi kerapian.
Sekali sebuah path dipublikasikan, ia tidak berubah bentuk tanpa naik versi.

**2. Satu envelope, bukan tiga.** Sukses `{"data": ..., "meta": {...}}` lewat
`JsonResource`. Galat `{"message": "...", "errors": {...}}` — bentuk bawaan Laravel.
Karena `App\Http\Resources` masih kosong, pola ini dibangun dari nol di sini; jangan
menyalin gaya ad-hoc dari controller web yang ada.

**3. Exception harus dirender JSON.** `withExceptions()` masih kosong, jadi hari ini
`ValidationException`, `AuthenticationException`, dan 404 semuanya balik sebagai
halaman HTML.

**4. Semua nominal adalah string desimal, bukan angka JSON.** `"150000.00"`, bukan
`150000`. Ini bukan selera: repo punya `Money` dan `Invoice::recalculateFromPayments()`
justru karena galat pembulatan pernah mengubah **cabang** lunas/sebagian, bukan cuma
tampilan (§6.6.4). Float di JSON menghidupkan ulang kelas bug itu di seberang, di
aplikasi yang tidak punya test kita.

Berlaku untuk kedua API — termasuk `paket.harga_bulanan` di payload webhook, yang
kalau kelak dipakai menghitung tagihan di sistem lain punya risiko yang sama.

**5. Rate limit wajib, dan endpoint kredensial punya limiter sendiri.**

| Limiter | Batas | Kunci |
|---|---|---|
| `customer-portal-auth` | 5 / 15 menit | (IP + `login_id`) |
| `customer-portal-auth-ip` | 20 / 15 menit | IP saja |
| `customer-portal-api` | 120 / menit | token + IP |
| pengiriman webhook | backoff antrean | per endpoint |

Dua limiter untuk kredensial, bukan satu. Kunci per-`login_id` saja **tidak menutup
penyapuan**: satu percobaan untuk masing-masing dari 1.900 login ID memberi ember baru
tiap kali, jadi seluruh daftar pelanggan satu cabang bisa disisir dari satu IP tanpa
pernah menyentuh batas. Limiter per-IP-saja yang menghentikannya.

Limiter API (120/menit) **tidak boleh** dipakai untuk `login`, `claim`, atau
`me/password` — 120 percobaan kredensial per menit adalah brute force yang diizinkan.

Rate limiter tinggal di cache, dan cache bisa di-flush. Karena itu hitungan kegagalan
**juga** disimpan di DB (`customer_portal_accounts.failed_attempts` / `locked_until`),
mengikuti alasan yang sama seperti lockout PIN di §6.5.4.

**6. CORS.** Portal ada di domain berbeda, jadi tanpa `config/cors.php` semua
panggilan dari browser diblokir. Whitelist origin portal **spesifik**, hanya untuk
grup route `/api/customer-portal/*`. Bukan wildcard, dan tidak berlaku ke seluruh app
— endpoint staf tetap same-origin.

**7. Urutan route statis dulu.** Aturan yang sudah ditandai eksplisit di
`routes/web.php` berlaku sama di `routes/api.php`. `/me/payments/{payment_number}/receipt`
didaftarkan sebelum `/me/payments/{payment_number}`.

**8. Middleware `permission` tidak berlaku di portal.**
`app/Http/Middleware/CheckPermission.php:16-40` memanggil `auth()->check()` dan
`auth()->user()` tanpa parameter guard, jadi terikat ke guard default. Dipakai di
jalur portal, ia akan menanyakan permission pegawai kepada pelanggan. Portal punya
penjaganya sendiri: kepemilikan baris.

## Larangan keras

1. **Jangan pernah menerima `customer_id` dari request di jalur portal.** Pemilik data
   ditentukan oleh token, titik. Satu parameter yang lolos berarti pelanggan mana pun
   bisa membaca tagihan pelanggan lain.
2. **Jangan campur POP scope dengan kepemilikan portal.**
   `EffectiveAccessService::getAllowedPopIds()` mengembalikan array kosong untuk
   ALL_POP — makna yang aman untuk pegawai, bencana kalau ditafsirkan sebagai "tanpa
   filter" di portal. Jalur portal tidak memanggilnya sama sekali.
3. **Dokumen milik orang lain dijawab 404, bukan 403.** 403 mengonfirmasi bahwa nomor
   itu ada.
4. **Jangan menaruh kredensial pelanggan di tabel `customers`.** Alasannya bukan
   kerapian — lihat `database-schema.md` bagian audit log.
5. **Jangan mencampur token pelanggan dengan token staf di satu tabel.**
6. **Payload webhook pemasangan memuat PII pelanggan.** Endpoint wajib HTTPS,
   didaftarkan manual oleh Owner, log-nya punya kebijakan purge. Payload webhook
   **portal** justru tidak boleh memuat PII sama sekali (§6.6.6).
7. **Jangan bocorkan catatan internal.** `tickets.catatan_teknis`,
   `payments.reject_reason`, `payments.note`, dan nama pegawai tidak keluar ke
   pelanggan. Daftar putih, bukan daftar hitam.
