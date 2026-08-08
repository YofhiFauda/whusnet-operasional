# Modul QR Code Pelanggan

**Status: RANCANGAN — belum diimplementasi. Tidak ada kode, migration, route, atau tabel untuk modul ini di aplikasi.**

Last updated: 2026-08-07

---

## Isi folder

| File | Isi |
|---|---|
| [`rancangan-qr-pelanggan.md`](rancangan-qr-pelanggan.md) | Rancangan lengkap: kelayakan, desain payload + HMAC, skema DB, routing, 4 fungsi, keamanan, rencana fase, log keputusan |

Dokumen modul standar (`business-logic.md`, `database-schema.md`, `flowchart.md`, `user-flow.md`) **belum dibuat** — akan menyusul saat implementasi dimulai. Membuatnya sekarang hanya menghasilkan dokumentasi untuk kode yang belum ada.

---

## Ringkasan satu menit

Satu QR statis per pelanggan (stiker di ONT), melayani 4 fungsi. Routing ditentukan server berdasarkan siapa yang memindai — bukan 4 QR berbeda.

```
https://ops.whusnet.id/q/{ULID26}.{HMAC10}

  Tamu           → halaman tagihan (gerbang PIN)             [pembayaran]
  Teknisi + task → mulai Task + geolocation                  [absen]
  Helpdesk/FOP   → form tiket ter-prefill                    [ticketing]
  Pelanggan+PIN  → login portal pelanggan                    [portal ditunda]
```

**Identitas:** `(pop_id, customer_code)` — persis composite unique tabel `customers`.
**Keamanan:** opaque token (bisa dicabut) **+** HMAC-SHA256 dengan `QR_HMAC_SECRET` (tidak bisa dipalsukan) **+** PIN 6 digit untuk membuktikan pemindainya pelanggan. Ketiganya menjawab pertanyaan berbeda.

---

## Dua media cetak — jangan digabung

| Media | Isi | Ditempel/diberikan |
|---|---|---|
| **Stiker ONT** | QR + nama + CID + signature | Ditempel di ONT/luar rumah — publik, memang harus bisa dipindai teknisi |
| **Kartu Pelanggan** | QR + **PIN** | Diserahkan ke tangan pelanggan — privat, seperti kartu ATM |

**PIN tidak boleh dicetak di stiker ONT.** Stiker tertempel di luar rumah; memotretnya akan memberi QR dan PIN sekaligus, dan dua faktor runtuh jadi satu. Ini pembeda utama antara rancangan ini dan usulan awal "PIN dicetak di bawah QR".

---

## Tiga hal yang paling mudah salah

1. **Jangan hash `display_id`.** `display_id` berubah RQ↔CID seiring lifecycle pelanggan. REQ ID (`customer_code`) di baliknya permanen — itu yang di-hash.

2. **Jangan buang `pop_id` dari bahan HMAC.** `customer_code` cuma unik per POP sejak migration `2026_07_20_141841_scope_customer_code_unique_to_pop`. Tanpa `pop_id`, dua pelanggan beda cabang dengan REQ ID sama mendapat QR identik — teknisi scan stiker A, sistem buka task B.

3. **QR sendirian tidak membuktikan kehadiran teknisi.** Stiker bisa difoto, dan foto QR asli tetap punya signature yang sah. Pembuktian datang dari geolocation + cek penugasan tim + cek jadwal. HMAC tidak menutup celah ini dan tidak dirancang untuk itu.

4. **Jangan masukkan `pin_hash` ke bahan HMAC.** Kalau ikut ditandatangani, setiap reset PIN mengubah signature → stiker QR yang sudah tertempel mati → "pelanggan lupa PIN" berubah jadi kunjungan teknisi. Token QR dan PIN sengaja dua sumbu rotasi terpisah.

5. **Tidak ada layar "lihat PIN".** PIN tampil sekali saat diterbitkan, setelah itu hanya bisa diterbitkan ulang. Kalau admin bisa melihat PIN pelanggan, PIN berhenti membuktikan identitas pelanggan.

---

## Prasyarat sebelum implementasi

Belum diputuskan — lihat §11 dokumen rancangan:

- Coverage `customers.latitude`/`longitude` (memblokir fungsi absen kalau banyak kosong)
- Radius toleransi absen (usulan 150 m)
- Media & ukuran cetak stiker
- Pelanggan legacy dengan `customer_code`/`pop_id` kosong
- Payment gateway (tidak memblokir Fase 1–3)

## Dependensi yang belum ada

- `endroid/qr-code` atau `bacon/bacon-qr-code` — belum di `composer.json`, butuh persetujuan
- Helper base32 (~20 baris di `app/Helpers/`, PHP tidak punya bawaan)
- `QR_HMAC_SECRET` di `.env`, terpisah dari `APP_KEY`

---

## Kaitan dengan modul lain

| Modul | Kaitan |
|---|---|
| [Task Teknisi](../task-teknisi/README.md) | Fungsi absen memanggil `TaskService::start()`. **Semua guard existing tetap berlaku** — QR hanya jalur masuk baru, bukan alur paralel |
| [Ticketing](../ticketing/README.md) | Fungsi ticketing hanya mem-prefill `customer_id` di form. **Alur sync Ticket↔FopTask↔Task tidak disentuh** |
| [Billing & Pembayaran](../billing-pembayaran/README.md) | Halaman tagihan publik membaca invoice. Pencatatan pembayaran tetap lewat jalur existing; `PaymentObserver` tidak dikecualikan |
| [RBAC](../rbac/README.md) | Permission baru (`customers.qr.*`, `qr_scan.attendance`, `qr_scan_logs.view`) di-generate lewat `features` × `actions`, bukan hardcode. Semua endpoint terautentikasi lewat `EffectiveAccessService` |
