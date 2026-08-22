# Keputusan & Analisa — API 2: Portal Pelanggan

Dokumen pendamping. `business-logic.md`/`database-schema.md`/`flowchart.md`
menjelaskan **rancangan seperti apa adanya**; berkas ini menjelaskan **kenapa jadi
begitu, dan apa yang ditolak di jalan.**

---

## 1. Alternatif yang ditolak

| Ditolak | Kenapa |
|---|---|
| Login pakai **`cid`** | Tidak punya unique constraint — hanya index biasa (`customers_cid_idx`). CID legacy bisa tabrakan lintas cabang, dan `where('cid',…)->first()` akan menerbitkan token untuk pelanggan yang salah. Juga nullable: pelanggan pra-aktivasi tidak bisa login padahal punya tagihan pemasangan. Diganti `login_id` = `{prefix_pop}-{customer_code}` |
| Login pakai **nomor HP** | `primary_phone` tidak unik, ada `alternative_phone`, data legacy bisa dobel atau kosong |
| Kolom **`password` di `customers`** | `Customer` memakai `RecordsAuditLogs` tanpa override `$auditEvents`, jadi `updated` menulis nilai lama & baru mentah ke `audit_logs`. `$hidden` tidak menolong — ia memfilter `attributesToArray()`, bukan `getChanges()`. Setiap ganti password menyimpan hash bcrypt lama+baru, terbaca staf. `User` lolos karena `User.php:28` menyetel `$auditEvents = ['deleted']`. Diganti tabel `customer_portal_accounts` |
| **`laravel/sanctum`** | Tabel `personal_access_tokens` polymorphic akan dipakai bersama staf; satu bug scoping bisa menyeberangkan hak akses antar dua populasi. Diganti `customer_portal_tokens`. Efek samping: nol dependensi baru |
| **Token bearer 30 hari tanpa rotasi** | Token yang bocor hidup sebulan penuh tanpa sinyal apa pun. Diganti access 15 menit + refresh 30 hari rotating sekali-pakai; pemakaian ulang refresh = indikasi pencurian, seluruh rantai dicabut |
| **Admin men-set password pelanggan** | Begitu ada orang lain yang tahu password, password berhenti berfungsi sebagai bukti identitas — dan itu satu-satunya gunanya. Diganti jalur klaim PIN; helpdesk menerbitkan PIN baru, bukan password |
| **Prefix `/api/v1/portal`** | `/api/customer-portal/*` sudah jadi kontrak yang dipegang tim portal (§6.6.4). Menyeragamkan demi kerapian akan memecahkan konsumen |
| Rate limit login **hanya keyed `login_id`+IP** | Memberi ember baru untuk tiap login ID, jadi penyapuan satu percobaan × 1.900 akun dari satu IP tidak pernah menyentuh batas. Ditambah limiter per-IP-saja |
| **Lockout hanya di cache** | Cache di-flush, lockout hilang. Hitungan kegagalan juga disimpan di `customer_portal_accounts.failed_attempts`/`locked_until` |
| **Kwitansi = keluaran `ReceiptPresenter` apa adanya** | Presenter membawa `penerima`, `penagih`, `catatan` — nama pegawai dan catatan internal. Satu endpoint kwitansi akan membatalkan daftar putih endpoint `/me/payments` di sebelahnya |
| **Menyembunyikan pembayaran yang ditolak** | Uang yang sudah diserahkan ke kolektor lenyap dari layar pelanggan tanpa penjelasan. Tetap ditampilkan, tapi `reject_reason` tidak keluar — statusnya "belum terverifikasi, hubungi admin" |
| **Nominal sebagai angka JSON** | Galat float pernah mengubah *cabang* lunas/sebagian di repo ini, bukan cuma tampilan. Semua nominal string desimal |
| **Status tiket dari `tickets.status`** | Begitu `handler=FOP`, `TicketHandlingStatus` berhenti bermakna — tiket yang sudah selesai di lapangan tampil "Sedang Ditangani" selamanya. Pakai `Ticket::resolveStatus()` |
| **`Ticket::statusLabel()` untuk portal** | Mengembalikan "Diproses NOC", "Ditangani Helpdesk", "Terputus" — struktur organisasi internal yang §6.6.7 larang keluar |
| **403 untuk dokumen milik pelanggan lain** | 403 mengonfirmasi bahwa nomor itu ada. Selalu 404 |
| **Notifikasi pelanggan di fase 1** | `Customer` bukan `Notifiable`, dan `SendCustomerActivationNotification` masih menulis "Simulasi Telegram dikirim ke…". Membangun kanal ke pelanggan dari nol di tengah pekerjaan API |

---

## 2. Temuan review 2026-08-18 dan penyelesaiannya

Lima belas temuan, semuanya ditutup di rev. 2. Diringkas di sini supaya tidak perlu
menggali riwayat percakapan.

| # | Temuan | Penyelesaian |
|---|---|---|
| 1 | Login `cid` — tidak unique, nullable | `login_id` `{prefix_pop}-{customer_code}` |
| 2 | Hash password bocor ke `audit_logs` | Tabel `customer_portal_accounts` terpisah |
| 3 | Status tiket dibaca mentah | `Ticket::resolveStatus()` + presenter portal sendiri |
| 4 | Kwitansi membocorkan nama pegawai | Buang `penerima`, `penagih`, `catatan` |
| 5 | Token numpang Sanctum, 30 hari tanpa rotasi | `customer_portal_tokens`, 15 menit + refresh rotating |
| 6 | Rate limit login tidak menutup penyapuan | Dua limiter + lockout di DB |
| 7 | Admin men-set password | Jalur klaim PIN |
| 8 | Prefix bentrok, tanpa CORS | `/api/customer-portal/*` + `config/cors.php` + `X-Portal-Client` |
| 9 | "Tidak perlu dikirim" hanya benar kalau seDB | Dipecah: isi ditarik lewat API, kabar lewat outbox dari `Invoice::recalculateFromPayments()` |
| 10 | `overpay_amount` hilang | `overpay_amount` + `billing_period` masuk daftar putih |
| 11 | Nominal float | String desimal |
| 12 | Hash vs enkripsi secret | `secret_encrypted` + test penjaga |
| 13 | Pemasangan revisi → provisioning dobel | `idempotency_key` (relevan `api-webhook-pemasangan`) |
| 14 | Semantik baris outbox ambigu | Satu baris per event, `attempts` naik di tempat |
| 15 | Dua kutipan `file:line` meleset | Dikoreksi |

Kesalahan pokok yang menyebabkan sebagian besar temuan: rancangan awal disusun dari
sapuan kode tanpa membuka `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6,
yang sudah memuat kontrak portal terkonfirmasi. Pelajaran operasionalnya sederhana —
sebelum merancang modul baru, cari dulu apakah `docs/plan/` sudah memuat keputusan
untuk area yang sama.

---

## 3. Pertanyaan yang masih terbuka

| # | Pertanyaan | Kenapa tidak ditebak |
|---|---|---|
| 1 | `{prefix_pop}` di `login_id` = `pops.registration_prefix` atau `pops.cid_prefix`? | Keduanya ada (`app/Models/Pop.php:21-22`), §6.6.2 tidak menyebut kolomnya. Salah pilih = seluruh kartu pelanggan tercetak dengan login ID yang tidak cocok |
| 2 | Nama pelanggan ikut di pesan Telegram Eksternal (`api-webhook-pemasangan`)? | Lintas-modul; keputusan produk yang sama juga relevan di sini karena login_id nanti masuk payload `api-webhook-pemasangan` |
| 3 | Beban merawat dua dokumen untuk satu portal (modul ini + QR §6.6) | Kalau mulai terasa, gabungkan — jangan biarkan keduanya menyimpang diam-diam |
