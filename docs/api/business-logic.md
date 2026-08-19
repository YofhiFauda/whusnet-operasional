# Business Logic — API Eksternal

> API 2 mengikuti kontrak yang sudah ditetapkan di
> `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6 (baris 804-1060), sebagian
> **dikonfirmasi pemilik produk**. Bagian ini merinci dan melengkapinya, tidak
> menggantikannya. Kalau ada beda, §6.6 yang menang.

## API 1 — Webhook Pemasangan (outbound)

### Satu event: tombol Aktivasi di Laporan Pemasangan

**Revisi 2026-08-19 — titik pemicunya berubah, dan rancangan sebelumnya salah.**

Versi pertama dokumen ini memakai dua event, `installation.started` (tombol Mulai
Pemasangan) dan `installation.completed` (laporan tersimpan `completed`). Alasannya:
SN dan ODP belum ada saat pemasangan dimulai, jadi satu event dianggap mustahil.

Alasan itu gugur. Laporan Pemasangan sekarang berupa wizard enam langkah, dan
**tombol "Aktivasi"** — yang tampil di panel step 6 dan mengirim form step 5 ke
`CustomerInstallationController::storePemasangan()` — adalah titik di mana keenam data
yang diminta sudah lengkap sekaligus:

| Data | Sumber saat Aktivasi ditekan |
|---|---|
| Nama | `customers.full_name`, sudah ada sejak pendaftaran |
| Cabang POP | `customers.pop_id` |
| Desa | `customers.village_id` |
| Paket | `customers.internet_package_id` |
| **SN** | baru saja ditulis ke `customer_technical_details.router_or_ont_serial` + `customer_devices.serial_number` |
| **ODP** | baru saja ditulis ke `customer_technical_details.odp_number` / `odp_port` |

Jadi satu event sudah cukup, dan dua event justru mengirim kabar setengah jadi lebih
dulu tanpa ada yang membutuhkannya:

| Event | Kapan |
|---|---|
| `installation.activated` | Teknisi menekan **Aktivasi** di Laporan Pemasangan |

`installation.started` dan `installation.completed` **dibatalkan dari rancangan.**
Kalau nanti sistem luar memang perlu tahu pekerjaan sedang berjalan, itu event
tambahan yang dirancang saat kebutuhannya muncul — bukan sekarang.

### Titik pemicu: event baru, dan controller memang harus disentuh

Berbeda dari rancangan sebelumnya, **keuntungan "nol perubahan controller" hilang di
sini.** `storePemasangan()` tidak menyiarkan event apa pun. Yang ada di repo —
`InstallationStarted` (`:101`) dan `InstallationCompleted` (`:512`) — menempel di
titik yang salah untuk kebutuhan ini.

Jadi perlu event baru, `App\Events\InstallationActivated`, di-dispatch dari
`storePemasangan()`. Satu baris, ditaruh sebagai pernyataan **terakhir sebelum
`DB::commit()`**.

Kenapa harus paling akhir: method itu menulis berurutan — `customer_installations`
(foto, catatan, status), lalu `customer_technical_details` (SN, ODP, OLT, VLAN),
material dan alat kerja, dan **terakhir `customer_devices`** (`:735-750`). Event yang
di-dispatch sebelum baris terakhir itu menghasilkan payload tanpa data perangkat yang
justru jadi alasan event ini ada.

Alternatif yang **ditolak**: mengamati (`observer`) `CustomerDevice` atau
`CustomerTechnicalDetail`. Terlihat menghindari sentuhan controller, tapi keduanya
juga ditulis dari modal admin (`store()`) dan dari jalur lain, sehingga event akan
terbit di saat yang tidak diminta — dan karena datanya tersebar di dua model, satu
Aktivasi akan memicunya **dua kali**. Event eksplisit lebih jujur soal maksudnya.

`storePemasangan()` memakai `DB::beginTransaction()` manual di `:660` dengan
`DB::commit()` di `:752`. Aturannya sama seperti sebelumnya: baris outbox ditulis **di
dalam** transaksi, pengirimannya berjalan **setelah commit**. Job yang berjalan sebelum
commit membaca data yang belum tertulis; kalau transaksi di-rollback, sistem luar
sudah menerima kabar soal pemasangan yang tidak pernah tercatat.

Satu jebakan kecil tapi nyata: presenter **harus membaca relasi yang segar**. Objek
`$customer` di dalam method itu sudah lama dimuat, dan `customerDevice` /
`customerTechnicalDetail` yang sempat ter-cache akan mengembalikan nilai sebelum
Aktivasi. Muat ulang relasinya di presenter, jangan percaya yang sudah menempel.

> **Catatan status kode:** `storePemasangan()`, `storeSpeedtest()`, dan wizard enam
> langkah di `resources/views/installations/report.blade.php` masih berupa perubahan
> yang belum di-commit saat rancangan ini ditulis. Kalau nama method, urutan step,
> atau letak tombol Aktivasi berubah sebelum implementasi dimulai, titik pemicu di
> dokumen ini ikut berubah — periksa ulang, jangan salin nomor barisnya buta.

### Payload

```json
{
  "event": "installation.activated",
  "event_id": "0f4a9b2e-7c31-4d5a-9f10-2b8e6c5a1d33",
  "idempotency_key": "installation:1174",
  "occurred_at": "2026-08-19T14:32:07+07:00",
  "data": {
    "customer": {
      "cid": "C1X4ARQ000631",
      "login_id": "PNG-RQ000631",
      "nama": "Masudah Yuni Fitri"
    },
    "pop":   { "code": "PNR-JTS", "name": "Jetis", "type": "cabang" },
    "desa":  { "id": 3517, "name": "Joresan", "kecamatan": "Mlarak", "kota": "Kabupaten Ponorogo" },
    "paket": { "code": "PKT-20M", "name": "Home 20 Mbps", "bandwidth": "20 Mbps", "harga_bulanan": "150000.00" },
    "perangkat": {
      "sn": "ZTEGC1234567", "odp": "ODP-JTS-04", "odp_port": "3",
      "olt": "OLT-JTS-01", "vlan": "120"
    },
    "task": {
      "number": "TASK-2026-0184",
      "started_at": "2026-08-19T09:12:00+07:00"
    }
  }
}
```

`idempotency_key` = `installation:{customer_installations.id}` — baris pemasangan yang
sedang dikerjakan. Lihat "Aktivasi bisa ditekan berkali-kali" di bawah; angka itu
bukan hiasan.

Tidak ada `completed_at`: saat Aktivasi ditekan, `customer_installations.installation_status`
sengaja **masih `in_progress`** (`storePemasangan():676`). Pemasangan baru berstatus
selesai di step 6 (Laporan Speedtest). Mengirim `completed_at` di sini berarti
mengarang tanggal untuk keadaan yang belum terjadi.

`harga_bulanan` adalah **string desimal**, sama seperti seluruh nominal di kedua API.

`login_id` disertakan supaya sistem luar bisa merujuk pelanggan dengan identitas yang
sama seperti portal. `cid` tetap dikirim karena itu yang tercetak di kwitansi dan
dipakai staf, tapi **`cid` tidak cocok jadi kunci** — ia nullable untuk pelanggan yang
belum aktif dan tidak punya unique constraint (lihat "Kenapa bukan cid" di bawah).
Kunci pencocokan yang stabil adalah `login_id`.

### Dari mana tiap field dibaca

`nama` = `customers.full_name`. `cid` = `customers.cid`, jatuh ke `customer_code`
kalau kosong (pola yang sama dipakai `ReceiptPresenter.php:76`). `pop` dari relasi
`Customer::pop()` (`app/Models/Customer.php:131`) — `code`, `name`, `type`. `desa`
dari `Customer::village()` (`:107`) naik ke kecamatan dan kota. `paket` dari
`Customer::internetPackage()` (`:115`) — `package_code`, `name`, `bandwidth_label`,
`monthly_price`.

**SN dan ODP tidak punya satu rumah.** Keduanya tersebar:

| Data | Sumber 1 | Sumber 2 | Sumber 3 |
|---|---|---|---|
| SN | `customer_devices.serial_number` | `customer_technical_details.router_or_ont_serial` | `customers.ont_sn` |
| ODP | `customer_devices.odp` | `customer_technical_details.odp_number` | `customers.odp_code` |

Rantai fallback kiri-ke-kanan ini mengikuti tab perangkat di halaman pelanggan,
`resources/views/customers/tabs/_device.blade.php:43`:

```php
$odpCode = $device?->odp ?: ($tech?->odp_number ?: $customer->odp_code);
```

**Peringatan: repo ini belum sepakat soal urutannya.** `TicketService.php:580`
membacanya terbalik — `$customer->odp_code ?: $device?->odp` — dan melewatkan
`customer_technical_details` sama sekali. Untuk pelanggan yang `odp_code`-nya diisi
saat pendaftaran lalu ternyata dipasang di ODP lain, kedua tempat itu menampilkan
nilai berbeda hari ini.

Webhook memakai urutan **perangkat dulu** (versi blade), karena
`customer_devices`/`customer_technical_details` diisi teknisi di lapangan saat
pemasangan, sementara `customers.odp_code` bisa berupa rencana awal dari pendaftaran.
Untuk provisioning, ODP yang benar adalah yang kabelnya betul-betul masuk.

Ada ketimpangan penulisan yang harus diketahui, bukan diperbaiki diam-diam di task
ini: **kedua** jalur penyimpanan menulis `serial_number` ke `customer_devices` tapi
tidak pernah menulis `odp`/`odp_port` ke sana — `storePemasangan()` di `:735-750` dan
`store()` di `:478-493`. Yang menerima ODP hanya `customer_technical_details`
(`storePemasangan():696-712`, `store():418-442`).

Akibatnya praktis: setelah Aktivasi, **SN ketemu di sumber 1, ODP ketemu di sumber 2.**
Rantai fallback menutupinya, tapi jangan berasumsi keduanya datang dari tempat yang
sama — sebuah presenter yang cuma membaca `customer_devices` akan mengirim ODP `null`
untuk pemasangan yang ODP-nya jelas-jelas diisi teknisi.

Perlu juga dicatat: **ODP bukan entitas.** Tidak ada model `Odp` maupun tabel `odps`
di repo — ODP hanyalah string bebas di tiga kolom. Payload mengirim kode ODP apa
adanya, tanpa id. Kalau kelak ODP jadi master data, itu perubahan payload yang butuh
versi baru.

### Perakit payload

Satu kelas, `app/Services/Webhooks/InstallationWebhookPresenter`, meniru alasan
`ReceiptPresenter` (`app/Services/Receipts/ReceiptPresenter.php:8-23`): sebelum kelas
itu ada, tiga halaman cetak merakit isi kwitansi sendiri-sendiri dan satu pembayaran
tercetak beda-beda tergantung tombol mana yang ditekan. Webhook punya risiko yang
sama begitu event kedua ditambahkan.

Aturannya sama: **event boleh berbeda, isi tidak.**

### Aktivasi bisa ditekan berkali-kali — dan itu normal

Ini bagian yang paling mudah terlewat, dan pindahnya titik pemicu ke tombol Aktivasi
justru membuatnya **lebih sering** terjadi, bukan kurang.

`storePemasangan()` menerima status `installation_in_progress` **dan**
`revision_installation` (`:573-577`), dan ia sekadar `updateOrCreate` — tidak ada
gerbang "sudah pernah diaktivasi". Jadi Aktivasi bisa ditekan berulang dalam satu
pemasangan yang sama:

- Teknisi salah ketik SN, kembali ke step 5, perbaiki, tekan Aktivasi lagi.
- Foto kontrak buram, diunggah ulang, tekan Aktivasi lagi.
- Verifikasi admin menolak → `revision_installation` → teknisi perbaiki → Aktivasi lagi.

Semuanya sah dan semuanya harus terkirim: penekanan kedua justru sering membawa SN
atau ODP yang **benar**, dan itu persis informasi yang paling dibutuhkan sistem
provisioning.

Yang rusak kalau tidak ditangani adalah kontrak idempotensinya. Dengan `event_id`
saja, penerima melihat beberapa event berbeda dan tidak punya cara tahu bahwa yang
terbaru **menggantikan** yang sebelumnya. Untuk provisioning, itu berarti beberapa
kali penyalaan layanan atau beberapa baris pelanggan untuk satu rumah.

Karena itu payload membawa **dua** kunci dengan tugas berbeda:

| Kunci | Tetap sama saat | Untuk |
|---|---|---|
| `event_id` | percobaan **ulang pengiriman** kejadian yang sama | Membuang kiriman dobel akibat jaringan |
| `idempotency_key` | Aktivasi ditekan lagi untuk pemasangan yang sama | Mengenali bahwa event ini **state terbaru** |

`idempotency_key` = `installation:{customer_installations.id}` — id baris pemasangan
yang sedang dikerjakan, yaitu baris yang diambil `storePemasangan()` lewat
`$customer->installations()->latest()->first()` (`:635`).

Dipilih id baris, bukan `customer_id` atau hitungan percobaan, karena itu yang paling
tepat menggambarkan maksudnya: **satu baris `customer_installations` = satu pemasangan
fisik.** Semua penekanan Aktivasi terhadap baris yang sama adalah koreksi atas
pemasangan yang sama, jadi kuncinya memang harus sama. Kalau kelak revisi membuat
baris pemasangan baru, kuncinya berubah dengan sendirinya — dan itu pun benar, karena
saat itu memang pemasangan yang berbeda.

Penerima memperlakukan event ini sebagai **upsert atas state pemasangan**, bukan
penambahan — yang terakhir menang, sama semangatnya dengan "payload berisi STATE
penuh, bukan delta" di §6.6.6.

### Pengiriman, kegagalan, dan pengulangan

Pengiriman lewat outbox + job terantre (Horizon sudah terpasang). Retry backoff:
1 menit → 5 menit → 30 menit → 2 jam → 6 jam, maksimal 8 percobaan — angka yang sama
dengan §6.6.6 supaya tidak ada dua kebijakan retry di satu sistem.

Setelah percobaan terakhir gagal, `webhook_endpoints.consecutive_failures` naik;
melewati ambang, endpoint dinonaktifkan otomatis dan Owner diberi tahu. Endpoint mati
yang terus dicoba selamanya hanya menumpuk antrean — tapi kegagalan **tidak boleh
hilang diam-diam**, jadi baris outbox berstatus `failed` tetap tinggal sebagai daftar
yang bisa direkonsiliasi.

Urutan tidak dijamin. Kalau `started` gagal dan masuk backoff sementara `completed`
lolos, konsumen bisa menerima terbalik. `occurred_at` adalah penentu urutan, bukan
waktu terima.

### Keamanan

Header: `X-Whusnet-Signature: t=<unix>,v1=<hex>`, dengan `v1` = HMAC-SHA256 atas
`"{t}.{raw body}"` memakai secret endpoint.

Penerima wajib:

1. Menolak kalau selisih `t` terhadap waktu sekarang lebih dari 5 menit.
2. Menghitung ulang signature atas **raw body**, bukan hasil `json_decode` lalu
   `json_encode` ulang — susunan kunci bisa berubah dan signature gagal tanpa sebab
   yang kelihatan.
3. Membandingkan dengan `hash_equals`, bukan `===`.

**Secret disimpan terenkripsi simetris, BUKAN di-hash.** Ini perbedaan yang gampang
salah dan mahal: HMAC menuntut kedua pihak memegang rahasia yang **sama**, jadi kita
harus bisa membacanya kembali setiap kali menandatangani. `Hash::make()` di kolom ini
membuat semua pengiriman gagal ditandatangani setelah endpoint dibuat, dan tidak bisa
dipulihkan tanpa menerbitkan ulang secret ke semua konsumen. Kolomnya dinamai
`secret_encrypted` justru supaya salah paham ini tidak terjadi. Plaintext hanya
ditampilkan sekali, saat pembuatan.

Endpoint wajib HTTPS, didaftarkan manual oleh Owner, tidak ada self-service.

Endpoint boleh diikat ke satu `pop_id`. Ini bukan kenyamanan: CLAUDE.md menyatakan
setiap query pelanggan wajib lewat pembatasan POP, dan webhook adalah query pelanggan
yang hasilnya dikirim keluar organisasi. Endpoint tanpa `pop_id` menerima seluruh
cabang dan harus diperlakukan sebagai keputusan sadar.

### Konsumen pertama: gateway AI agent (OpenClaw) → Telegram

Rencana penggunaannya: sebuah gateway/orkestrator antar-tool berlangganan event
pemasangan, lalu menyampaikannya ke Telegram lewat LLM. **Whusnet tidak berubah sama
sekali untuk ini** — gateway cukup didaftarkan sebagai satu baris `webhook_endpoints`
biasa. Tidak ada endpoint baru, tidak ada kolom `channel`, tidak ada kode Telegram di
repo ini.

Pembagian tugasnya tegas: **Whusnet mendorong fakta, gateway memutuskan kalimatnya.**
Itu sebabnya bentuk yang sudah dirancang (JSON + HMAC + `event_id` +
`idempotency_key`) cocok apa adanya — semuanya kontrak mesin-ke-mesin, bukan teks
siap-kirim.

Yang **tidak** boleh dilakukan: memanggil `TelegramBotService` dari listener webhook.
Terlihat lebih pendek, tapi memindahkan tiga masalah ke dalam repo ini yang sebenarnya
sudah dipegang gateway — Telegram tidak mengenal `event_id` (retry setelah respons
timeout menghasilkan pesan dobel di grup), punya rate limit sendiri (~30 pesan/detik
global, ~20/menit per grup) yang tidak sejalan dengan backoff kita, dan
`TelegramBotService::sendMessage()` (`app/Services/TelegramBotService.php:41-52`)
mengembalikan `bool` sambil menelan galat, sehingga outbox tidak bisa membedakan 429
"perlambat" dari 401 "token salah".

Kewajiban di sisi gateway, dan ini bagian dari kontrak:

- **Dedupe pakai `event_id`.** Retry kita mengirim id yang sama; tanpa dedupe, satu
  jaringan lambat jadi beberapa pesan identik di grup.
- **Perlakukan `idempotency_key` sebagai penanda pengganti.** Pemasangan revisi
  Aktivasi ditekan lagi (koreksi SN, foto ulang, revisi) menerbitkan `installation.activated`
  lagi dengan kunci yang sama — itu koreksi, bukan pemasangan kedua.
- **Verifikasi signature.** Meskipun gateway milik sendiri, endpoint-nya tetap
  terbuka di internet. Tanpa verifikasi, siapa pun yang tahu URL-nya bisa menyuntikkan
  "pemasangan selesai" palsu ke grup operasional.

**PII berpindah, tidak hilang.** Payload memuat nama, desa, paket, SN, dan ODP.
Whusnet tidak memangkasnya — sistem provisioning butuh data itu. Yang harus diputuskan
di sisi gateway adalah apa yang benar-benar sampai ke chat: grup Telegram menyimpan
riwayat, dan siapa pun yang ditambahkan belakangan bisa membacanya ke belakang. Untuk
grup operasional, CID + POP + SN/ODP biasanya sudah cukup tanpa nama pelanggan.

**Diam di Telegram bukan berarti tidak ada pemasangan.** Kalau gateway mati, outbox
menahan dan mengulang; baris `failed` tetap tinggal sebagai daftar rekonsiliasi.
Halaman riwayat pengiriman adalah tempat memeriksanya — bukan grup chat.

#### Yang sengaja belum dirancang: API baca untuk mesin

Gateway yang menjalankan LLM biasanya juga ingin **bertanya** ("berapa pemasangan
selesai hari ini di Jetis?", "status pelanggan C1X4ARQ000631?"). Itu permukaan ketiga
— API operasional untuk mesin, dengan token mesin, POP scope, dan daftar putih kolom
sendiri — dan **tidak ada di dokumen ini**. Keputusan saat ini: agent hanya menerima
event.

Batas itu ditulis di sini supaya tidak longgar diam-diam. Menaikkannya dari "terima
event" ke "boleh bertanya" adalah penambahan permukaan yang terukur. Menaikkannya ke
**"boleh bertindak"** adalah kategori yang berbeda: agent yang boleh mengubah data
sekaligus membaca teks yang ditulis orang luar — `detail_keluhan` sebuah tiket diisi
pelanggan — berarti kalimat di dalam tiket itu bisa berfungsi sebagai perintah. Untuk
sistem yang memegang tagihan dan saldo, itu dirancang sendiri dengan daftar putih
aksi, konfirmasi manusia untuk aksi berisiko, dan audit terpisah. Jangan diselundupkan
sebagai "sekalian" ke fase webhook.

---

## API 2 — Portal Pelanggan

Prefix `/api/customer-portal` (§6.6.4).

### Portal adalah aplikasi terpisah — konsekuensinya menyebar ke mana-mana

§6.6.1 menetapkan, dan pemilik produk mengonfirmasi: portal berjalan **di domain
berbeda, tanpa kredensial DB operasional, tanpa replika**. Ia klien tipis yang tidak
menghitung apa pun sendiri — sisa tagihan, status lunas, dan status tiket datang sudah
jadi dari API.

Ini bukan detail deployment. Ia menentukan tiga hal di dokumen ini: kenapa CORS wajib,
kenapa ada client secret di samping token pelanggan, dan kenapa kebutuhan #3
("kwitansi terkirim") tidak selesai hanya dengan menyediakan endpoint.

### Autentikasi

**Login ID = `{prefix_pop}-{customer_code}`** (mis. `PNG-RQ000631`), dicetak di kartu
pelanggan bersama PIN (§6.6.2).

#### Kenapa bukan `cid`

`cid` terlihat seperti pilihan alami — unik per-POP, tercetak di kwitansi
(`ReceiptPresenter.php:76`), tidak berubah. Tapi sebagai kunci login ia gagal di dua
titik yang keduanya berakhir buruk:

- **Tidak ada unique constraint.** Yang ada cuma index biasa,
  `customers_cid_idx` (`2026_07_24_145737_add_customer_search_prefix_indexes.php:27`).
  Yang benar-benar unique adalah composite `(pop_id, customer_code)`
  (`2026_07_20_141841_scope_customer_code_unique_to_pop.php`). CLAUDE.md sendiri
  memperingatkan risiko tabrakan ID legacy lintas cabang. Sebuah
  `where('cid', $x)->first()` yang menerbitkan token untuk baris pertama yang cocok
  adalah pengambilalihan akun yang menunggu terjadi.
- **Nullable.** Pelanggan yang belum aktif belum punya CID, padahal sudah punya
  tagihan pemasangan yang justru ingin ia lihat.

`login_id` menutup keduanya: `customer_code` unik per POP, dan prefix POP-lah yang
melengkapinya jadi unik global. Ia juga bukan `display_id` — `display_id` berubah
RQ↔CID seiring lifecycle, jadi login ID yang memakainya akan basi begitu pelanggan
aktif.

#### Token

Tabel sendiri, `customer_portal_tokens` (§6.6.2) — **bukan** menumpang Sanctum
`personal_access_tokens` yang polymorphic. Alasannya bukan preferensi: tabel itu akan
dipakai bersama `users` (staf), dan mencampur kredensial pelanggan dengan kredensial
staf berarti satu bug pada scoping token berpotensi menyeberangkan hak akses antar
dua populasi yang seharusnya tidak pernah bersinggungan. Repo juga sudah punya pola
tabel token eksplisit (`customer_qr_tokens`).

Efek samping yang menguntungkan: **Sanctum tidak jadi dibutuhkan.** Tidak ada
dependensi baru yang perlu disetujui untuk API 2.

- `access_token` **15 menit**, `refresh_token` **30 hari, rotating, sekali pakai**.
- Refresh token yang dipakai dua kali = indikasi token dicuri → seluruh rantai
  turunannya dicabut, pelanggan login ulang. Tanpa aturan ini, pencuri token bisa
  memperpanjang akses selamanya tanpa terdeteksi.
- Token disimpan sebagai **hash**, diverifikasi `hash_equals`. Berbeda dari secret
  webhook, token tidak pernah perlu dibaca ulang — jadi di sini hash memang benar.
- Portal menyimpan token di sesi server-side HttpOnly, **bukan** localStorage.
- Pelanggan `terminated` → akun dinonaktifkan dan token dicabut lewat
  `CustomerObserver`.

### Aktivasi akun: PIN, bukan password dari admin

Jalur resminya `POST /auth/claim` dengan `login_id` + PIN 6 digit dari kartu
(§6.6.5). Pelanggan menetapkan sendiri passwordnya (≥10 karakter, ditolak kalau memuat
login_id/nomor HP/tanggal lahir, dicek terhadap daftar password umum).

**Admin tidak boleh men-set password pelanggan.** Argumennya sudah dipakai untuk PIN
di §6.5.2 dan berlaku sama di sini: begitu ada orang lain yang tahu password
pelanggan, password berhenti berfungsi sebagai bukti identitas — dan pembuktian itu
persis satu-satunya gunanya. Untuk lupa password, yang diterbitkan helpdesk adalah
**PIN klaim baru**, bukan password pilihan admin.

Ini juga jawaban untuk ~1.900 pelanggan legacy: mereka masuk lewat jalur klaim yang
sama begitu kartu ber-PIN sampai, bukan lewat password sementara yang diketik petugas.

### Daftar endpoint

| Metode | Path | Fungsi |
|---|---|---|
| POST | `/auth/login` | `login_id` + password → access + refresh token |
| POST | `/auth/claim` | `login_id` + PIN → tetapkan password pertama |
| POST | `/auth/refresh` | refresh token → pasangan token baru (rotating) |
| POST | `/auth/logout` | cabut token yang sedang dipakai |
| POST | `/auth/logout-all` | cabut semua token pelanggan itu |
| PUT | `/me/password` | ganti password |
| GET | `/me` | profil ringkas, status layanan, paket aktif |
| GET | `/me/invoices` | daftar tagihan (filter `status`, `period`; paginasi) |
| GET | `/me/invoices/{invoice_number}` | detail tagihan + pembayaran yang menempel |
| GET | `/me/payments` | riwayat pembayaran |
| GET | `/me/payments/{payment_number}/receipt` | isi kwitansi |
| GET | `/me/balance` | saldo + mutasi |
| GET | `/me/tickets` | riwayat ticketing |
| GET | `/me/tickets/{ticket_number}` | detail tiket + riwayat |

Semua butuh header `X-Portal-Client` + client secret, di samping bearer token.

### #1 — Ganti password

`PUT /me/password` dengan `current_password` dan `new_password`.

`current_password` **wajib**. Sesi yang dicuri tidak boleh cukup untuk mengambil alih
akun secara permanen.

Setelah berhasil: **semua token pelanggan itu dicabut kecuali sesi yang sedang
dipakai**, `password_changed_at` distempel, pelanggan diberi tahu, dan audit mencatat
siapa/kapan/IP — **tidak pernah passwordnya**.

### #2 — Tagihan dan pembayaran

Daftar putih kolom mengikuti §6.6.4.

**Invoice** — keluar: `invoice_number`, `invoice_type`, `billing_period`,
`issue_date`, `due_date`, `total_amount`, `paid_amount`, `remaining_amount`,
`invoice_status` + label. Haram: `id`, `pop_id`, `customer_service_id`,
`internet_package_id`, `old_invoice_id`, `old_cost_id`, `old_request_id`.

**Payment** — keluar: `payment_number`, `payment_date`, `billing_period`, `amount`,
`overpay_amount`, `payment_method`, `payment_status` + label, ada/tidaknya kwitansi.
Haram: `id`, `received_by`, `collected_by`, `collector_deposit_id`,
`payment_batch_id`, `cash_deposit_id`, `idempotency_key`, `old_*`, `note`,
`reject_reason`, `rejected_by`, `proof_file`.

Tiga yang perlu penjelasan:

- **`overpay_amount` justru wajib keluar.** Kelebihan bayar adalah uang pelanggan.
  Kolomnya ada (`2026_08_03_140001_add_overpay_amount_to_payments_table.php`) dan
  sudah tampil di kwitansi sebagai `lebih_bayar`. Kalau kwitansi mencantumkannya tapi
  daftar pembayaran di portal tidak, yang lahir adalah sengketa — persis biaya yang
  lebih mahal daripada menampilkannya.
- **`billing_period` ikut keluar di daftar pembayaran**, supaya pelanggan bisa
  mencocokkan pembayaran dengan bulan tagihannya tanpa membuka satu per satu.
- **`reject_reason` haram.** Isinya alasan internal ("setoran kolektor belum masuk",
  "bukti transfer tidak terbaca") — sebagian menyangkut petugas, sebagian terbaca
  sebagai tuduhan. Pembayaran `ditolak` ditampilkan sebagai **"belum terverifikasi —
  hubungi admin"**, titik. Yang penting: ia **tetap ditampilkan**. Menyembunyikannya
  membuat uang yang sudah diserahkan ke kolektor lenyap dari layar pelanggan tanpa
  penjelasan.

`paid_amount`, `remaining_amount`, dan `invoice_status` **dibaca apa adanya dari
kolom**, tidak dihitung ulang di lapisan API maupun di portal.
`Invoice::recalculateFromPayments()` (`:172-203`) adalah satu-satunya sumber kebenaran
ketiga nilai itu, dan ia sudah memperhitungkan bahwa hanya payment `VALID` yang
dihitung (`:183`) serta invoice `BATAL` dilewati (`:174`).

Semua nominal keluar sebagai **string desimal**.

### #3 — Kwitansi ke portal

Permintaannya: "setelah pembayaran selesai, kwitansi terkirim ke portal pelanggan,
baik lewat kolektor maupun langsung." Kebutuhan ini punya **dua bagian**, dan
memuaskan salah satunya saja meninggalkan lubang.

#### Bagian A — isi kwitansi: tidak ada yang perlu dibangun

Kwitansi di sistem ini bukan dokumen yang disimpan — ia turunan dari baris `payments`.
Nomor kwitansi *adalah* `payments.payment_number` (`ReceiptPresenter.php:55`); tidak
ada sekuens kwitansi terpisah. Seluruh isinya dirakit `ReceiptPresenter::for()`, dan
ketiga bentuk cetakan yang ada (thermal, A4, kartu kolektor) membaca kunci yang sama.

Karena endpoint portal hanya menanyakan `payments` milik pemilik token, kwitansi
tersedia begitu pembayaran tersimpan — dari jalur mana pun: admin/kasir
(`app/Services/PaymentService.php:81-98`), batch kolektor (`CollectorPaymentService`),
atau kolektor mencatat sendiri (`CollectorPaymentController@store`). Tidak ada
duplikasi data dan tidak ada kemungkinan isi portal menyimpang dari struk yang
dipegang pelanggan.

**Tapi presenter tidak boleh dikembalikan apa adanya.** Ia dirancang untuk cetakan
internal, dan tiga kuncinya adalah data pegawai:

| Kunci presenter | Nasib di API |
|---|---|
| `penerima` (`:99`) | **Dibuang** — nama pegawai penerima |
| `penagih` (`:100-101`) | **Dibuang** — nama kolektor, atau "Kasir POP X" |
| `catatan` (`:106`) | **Dibuang** — `payments.note`, catatan kerja internal |
| `nomor`, `tanggal_bayar`, `metode`, `pelanggan`, `invoice`, `dibayar`, `lebih_bayar`, `keterangan_cicilan`, `status`, `status_valid` | Keluar |

Tanpa pemangkasan ini, satu endpoint kwitansi membatalkan daftar putih endpoint
`/me/payments` di sebelahnya — `received_by` dan `collected_by` dilarang di sana, lalu
keluar lewat sini sebagai nama lengkap.

Dua kunci yang **wajib** ikut: `status_valid` (`:60-61`), yang ada justru karena
lembar A4 dulu mencetak semua kwitansi hijau termasuk yang ditolak; dan
`keterangan_cicilan` (`:63`), supaya pembayaran sebagian tidak terbaca sebagai
pelunasan.

Presenter mengembalikan nilai terformat untuk cetak (`"Rp 150.000"`, `"18/08/2026"`).
Resource menambahkan pendamping mentah — `dibayar_raw` sebagai **string desimal**,
`tanggal_bayar_iso` sebagai ISO-8601 — tanpa mengubah kunci yang dipakai ketiga view.

Kalau kelak ada berkas kwitansi terunggah yang perlu diambil pelanggan, ia
**di-stream lewat controller yang memeriksa kepemilikan token**, dari disk `local`
privat — pola sama dengan `TicketController::download()`. Jangan pernah mengirim URL
storage ke portal: URL yang bocor jadi akses permanen tanpa autentikasi.

#### Bagian B — kabar bahwa pembayaran selesai: butuh outbox

Karena portal aplikasi terpisah tanpa akses DB, ia **tidak tahu** ada pembayaran baru
sampai seseorang membuka halaman. Kata "terkirim" di kebutuhan #3 menagih bagian ini,
dan ia tidak gratis.

Mekanismenya sudah ditetapkan §6.6.6 dan dokumen ini mengikutinya:

- **Titik picu satu-satunya: `Invoice::recalculateFromPayments()`.** Bukan
  `PaymentObserver`. Semua jalur lewat sana — bayar satuan, bulk, batch kolektor,
  **dan penolakan/pembatalan pembayaran**. Observer pembayaran melewatkan jalur reject
  dan menembak sebelum invoice selesai dihitung.
- Baris `webhook_outbox` di-INSERT **di dalam** transaksi, dikirim setelah commit.
- Event `invoice.updated` membawa **state penuh**, bukan delta: `invoice_status`,
  `total_amount`, `paid_amount`, `remaining_amount` sebagai string desimal. Event bisa
  hilang, dobel, atau datang tidak berurutan; dengan state penuh, yang terakhir
  menang. Dengan delta, satu event dobel langsung membuat angka di portal salah.
- **Payload tidak memuat PII** — hanya `login_id`, nomor dokumen, dan nominal. Tanpa
  nama, alamat, nomor HP. Ini kebalikan dari webhook pemasangan, yang memang harus
  membawa identitas; bedanya disengaja.
- Portal boleh menampilkan isi webhook, tapi tidak menyimpannya sebagai sumber. Kalau
  webhook hilang, halaman tetap benar karena menarik dari `GET /me/invoices`.
- **Yang tidak dikirim: apa pun yang belum final.** Pembayaran yang masih menunggu
  verifikasi tidak memicu event "lunas".

Notifikasi **ke pelanggan sebagai manusia** (WA/SMS/push) tetap di luar cakupan.
`Customer` bukan `Notifiable` dan `SendCustomerActivationNotification`
(`app/Jobs/SendCustomerActivationNotification.php:19-29`) masih menulis "Simulasi
Telegram dikirim ke…". Yang dijanjikan di sini adalah portal yang **isinya sudah
benar** saat pelanggan membukanya, bukan pemberitahuan yang mengetuk pelanggan.

### #4 — Riwayat ticketing

Prasyarat: relasi `Customer::tickets()` **belum ada**. Satu-satunya query tiket
per-pelanggan di repo adalah `TicketController@duplicates`
(`app/Http/Controllers/TicketController.php:596-620`), untuk deteksi duplikat di form.

Hati-hati: blok berjudul "Riwayat Ticketing" di halaman detail pelanggan
(`CustomerController.php:976-990`) sebenarnya memuat `tasks` dan `fopTasks`, **bukan**
`tickets`. Jangan mencontoh blok itu.

**Boleh keluar:** `ticket_number`, tanggal dibuat, kategori keluhan, `detail_keluhan`,
status versi pelanggan, `resolved_at`.

**Haram keluar** (§6.6.7): `catatan_teknis` (kolom ini sengaja dipisah dari
`detail_keluhan` supaya catatan internal NOC tidak tercampur — mengirimkannya
membatalkan pemisahan itu), `handler`/`status` mentah, `fop_task_id` dan nomor
`TFOP-`/`TASK-`, `ticket_histories` mentah beserta nama pegawai, lampiran, koordinat,
dan snapshot perangkat.

#### Status tiket: jangan baca `tickets.status`

Ini jebakan yang paling mahal di fitur ini. **Begitu `handler = FOP`,
`TicketHandlingStatus` berhenti bermakna** — status sesungguhnya turun dari
FopTask/Task. Presenter yang cuma membaca `tickets.status` akan menampilkan "Sedang
Ditangani" **selamanya** untuk tiket yang sudah lama selesai di lapangan.

Repo sudah menyelesaikan separuhnya. `Ticket::resolveStatus()`
(`app/Models/Ticket.php:439`) mengembalikan `TaskStatus` dari FopTask saat
`handler = FOP`, dan `null` selain itu — **pakai method ini**, jangan menulis
resolusi kedua.

Yang **tidak** bisa dipakai adalah `Ticket::statusLabel()` (`:447`). Ia label untuk UI
staf dan mengembalikan "Diproses NOC", "Ditangani Helpdesk", "Selesai (Helpdesk)" —
persis struktur organisasi internal yang §6.6.7 larang keluar. Ia juga mengembalikan
"Terputus" untuk tiket orphan, istilah yang tidak berarti apa-apa bagi pelanggan.

Portal butuh presenter sendiri, bertumpu pada `resolveStatus()`:

| Kondisi internal | Tampil di portal |
|---|---|
| `handler=helpdesk`, `status=open` | Diterima |
| `handler=noc`, `status=open` | Sedang Ditangani |
| `handler=fop`, `resolveStatus()` belum selesai | Sedang Ditangani |
| `status=closed`, atau `resolveStatus()` selesai | Selesai |
| `status=cancelled`, atau FopTask dibatalkan | Dibatalkan |
| `handler=fop`, FopTask hilang (orphan) | Sedang Ditangani |

Baris terakhir disengaja: tiket orphan adalah kegagalan data internal. Menampilkannya
sebagai "Terputus" memindahkan masalah kita ke layar pelanggan. Ia tetap "Sedang
Ditangani" sampai seseorang membereskannya, dan `Ticket::isOrphan()`
(`app/Models/Ticket.php:83`) sudah tersedia untuk memunculkannya di sisi internal.

Wajib ada test untuk tiket pasca-FOP yang sudah selesai. Baca
`docs/ticketing/business-logic.md` sebelum menulis presenternya.

### Kepemilikan data — penjaga tunggal portal

Portal tidak punya RBAC. Penggantinya satu aturan: **setiap query difilter
`customer_id` milik token.**

Cara menegakkannya bukan dengan mengingat menulis `->where()` di tiap controller baru
— itu cara yang gagal begitu ada controller kelima. Sediakan satu titik (base
controller atau trait `ScopedToAuthenticatedCustomer`) yang membuka query sudah
terfilter, dan biarkan controller hanya menambah filter tampilan.

- **`customer_id` tidak pernah datang dari request.** Tidak sebagai query string,
  tidak sebagai body, tidak sebagai header. Portal secara struktural **tidak mampu**
  meminta data orang lain, bukan sekadar "tidak seharusnya".
- **`EffectiveAccessService` tidak dipanggil di jalur portal.**
- **Binding pakai nomor dokumen** (`INV-…`, `PAY-…`, `TKT-…`), bukan `id`
  autoincrement — id berurutan mengundang enumerasi dan membocorkan volume bisnis.
  Lalu **tetap** verifikasi kepemilikan. Nomor yang bukan miliknya dijawab **404**.

### Yang sengaja tidak masuk rancangan

- Pembayaran online / payment gateway — ditahan, lihat §6.6.8.
- Pelanggan membuat tiket sendiri. Alur ticketing bertumpu pada helpdesk yang
  menyaring dan melengkapi snapshot pelanggan; membuka pembuatan tiket dari luar
  melewati penyaringan itu dan menyentuh bagian paling rawan di repo.
- Ubah data pelanggan dari portal. Portal read-only kecuali ganti password.
- UI portal itu sendiri — proyek dan repo terpisah.
