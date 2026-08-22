# Business Logic — API Baru: Topologi Jaringan & Konfirmasi Assignment

## Kenapa dibutuhkan

`installation.activated` (`api-webhook-pemasangan`) satu arah — Whusnet kasih data pemasangan ke
Website B, titik. Tapi Website B yang tahu OLT/port fisik mana pelanggan tersambung,
dan itu perlu dipetakan ke **Mini POP** + **Distribusi** di sistem Whusnet supaya CID
pelanggan benar dan billing/laporan per-cabang akurat.

Sebelum bisa mengonfirmasi assignment yang benar, Website B perlu tahu **kode apa
saja yang valid** — mereka tidak tahu skema internal Whusnet begitu saja. Karena itu
dua endpoint, bukan satu:

1. **Baca dulu** — apa saja pilihan Mini POP/Distribusi yang ada.
2. **Konfirmasi** — assignment mana yang benar untuk pelanggan spesifik.

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

Tulis. Konfirmasi Mini POP + Distribusi yang benar untuk satu pelanggan, **dan**
opsional isi ulang kredensial jaringan hasil provisioning Website B — semuanya satu
momen konfirmasi, satu request.

```json
{
  "idempotency_key": "installation:8842:activation:1",
  "mini_pop_code": "C1",
  "distribution_code": "A",
  "perangkat": {
    "pppoe_username": "C1X4ARQ000631",
    "pppoe_password": "a1b2c3d4",
    "ip_address": "10.20.30.5"
  }
}
```

- `idempotency_key` — dari payload `installation.activated` yang mereka terima di
  `api-webhook-pemasangan`, dipakai mencari pelanggan mana. **Bukan `customer_id`** — Website B tidak
  tahu ID internal Whusnet, dan menerima ID mentah dari request luar adalah
  larangan keras lintas-API (`../README.md`).
- `mini_pop_code` / `distribution_code` — **kode**, dari daftar hasil endpoint #1.
  Bukan ID internal — sama alasannya.
- `perangkat` — **opsional** (object bisa tidak dikirim sama sekali kalau Website B
  belum tahu kredensial ini di momen yang sama). Kalau dikirim, tiap field di
  dalamnya juga nullable satu-satu — kirim yang sudah diketahui, kosongkan sisanya,
  jangan menunggu ketiganya lengkap.
  - `pppoe_username` — dipakai pelanggan login ke PPPoE server.
  - `pppoe_password` — **kredensial sensitif**, lihat "Keamanan kredensial jaringan"
    di bawah.
  - `ip_address` — IP yang dialokasikan Website B untuk pelanggan ini.

**Kenapa field ini ditambahkan ke endpoint yang sama, bukan endpoint ketiga.**
Kebutuhannya: begitu Website B mengonfirmasi aktivasi (assign Mini POP/Distribusi),
mereka *sekaligus* sudah tahu (atau baru saja menetapkan) kredensial PPPoE dan IP
pelanggan — satu momen provisioning, bukan dua kejadian terpisah dengan jarak waktu.
Memisahkannya jadi endpoint lain berarti dua request untuk satu kejadian bisnis,
plus risiko salah satu terkirim tapi yang lain gagal (state pelanggan jadi separuh
ter-update). Redesain jadi endpoint terpisah bisa dipertimbangkan lagi kalau nanti
faktanya dua kejadian ini sering terjadi di waktu yang benar-benar berbeda.

### Validasi — reuse, bukan tulis ulang

Logic validasi **wajib** reuse dari
`CustomerNetworkAssignmentController::update()` (`:67-140`), bukan aturan baru yang
berbeda:

1. Resolve `idempotency_key` → pelanggan. Tidak ketemu → 404.
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
7. **Kalau `perangkat` dikirim**, upsert ke `customer_devices` — pola sama persis
   `customerDevice()->updateOrCreate(['customer_id' => $customer->id], [...])` yang
   sudah dipakai `CustomerInstallationController::storePemasangan()`
   (`:735-750`). Hanya field yang dikirim (bukan `null`) yang ditimpa —
   `array_filter(..., fn ($v) => $v !== null)` — supaya request yang cuma bawa
   `pppoe_username` tidak diam-diam mengosongkan `ip_address` yang sudah tersimpan
   dari input teknisi sebelumnya.

**Kredensial ini bisa sudah pernah diisi manual oleh teknisi.** Wizard Laporan
Pemasangan (`storePemasangan()`) sudah punya field `pppoe_username`/
`pppoe_password`/`ip_address` yang boleh diisi teknisi di lapangan (kalau mereka
kebetulan tahu). Endpoint ini **menimpa** nilai itu kalau Website B kirim nilai baru
— provisioning Website B dianggap sumber kebenaran yang lebih akhir untuk kredensial
jaringan, karena merekalah yang benar-benar mengaktifkan PPPoE server-nya.

### Auth — terpisah dari endpoint #1

Token bearer **beda** dari token baca topologi. Baca vs tulis punya kelas risiko
beda: baca cuma expose struktur internal, tulis mengubah CID pelanggan. Kalau token
baca bocor, dampaknya expose topologi. Kalau token tulis bocor, dampaknya bisa
mengubah identitas pelanggan mana pun tanpa staf mana pun mengecek.

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
   luar organisasi** ke kolom yang sama — pertimbangkan apakah ini titik yang tepat
   untuk sekalian menambahkan `encrypted` cast (lihat pertanyaan terbuka di
   `rencana-implementasi.md`).

### Audit

Endpoint ini permukaan masuk baru dari luar organisasi, menyentuh data sensitif —
wajib tercatat, bukan cuma disimpan.

`AuditLog::create()` (pola sama `CustomerNetworkAssignmentController::update()`
`:123-138`), dengan penyesuaian karena pemanggilnya bukan staf yang login:

- `user_id` = `null` (tidak ada staf).
- `user_agent` = string tetap yang mengidentifikasi sumbernya, mis.
  `"API — Website B integration"`.
- `ip_address` = IP pemanggil asli — tetap tercatat, jadi jejak forensik tidak
  hilang meski bukan staf.
- `old_values`/`new_values` untuk `mini_pop_id`, `distribution_id`, `cid` —
  **plus** untuk `customer_devices` kalau `perangkat` dikirim: `pppoe_username`,
  `ip_address` boleh masuk `new_values`/`old_values` apa adanya, tapi
  `pppoe_password` **wajib** ditulis sebagai penanda tetap (mis. `"[diubah]"` /
  `"[terisi]"`), bukan nilainya — sama semangatnya dengan kenapa password portal
  pelanggan tidak pernah ditulis ke audit log (`../api-portal-pelanggan/database-schema.md`).

### Respons

- Berhasil → `2xx`, balikan `cid` terbaru (kalau diregenerate) supaya Website B bisa
  konfirmasi apa yang tersimpan. **`pppoe_password` tidak ikut dikembalikan**
  (lihat "Keamanan kredensial jaringan").
- Validasi gagal (poin 1-4 di atas) → error, **tidak ada yang berubah** di database.
  Tidak ada jalur approval manual staf sebelum tersimpan — kalau valid, langsung
  eksekusi (keputusan default, lihat `keputusan.md`).
