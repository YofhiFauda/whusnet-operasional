# Business Logic — API Baru: Topologi Jaringan & Konfirmasi Assignment

## Kenapa dibutuhkan

`installation.activated` (`api-webhook-pemasangan`) satu arah — Whusnet kasih data pemasangan ke
Website B, titik. Tapi Website B yang tahu OLT/port fisik mana pelanggan tersambung,
dan itu perlu dipetakan ke **Mini POP** + **Distribusi** di sistem Whusnet supaya CID
pelanggan benar dan billing/laporan per-cabang akurat.

Sebelum bisa mengonfirmasi assignment yang benar, Website B perlu tahu **kode apa
saja yang valid** — mereka tidak tahu skema internal Whusnet begitu saja. Karena itu
tiga endpoint, bukan satu:

1. **Baca dulu** (`GET /pop-distribusi`) — apa saja pilihan Mini POP/Distribusi yang ada.
2. **Konfirmasi assignment** (`POST /installations/network-assignment`) — Mini POP/Distribusi mana yang benar untuk pelanggan spesifik.
3. **Konfirmasi perangkat** (`POST /installations/network-device`) — kredensial PPPoE & titik sambung OLT hasil provisioning.

Endpoint #2 dan #3 awalnya satu endpoint gabungan (rev 1-10) — dipecah (rev 12) atas
keputusan pemilik produk supaya keduanya bisa berkembang independen. Lihat
keputusan.md §19 untuk analisis lengkap.

## Endpoint #1 — `GET /api/v1/pop-distribusi`

Referensi, baca-saja. Balikin **seluruh hierarki**: Cabang POP → Mini POP di
bawahnya → Distribusi di tiap Mini POP, kode + nama.

```json
{
  "data": [
    {
      "pop_code": "PNR-JTS",
      "pop_name": "Jetis",
      "mini_pops": [
        {
          "code": "C1",
          "name": "Mini POP C1",
          "distributions": [
            { "code": "A", "name": "Distribusi A" },
            { "code": "B", "name": "Distribusi B" }
          ]
        }
      ]
    }
  ]
}
```

**Kenapa daftar lengkap, bukan di-scope per pelanggan.** Dikonfirmasi kebutuhan:
Website B mau tahu **semua** POP/Mini POP/Distribusi sekaligus, bukan cuma yang
relevan untuk satu pelanggan — mereka cache di sisi mereka, dipakai lintas
pemasangan.

**Kenapa endpoint baca terpisah, bukan dikirim di tiap payload `installation.activated`.**
Mini POP dan Distribusi **jarang berubah** (bulanan/tahunan) — mengirim daftar
lengkap di **setiap** event pemasangan itu boros, data yang sama diulang-ulang.
Endpoint baca yang dipanggil sesuka Website B (cache, refresh berkala) lebih pas
untuk data yang jarang berubah tapi tetap harus selalu benar.

**Sumber data.** `pops` (type `mini_pop`, `parent_id` = Cabang POP) dan
`distributions` (`pop_id` = Mini POP) — tabel yang sudah ada, tidak ada perubahan
skema. Query yang sama persis dengan yang dipakai
`CustomerNetworkAssignmentController::data()` (`app/Http/Controllers/CustomerNetworkAssignmentController.php:20-45`),
cuma tanpa scoping ke satu pelanggan.

**Auth.** Token bearer baca-saja, hardcode di `.env` — risiko rendah, cuma expose
struktur topologi internal, bukan data pelanggan.

## Endpoint #2 — `POST /api/v1/installations/network-assignment`

Tulis. Konfirmasi Mini POP + Distribusi yang benar untuk satu pelanggan.

```json
{
  "idempotency_key": "installation:1938:activation:7",
  "mini_pop_code": "D1",
  "distribution_code": "X6A"
}
```

- `idempotency_key` — dari payload `installation.activated` yang mereka terima di
  `api-webhook-pemasangan`, dipakai mencari pelanggan mana. **Bukan `customer_id`** — Website B tidak
  tahu ID internal Whusnet, dan menerima ID mentah dari request luar adalah
  larangan keras lintas-API (`../README.md`).
- `mini_pop_code` / `distribution_code` — **kode**, dari daftar hasil endpoint #1.
  Bukan ID internal — sama alasannya. **Keduanya wajib** — beda dari controller
  staf yang direuse (`nullable`), lihat "Kenapa wajib, bukan nullable" di bawah.

### Validasi — reuse, bukan tulis ulang

Logic validasi **wajib** reuse dari
`CustomerNetworkAssignmentController::update()` (`:67-140`), bukan aturan baru yang
berbeda:

1. Resolve `idempotency_key` → pelanggan lewat `webhook_outbox` (`lockForUpdate()`). Tidak ketemu → 404.
2. Status pelanggan tidak boleh di `BLOCKED_STATUSES` (`registered`,
   `waiting_survey`, `survey_in_progress`, `surveyed`, `waiting_acc`,
   `waiting_installation`, `rejected`) — belum ada dasar teknis untuk assignment.
3. `mini_pop_code` → resolve ke `Pop` dengan `type=mini_pop` **dan**
   `parent_id` = Cabang POP pelanggan. Tidak cocok → 422.
4. `distribution_code` → resolve ke `Distribution` dengan `pop_id` = Mini POP hasil
   langkah 3. Tidak cocok → 422.
5. Simpan `customer.mini_pop_id`, `customer.distribution_id`.
6. **Kalau pelanggan `active`/`suspended` (CID sudah final)**, regenerate CID:
   `$customer->pop->generateComplexCid($customer, $customer->distribution)` —
   persis baris `:118` di controller yang sudah ada. CID tidak auto-update sendiri;
   tanpa langkah ini, assignment baru tidak pernah tercermin di identitas
   pelanggan.

**Kenapa `mini_pop_code`+`distribution_code` wajib, bukan `nullable` seperti
controller staf.** `CustomerNetworkAssignmentController::update()` bikin keduanya
`nullable` — itu aman di modal staf karena modal selalu menampilkan nilai "current"
dulu sebelum staf submit, jadi field yang dikosongkan berarti sengaja di-unset.
Endpoint API ini **tidak** punya referensi "nilai sekarang" seperti itu di sisi
Website B, dan sejak endpoint ini dipecah dari `perangkat` (keputusan.md §19),
satu-satunya alasan endpoint ini dipanggil memang untuk assign — jadi keduanya
`required` polos, bukan `nullable`.

### Auth

Token bearer tulis — **dibagi bareng** endpoint #3 (`network-device`), sama-sama
kelas risiko "menulis data pelanggan". **Beda** dari token baca endpoint #1
(keputusan.md §5).

**Rate limit.** 20 request/menit per kombinasi token+IP — limiter **terpisah**
dari endpoint #3 meski token-nya sama (keputusan.md §19): kegagalan beruntun di
satu endpoint tidak boleh menghabiskan kuota endpoint yang lain.

**Alert kegagalan beruntun.** ≥5 respons 422 beruntun dalam 10 menit dari
token+IP yang sama memicu notifikasi ke staf lewat `TelegramBotService` (reuse
channel notif teknisi yang sudah ada) — pola kegagalan berulang lebih mungkin
berarti kesalahan integrasi Website B daripada kebetulan.

### Idempotency

`idempotency_key` yang sama bisa dipakai ulang di endpoint #3 (`network-device`)
kalau assignment dan kredensial perangkat dikonfirmasi lewat request terpisah.
Dedup **di-scope ke `idempotency_key` + hash isi body** (bukan key doang) — kalau
cuma key, request susulan ke endpoint #3 dengan key yang sama akan salah dianggap
retry dan tidak diproses. Lihat `database-schema.md` untuk detail kolom
`audit_logs.request_hash`.

Seluruh langkah di atas (termasuk cek dedup) dibungkus satu `DB::transaction()`,
dengan `Customer::where(...)->lockForUpdate()` di langkah 1 — mengunci baris
pelanggan sampai transaksi selesai, menutup race dua request `idempotency_key`
sama yang datang nyaris bersamaan.

### Audit

`AuditLog::create()` (pola sama `CustomerNetworkAssignmentController::update()`
`:123-138`), dengan penyesuaian karena pemanggilnya bukan staf yang login:

- `user_id` = `null` (tidak ada staf).
- `action` = `'network_assignment'`.
- `user_agent` = string tetap yang mengidentifikasi sumbernya:
  `"API — Website B integration"`.
- `ip_address` = IP pemanggil asli — tetap tercatat, jadi jejak forensik tidak
  hilang meski bukan staf.
- `old_values`/`new_values` untuk `mini_pop_id`, `distribution_id`, `cid`.

### Respons

- Berhasil → `2xx`:
  ```json
  {
    "mini_pop_code": "D1",
    "distribution_code": "X6A"
  }
  ```
  Kalau pelanggan sudah `active`/`suspended`, `cid` ikut disertakan (CID final
  yang baru tersimpan):
  ```json
  {
    "cid": "D001X6ARQ000025",
    "mini_pop_code": "D1",
    "distribution_code": "X6A"
  }
  ```
  Key `cid` **dihilangkan total** kalau belum final — bukan `null`. Lihat
  keputusan.md §18 untuk alasan lengkap kenapa presence-based, bukan nilai
  `null`/preview.
- Validasi gagal → error, **tidak ada yang berubah** di database. Tidak ada
  jalur approval manual staf sebelum tersimpan — kalau valid, langsung
  eksekusi (keputusan.md §7).

## Endpoint #3 — `POST /api/v1/installations/network-device`

Tulis. Perbarui kredensial PPPoE dan/atau detail titik sambung OLT untuk
pelanggan yang **assignment Mini POP/Distribusi-nya sudah ada** (lewat endpoint
#2 sebelumnya).

```json
{
  "idempotency_key": "installation:1938:activation:7",
  "perangkat": {
    "pppoe_username": "D1X6ARQ000025_BROTONEGARAN_SAMSUDIN",
    "pppoe_password": "secretpassword123",
    "olt_number": "3",
    "olt_slot": "1",
    "olt_port": "8",
    "vlan": "301"
  }
}
```

- `idempotency_key` — sama seperti endpoint #2, boleh nilai yang **sama**
  (lihat "Idempotency" di atas) kalau ini konfirmasi susulan dari
  `installation.activated` yang sama.
- `perangkat` — **wajib** ada sebagai objek, tapi tiap field di dalamnya
  nullable satu-satu — kirim yang sudah diketahui, kosongkan sisanya. Objek
  yang seluruh isinya kosong/null ditolak (422) — gak ada gunanya mengonfirmasi
  "tidak ada apa-apa".
  - `pppoe_username` — dipakai pelanggan login ke PPPoE server.
  - `pppoe_password` — **kredensial sensitif**, lihat "Keamanan kredensial jaringan"
    di bawah.
  - `olt_number` / `olt_slot` / `olt_port` / `vlan` — detail titik sambung fisik
    OLT hasil provisioning Website B. **Target tabel beda dari dua field di
    atas** — lihat "Dua tabel, satu object `perangkat`" di bawah.

**Tidak ada `ip_address`.** Field ini sempat dirancang (rev 5), tapi konsep
"IP jaringan pelanggan" dihapus dari seluruh sistem (keputusan produk,
2026-08-22) — `customers.ip_address` dan `customer_devices.ip_address` tidak
ada lagi, jadi tidak ada kolom tujuan buat field ini menulis apa pun.

### Validasi

1. Resolve `idempotency_key` → pelanggan lewat `webhook_outbox` (`lockForUpdate()`). Tidak ketemu → 404.
2. `perangkat` tidak boleh seluruhnya kosong/null → 422.
3. Pelanggan **wajib** sudah punya `mini_pop_id` **dan** `distribution_id`
   tersimpan — tanpa itu, kredensial jaringan tidak ada gunanya (gak jelas
   ini kredensial buat Mini POP/Distribusi yang mana). Belum ada → 422,
   pesan mengarahkan ke endpoint #2.
4. Upsert ke **dua tabel sekaligus** — lihat "Dua tabel, satu object
   `perangkat`" di bawah. Tiap tabel di-`array_filter` sendiri-sendiri
   (`fn ($v) => $v !== null`), jadi field yang gak dikirim di satu tabel
   tidak ikut mengosongkan field lain di tabel yang **sama**.

**Kredensial ini bisa sudah pernah diisi manual oleh teknisi.** Wizard Laporan
Pemasangan (`storePemasangan()`) sudah punya field `pppoe_username`/
`pppoe_password` (di `customer_devices`) dan `olt_number`/`olt_slot`/
`olt_port`/`vlan` (di `customer_technical_details`) yang boleh diisi teknisi di
lapangan (kalau mereka kebetulan tahu). Endpoint ini **menimpa** nilai itu kalau
Website B kirim nilai baru — provisioning Website B dianggap sumber kebenaran
yang lebih akhir untuk kredensial jaringan, karena merekalah yang benar-benar
mengaktifkan PPPoE server-nya dan tahu port OLT fisik yang dipakai.

### Dua tabel, satu object `perangkat`

`perangkat` di body cuma **satu** object di sisi API, tapi field di dalamnya
menyebar ke **dua tabel** yang beda — Website B gak perlu tahu ini, cukup Whusnet
yang rapikan di sisi implementasi:

| Field `perangkat` | Tabel tujuan | Kolom |
|---|---|---|
| `pppoe_username`, `pppoe_password` | `customer_devices` | sama nama |
| `olt_number`, `olt_slot`, `olt_port`, `vlan` | `customer_technical_details` | sama nama |

Upsert kedua tabel pakai pola sama persis
`CustomerInstallationController::storePemasangan()`:
`customerDevice()->updateOrCreate(['customer_id' => $customer->id], array_filter([...]))`
(`:735-750`) untuk `customer_devices`, dan
`CustomerTechnicalDetail::updateOrCreate(['customer_id' => $customer->id], array_filter([...]))`
(`:696-711`) untuk `customer_technical_details` — **jangan tulis ulang logic
upsert baru**, dua controller ini sudah jadi rujukan.

**Kenapa gak dipisah jadi dua object di body** (mis. `perangkat` vs
`topologi_olt`). Ditolak — dari sudut pandang Website B ini **satu** kelompok data
hasil provisioning fisik (semua muncul dari OLT/port yang sama saat mereka
aktivasi), pemisahan tabel di sisi Whusnet adalah detail implementasi internal
yang gak perlu bocor ke kontrak API. Satu object `perangkat` tetap kontrak yang
benar; pemetaan ke dua tabel murni urusan controller.

**Kenapa `vlan` (endpoint ini) bukan `vlan_id` (`customer_devices`).** Dua kolom
beda yang kebetulan mirip nama: `customer_technical_details.vlan` (nomor VLAN di
titik OLT, yang dimaksud field `perangkat.vlan`) vs `customer_devices.vlan_id`
(kolom lain, konteks device, **tidak disentuh** endpoint ini). Implementasi wajib
sadar bedanya — salah pasang kolom di sini gampang lolos code review karena
namanya mirip tapi tabelnya beda.

**Catatan `olt_number` juga dipakai di CID generation** (`Pop::resolveMiniPopSegment()`,
fallback kalau `customer->miniPop` belum di-assign eksplisit — lihat `app/Models/Pop.php:330-339`).
Karena assignment (endpoint #2) selalu mengisi `mini_pop_id` eksplisit lebih
dulu (prioritas pertama di `resolveMiniPopSegment()`), nilai `olt_number` yang
ditulis lewat endpoint ini **tidak** memengaruhi CID — disebut di sini supaya
jelas ini bukan kebetulan yang belum dipikirkan.

### Auth

Token bearer tulis — **sama** dengan endpoint #2 (satu kelas risiko). Rate
limit **terpisah**: 20 request/menit per kombinasi token+IP, bucket sendiri
(lihat "Auth" di endpoint #2).

### Keamanan kredensial jaringan

`pppoe_password` bukan data biasa — itu kredensial akses internet pelanggan.
Beberapa aturan wajib, tidak bisa ditawar:

1. **Tidak pernah masuk `audit_logs`.** `CustomerDevice::$auditHidden` sudah
   memasukkan `pppoe_password`/`wifi_password` (`app/Models/CustomerDevice.php:38-41`)
   — perilaku itu **wajib tetap berlaku** untuk baris yang ditulis lewat endpoint
   ini, sama seperti jalur staf. Endpoint ini tidak boleh menulis lewat jalur lain
   yang melewati guard tersebut.
2. **Tidak pernah masuk log aplikasi/request log.** Kalau ada middleware logging
   request body (`Log::info($request->all())` dsb.), field ini wajib di-redact
   sebelum baris log ditulis — bukan cuma di titik penyimpanan akhir.
3. **Tidak pernah dikirim balik di response.** Endpoint ini tulis-saja untuk field
   ini; respons sukses tidak mengembalikan `pppoe_password` yang baru saja
   disimpan, meski Website B sendiri yang mengirimnya.
4. **Penyimpanan tetap plaintext di `customer_devices.pppoe_password`** —
   **konsisten dengan perilaku yang sudah ada** (kolom ini sudah plaintext sejak
   `storePemasangan()`/`store()` legacy, tidak ada `encrypted` cast). Ini bukan
   celah baru yang dibuat endpoint ini, tapi endpoint ini membuka **jalur baru dari
   luar organisasi** ke kolom yang sama — ditunda, lihat keputusan.md §9.

### Audit

- `user_id` = `null`, `action` = `'network_device_update'`, `user_agent` =
  `"API — Website B integration"`, `ip_address` = IP pemanggil asli.
- `old_values`/`new_values` untuk `mini_pop_id`, `distribution_id`, `cid`
  (konteks, tidak berubah lewat endpoint ini) — **plus** `customer_devices`/
  `customer_technical_details` kalau `perangkat` dikirim: `pppoe_username`,
  `olt_number`, `olt_slot`, `olt_port`, `vlan` boleh masuk apa adanya (bukan
  data sensitif), tapi `pppoe_password` **wajib** ditulis sebagai penanda
  tetap (`"[diubah]"`), bukan nilainya.

### Respons

**Beda dari endpoint #2** — bukan cuma `mini_pop_code`/`distribution_code`/`cid`
(itu konteks assignment lama, gak berubah lewat endpoint ini). Endpoint #3
menambahkan key `perangkat`, echo balik field yang **barusan disimpan** oleh
request ini:

```json
{
  "mini_pop_code": "D1",
  "distribution_code": "X6A",
  "perangkat": {
    "pppoe_username": "D1X6ARQ000025_BROTONEGARAN_SAMSUDIN",
    "olt_number": "3",
    "olt_slot": "1",
    "olt_port": "8",
    "vlan": "301"
  }
}
```

Aturannya:
- **Cuma field yang dikirim** yang muncul di `perangkat` — kirim `olt_number`
  doang, respons cuma punya `perangkat.olt_number`.
- **`pppoe_password` TIDAK PERNAH muncul**, sama larangan seperti audit log.
- **`device_type` (default internal `'other'` saat baris `customer_devices`
  belum ada) TIDAK ikut di-echo** — itu bukan bagian kontrak `perangkat`,
  Website B gak pernah mengirimnya, jadi gak perlu ditampilkan balik.
- Key `perangkat` **hilang total** (bukan `{}`) kalau tidak ada field yang
  berhasil diproses — walau ini kondisinya jarang kejadian karena validasi
  sudah menolak `perangkat` kosong duluan (422).

Kenapa harus beda dari endpoint #2: kalau responsnya sama persis, Website B
manggil `/network-device` gak dapat bukti balik field perangkat yang baru
mereka kirim beneran kesimpen — cuma dapat ulang state assignment lama yang
gak ada hubungannya dengan request ini.
