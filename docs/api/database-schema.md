# Database Schema — API Eksternal

Semua di bawah ini **rancangan**. Belum ada satu migrasi pun yang dibuat.

## Ringkasan

| Objek | Jenis | Untuk |
|---|---|---|
| `customer_portal_accounts` | tabel baru | Kredensial portal (§6.6.5) |
| `customer_portal_tokens` | tabel baru | Token akses & refresh portal (§6.6.2) |
| `webhook_endpoints` | tabel baru | Pendaftaran konsumen webhook |
| `webhook_outbox` | tabel baru | Antrean + jejak pengiriman, dua keluarga event |

**Tabel `customers` tidak diubah sama sekali.** Tidak ada tabel baru untuk tagihan,
pembayaran, kwitansi, atau tiket. Semuanya dibaca dari struktur yang sudah ada.

Tidak ada `personal_access_tokens`, dan karena itu **tidak ada dependensi Sanctum**.

---

## 1. `customer_portal_accounts`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | id | |
| `customer_id` | FK `customers`, **unique** | Satu akun per pelanggan di fase ini |
| `login_id` | string(64), **unique** | `{prefix_pop}-{customer_code}`, mis. `PNG-RQ000631` |
| `password_hash` | string(255) | bcrypt |
| `password_changed_at` | timestamp nullable | |
| `failed_attempts` | unsigned smallint, default 0 | |
| `locked_until` | timestamp nullable | |
| `status` | string(20) | `pending_claim` / `active` / `disabled` |
| `claimed_at` | timestamp nullable | |
| `last_login_at` | timestamp nullable | |
| `timestamps` | | |

### Kenapa tabel terpisah, bukan kolom di `customers`

Menempelkan `password` ke `customers` terlihat hemat — satu tabel, nol join. Ia
membocorkan hash password ke audit log, dan `$hidden` tidak menolong.

Jalurnya konkret. `Customer` memakai trait `RecordsAuditLogs`
(`app/Models/Customer.php:64-66`) dan **tidak** meng-override `$auditEvents`, jadi
default `['updated', 'deleted']` aktif (`app/Models/Concerns/RecordsAuditLogs.php:58-64`).
Pada setiap `updated`, trait itu mengambil `getChanges()` dan menulis nilai lama
(`getOriginal($field)`) serta nilai baru (`getAttribute($field)`) **mentah** ke
`audit_logs` (`:21-40`). Satu-satunya penyaring adalah `auditIgnoredFields()`, yang
defaultnya cuma `created_at`, `updated_at`, `remember_token`.

`$hidden` tidak ikut campur di jalur itu sama sekali — ia memfilter
`attributesToArray()`, bukan `getChanges()`. Akibatnya: setiap kali pelanggan
memakai fitur #1 (ganti password), hash bcrypt lama **dan** baru tersimpan permanen
di `audit_logs` modul "Data Pelanggan", terbaca staf mana pun yang bisa membuka
riwayat pelanggan.

`User` lolos dari masalah ini bukan karena kebetulan: `app/Models/User.php:28`
menyetel `$auditEvents = ['deleted']` — persis supaya perubahan password staf tidak
tercatat.

Efek kedua yang lebih kecil tapi mengganggu: `portal_last_login_at` di kolom
`customers` berarti satu baris audit "Data Pelanggan diubah" per login per pelanggan,
membanjiri riwayat yang dipakai staf untuk menelusuri perubahan data sungguhan.

Menambahkan `password` ke `auditIgnoredFields()` bisa menutup gejalanya, tapi
mengandalkan satu daftar yang harus diingat setiap kali kolom sensitif ditambahkan.
Tabel terpisah menutupnya secara struktural: master pelanggan dibaca hampir semua
modul, dan kredensial tidak ikut tertarik ke mana-mana. Ini juga keputusan §6.6.5,
dengan alasan yang sama.

### Indeks

`login_id` unique sudah cukup — ia satu-satunya jalur lookup saat login.

---

## 2. `customer_portal_tokens`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | id | |
| `customer_id` | FK `customers` | Denormal, supaya pencabutan massal murah |
| `token_hash` | string(64), index | Hash, **bukan** plaintext — pola sama `customer_qr_tokens` |
| `type` | string(10) | `access` / `refresh` |
| `parent_id` | FK self, nullable | Rantai rotasi refresh |
| `expires_at` | timestamp | access 15 menit, refresh 30 hari |
| `revoked_at` | timestamp nullable | |
| `last_used_at` | timestamp nullable | |
| `ip_address` | string(45) nullable | Saat diterbitkan |
| `timestamps` | | |

Bukan menumpang Sanctum `personal_access_tokens` (§6.6.2). Tabel itu polymorphic dan
akan dipakai bersama `users`; mencampur kredensial staf dan pelanggan di satu tabel
berarti satu bug scoping berpotensi menyeberang antar dua populasi yang seharusnya
tidak pernah bersinggungan.

**Refresh sekali pakai.** Refresh token yang dipakai kedua kalinya adalah indikasi
token dicuri: seluruh rantai `parent_id` turunannya dicabut dan pelanggan login ulang.
Tanpa ini, pencuri bisa memperpanjang akses selamanya tanpa sinyal apa pun.

Token disimpan sebagai hash — berbeda dari secret webhook di bawah, token tidak pernah
perlu dibaca kembali.

---

## 3. `webhook_endpoints`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | id | |
| `name` | string(100) | Label manusia, mis. "NMS Ponorogo" |
| `url` | string(500) | Wajib `https://`, divalidasi saat simpan |
| `secret_encrypted` | text | **Terenkripsi simetris** (`encrypted` cast), bukan hash |
| `events` | json | Event yang dilanggan, mis. `["installation.activated"]` |
| `pop_id` | FK `pops` nullable | Batasi cabang. `null` = semua cabang, keputusan sadar |
| `is_active` | boolean | Dimatikan manual atau otomatis setelah gagal beruntun |
| `consecutive_failures` | unsigned int | Direset ke 0 setiap pengiriman sukses |
| `last_failed_at` | timestamp nullable | |
| `timestamps` | | |

**`secret_encrypted`, bukan `secret_hash`.** HMAC menuntut kedua pihak memegang
rahasia yang sama, jadi kita harus bisa membacanya kembali setiap kali menandatangani.
`Hash::make()` di kolom ini membuat seluruh pengiriman gagal ditandatangani setelah
endpoint dibuat, dan tidak bisa dipulihkan tanpa menerbitkan ulang secret ke semua
konsumen. Nama kolomnya sengaja menyebut "encrypted" supaya salah paham itu tidak
terjadi. Plaintext hanya ditampilkan sekali, saat pembuatan.

Secret webhook portal (`PORTAL_WEBHOOK_SECRET`, §6.6.6) terpisah dari `QR_HMAC_SECRET`
dan `APP_KEY`, dengan rotasi jendela dua-secret.

`pop_id` mengikuti aturan keras CLAUDE.md: setiap aliran data pelanggan punya pembatas
cabang. Webhook mengirim data ke luar organisasi, jadi pembatas itu lebih penting,
bukan kurang.

---

## 4. `webhook_outbox`

Satu tabel untuk **dua keluarga event**: `installation.*` (API 1, ke sistem
provisioning) dan `invoice.updated` (API 2, ke portal — `portal_outbox` di §6.6.6).
Digabung karena mekanismenya identik sampai ke angka backoff-nya; dua tabel berarti
dua worker, dua kebijakan retry, dan dua tempat mencari saat ada yang tidak sampai.
Kalau saat implementasi nama `portal_outbox` lebih disukai untuk keluarga kedua, itu
keputusan penamaan, bukan perbedaan desain.

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | id | |
| `webhook_endpoint_id` | FK | |
| `event` | string(50) | `installation.activated` / `invoice.updated` |
| `event_id` | uuid, index | **Satu baris = satu event.** Tetap sama di semua percobaan |
| `idempotency_key` | string(100) nullable, index | Mengelompokkan event yang saling menggantikan |
| `customer_id` | FK nullable, `nullOnDelete` | Penelusuran |
| `payload` | json | Isi yang dikirim, apa adanya |
| `status` | string(15) | `pending` / `delivered` / `failed` |
| `attempts` | unsigned tinyint | Dinaikkan di tempat, maks 8 |
| `next_attempt_at` | timestamp nullable, index | |
| `response_status` | unsigned smallint nullable | Percobaan terakhir |
| `last_error` | text nullable | Dipotong, mis. 1 KB |
| `delivered_at` | timestamp nullable | |
| `timestamps` | | |

### Satu baris per event, bukan satu baris per percobaan

Ini perlu dinyatakan tegas karena kedua bacaan menghasilkan sistem yang berbeda.
**Satu baris mewakili satu event**; `attempts` dinaikkan di tempat dan `last_error`
ditimpa. Percobaan ulang mengirim **payload yang tersimpan di baris itu**, tidak
merakit ulang dari model.

Alasannya: kalau payload dirakit ulang tiap percobaan, percobaan ke-3 bisa mengirim
data yang sudah berubah — dan penerima yang mengandalkan idempotensi `event_id` akan
membuangnya sebagai duplikat, sehingga perubahan itu hilang tanpa jejak.

Konsekuensinya, riwayat per-percobaan tidak tersimpan; yang tersimpan adalah keadaan
terakhir. Untuk forensik pengiriman yang lebih dalam, log aplikasi yang dipakai, bukan
tabel ini.

Indeks: `(status, next_attempt_at)` untuk worker mengambil pekerjaan,
`(webhook_endpoint_id, created_at)` untuk halaman riwayat, `event_id` dan
`idempotency_key` untuk penelusuran.

### Retensi

**Wajib ditetapkan sebelum tabel ini hidup.** Payload `installation.*` memuat nama,
desa, paket, dan perangkat pelanggan. Dibiarkan tumbuh, tabel ini jadi salinan data
pelanggan kedua yang tidak pernah diaudit siapa pun.

Rancangan: baris `delivered` dipruning 90 hari, mengikuti kebijakan `qr_scan_logs`
(§4.2). Baris `failed` **tidak** ikut dipruning otomatis — ia daftar rekonsiliasi
"event mana yang belum sampai", dan menghapusnya berarti kegagalan hilang diam-diam.

Alternatif yang **ditolak**: menyimpan payload hanya saat gagal. Terdengar hemat, tapi
menghilangkan kemampuan menjawab "apa persisnya yang kalian kirim ke kami" —
pertanyaan pertama setiap kali integrasi disalahkan.

Payload `invoice.updated` tidak memuat PII sama sekali (§6.6.6), jadi retensinya bisa
lebih longgar; kebijakan tunggal 90 hari dipilih supaya tidak ada dua aturan purge di
satu tabel.

---

## Tabel yang dibaca tanpa diubah

| Tabel | Dipakai untuk | Catatan |
|---|---|---|
| `customers` | identitas, profil, payload webhook | **Tidak ditambah kolom apa pun** |
| `pops` | `login_id` (`registration_prefix`/`cid_prefix`), payload | |
| `invoices` | daftar & detail tagihan | Nilai dibaca apa adanya; sumber kebenarannya `Invoice::recalculateFromPayments()` |
| `payments` | riwayat + kwitansi | Nomor kwitansi = `payment_number` |
| `customer_balance_mutations` | saldo | Ledger append-only, saldo lewat `CustomerBalanceService` |
| `tickets`, `ticket_histories` | riwayat ticketing | Butuh relasi `Customer::tickets()` — perubahan model, bukan skema |
| `customer_devices`, `customer_technical_details` | SN & ODP webhook | Rantai fallback, lihat `business-logic.md` |
| `villages`, `internet_packages` | payload webhook | |

### Perubahan model (bukan migrasi) yang jadi prasyarat

1. **`Customer::tickets()`** sebagai `HasMany(Ticket::class)`. Kolom
   `tickets.customer_id` sudah ada sejak
   `2026_07_23_000001_create_tickets_table.php:19-24` dengan `restrictOnDelete` —
   relasinya saja yang belum pernah ditulis.
2. **`Customer::portalAccount()`** sebagai `HasOne`.

Karena kredensial tidak lagi menempel di `customers`, tidak ada `$hidden` baru yang
perlu dijaga di sana. Itu justru intinya: risiko serialisasi model pelanggan — yang
nyata, karena beberapa closure di `routes/web.php` mengembalikan model Eloquent
langsung sebagai JSON (mis. pencarian wilayah `:762-816` dan POP `:805-816`,
meski keduanya kebetulan mengembalikan City/District/Village/Pop, bukan `Customer`) —
tidak pernah bertemu kolom rahasia.
