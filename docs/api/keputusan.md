# Keputusan & Analisa — API Eksternal

Dokumen pendamping. Empat berkas lain menjelaskan **rancangan seperti apa adanya
sekarang**; berkas ini menjelaskan **kenapa jadi begitu, dan apa yang ditolak di
jalan.**

Alasannya praktis: rancangan yang sudah rapi terbaca seolah selalu begitu. Tanpa
catatan ini, enam bulan lagi seseorang akan mengusulkan ulang salah satu alternatif
yang sudah ditolak — dan tidak ada yang bisa menjawab kenapa dulu tidak dipakai.
Repo ini sudah punya pola yang sama di `docs/plan/kolektor/analisa-*.md` dan
`docs/fop-task/analisa-*.md`.

---

## 1. Riwayat revisi

| Rev | Tanggal | Yang berubah | Pemicu |
|---|---|---|---|
| 1 | 2026-08-18 | Rancangan awal: 2 event (`started`+`completed`), Sanctum, login `cid`, password di `customers` | Permintaan awal |
| 2 | 2026-08-18 | 15 temuan review ditambal; API 2 diselaraskan ke QR §6.6 | Review kode |
| 3 | 2026-08-18 | Trigger dikoreksi ke tombol **Aktivasi Laporan Speedtest** | Koreksi pemilik produk |
| 4 | 2026-08-19 | Fan-out dua transport; Telegram Internal vs Eksternal dipisah | Skenario Website B + Telegram |
| 5 | 2026-08-19 | Hanya `InstallationActivated`; `installation.completed` dihapus dari kontrak | Keputusan pemilik produk |
| 6 | 2026-08-19 | Log keputusan ini dibuat | Analisa sebelumnya belum tercatat |

---

## 2. Kenapa Webhook untuk API 1, bukan REST

Ringkasannya ada di `README.md`. Ini alasan lengkapnya, termasuk sisi yang merugikan.

### Alasan pokok: kebutuhannya berbentuk kejadian

Permintaan aslinya *"trigger saat tombol Aktivasi ditekan"*. REST tidak bisa
menyatakan "ketika X terjadi" — ia hanya menjawab kalau ada yang bertanya. Memakai
REST berarti konsumen harus **menebak** kapan harus bertanya.

### Empat argumen pendukung

**1. Biaya polling ditanggung dua pihak, hasilnya lebih buruk.** Supaya Website B
menangkap Aktivasi dalam satu menit, ia polling tiap menit — 1.440 permintaan/hari
yang 99% lebih mengembalikan "tidak ada apa-apa". Whusnet lalu wajib menyediakan
endpoint "pelanggan yang berubah sejak X" beserta cursor, dan Website B wajib
menyimpan posisi cursor itu dengan benar. Itu **lebih banyak** kerja di kedua sisi
daripada satu POST bertanda tangan.

**2. Latensi menentukan kegunaannya.** Provisioning idealnya jalan saat teknisi masih
di lokasi — kalau layanan gagal menyala, dia masih bisa membetulkan. Webhook sampai
dalam hitungan detik; polling dalam hitungan interval.

**3. Telegram sebagai tujuan hanya bisa menerima dorongan.** Ia tidak akan pernah
menarik data dari kita. Jadi kalau Telegram Eksternal ikut jadi tujuan, mekanisme push
**harus** dibangun apa pun keputusannya — memilih REST berarti membangun keduanya.

**4. Aktivasi berulang justru butuh push.** Teknisi meralat SN lalu tekan Aktivasi
lagi. Dengan push, koreksi sampai seketika. Dengan polling, konsumen bisa mengambil
data pada jeda antara SN salah dan SN benar, memprovision dengan yang salah, dan tidak
pernah tahu ada perbaikan.

### Empat kelemahan webhook — dicatat, bukan disembunyikan

| Kelemahan | Dampak | Penawar |
|---|---|---|
| Konsumen mati = data hilang dari sisi mereka | Setelah 8 percobaan habis, kabar itu tidak pernah sampai | Endpoint baca rekonsiliasi (§3) |
| Tidak bisa backfill | Konsumen baru mulai dengan riwayat nol | Endpoint baca rekonsiliasi (§3) |
| Tidak bisa menjawab "status pelanggan X sekarang apa?" | Webhook hanya mengabarkan perubahan | Endpoint baca rekonsiliasi (§3) |
| Beban onboarding lebih berat | Konsumen wajib punya endpoint HTTPS publik + verifikasi HMAC; mitra kecil tidak bisa mulai dengan `curl` | Diterima sadar; transport `telegram` jadi jalur ringan untuk mitra yang hanya perlu kabar |

Keempatnya menunjuk ke solusi yang sama, dan itu **bukan** mengganti webhook.

---

## 3. Endpoint baca rekonsiliasi — dirancang, belum masuk fase

Belum ada di `rencana-implementasi.md` karena belum ada yang memintanya. Bentuknya
dicatat di sini supaya tidak dirancang ulang dari nol saat dibutuhkan.

```
GET /api/v1/installations/{cid}
GET /api/v1/installations?activated_since=2026-08-01T00:00:00%2B07:00&cursor=…
```

- Isi respons **identik** dengan `data` di payload webhook — dirakit presenter yang
  sama. Kalau isinya berbeda, ia jadi sumber kebenaran kedua yang pasti menyimpang.
- Auth: token klien per-konsumen, dibatasi ke `pop_id` yang sama dengan endpoint
  webhook-nya.
- Paginasi cursor, bukan offset — data bertambah selama halaman dibaca.
- Rate limit sendiri; ini endpoint pemulihan, bukan pengganti webhook. Konsumen yang
  memanggilnya tiap menit sedang salah memakai.

Prinsip yang berlaku sama seperti portal (§6.6.6): **webhook memberi tahu, API yang
jadi kebenaran.**

---

## 4. Peta pengembangan

### Murah — strukturnya sudah menyiapkan tempat

| Pengembangan | Yang perlu diubah |
|---|---|
| Event baru (`customer.suspended`, `customer.terminated`, `package.changed`, `device.replaced`) | Satu nilai di `webhook_endpoints.events` + satu listener. Outbox, retry, HMAC, purge tinggal dipakai |
| Tujuan baru (WhatsApp, email, Slack, antrean cloud) | Satu adapter. Kolom `transport` sudah jadi titik cabangnya |
| Konsumen baru | Satu baris `webhook_endpoints`. Nol kode |
| Routing per cabang | Sudah ada — `pop_id` per endpoint |

### Paling bernilai berikutnya: arah balik

Website B melaporkan hasil provisioning — `provisioning.succeeded` /
`provisioning.failed` beserta alasannya — dan Whusnet menuliskannya ke task atau
catatan pelanggan. Saat itu teknisi tahu dari sistem kita apakah layanan benar-benar
menyala, bukan dari menelepon orang.

Di titik ini API 1 berubah dari satu arah jadi lengkap, dan ia butuh **endpoint masuk**
yang diautentikasi — jadi lapisan REST dari §3 sudah terpakai. Dirancang terpisah saat
dibutuhkan; jangan diselundupkan ke fase 1.

### Butuh naik versi — jangan dianggap gratis

- Perubahan bentuk payload apa pun.
- ODP jadi master data. Sekarang ia string bebas di tiga kolom, tanpa model maupun
  tabel; begitu jadi entitas, payload berubah.

### Jangan dikembangkan

Memakai pesan Telegram sebagai pemicu tindakan otomatis. Telegram tidak bisa
diverifikasi tanda tangannya — cukup untuk kabar, tidak cukup untuk menyalakan layanan.

---

## 5. Rekomendasi terbuka: field `version` di payload

**Belum diterapkan.** Payload sekarang tidak punya penanda versi
(`README.md` §Versioning membahas versi URL `/api/v1/`, hal berbeda).

Usul: tambahkan `"version": 1` di level atas payload **sekarang**, saat belum ada satu
konsumen pun. Nanti, ketika bentuknya perlu berubah, konsumen bisa menangani dua versi
berdampingan alih-alih rusak serentak.

Menambahkannya hari ini gratis. Menambahkannya setelah tiga mitra terhubung berarti
negosiasi dengan tiga pihak.

---

## 6. Alternatif yang ditolak

Bagian terpenting dokumen ini. Setiap baris pernah terlihat masuk akal.

### API 1

| Ditolak | Kenapa |
|---|---|
| Trigger di tombol **Mulai Pemasangan** | `start()` hanya memindahkan status. SN dan ODP belum ada sama sekali — payload akan selalu kosong di dua field yang paling dibutuhkan |
| Trigger di **penyelesaian laporan** (`storeSpeedtest()`) | Terlalu belakang. Sistem luar baru tahu setelah teknisi mengisi angka speedtest, padahal data perangkat sudah siap sejak step 5 |
| **Dua event** (`started` + `completed`) | Lahir dari asumsi keliru bahwa trigger ada di tombol Start. Begitu titik pemicu benar, event kedua tidak menambah apa pun bagi konsumen — dihapus atas keputusan pemilik produk (rev. 5) |
| **Menumpang event yang sudah ada** (`InstallationStarted`/`InstallationCompleted`) | Menautkan nasib webhook eksternal ke event internal yang melayani dashboard FOP dan bisa berubah kapan saja. `InstallationCompleted` juga disiarkan dari `store()` legacy, sehingga modal admin — yang tidak punya gerbang kelengkapan data — ikut memicu webhook |
| **Klaim "nol edit controller"** | Terbukti salah setelah trigger dikoreksi: `storePemasangan()` tidak menyiarkan apa pun. Perubahan controller tak terhindarkan; lebih baik disebut terang-terangan |
| Kolom **`secret_hash`** untuk secret webhook | HMAC menuntut kedua pihak memegang rahasia yang sama. Hash satu arah membuat semua pengiriman gagal ditandatangani, tidak bisa dipulihkan tanpa menerbitkan ulang secret ke semua konsumen. Jadi `secret_encrypted` |
| **Satu baris outbox per percobaan** | Merakit ulang payload tiap percobaan membuat percobaan ke-3 bisa mengirim data yang sudah berubah, lalu dibuang penerima sebagai duplikat `event_id` — perubahan hilang tanpa jejak. Satu baris per event, `attempts` naik di tempat |
| **Simpan payload hanya saat gagal** | Terdengar hemat, tapi menghilangkan kemampuan menjawab "apa persisnya yang kalian kirim ke kami" — pertanyaan pertama setiap kali integrasi disalahkan |
| **Dua tabel outbox** (installation vs portal) | Mekanismenya identik sampai ke angka backoff. Dua tabel = dua worker, dua kebijakan retry, dua tempat mencari saat ada yang tidak sampai |
| **Telegram Eksternal memakai `config('services.telegram.*')`** | Satu bot token dan satu chat id global, dipakai enam pemanggilan internal. Pesan untuk pihak luar akan mendarat di grup internal — pemisahan batal seketika. Kredensial per-endpoint di `webhook_endpoints.config` |
| **Telegram menerima setiap penekanan Aktivasi** | Batas Telegram ~20 pesan/menit per grup; membanjiri berarti pesan dibuang, bukan sekadar berisik. Transport `telegram` melewati kiriman yang teksnya tidak berubah (`skipped`); Website B tetap menerima semua |
| **Memindahkan 6 pemanggilan Telegram Internal ke outbox** | Menyentuh empat modul sekaligus. Task tersendiri, bukan efek samping pekerjaan webhook |

### API 2

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

## 7. Temuan review 2026-08-18 dan penyelesaiannya

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
| 13 | Pemasangan revisi → provisioning dobel | `idempotency_key` |
| 14 | Semantik baris outbox ambigu | Satu baris per event, `attempts` naik di tempat |
| 15 | Dua kutipan `file:line` meleset | Dikoreksi |

Kesalahan pokok yang menyebabkan sebagian besar temuan: rancangan rev. 1 disusun dari
sapuan kode tanpa membuka `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6,
yang sudah memuat kontrak portal terkonfirmasi. Pelajaran operasionalnya sederhana —
sebelum merancang modul baru, cari dulu apakah `docs/plan/` sudah memuat keputusan
untuk area yang sama.

---

## 8. Pertanyaan yang masih terbuka

| # | Pertanyaan | Kenapa tidak ditebak |
|---|---|---|
| 1 | `{prefix_pop}` di `login_id` = `pops.registration_prefix` atau `pops.cid_prefix`? | Keduanya ada (`app/Models/Pop.php:21-22`), §6.6.2 tidak menyebut kolomnya. Salah pilih = seluruh kartu pelanggan tercetak dengan login ID yang tidak cocok |
| 2 | Nama pelanggan ikut di pesan Telegram Eksternal? | Berguna untuk dibaca manusia, tapi jadi catatan permanen di pihak luar di luar jangkauan purge 90 hari |
| 3 | Field `version` di payload ditambahkan sekarang? | Lihat §5. Gratis sekarang, mahal nanti |
| 4 | Endpoint rekonsiliasi masuk fase 1 atau menyusul? | Lihat §3. Belum ada yang memintanya |
| 5 | Beban merawat dua dokumen untuk satu portal (modul ini + QR §6.6) | Kalau mulai terasa, gabungkan — jangan biarkan keduanya menyimpang diam-diam |
