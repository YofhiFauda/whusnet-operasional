# Business Logic — API Eksternal

> API 2 mengikuti kontrak yang sudah ditetapkan di
> `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6 (baris 804-1060), sebagian
> **dikonfirmasi pemilik produk**. Bagian ini merinci dan melengkapinya, tidak
> menggantikannya. Kalau ada beda, §6.6 yang menang.

## API 1 — Webhook Pemasangan (outbound)

### Titik pemicu: tombol "Aktivasi Laporan Speedtest"

Bukan tombol **Mulai Pemasangan**, dan bukan penyelesaian laporan. Pemicunya adalah
tombol **Aktivasi Laporan Speedtest** di wizard Laporan Pemasangan
(`resources/views/installations/report.blade.php:665`), yang men-submit form step 5
(`:385`) ke `CustomerInstallationController::storePemasangan()`
(`app/Http/Controllers/CustomerInstallationController.php:569`).

Docblock methodnya menyebut perannya persis (`:558-566`): *"Ini 'tombol Aktivasi' yang
membuka Laporan Speedtest (step 6)"*.

**Kenapa titik ini yang benar, dan bukan yang lain:**

| Kandidat | Endpoint | Kenapa tidak |
|---|---|---|
| Tombol Mulai Pemasangan | `start()` `:33` | Hanya memindahkan status ke `installation_in_progress`. SN dan ODP belum ada sama sekali |
| Simpan Laporan Speedtest | `storeSpeedtest()` `:772` | Terlalu belakang — pemasangan fisik sudah selesai dan sistem luar baru diberi tahu setelah teknisi mengisi angka speedtest |
| **Aktivasi Laporan Speedtest** | **`storePemasangan()` `:569`** | **Semua enam data sudah tersimpan, dan pemasangan fisik baru saja rampung** |

Wizard Laporan Pemasangan sengaja dipecah dua submit. Step 5 (`storePemasangan`)
menyimpan perangkat, SN, ODP, foto, dan material — **tanpa** menyelesaikan task
maupun memindahkan workflow (`:675-677`: status instalasi tetap `in_progress`).
Step 6 (`storeSpeedtest`) barulah titik penyelesaian. Tombol Aktivasi adalah gerbang
di antara keduanya: ia menyimpan step 5 dan membuka panel speedtest.

Itu berarti tepat pada saat tombol ditekan, keenam data yang diminta sudah ada di
database — dan itulah yang membuat permintaan satu-trigger bisa dipenuhi apa adanya.

### Satu event: `installation.activated`

| Data diminta | Sumber saat Aktivasi ditekan | Status |
|---|---|---|
| Nama | `customers.full_name` | Sudah ada sejak pendaftaran |
| Cabang POP | `customers.pop_id` → `pops` | Sudah ada sejak pendaftaran |
| Desa | `customers.village_id` → `villages` | Sudah ada sejak pendaftaran |
| Paket | `customers.internet_package_id` | Sudah ada sejak pendaftaran |
| **SN** | `serial_number` → ditulis `:699` & `:740` | **Baru saja disimpan** |
| **ODP** | `odp_number`/`odp_port` → ditulis `:700-701` | **Baru saja disimpan** |

**`installation.activated` adalah satu-satunya event di API 1.** Keputusan pemilik
produk, 2026-08-19. Konsumen membutuhkan data perangkat untuk provisioning, dan itu
sudah lengkap saat Aktivasi ditekan — kabar penyelesaian pemasangan tidak menambah
apa pun bagi mereka.

Aturan turunannya, dan ini yang paling sering dilanggar belakangan: **jangan pernah
memasang listener webhook pada event instalasi lain yang sudah ada di aplikasi.**
Modul ini punya satu event dan satu titik pemicu. Kalau suatu saat konsumen benar-benar
meminta kabar penyelesaian, itu event baru yang dirancang tersendiri, bukan listener
tambahan yang ditempelkan ke event lama karena "kebetulan sudah ada".

### Konsekuensi: controller HARUS disentuh

Revisi sebelumnya dokumen ini menyatakan webhook bisa dipasang tanpa mengubah
`CustomerInstallationController` sama sekali, dengan menumpang event yang sudah
disiarkan di sana. **Itu tidak berlaku untuk titik pemicu yang benar:**
`storePemasangan()` tidak menyiarkan apa pun.

Jadi implementasinya perlu satu kelas event baru — `App\Events\InstallationActivated`,
membawa `Customer` dan memakai `SerializesModels`, mengikuti bentuk event instalasi
lain di `app/Events/` — di-dispatch di dalam transaksi `storePemasangan()`, sebelum
`DB::commit()` di `:751`.

Perubahannya kecil dan terisolasi: satu baris dispatch, satu kelas event, satu
listener. Tapi ia tetap perubahan pada berkas 900+ baris yang memegang alur paling
rawan di modul pemasangan, jadi disebut terang-terangan alih-alih disembunyikan di
balik klaim "nol edit".

**Event baru, bukan memakai ulang yang sudah ada.** Event instalasi yang sekarang
disiarkan di controller ini melayani dashboard realtime FOP. Mengubah artinya, atau
menambahkan listener webhook di atasnya, akan mengubah perilaku konsumen yang sudah
berjalan — dan menautkan nasib webhook eksternal ke event internal yang bisa berubah
kapan saja tanpa ada yang ingat webhook ikut mendengarkan.

### Perhatian: `store()` legacy masih hidup

Endpoint lama `customers.installation.store` (`routes/web.php:530` → `store()` `:241`)
**tidak dihapus**. Ia dipakai modal admin di
`resources/views/customers/tabs/_installation.blade.php`, dengan radio
`installation_status` manual.

Artinya ada dua jalur berbeda yang bisa menyimpan data pemasangan: wizard teknisi
(dua submit) dan modal admin (satu submit). Rancangan ini memilih **hanya wizard
teknisi** — itu jalur lapangan yang sebenarnya dan satu-satunya yang punya gerbang
kelengkapan data (3 foto wajib + material terpakai). Modal admin tidak memicu webhook.

Kalau kelak jalur admin juga perlu memicu webhook, ia men-dispatch
`InstallationActivated` yang sama secara eksplisit — keputusan sadar, bukan efek
samping dari menumpang event lain.

### Payload

```json
{
  "event": "installation.activated",
  "event_id": "0f4a9b2e-7c31-4d5a-9f10-2b8e6c5a1d33",
  "idempotency_key": "installation:8842:activation:3",
  "occurred_at": "2026-08-18T14:32:07+07:00",
  "data": {
    "customer": {
      "cid": "C1X4ARQ000631",
      "nama": "Masudah Yuni Fitri"
    },
    "pop":   { "code": "PNR-JTS", "name": "Jetis", "type": "cabang" },
    "desa":  { "id": 3517, "name": "Joresan", "kecamatan": "Mlarak", "kota": "Kabupaten Ponorogo" },
    "paket": { "code": "PKT-20M", "name": "Home 20 Mbps", "bandwidth": "20 Mbps", "harga_bulanan": "150000.00" },
    "perangkat": {
      "sn": "ZTEGC1234567", "odp": "ODP-JTS-04", "odp_port": "3",
      "olt": "OLT-JTS-01/1/3", "vlan": "120"
    },
    "task": {
      "number": "TASK-2026-0184",
      "started_at": "2026-08-18T09:12:00+07:00"
    }
  }
}
```

`harga_bulanan` adalah **string desimal**, sama seperti seluruh nominal di kedua API.

**`login_id` DI-OMIT dari payload** (keputusan implementasi 2026-08-20, beda dari
rancangan awal di atas): tabel `customer_portal_accounts` (API 2) belum ada, jadi
belum ada sumber data nyata untuk field ini. `cid` tetap dikirim — nullable untuk
pelanggan yang belum aktif dan tidak punya unique constraint (lihat "Kenapa bukan
cid" di bawah), tapi ia satu-satunya identitas yang dikirim untuk fase ini. Begitu
API 2 jadi, `login_id` ditambahkan sebagai **perubahan payload versi baru** — bukan
ditambal diam-diam ke payload yang sudah berjalan.

`perangkat.olt` = gabungan `olt_number`/`olt_slot`/`olt_port` (`customer_technical_details`)
dipisah `/`, bagian kosong di-skip — bukan `olt_number` polos. Fallback ke
`customers.olt_code` kalau ketiganya kosong. Tidak ada preseden format gabungan lain
di repo ini; kalau Website B butuh slot/port terpisah, itu perubahan payload
tersendiri.

Tidak ada `task.completed_at`: pada saat Aktivasi ditekan, task PSB memang belum
selesai — penyelesaiannya terjadi di step 6. Mengirim kunci yang selalu `null` hanya
mengundang konsumen menunggunya terisi.

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

**Untuk ODP, sumber 1 hampir selalu kosong — dan itu bukan kebetulan.**
`storePemasangan()` menulis `odp_number`/`odp_port` ke `customer_technical_details`
(`:700-701`) tapi **tidak** menulis `odp`/`odp_port` ke `customer_devices`
(`:735-750`). Ketimpangan yang sama ada di `store()` legacy (`:418-442` vs
`:478-493`). Jadi ODP hasil pemasangan praktis selalu ketemu di sumber 2, dan rantai
fallback bukan kemewahan defensif — ia jalur normal.

Merapikan penulisan ganda itu adalah task tersendiri dengan risikonya sendiri. Jangan
diselundupkan ke pekerjaan webhook.

Perlu juga dicatat: **ODP bukan entitas.** Tidak ada model `Odp` maupun tabel `odps`
di repo — ODP hanyalah string bebas di tiga kolom. Payload mengirim kode ODP apa
adanya, tanpa id. Kalau kelak ODP jadi master data, itu perubahan payload yang butuh
versi baru.

### Perakit payload

Satu kelas, `app/Services/Webhooks/InstallationWebhookPresenter`, meniru alasan
`ReceiptPresenter` (`app/Services/Receipts/ReceiptPresenter.php:8-23`): sebelum kelas
itu ada, tiga halaman cetak merakit isi kwitansi sendiri-sendiri dan satu pembayaran
tercetak beda-beda tergantung tombol mana yang ditekan.

Di sini presenter tetap dibutuhkan meski event-nya cuma satu, karena **tujuannya
lebih dari satu**. Website B menerima JSON, Telegram Eksternal menerima teks — dan
rantai fallback SN/ODP di atas tidak boleh disalin ke renderer kedua. Ia dirakit
sekali, dirender dua kali.

### Satu event, banyak tujuan: transport

Satu `installation.activated` bisa punya beberapa pelanggan sekaligus. Contoh yang
sudah disepakati: **Website B** (sistem provisioning) dan **Telegram Eksternal**
(notifikasi + catatan untuk pihak luar).

Keduanya bukan dua sistem terpisah. Satu event → payload dirakit **sekali** oleh
presenter → satu baris `webhook_outbox` per tujuan → pengiriman independen dengan
retry masing-masing. Website B mati tidak menahan Telegram, dan sebaliknya.

Yang berbeda hanyalah cara mengetuk pintunya:

| | `http_json` (Website B) | `telegram` (Telegram Eksternal) |
|---|---|---|
| Tujuan | URL milik konsumen | `api.telegram.org/bot{token}/sendMessage` |
| Auth | HMAC `X-Whusnet-Signature` | bot token di dalam URL |
| Bentuk kiriman | JSON terstruktur | teks ter-render, `parse_mode: HTML` |
| Verifikasi signature | wajib | **tidak mungkin** — Telegram tidak membaca header kita |
| Sukses | 2xx | `ok: true` di body respons |

Karena itu listener tahu dua tujuan tetap dari `config/webhooks.php` (bukan lagi
tabel `webhook_endpoints` sejak rev. 8, `keputusan.md`), dan presenter punya dua
renderer di atas **satu** sumber data: `toJson()` untuk `http_json`, `toTelegramText()`
untuk `telegram`. Bukan dua pipeline — satu outbox, dua adapter pengiriman. Kalau isi
payload perlu field baru, ia ditambahkan di presenter dan kedua renderer ikut, sama
seperti aturan `ReceiptPresenter`.

Telegram tidak menerima signature, jadi jaminan keasliannya berbeda dan lebih lemah:
yang membuktikan pesan itu dari kita hanyalah bot yang mengirimnya. Itu memadai untuk
notifikasi, **tidak** memadai untuk memicu tindakan otomatis. Konsumen yang perlu
memprovision layanan berlangganan `http_json`, bukan membaca grup Telegram.

### Telegram Internal vs Telegram Eksternal

Repo ini sudah mengirim Telegram dari enam tempat — `CustomerInstallationController`,
`CustomerSurveyController`, `CustomerController`, `CustomerVerificationController`,
`SendTaskNotificationJob`, `CheckCountdownStatus` — semuanya lewat
`TelegramBotService::sendMessage()` inline. Itu **Telegram Internal**: kabar untuk tim
sendiri, dan ia **tidak disentuh sama sekali** oleh rancangan ini.

Yang berpasangan dengan webhook adalah **Telegram Eksternal**: saluran terpisah untuk
pihak luar, tujuan tetap `telegram_external` di `config/webhooks.php`.

| | Telegram Internal | Telegram Eksternal |
|---|---|---|
| Pembaca | Tim Whusnet | Pihak luar (mitra/vendor) |
| Jalur | `TelegramBotService` inline di 6 tempat | `webhook_outbox`, transport `telegram` |
| Kredensial | `config('services.telegram.*')` global (`TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID`) | `config('webhooks.telegram_external')` (`TELEGRAM_EXTERNAL_BOT_TOKEN`/`TELEGRAM_EXTERNAL_CHAT_ID`) — env var **berbeda**, bukan lagi kolom DB sejak rev. 8 |
| Dipicu | bermacam kejadian operasional | hanya event yang dilanggan |
| Retry | tidak ada — galat ditelan ke log | ada, backoff sama dengan `http_json` |
| Diubah task ini? | **tidak** | dibangun baru |

**Kredensialnya wajib terpisah, dan ini bukan detail.** `config/services.php:38-41`
cuma punya satu `TELEGRAM_BOT_TOKEN` dan satu `TELEGRAM_CHAT_ID`. Kalau transport
`telegram` membacanya, pesan untuk pihak luar mendarat di grup internal yang sama —
pemisahan yang baru saja dibuat langsung batal, dan pihak luar berpotensi ikut membaca
kabar operasional internal kalau mereka ditambahkan ke grup itu.

Jadi adapter `telegram` membaca `bot_token` dan `chat_id`-nya sendiri dari
`config/webhooks.php` (nilainya dari env var terpisah, bukan dari
`config('services.telegram.*')`). `TelegramBotService` yang ada tidak dipakai ulang
apa adanya — ia mengunci kredensial di konstruktor dari config global (`:15-18`).
Adapter eksternal memerlukan token dan chat sendiri.

Karena keduanya terpisah penuh — bot berbeda, chat berbeda, pemicu berbeda — **tidak
ada tabrakan**. Dua integrasi Telegram yang berjalan bersamaan bukan risiko teknis:
keduanya sekadar `Http::post()` ke `api.telegram.org`, tanpa state bersama.

### Isi pesan Telegram Eksternal

Payload `http_json` memuat identitas lengkap karena provisioning memang membutuhkannya,
dan ia dikirim ke satu endpoint dengan retensi yang bisa kita minta pertanggungjawaban.

Telegram berbeda: pesan yang terkirim ke sebuah chat **menetap selamanya di luar
kendali kita**. Kebijakan purge 90 hari untuk `webhook_outbox` tidak menjangkau
riwayat chat. Siapa pun yang ditambahkan ke grup itu tahun depan bisa menggulir ke
atas dan membaca data pelanggan hari ini.

Karena grup ini eksternal, isinya dibatasi:

**Keluar:** `login_id` atau CID, nama POP, desa, nama paket, SN, ODP, nomor task,
nomor aktivasi, waktu.

**Tidak keluar:** nomor HP, alamat lengkap, NIK, koordinat, dan kredensial perangkat
(`pppoe_password`, `wifi_password`) — yang terakhir tidak pernah masuk payload mana
pun, termasuk `http_json`.

Nama pelanggan: **keputusan Anda.** Ia berguna untuk pembacaan manusia dan berisiko
sebagai catatan permanen di pihak luar. Kalau ragu, kirim `login_id` saja — yang butuh
nama membuka sistemnya. Catatan pembanding: pesan Telegram Internal yang ada sekarang
(`storeSpeedtest()` `:876-886`) memuat nama **dan** nomor HP; itu memang untuk tim
sendiri, dan jangan dijadikan acuan untuk kanal eksternal.

### Aktivasi bisa ditekan berkali-kali

Ini kasus yang paling mudah terlewat, dan pada titik pemicu ini ia **lebih sering**
terjadi daripada di skenario mana pun.

`storePemasangan()` tidak mengunci apa pun setelah sukses. Teknisi yang salah mengetik
SN, memasang ulang di ODP lain, atau menambah foto tinggal mengubah isian dan menekan
Aktivasi lagi — `updateOrCreate` di `:695` dan `:735` memang dirancang untuk itu.
Ditambah alur revisi: verifikasi admin menolak (`revision_installation`, diterima di
`:574`), teknisi memperbaiki, tekan Aktivasi lagi.

Semua penekanan itu sah dan semuanya harus terkirim — penekanan kedua justru sering
membawa SN atau ODP yang **benar**, yang persis dibutuhkan sistem provisioning.

Yang rusak adalah kontrak idempotensinya. Kalau `event_id` dipakai sendirian, penerima
menghadapi dua event dengan id berbeda dan tidak punya cara tahu bahwa yang kedua
**menggantikan** yang pertama, bukan menambah. Untuk provisioning, itu berarti dua
kali penyalaan layanan atau dua baris pelanggan dengan SN berbeda.

Karena itu payload membawa **dua** kunci dengan tugas berbeda:

| Kunci | Tetap sama saat | Untuk |
|---|---|---|
| `event_id` | percobaan **ulang pengiriman** kejadian yang sama | Membuang kiriman dobel akibat jaringan |
| `idempotency_key` | — berubah tiap penekanan Aktivasi | Mengenali bahwa event ini **state terbaru** untuk pemasangan itu |

`idempotency_key` = `installation:{customer_id}:activation:{n}`, dengan `n` dinaikkan
tiap penekanan. Penerima memperlakukan event sebagai **upsert atas state pemasangan
pelanggan** — nomor tertinggi yang menang, sama semangatnya dengan "payload berisi
STATE penuh, bukan delta" di §6.6.6.

Konsekuensi operasional yang harus disadari: penekanan berulang berarti webhook bisa
terkirim beberapa kali untuk satu pemasangan dalam hitungan menit. Itu normal, bukan
bug, dan konsumen harus dirancang menerimanya sejak awal.

### Aktivasi berulang: Website B menerima semua, Telegram tidak

Ini satu-satunya titik di mana kedua transport **sengaja berperilaku beda**.

Aktivasi bisa ditekan berkali-kali dan itu jalur normal. Untuk Website B setiap
penekanan harus terkirim: penekanan kedua sering membawa SN atau ODP yang benar, dan
penerima memperlakukannya sebagai upsert lewat `idempotency_key`. Kehilangan satu pun
berarti provisioning memakai data basi.

Untuk manusia yang membaca grup Telegram, perilaku yang sama jadi masalah: satu
pemasangan bermasalah bisa mengirim lima pesan dalam dua menit. Telegram sendiri
membatasi sekitar 20 pesan/menit per grup dan membalas `429` di atas itu — jadi
membanjiri kanal bukan cuma berisik, ia membuang pesan.

Dua aturan untuk transport `telegram`, keduanya **tidak** berlaku untuk `http_json`:

1. **Lewati kalau tidak ada yang berubah.** Kalau teks ter-render identik dengan
   kiriman terakhir yang berhasil untuk pelanggan yang sama, jangan kirim. Penekanan
   Aktivasi yang tidak mengubah data apa pun tidak menghasilkan kabar apa pun.
2. **Sebutkan nomor aktivasinya** saat memang berubah — mis. *"Aktivasi #2 — data
   perangkat diperbarui"*. Pembaca harus bisa membedakan koreksi dari pemasangan baru.
   Tanpa itu, dua pesan untuk satu pelanggan terbaca sebagai dua pemasangan.

Baris outbox untuk Telegram yang dilewati aturan 1 ditandai `skipped`, bukan dihapus —
supaya pertanyaan "kenapa mitra tidak dapat kabar" punya jawaban.

### Pengiriman, kegagalan, dan pengulangan

Pengiriman lewat outbox + job terantre (Horizon sudah terpasang). Retry backoff:
1 menit → 5 menit → 30 menit → 2 jam → 6 jam, maksimal 8 percobaan — angka yang sama
dengan §6.6.6 supaya tidak ada dua kebijakan retry di satu sistem.

Sejak rev. 8 tidak ada lagi `is_active`/`consecutive_failures` di DB untuk dimatikan —
tujuan tetap satu, tidak ada saklar per-tujuan. Kegagalan beruntun tetap harus terlihat:
job pengirim mencatat count berurutan (mis. di cache atau log terstruktur) dan
mengirim alert manual ke Owner setelah melewati ambang, tapi baris outbox tidak
berhenti dicoba — kegagalan **tidak boleh hilang diam-diam**, jadi baris berstatus
`failed` tetap tinggal sebagai daftar yang bisa direkonsiliasi.

Urutan tidak dijamin, dan dengan Aktivasi yang bisa ditekan berulang itu bukan kasus
teoretis: kalau penekanan pertama gagal lalu masuk backoff sementara penekanan kedua
langsung lolos, konsumen menerima yang lama **setelah** yang baru. Penentu urutan
adalah `occurred_at` dan nomor di `idempotency_key`, bukan waktu terima — event dengan
nomor lebih rendah yang datang belakangan harus dibuang, bukan ditimpakan.

### Keamanan

Header: `X-Whusnet-Signature: t=<unix>,v1=<hex>`, dengan `v1` = HMAC-SHA256 atas
`"{t}.{raw body}"` memakai secret endpoint.

Penerima wajib:

1. Menolak kalau selisih `t` terhadap waktu sekarang lebih dari 5 menit.
2. Menghitung ulang signature atas **raw body**, bukan hasil `json_decode` lalu
   `json_encode` ulang — susunan kunci bisa berubah dan signature gagal tanpa sebab
   yang kelihatan.
3. Membandingkan dengan `hash_equals`, bukan `===`.

**Secret disimpan di `.env`, BUKAN di-hash.** HMAC menuntut kedua pihak memegang
rahasia yang **sama**, jadi kita harus bisa membacanya kembali setiap kali
menandatangani — plaintext di `.env` (tidak pernah masuk git) memenuhi itu, sama
seperti kolom `encrypted` di DB pada rancangan sebelumnya, cuma sumbernya beda sejak
rev. 8 (`keputusan.md`).

URL tujuan wajib HTTPS, ditetapkan developer lewat `.env` + deploy — **tidak ada
lagi pendaftaran lewat form Owner**, karena tidak ada lagi form. Ini juga berarti
gap validasi SSRF yang sempat dicatat (rev. 7) sudah tidak relevan: tidak ada input
pengguna yang jadi URL tujuan.

**Tidak ada lagi `pop_id` per endpoint.** Konsumen tunggal (Website B) menerima data
seluruh cabang. Ini penyimpangan sadar dari aturan keras CLAUDE.md soal pembatasan POP
di setiap query pelanggan — diterima untuk sekarang karena cuma ada satu konsumen;
kalau nanti konsumen kedua butuh cabang berbeda, itu alasan pertama untuk membangun
kembali mekanisme routing per cabang (`keputusan.md` §4).

### Callback Hasil Provisioning (inbound) — fase terpisah, belum masuk implementasi

**Kenapa dibutuhkan.** Tanpa arah balik, `installation.activated` cuma "tembak dan
lupa" — Whusnet tidak pernah tahu provisioning di Website B benar-benar berhasil.
Teknisi masih harus menelepon manual untuk memastikan layanan menyala. Ini item
"paling bernilai berikutnya" di peta pengembangan (`keputusan.md` §4), dirinci di sini
supaya tidak dirancang ulang dari nol saat dikerjakan.

**Hanya berlaku untuk transport `http_json`.** Telegram Eksternal tidak bisa jadi
pemanggil API balik ke kita — ia cuma penerima teks. Callback ini pasangan searah dari
Website B (tujuan `http_json` di `config/webhooks.php`), bukan grup Telegram mana pun.

**Endpoint**

```
POST /api/v1/installations/provisioning-callback
```

Bukan path-param `{cid}` — `cid` nullable dan tidak stabil, alasan sama seperti kenapa
`login_id` dipilih jadi kunci di payload keluar. Identitas ada di body lewat
`event_id`/`idempotency_key`, dicocokkan ke baris `webhook_outbox` yang pernah kita
kirim.

**Auth — kredensial baru, bukan numpang HMAC secret webhook.** Secret webhook dipakai
satu arah: Whusnet menandatangani ke Website B. Arah balik butuh kredensial
kebalikannya — Website B mengautentikasi ke Whusnet. Memakai secret yang sama berarti
satu kebocoran mengompromikan dua arah sekaligus. Dipakai token bearer tetap,
hardcode di `.env` (`WEBHOOK_WEBSITE_B_CALLBACK_TOKEN`) — ikut pivot rev. 8, bukan
tabel — diverifikasi `hash_equals` terhadap hash yang dihitung di tempat.

**Body**

```json
{
  "event_id": "0f4a9b2e-7c31-4d5a-9f10-2b8e6c5a1d33",
  "idempotency_key": "installation:8842:activation:3",
  "status": "succeeded",
  "reason": null,
  "provider_reference": "WB-PROV-99213",
  "occurred_at": "2026-08-18T14:40:00+07:00"
}
```

- `event_id` + `idempotency_key` **wajib cocok** dengan baris `webhook_outbox` yang
  pernah dikirim ke endpoint pemanggil. Tidak cocok → **404**, bukan diterima diam-diam
  — mencegah callback palsu atau nyasar menempel ke pelanggan yang salah.
- `status`: `succeeded` / `failed`. `reason` wajib kalau `failed`.
- `provider_reference` nullable — ID internal Website B, untuk penelusuran silang.

**Satu callback, satu aktivasi — terminal, tidak boleh flip-flop.** Setiap
`idempotency_key` cuma boleh punya **satu** baris hasil final:

- Percobaan kedua dengan `idempotency_key` sama dan isi **identik** (retry jaringan di
  sisi Website B sendiri) → diterima diam-diam sebagai duplikat, tidak membuat baris
  baru, balas 200.
- Percobaan kedua dengan isi **berbeda** (mencoba mengubah hasil yang sudah final) →
  **ditolak** (409), dicatat `rejected` dengan alasan `already_finalized`.

**Dua level "gagal" — jangan disamakan, keduanya wajib tercatat:**

| Level | Contoh | Status tercatat |
|---|---|---|
| **Gagal callback** (infra) — request ditolak sebelum isinya dianggap sah: token salah, `idempotency_key` tidak dikenal, sudah final (duplikat berbeda isi), timeout | 401/404/409 di gerbang kita | `rejected` + alasan (`invalid_token` / `unknown_event` / `already_finalized`) |
| **Gagal provisioning** (bisnis) — callback sah diterima, isinya melaporkan kegagalan di sisi Website B | `status: "failed", reason: "VLAN conflict"` | `failed` |

Satu tabel log, tiga nilai `status`: `succeeded` / `failed` / `rejected`. Prinsipnya:
setiap request yang masuk **selalu** menghasilkan satu baris — diterima atau ditolak,
tidak ada yang hilang tanpa jejak. Ini **log terpisah**
(`installation_provisioning_callbacks`), bukan ditumpang ke `task` sebagai catatan
utama; task/Telegram Internal cuma menerima mirror ringkas dari baris `succeeded`/
`failed` (bukan `rejected`) supaya teknisi/NOC tahu tanpa membuka log mentah. Baris log
inilah sumber kebenarannya, sama prinsipnya seperti `webhook_outbox` untuk arah keluar.

**Pertanyaan yang masih terbuka sebelum ini bisa naik ke `rencana-implementasi.md`:**

1. Bentuk final mirror ke task — catatan teks di histori task, atau kolom status
   khusus? Belum diputuskan.
2. Kegagalan bisnis (`status=failed`) memicu notifikasi aktif ke teknisi (mis. lewat
   Telegram Internal), atau cukup tercatat pasif dan dilihat kalau dicek?

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

