# Analisa Bug Migrasi: Pembayaran Awal vs Bulanan

Dokumen ini merangkum investigasi dan perbaikan pada `app/Console/Commands/MigrateLegacyDataCommand.php` terkait pemisahan invoice **AWAL** (registrasi/PSB) vs **BULANAN** (recurring), berdasarkan cross-check langsung ke dump SQL legacy (`jetis_db_aplikasi_jetis.sql`, `sand_db_sandya.sql`).

## Ringkasan Masalah Awal

Data hasil migrasi mencampur pembayaran awal (biaya lain, PPN, diskon, kadang prorate) dengan pembayaran bulanan (harusnya flat = harga paket). Root cause bertumpuk beberapa lapis, ditemukan lewat beberapa sample pelanggan nyata.

## Root Cause #1 — Prorate ikut nyasar ke invoice BULANAN

**Kasus contoh:** Toty Wibowo, paket 138.000, `BIAYABULANAN` tercatat 26.710 (prorate hasil hitungan admin lama).

Kode awal membuat invoice BULANAN dengan nominal prorate (26.710) alih-alih flat. Padahal aturan bisnis: **BULANAN harus selalu flat = harga paket**; nominal di bawah itu adalah prorate periode pertama yang seharusnya masuk ke invoice AWAL.

**Fix:** jika `BIAYABULANAN < harga paket`, seluruh nominal tersebut digabung ke invoice AWAL (`installTotal += monthlyFee`), tidak ada invoice BULANAN terpisah untuk baris tersebut.

## Root Cause #2 — Payment lumped (gabungan) hanya nempel ke satu invoice

**Kasus:** pembayaran CASH tunggal yang totalnya = jumlah invoice AWAL + BULANAN sekaligus, tapi sistem lama cuma mencatatnya sebagai bukti "tagihan bulanan" — sehingga migrasi menempelkannya 100% ke invoice BULANAN dan invoice AWAL tetap "Belum Dibayar" walau uangnya sudah masuk.

**Fix (iterasi awal):** deteksi kalau `BAYAR == awal + bulanan`, split jadi 2 payment record.

## Root Cause #3 — `biaya_tagihan` berfungsi ganda

**Temuan penting:** tabel `biaya_tagihan` di legacy **tidak selalu 1 baris = 1 kejadian PSB**. Untuk sebagian pelanggan lama (contoh: `PE000401` sampai 40 baris), tabel ini justru menyimpan log tagihan bulanan berulang.

**Aturan final yang dipakai:**
- Baris dengan `BIAYAPASANG > 0` atau `BIAYALAINLAIN > 0` → baris **registrasi/AWAL**. Nominal invoice = **actual paid** (grab dari bukti bayar asli), fallback ke `pasang + lainlain + bulanan` kalau belum ada bukti bayar sama sekali.
- Baris lain (tanpa pasang/lainlain, murni bulanan) → invoice **BULANAN** flat = `BIAYABULANAN` apa adanya.

Prinsip: prorate/nominal final sudah dihitung manual oleh admin lama — sistem migrasi **grab as-is**, tidak menghitung ulang.

## Root Cause #4 — Bug duplicate-insert di data legacy

**Kasus:** `PE000401` (40 baris identik, tanggal sama `2023-02-06`, jeda antar baris hanya hitungan menit) dan `PE000377` (17 baris identik `2023-01-16`). Ini bukan riwayat 40 bulan tagihan asli — ini bug retry/duplicate-submit di sistem lama (kemungkinan form ke-submit berkali-kali / cron loop).

Bug yang sama juga menular ke tabel bukti pembayaran `apikeuangan_buktitransaksitagihan` — tiap baris duplikat invoice punya baris duplikat bukti bayar sendiri dengan `BAYAR` yang sama persis. Kalau tidak di-dedup, total pembayaran ter-akumulasi salah (contoh: 40 × 113.143 ≈ Rp 4,5 juta untuk transaksi yang sebenarnya cuma sekali).

**Fix:** dedup berbasis signature, bukan berdasar ID (karena ID legacy selalu unik walau datanya duplikat):
- `biaya_tagihan`: signature = `IDPELANGGAN|IDPERMINTAAN|BIAYAPASANG|BIAYABULANAN|BIAYALAINLAIN|tanggal(TGLINSERT)`. Baris pertama jadi canonical, baris lain di-skip tapi ID-nya tetap dipetakan ke canonical (`canonicalCostId`) supaya payment yang nempel ke ID duplikat tidak nyasar.
- `apikeuangan_buktitransaksitagihan`: signature = `IDPERMINTAAN|BAYAR|tanggal(INSERTED_AT)`. Dedup dilakukan di awal, sebelum data dipakai di mana pun.

Catatan: dedup berbasis tanggal (bukan menit/detik) supaya tagihan bulanan yang genuinely berulang di bulan berbeda tidak ikut ke-collapse (lihat `PE000377`, baris ke-18 `IN001241` bertanggal beda 2024-05-27 tetap dianggap tagihan terpisah).

## Root Cause #5 — Payment placeholder `BAYAR = 0`

**Kasus:** Wiyono Wonoketro (`RQ001191`), invoice `IN001266-AWAL` (Rp 11.000, Belum Dibayar) tapi ada payment `PAY-667b79addf3` senilai **Rp 0** berstatus "Valid" nempel di situ.

Row sumber di `apikeuangan_buktitransaksitagihan` memang `BAYAR = 0` — ini bukan pembayaran, melainkan log placeholder aktivasi (sistem lama mencatat *event*-nya duluan, sebelum uang benar-benar diterima).

**Fix:** skip pembuatan payment record kalau `BAYAR <= 0`.

## Bukan Bug: Kejadian Bisnis Ganda yang Sah

**Kasus:** Wiyono Wonoketro, dua invoice AWAL:
- `IN001266-AWAL` (Rp 11.000, 2024-06, Belum Dibayar) — pemasangan awal.
- `IN001635-AWAL` (Rp 120.032, 2025-05, Lunas) — reaktivasi.

Cross-check status log `RQ001191`: pelanggan diputus (`Putus Langganan`, "menunggak januari") 2025-04-12, lalu direaktivasi 2025-05-05. Dua invoice AWAL ini mencerminkan dua kejadian tagih yang **benar-benar berbeda** (pasang awal vs reaktivasi setelah nunggak), bukan data ganda. Utang lama Rp 11.000 memang belum pernah dibayar — itu piutang riil yang terbawa, bukan kesalahan migrasi.

## Kesimpulan

Ketidakrapian yang muncul di hasil migrasi murni **warisan dari database lama yang tidak punya constraint/validasi input yang ketat**, bukan murni bug pada skrip migrasi:

1. Sistem lama menyimpan format `BIAYABULANAN` tidak konsisten (kadang flat, kadang sudah-diprorate) tergantung siapa yang input.
2. Tabel `biaya_tagihan` dipakai untuk dua makna berbeda (PSB sekali vs log bulanan berulang) tanpa penanda eksplisit.
3. Ada bug retry/duplicate-submit di aplikasi lama yang menggandakan baris data di dua tabel sekaligus.
4. Ada baris "log event" dengan nominal 0 yang bercampur dengan baris pembayaran asli.
5. Riwayat bisnis nyata (putus-aktifkan-lagi) menghasilkan pola data yang sekilas terlihat aneh tapi sebenarnya valid.

Perbaikan pada `MigrateLegacyDataCommand.php` menangani poin 1-4 dengan pendekatan *grab actual paid, jangan hitung ulang* + dedup berbasis signature data (bukan ID). Poin 5 tidak memerlukan perbaikan kode — cukup dipahami sebagai representasi valid dari riwayat pelanggan.
