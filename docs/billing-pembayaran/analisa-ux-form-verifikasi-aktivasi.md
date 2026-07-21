# Analisa UX Form Verifikasi Aktivasi (Tagihan Pertama)

Konteks: form di `resources/views/verifications/admin.blade.php` membingungkan
karyawan baru — dua field bertumpuk arti ("SUBTOTAL (PRORATA + BIAYA)" vs
"TAGIHAN PRORATE"), dan urutannya menampilkan hasil sebelum komponennya diisi.

Akar masalahnya form ini minta admin memvalidasi aritmatika yang seharusnya
bukan urusan dia. Karyawan baru harus paham prorata, subtotal, urutan
diskon-vs-PPN — padahal keputusan riilnya cuma **4**.

## Solusi praktis: form jadi kwitansi, bukan kalkulator

Yang admin isi (sisanya server yang tahu):

| Field | Default | Kenapa masih diisi manual |
|---|---|---|
| Tanggal aktivasi | tanggal Task Pemasangan selesai | kadang beda dari hari verifikasi |
| Biaya pemasangan | `internet_packages.installation_fee` (kolomnya sudah ada, tinggal prefill) | bisa digratiskan/promo |
| Materai | 0 | tidak semua pemasangan pakai materai |
| Kabel tambahan | 0 | kasuistis lapangan |
| Tiang tambahan | 0 | kasuistis lapangan |

**Dihapus dari form**, diturunkan server:

- Periode tagihan → bulan dari tanggal aktivasi
- Jatuh tempo → sama dengan tanggal aktivasi (tagihan awal dibayar di tempat,
  bukan menunggu tempo; tempo tanggal 10 hanya berlaku untuk tagihan bulanan)
- Subtotal, prorata, diskon, PPN, total → tampil sebagai angka, bukan input

Field PPN tetap ditampilkan walau nilainya 0% — PPN sudah termasuk di harga
paket untuk semua paket, field-nya dipertahankan sebagai cadangan. Lihat
[perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md)
bagian 6.1.

Panel hitungan diganti kwitansi bahasa manusia:

```
Aktif 21 Jul 2026 · ditagih 11 dari 31 hari

Langganan Juli (11 dari 31 hari)      Rp  39.032
Biaya pemasangan                      Rp 100.000
Materai                               Rp  10.000
Kabel tambahan                        Rp       0
Tiang tambahan                        Rp       0
──────────────────────────────────────────────
TAGIHAN PERTAMA                       Rp 149.032
Dibayar saat aktivasi
Mulai Agustus: Rp 110.000/bulan, jatuh tempo tanggal 10
```

Baris PPN hanya muncul kalau rate-nya > 0 — untuk semua paket saat ini PPN
sudah termasuk harga, jadi barisnya tidak ditampilkan sama sekali.

Tidak ada istilah yang perlu dijelaskan. "15 dari 30 hari" sudah menjelaskan
prorata tanpa menyebut kata prorata. Baris terakhir menjawab pertanyaan
pelanggan yang paling sering ("bulan depan bayar berapa?") tanpa admin perlu
menghitung.

## Efek ke kecepatan

Sekarang: 8 field terlihat, 4 di antaranya angka readonly yang bikin ragu,
admin sering berhenti mengecek apakah totalnya masuk akal.

Sesudah: kasus normal = **nol pengetikan**, baca kwitansi, klik Aktivasi.

## Satu bug yang ikut mati

`billing_period` (default bulan ini) dan `issue_date` (default hari ini)
sekarang **tidak tersambung**. Admin yang mengubah tanggal aktivasi ke bulan
lain menghasilkan invoice berperiode Juni tapi prorata Juli.
`GenerateMonthlyInvoicesCommand` melewati bulan aktivasi berdasarkan
`activation_date`, jadi kombinasi itu bisa menghasilkan tagihan dobel.
Menurunkan periode dari tanggal aktivasi menutup celah ini.

## Lingkup kerja

- `resources/views/verifications/admin.blade.php` — tata ulang, tambah field materai
- `CustomerVerificationController::finalVerify()` — turunkan `billing_period`/`due_date`, prefill biaya pemasangan dari paket
- `App\Services\InitialInvoiceService` — tambah komponen materai (`other_fee`) + `next_month_amount` untuk baris terakhir kwitansi
- Test: periode & jatuh tempo mengikuti tanggal aktivasi, materai masuk subtotal

Server-side hitung sudah beres (lihat
[perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md)
bagian 5.1), jadi ini murni penyederhanaan input.

## Latar: arti dua field yang membingungkan

- **TAGIHAN PRORATE** = bagian langganan bulan pertama saja.
  `hari_sisa / hari_sebulan × harga_paket`. Aktivasi 16 Juni → 15/30 × 300.000 = 150.000.
- **SUBTOTAL (PRORATA + BIAYA)** = prorata + biaya pemasangan + kabel + tiang.
  Dasar hitung diskon & PPN. Yang masuk kolom `invoices.subtotal`.

Prorata adalah **komponen di dalam** subtotal, bukan angka sejajar. Tata letak
saat ini menampilkan subtotal sebelum komponennya diinput:

```
[ SUBTOTAL (prorata+biaya) ] [ TAGIHAN PRORATE ]   ← subtotal muncul duluan
[ pemasangan ] [ kabel ] [ tiang ]                 ← padahal isinya dari sini
[ diskon ] [ PPN % ]
[ TOTAL ]
```

Kalau redesign kwitansi di atas belum dikerjakan, minimal urutannya dibetulkan
mengikuti alur hitung:

```
[ TAGIHAN PRORATE (X dari Y hari) ]                ← readonly, dihitung dari tgl terbit
[ pemasangan ] [ kabel ] [ tiang ]                 ← input admin
[ SUBTOTAL ]                                       ← readonly, jumlah di atasnya
[ diskon ] [ PPN % ]
[ TOTAL ]
```
