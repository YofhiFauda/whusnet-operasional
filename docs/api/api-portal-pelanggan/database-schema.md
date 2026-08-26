# Database Schema — API 2: Portal Pelanggan

Semua di bawah ini **rancangan**. Belum ada satu migrasi pun yang dibuat.

## Ringkasan

| Objek | Jenis | Untuk |
|---|---|---|
| `customer_portal_accounts` | tabel baru | Kredensial portal (§6.6.5) |
| `customer_portal_tokens` | tabel baru | Token akses & refresh portal (§6.6.2) |

**`webhook_outbox` dipakai bareng, tidak didefinisikan ulang di sini.** Event
`invoice.updated` memakai tabel yang sama dengan `api-webhook-pemasangan` — lihat
`../api-webhook-pemasangan/database-schema.md` §2 untuk skema kolomnya. Nama final
**`webhook_outbox`** (dikonfirmasi 2026-08-24) — sebutan `portal_outbox` di draf lama
`docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6.6 sudah dikoreksi mengikuti ini.

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

Token disimpan sebagai hash — berbeda dari secret webhook `api-webhook-pemasangan`, token tidak pernah
perlu dibaca kembali.

Secret webhook portal (`PORTAL_WEBHOOK_SECRET`, §6.6.6) terpisah dari
`QR_HMAC_SECRET` dan `APP_KEY`, dengan rotasi jendela dua-secret.

---

## Tabel yang dibaca tanpa diubah

| Tabel | Dipakai untuk | Catatan |
|---|---|---|
| `customers` | identitas, profil | **Tidak ditambah kolom apa pun** |
| `pops` | `login_id` (`registration_prefix`/`cid_prefix`) | |
| `invoices` | daftar & detail tagihan | Nilai dibaca apa adanya; sumber kebenarannya `Invoice::recalculateFromPayments()` |
| `payments` | riwayat + kwitansi | Nomor kwitansi = `payment_number` |
| `customer_balance_mutations` | saldo | Ledger append-only, saldo lewat `CustomerBalanceService` |
| `tickets`, `ticket_histories` | riwayat ticketing | Butuh relasi `Customer::tickets()` — perubahan model, bukan skema |

### Perubahan model (bukan migrasi) yang jadi prasyarat

1. **`Customer::tickets()`** sebagai `HasMany(Ticket::class)`. Kolom
   `tickets.customer_id` sudah ada sejak
   `2026_07_23_000001_create_tickets_table.php:19-24` dengan `restrictOnDelete` —
   relasinya saja yang belum pernah ditulis.
2. **`Customer::portalAccount()`** sebagai `HasOne`.

Karena kredensial tidak lagi menempel di `customers`, tidak ada `$hidden` baru yang
perlu dijaga di sana. Itu justru intinya: risiko serialisasi model pelanggan — yang
nyata, karena beberapa closure di `routes/web.php` mengembalikan model Eloquent
langsung sebagai JSON (mis. pencarian wilayah, meski keduanya kebetulan mengembalikan
City/District/Village/Pop, bukan `Customer`) — tidak pernah bertemu kolom rahasia.
