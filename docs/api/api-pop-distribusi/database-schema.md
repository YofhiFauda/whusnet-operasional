# Database Schema — API Baru: Topologi Jaringan & Konfirmasi Assignment

## Tidak ada tabel baru

Ketiga endpoint membaca/menulis struktur yang **sudah ada**:

| Tabel | Dipakai untuk | Endpoint |
|---|---|---|
| `pops` | Baca daftar Mini POP (`type=mini_pop`, `parent_id`) | #1 (baca), #2 (validasi) |
| `distributions` | Baca daftar Distribusi (`pop_id` = Mini POP) | #1 (baca), #2 (validasi) |
| `customers` | Tulis `mini_pop_id`, `distribution_id`, `cid` | #2 (tulis) |
| `customer_devices` | Tulis `pppoe_username`, `pppoe_password` | #3 (tulis) |
| `customer_technical_details` | Tulis `olt_number`, `olt_slot`, `olt_port`, `vlan` | #3 (tulis) |
| `audit_logs` | Catat siapa/kapan/apa yang berubah | #2, #3 (tulis) |

**Dua kolom baru: `audit_logs.idempotency_key` + `audit_logs.request_hash`**
(`string`, `nullable`, composite index `(idempotency_key, request_hash)`). Dipakai
*hanya* oleh jalur endpoint #2 & #3 — jalur staf (`CustomerNetworkAssignmentController`)
tetap `null` di kedua kolom ini. `request_hash` = `sha256` dari body request yang
sudah dinormalisasi (urutan key konsisten).

Fungsinya dedup beneran, **di-scope ke isi request, bukan cuma key** — karena
`idempotency_key` yang sama dipakai ulang **lintas endpoint #2 dan #3** (assignment
dan kredensial jaringan dikonfirmasi di request terpisah, kadang beda endpoint
sekalian sejak keputusan.md §19). Sebelum memproses request, tiap endpoint query
`audit_logs->where('idempotency_key', $key)->where('request_hash', $hash)->exists()`:
- Ketemu → key + body identik pernah diproses sukses → **retry beneran**, tidak
  diproses ulang, balikin respons yang sama seperti hasil pertama.
- Key ada tapi `request_hash` beda → **request baru yang sah** (mis. request
  ke endpoint #2 lalu request ke endpoint #3 dengan key yang sama) → diproses normal.

Kalau dedup cuma pakai `idempotency_key` polos (tanpa hash), dua masalah muncul:
retry beneran tetap bikin baris audit dobel (hash-nya sama pun gak ada yang cek),
**dan** request susulan yang sah (endpoint #3 menyusul endpoint #2) malah salah
ke-block karena key-nya kebetulan sama dengan request sebelumnya.

**Tidak ada kolom baru di `customers`.** `mini_pop_id`, `distribution_id`, `cid`
sudah ada — dipakai jalur staf yang sudah ada
(`CustomerNetworkAssignmentController`) dan endpoint #2 cuma jalur kedua ke tabel
yang sama.

**Tidak ada kolom baru di `customer_devices`.** `pppoe_username`,
`pppoe_password` sudah ada (`app/Models/CustomerDevice.php`), sudah
diisi jalur staf yang sudah ada (`storePemasangan()`). Endpoint #3 jalur kedua ke
kolom yang sama — **`pppoe_password` tetap plaintext**, tidak ada `encrypted` cast
ditambahkan di sini karena konsisten dengan kolom yang sudah ada; lihat keputusan
di `rencana-implementasi.md` §"Keputusan resmi" #5.

**`ip_address` sudah dihapus dari seluruh sistem** (keputusan produk,
2026-08-22) — `customers.ip_address` dan `customer_devices.ip_address` tidak
ada lagi (migrasi `2026_08_22_120000_drop_ip_address_columns`). Endpoint #3
tidak pernah punya field `perangkat.ip_address` — kalau ada dokumen lama yang
masih menyebutnya, itu sudah usang.

**Tidak ada kolom baru di `customer_technical_details`.** `olt_number`,
`olt_slot`, `olt_port`, `vlan` sudah ada (`app/Models/CustomerTechnicalDetail.php`),
sudah diisi jalur staf yang sudah ada (`storePemasangan()`,
`CustomerInstallationController.php:288-291` & `:608-611`). Endpoint #3 jalur
kedua ke kolom yang sama. **Bukan kolom yang sama dengan `customer_devices`
sekalipun namanya mirip** — `customer_devices` punya kolomnya sendiri `vlan_id`
(beda dari `customer_technical_details.vlan`, beda semantik: `vlan_id` ID
konfigurasi di perangkat, `vlan` nomor VLAN di titik OLT). Endpoint #3 **hanya**
menulis `customer_technical_details.vlan`, tidak menyentuh `customer_devices.vlan_id`
— jangan disamakan saat implementasi.

## Kredensial — hardcode di `.env`, konsisten dengan `api-webhook-pemasangan`

Dua token bearer (baca vs tulis), pola sama seperti `api-webhook-pemasangan` rev. 8: satu
konsumen (Website B), tidak perlu tabel dinamis. **Endpoint #2 dan #3 berbagi token
tulis yang sama** — satu kelas risiko (sama-sama menulis data pelanggan), rate
limiter-nya yang dipisah per endpoint (keputusan.md §19), bukan token-nya.

```php
// config/webhooks.php (tambahan)
'pop_distribusi_read_token' => env('POP_DISTRIBUSI_READ_TOKEN'),
'network_assignment_write_token' => env('NETWORK_ASSIGNMENT_WRITE_TOKEN'),
```

Kalau nanti ada konsumen kedua untuk API ini, ikuti alasan yang sama dengan
`api-webhook-pemasangan/keputusan.md` §4 "Peta pengembangan" — tambah entri config + kode eksplisit
dulu, baru pertimbangkan tabel dinamis kalau jumlah konsumen mulai banyak.

## Kenapa `idempotency_key`, bukan `customer_id`, sebagai kunci pencarian

Endpoint #2 dan #3 menerima `idempotency_key` (dari payload `installation.activated`),
bukan `customer_id` atau `cid`:

- **`customer_id`** — ID internal Whusnet, Website B tidak pernah tahu nilainya
  (bukan bagian dari payload manapun). Menerimanya dari request luar juga larangan
  keras lintas-API (`../README.md`): satu parameter ID yang lolos dari luar berarti
  siapa pun bisa mencoba menebak-nebak ID pelanggan lain.
- **`cid`** — nullable untuk pelanggan pra-aktivasi, tidak stabil (berubah RQ↔CID
  seiring lifecycle), dan justru salah satu nilai yang mau ditulis endpoint #2
  (lewat regenerasi) — memakainya sebagai kunci pencarian sekaligus target tulisan
  berisiko race/ambigu.
- **`idempotency_key`** — sudah ada di payload yang mereka terima, unik per
  aktivasi, dan menunjuk baris `webhook_outbox` yang bisa dilacak balik ke
  pelanggan mana pun tanpa menyandingkan ID internal ke luar organisasi.

## Retensi `webhook_outbox` — sudah terjaga

Endpoint #2 dan #3 cuma bisa resolve pelanggan selama baris `webhook_outbox` sumber
`idempotency_key` masih ada. Kalau baris itu ikut kena purge/arsip rutin sebelum
Website B sempat konfirmasi (mis. assignment dilakukan manual oleh staf mereka,
menyusul beberapa hari setelah `installation.activated` diterima), endpoint akan
404 permanen tanpa jalan keluar buat pelanggan itu.

**Syarat:** retensi `webhook_outbox` minimal **90 hari** sebelum baris boleh
di-purge/diarsipkan — cukup untuk menutup rentang "aktivasi diterima → assignment
dikonfirmasi" yang wajar, termasuk kasus manual yang tertunda. Sudah dipenuhi:
`app/Console/Commands/PruneWebhookOutbox.php` (`webhook-outbox:prune`) defaultnya
`--days=90`, dan cuma menghapus baris `status=delivered` — baris `failed`/`pending`/
`skipped` tidak pernah ikut terhapus.
