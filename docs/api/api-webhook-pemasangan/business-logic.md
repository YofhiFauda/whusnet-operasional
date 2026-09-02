# Business Logic — API 1: Webhook Pemasangan

## Titik pemicu: tombol "Aktivasi Laporan Speedtest"

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

## Satu event: `installation.activated`

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

## Konsekuensi: controller HARUS disentuh

Rancangan awal dokumen ini menyatakan webhook bisa dipasang tanpa mengubah
`CustomerInstallationController` sama sekali, dengan menumpang event yang sudah
disiarkan di sana. **Itu tidak berlaku untuk titik pemicu yang benar:**
`storePemasangan()` tidak menyiarkan apa pun.

Implementasinya perlu satu kelas event baru — `App\Events\InstallationActivated`,
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

## Perhatian: `store()` legacy masih hidup

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

## Payload

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

`harga_bulanan` adalah **string desimal**, sama seperti seluruh nominal di semua API.

**`login_id` DI-OMIT dari payload** (keputusan implementasi 2026-08-20, beda dari
rancangan awal): tabel `customer_portal_accounts` (`api-portal-pelanggan/`) belum ada, jadi belum ada
sumber data nyata untuk field ini. `cid` tetap dikirim — nullable untuk pelanggan yang
belum aktif dan tidak punya unique constraint (lihat "Kenapa bukan cid" di
`api-portal-pelanggan/business-logic.md`), tapi ia satu-satunya identitas yang dikirim untuk fase
ini. Begitu `api-portal-pelanggan/` jadi, `login_id` ditambahkan sebagai **perubahan payload versi
baru** — bukan ditambal diam-diam ke payload yang sudah berjalan.

`perangkat.olt` = gabungan `olt_number`/`olt_slot`/`olt_port` (`customer_technical_details`)
dipisah `/`, bagian kosong di-skip — bukan `olt_number` polos. Fallback ke
`customers.olt_code` kalau ketiganya kosong. Tidak ada preseden format gabungan lain
di repo ini; kalau Website B butuh slot/port terpisah, itu perubahan payload
tersendiri.

Tidak ada `task.completed_at`: pada saat Aktivasi ditekan, task PSB memang belum
selesai — penyelesaiannya terjadi di step 6. Mengirim kunci yang selalu `null` hanya
mengundang konsumen menunggunya terisi.

## Dari mana tiap field dibaca

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

## Perakit payload

Satu kelas, `app/Services/Webhooks/InstallationWebhookPresenter`, meniru alasan
`ReceiptPresenter` (`app/Services/Receipts/ReceiptPresenter.php:8-23`): sebelum kelas
itu ada, tiga halaman cetak merakit isi kwitansi sendiri-sendiri dan satu pembayaran
tercetak beda-beda tergantung tombol mana yang ditekan.

Di sini presenter tetap dibutuhkan meski event-nya cuma satu, karena **tujuannya
lebih dari satu**. Website B menerima JSON, Telegram Eksternal menerima teks — dan
rantai fallback SN/ODP di atas tidak boleh disalin ke renderer kedua. Ia dirakit
sekali, dirender dua kali.

## Satu event, banyak tujuan: transport

Satu `installation.activated` bisa punya beberapa pelanggan sekaligus. Yang sudah
disepakati: **Website B** (sistem provisioning) dan **Telegram Eksternal**
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

Listener tahu dua tujuan tetap dari `config/webhooks.php`, dan presenter punya dua
renderer di atas **satu** sumber data: `toJson()` untuk `http_json`, `toTelegramText()`
untuk `telegram`. Bukan dua pipeline — satu outbox, dua adapter pengiriman. Kalau isi
payload perlu field baru, ia ditambahkan di presenter dan kedua renderer ikut, sama
seperti aturan `ReceiptPresenter`.

Telegram tidak menerima signature, jadi jaminan keasliannya berbeda dan lebih lemah:
yang membuktikan pesan itu dari kita hanyalah bot yang mengirimnya. Itu memadai untuk
notifikasi, **tidak** memadai untuk memicu tindakan otomatis. Konsumen yang perlu
memprovision layanan berlangganan `http_json`, bukan membaca grup Telegram.

## Telegram Internal vs Telegram Eksternal

Repo ini sudah mengirim Telegram dari enam tempat — `CustomerInstallationController`,
`CustomerSurveyController`, `CustomerController`, `CustomerVerificationController`,
`SendTaskNotificationJob`, `CheckCountdownStatus` — semuanya lewat
`TelegramBotService::sendMessage()` inline. Itu **Telegram Internal**: kabar untuk tim
sendiri, dan ia **tidak disentuh sama sekali** oleh modul ini.

Yang berpasangan dengan webhook adalah **Telegram Eksternal**: saluran terpisah untuk
pihak luar, tujuan tetap `telegram_external` di `config/webhooks.php`.

| | Telegram Internal | Telegram Eksternal |
|---|---|---|
| Pembaca | Tim Whusnet | Pihak luar (mitra/vendor) |
| Jalur | `TelegramBotService` inline di 6 tempat | `webhook_outbox`, transport `telegram` |
| Kredensial | `config('services.telegram.*')` global (`TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID`) | `config('webhooks.telegram_external')` (`TELEGRAM_EXTERNAL_BOT_TOKEN`/`TELEGRAM_EXTERNAL_CHAT_ID`) — env var **berbeda** |
| Dipicu | bermacam kejadian operasional | hanya event yang dilanggan |
| Retry | tidak ada — galat ditelan ke log | ada, backoff sama dengan `http_json` |

**Kredensialnya wajib terpisah, dan ini bukan detail.** `config/services.php`
cuma punya satu `TELEGRAM_BOT_TOKEN` dan satu `TELEGRAM_CHAT_ID`. Kalau transport
`telegram` membacanya, pesan untuk pihak luar mendarat di grup internal yang sama —
pemisahan yang baru saja dibuat langsung batal, dan pihak luar berpotensi ikut membaca
kabar operasional internal kalau mereka ditambahkan ke grup itu.

Karena keduanya terpisah penuh — bot berbeda, chat berbeda, pemicu berbeda — **tidak
ada tabrakan**. Dua integrasi Telegram yang berjalan bersamaan bukan risiko teknis:
keduanya sekadar `Http::post()` ke `api.telegram.org`, tanpa state bersama.

## Isi pesan Telegram Eksternal

Payload `http_json` memuat identitas lengkap karena provisioning memang membutuhkannya,
dan ia dikirim ke satu endpoint dengan retensi yang bisa kita minta pertanggungjawaban.

Telegram berbeda: pesan yang terkirim ke sebuah chat **menetap selamanya di luar
kendali kita**. Kebijakan purge 90 hari untuk `webhook_outbox` tidak menjangkau
riwayat chat. Siapa pun yang ditambahkan ke grup itu tahun depan bisa menggulir ke
atas dan membaca data pelanggan hari ini.

Karena grup ini eksternal, isinya dibatasi:

**Keluar:** CID, nama POP, desa, nama paket, SN, ODP, nomor task, nomor aktivasi,
waktu.

**Tidak keluar:** nomor HP, alamat lengkap, NIK, koordinat, dan kredensial perangkat
(`pppoe_password`, `wifi_password`) — yang terakhir tidak pernah masuk payload mana
pun, termasuk `http_json`.

## Aktivasi bisa ditekan berkali-kali

`storePemasangan()` tidak mengunci apa pun setelah sukses. Teknisi yang salah mengetik
SN, memasang ulang di ODP lain, atau menambah foto tinggal mengubah isian dan menekan
Aktivasi lagi — `updateOrCreate` di `:695` dan `:735` memang dirancang untuk itu.
Ditambah alur revisi: verifikasi admin menolak (`revision_installation`, diterima di
`:574`), teknisi memperbaiki, tekan Aktivasi lagi.

Semua penekanan itu sah dan semuanya harus terkirim — penekanan kedua justru sering
membawa SN atau ODP yang **benar**, yang persis dibutuhkan sistem provisioning.

Yang rusak adalah kontrak idempotensinya. Kalau `event_id` dipakai sendirian, penerima
menghadapi dua event dengan id berbeda dan tidak punya cara tahu bahwa yang kedua
**menggantikan** yang pertama, bukan menambah.

Karena itu payload membawa **dua** kunci dengan tugas berbeda:

| Kunci | Tetap sama saat | Untuk |
|---|---|---|
| `event_id` | percobaan **ulang pengiriman** kejadian yang sama | Membuang kiriman dobel akibat jaringan |
| `idempotency_key` | — berubah tiap penekanan Aktivasi | Mengenali bahwa event ini **state terbaru** untuk pemasangan itu |

`idempotency_key` = `installation:{customer_id}:activation:{n}`, dengan `n` dinaikkan
tiap penekanan. Penerima memperlakukan event sebagai **upsert atas state pemasangan
pelanggan** — nomor tertinggi yang menang.

## Aktivasi berulang: Website B menerima semua, Telegram tidak

Ini satu-satunya titik di mana kedua transport **sengaja berperilaku beda**.

Untuk Website B setiap penekanan harus terkirim: penekanan kedua sering membawa SN
atau ODP yang benar, dan penerima memperlakukannya sebagai upsert lewat
`idempotency_key`. Kehilangan satu pun berarti provisioning memakai data basi.

Untuk manusia yang membaca grup Telegram, perilaku yang sama jadi masalah: satu
pemasangan bermasalah bisa mengirim lima pesan dalam dua menit. Telegram sendiri
membatasi sekitar 20 pesan/menit per grup dan membalas `429` di atas itu.

Dua aturan untuk transport `telegram`, keduanya **tidak** berlaku untuk `http_json`:

1. **Lewati kalau tidak ada yang berubah.** Kalau teks ter-render identik dengan
   kiriman terakhir yang berhasil untuk pelanggan yang sama, jangan kirim.
2. **Sebutkan nomor aktivasinya** saat memang berubah — mis. *"Aktivasi #2 — data
   perangkat diperbarui"*.

Baris outbox untuk Telegram yang dilewati aturan 1 ditandai `skipped`, bukan dihapus —
supaya pertanyaan "kenapa mitra tidak dapat kabar" punya jawaban.

## Pengiriman, kegagalan, dan pengulangan

Pengiriman lewat outbox + job terantre (Horizon). Retry backoff: 1 menit → 5 menit →
30 menit → 2 jam → 6 jam, maksimal 8 percobaan.

Tidak ada `is_active`/`consecutive_failures` di DB untuk dimatikan — tujuan tetap
satu, tidak ada saklar per-tujuan. Kegagalan beruntun tetap harus terlihat: job
pengirim mencatat count berurutan dan mengirim alert manual ke Owner setelah melewati
ambang, tapi baris outbox tidak berhenti dicoba.

Urutan tidak dijamin: kalau penekanan pertama gagal lalu masuk backoff sementara
penekanan kedua langsung lolos, konsumen menerima yang lama **setelah** yang baru.
Penentu urutan adalah `occurred_at` dan nomor di `idempotency_key`, bukan waktu
terima — event dengan nomor lebih rendah yang datang belakangan harus dibuang, bukan
ditimpakan.

## Keamanan

Header: `X-Whusnet-Signature: t=<unix>,v1=<hex>`, dengan `v1` = HMAC-SHA256 atas
`"{t}.{raw body}"` memakai secret endpoint.

Penerima wajib:

1. Menolak kalau selisih `t` terhadap waktu sekarang lebih dari 5 menit.
2. Menghitung ulang signature atas **raw body**, bukan hasil `json_decode` lalu
   `json_encode` ulang.
3. Membandingkan dengan `hash_equals`, bukan `===`.

**Secret disimpan di `.env`, BUKAN di-hash.** HMAC menuntut kedua pihak memegang
rahasia yang **sama**, jadi kita harus bisa membacanya kembali setiap kali
menandatangani.

URL tujuan wajib HTTPS, ditetapkan developer lewat `.env` + deploy — tidak ada
pendaftaran lewat form. `SendWebhookOutboxJob` menolak kirim ke `website_b` kalau
`url` bukan `https://` atau `secret` kosong — baris langsung `failed`, tanpa masuk
siklus retry.

**Tidak ada `pop_id` per tujuan.** Konsumen tunggal (Website B) menerima data seluruh
cabang. Ini penyimpangan sadar dari aturan keras CLAUDE.md soal pembatasan POP di
setiap query pelanggan — diterima untuk sekarang karena cuma ada satu konsumen.

## Callback Hasil Provisioning (inbound) — fase terpisah, belum masuk implementasi

**Kenapa dibutuhkan.** Tanpa arah balik, `installation.activated` cuma "tembak dan
lupa" — Whusnet tidak pernah tahu provisioning di Website B benar-benar berhasil.
Teknisi masih harus menelepon manual untuk memastikan layanan menyala.

**Hanya berlaku untuk transport `http_json`.** Telegram Eksternal tidak bisa jadi
pemanggil API balik ke kita — ia cuma penerima teks.

**Endpoint**

```
POST /api/v1/installations/provisioning-callback
```

Bukan path-param `{cid}` — `cid` nullable dan tidak stabil. Identitas ada di body
lewat `event_id`/`idempotency_key`, dicocokkan ke baris `webhook_outbox` yang pernah
kita kirim.

**Auth — kredensial baru, bukan numpang HMAC secret webhook.** Secret webhook dipakai
satu arah: Whusnet menandatangani ke Website B. Arah balik butuh kredensial
kebalikannya. Dipakai token bearer tetap, hardcode di `.env`
(`WEBHOOK_WEBSITE_B_CALLBACK_TOKEN`), diverifikasi `hash_equals` terhadap hash yang
dihitung di tempat.

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
  pernah dikirim. Tidak cocok → **404**.
- `status`: `succeeded` / `failed`. `reason` wajib kalau `failed`.
- `provider_reference` nullable — ID internal Website B.

**Satu callback, satu aktivasi — terminal.** Setiap `idempotency_key` cuma boleh
punya **satu** baris hasil final:

- Isi identik (retry jaringan) → diterima diam-diam, balas 200.
- Isi berbeda (mencoba menimpa hasil final) → **ditolak** (409), `rejected` alasan
  `already_finalized`.

**Dua level "gagal":**

| Level | Contoh | Status tercatat |
|---|---|---|
| **Gagal callback** (infra) | token salah, `idempotency_key` tak dikenal, sudah final | `rejected` + alasan |
| **Gagal provisioning** (bisnis) | `status: "failed", reason: "VLAN conflict"` | `failed` |

Log terpisah (`installation_provisioning_callbacks`), bukan ditumpang ke `task`.

**Pertanyaan yang masih terbuka:**

1. Bentuk final mirror ke task — catatan teks atau kolom status khusus?
2. Kegagalan bisnis memicu notifikasi aktif ke teknisi, atau cukup tercatat pasif?
