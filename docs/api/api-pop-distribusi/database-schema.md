# Database Schema — API Baru: Topologi Jaringan & Konfirmasi Assignment

## Tidak ada tabel baru

Kedua endpoint membaca/menulis struktur yang **sudah ada**:

| Tabel | Dipakai untuk | Endpoint |
|---|---|---|
| `pops` | Baca daftar Mini POP (`type=mini_pop`, `parent_id`) | #1 (baca), #2 (validasi) |
| `distributions` | Baca daftar Distribusi (`pop_id` = Mini POP) | #1 (baca), #2 (validasi) |
| `customers` | Tulis `mini_pop_id`, `distribution_id`, `cid` | #2 (tulis) |
| `customer_devices` | Tulis `pppoe_username`, `pppoe_password`, `ip_address` (opsional, kalau `perangkat` dikirim) | #2 (tulis) |
| `audit_logs` | Catat siapa/kapan/apa yang berubah | #2 (tulis) |

**Tidak ada kolom baru di `customers`.** `mini_pop_id`, `distribution_id`, `cid`
sudah ada — dipakai jalur staf yang sudah ada
(`CustomerNetworkAssignmentController`) dan endpoint ini cuma jalur kedua ke tabel
yang sama.

**Tidak ada kolom baru di `customer_devices`.** `pppoe_username`,
`pppoe_password`, `ip_address` sudah ada (`app/Models/CustomerDevice.php`), sudah
diisi jalur staf yang sudah ada (`storePemasangan()`). Endpoint ini jalur kedua ke
kolom yang sama — **`pppoe_password` tetap plaintext**, tidak ada `encrypted` cast
ditambahkan di sini karena konsisten dengan kolom yang sudah ada; lihat pertanyaan
terbuka soal ini di `rencana-implementasi.md`.

## Kredensial — hardcode di `.env`, konsisten dengan `api-webhook-pemasangan`

Dua token bearer terpisah (baca vs tulis), pola sama seperti `api-webhook-pemasangan` rev. 8: satu
konsumen (Website B), tidak perlu tabel dinamis.

```php
// config/webhooks.php (tambahan)
'pop_distribusi_read_token' => env('POP_DISTRIBUSI_READ_TOKEN'),
'network_assignment_write_token' => env('NETWORK_ASSIGNMENT_WRITE_TOKEN'),
```

Kalau nanti ada konsumen kedua untuk API ini, ikuti alasan yang sama dengan
`api-webhook-pemasangan/keputusan.md` §4 "Peta pengembangan" — tambah entri config + kode eksplisit
dulu, baru pertimbangkan tabel dinamis kalau jumlah konsumen mulai banyak.

## Kenapa `idempotency_key`, bukan `customer_id`, sebagai kunci pencarian

Endpoint #2 menerima `idempotency_key` (dari payload `installation.activated`),
bukan `customer_id` atau `cid`:

- **`customer_id`** — ID internal Whusnet, Website B tidak pernah tahu nilainya
  (bukan bagian dari payload manapun). Menerimanya dari request luar juga larangan
  keras lintas-API (`../README.md`): satu parameter ID yang lolos dari luar berarti
  siapa pun bisa mencoba menebak-nebak ID pelanggan lain.
- **`cid`** — nullable untuk pelanggan pra-aktivasi, tidak stabil (berubah RQ↔CID
  seiring lifecycle), dan justru salah satu nilai yang mau ditulis endpoint ini
  (lewat regenerasi) — memakainya sebagai kunci pencarian sekaligus target tulisan
  berisiko race/ambigu.
- **`idempotency_key`** — sudah ada di payload yang mereka terima, unik per
  aktivasi, dan menunjuk baris `webhook_outbox` yang bisa dilacak balik ke
  pelanggan mana pun tanpa menyandingkan ID internal ke luar organisasi.
