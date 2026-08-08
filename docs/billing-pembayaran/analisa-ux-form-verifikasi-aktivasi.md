# Analisa UX Form Verifikasi Aktivasi (Tagihan Pertama)

> **Status: sudah diimplementasi (per 2026-07-21).**
> Form sekarang cuma punya 5 input — tanggal aktivasi, biaya pemasangan,
> materai, kabel, tiang — dan sisanya kwitansi read-only. Bagian "Latar" di
> paling bawah dipertahankan sebagai catatan kondisi lama, bukan deskripsi
> form yang berjalan.

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
| Biaya pemasangan | `internet_packages.installation_fee` (kolomnya sudah ada, tinggal prefill — wajib fallback 0, ada paket yang nilainya `null`) | bisa digratiskan/promo |
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
Aktif 21 Jul 2026 · ditagih 10 dari 31 hari

Langganan Juli (10 dari 31 hari)      Rp  35.484
Biaya pemasangan                      Rp 100.000
Materai                               Rp  10.000
Kabel tambahan                        Rp       0
Tiang tambahan                        Rp       0
──────────────────────────────────────────────
TAGIHAN PERTAMA                       Rp 145.484
Dibayar saat aktivasi
Mulai Agustus: Rp 110.000/bulan, jatuh tempo tanggal 10
```

Baris PPN hanya muncul kalau rate-nya > 0 — untuk semua paket saat ini PPN
sudah termasuk harga, jadi barisnya tidak ditampilkan sama sekali.

Angka 35.484 = `round(10/31 × 110.000)`. Hari aktivasi tidak ditagih, dan
pembulatannya `round` mengikuti legacy — lihat
[perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md)
bagian 3.1.

Tidak ada istilah yang perlu dijelaskan. "10 dari 31 hari" sudah menjelaskan
prorata tanpa menyebut kata prorata. Baris terakhir menjawab pertanyaan
pelanggan yang paling sering ("bulan depan bayar berapa?") tanpa admin perlu
menghitung.

## Efek ke kecepatan

Sekarang: 8 field terlihat, 4 di antaranya angka readonly yang bikin ragu,
admin sering berhenti mengecek apakah totalnya masuk akal.

Sesudah: kasus normal = **nol pengetikan**, baca kwitansi, klik Aktivasi.

## Bug yang ikut tertutup

**Sudah mati.** `billing_period` dan `issue_date` dulu diinput terpisah dan
divalidasi apa adanya, jadi admin yang mengubah tanggal aktivasi ke bulan lain
menghasilkan invoice berperiode Juni tapi prorata Juli — periode yang dicetak
di tagihan tidak sama dengan bulan yang benar-benar ditagih, sementara bulan
yang dilewati `GenerateMonthlyInvoicesCommand` mengikuti `activation_date`
(= `issue_date`), bukan periode yang tertulis.

Sekarang keduanya diturunkan dari tanggal aktivasi di `finalVerify()` dan tidak
lagi diterima dari klien. Dijaga oleh
`tests/Feature/TagihanAwalPeriodeIkutTanggalAktivasiTest.php`.

## Lingkup kerja

Semua sudah dikerjakan:

- `CustomerVerificationController::finalVerify()` — `billing_period`/`due_date` diturunkan dari `issue_date`, tidak lagi divalidasi dari request; menerima & menyimpan `other_fee`
- `App\Services\InitialInvoiceService` — komponen materai (`other_fee`) masuk subtotal, plus `next_month_amount` untuk baris terakhir kwitansi
- `resources/views/verifications/admin.blade.php` — input periode & jatuh tempo dihapus, field materai ditambah, biaya pemasangan prefill dari `internet_packages.installation_fee` (fallback 0), panel nominal `readonly` (`subtotal`, `prorate_amount`, `discount`, `ppn`, `total_amount`) diganti kwitansi. Parameter layanan (harga, diskon, PPN) dititipkan di `data-*` pada `#billing_params` — bukan `<input>`, supaya tidak ikut ter-POST.
- Test: `TagihanAwalPeriodeIkutTanggalAktivasiTest` (9 kasus) + tambahan di `tests/Unit/InitialInvoiceProrateFormulaTest.php`

Yang sudah beres sejak sebelumnya: perhitungan nominal di server (nilai kiriman
klien diabaikan) dan pengisian `customer_services.activation_date` saat
aktivasi. Lihat
[perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md)
bagian 5.1 dan 3.3.

## Latar: arti dua field yang membingungkan (sudah tidak ada di form)

Blok di bawah menggambarkan tata letak lama. Dipertahankan supaya kalau ada yang
menemukan kolom `invoices.subtotal` dan `invoices.prorate_amount` lalu bingung
bedanya, penjelasannya masih ada.

- **TAGIHAN PRORATE** = bagian langganan bulan pertama saja.
  `hari_sisa / hari_sebulan × harga_paket`. Aktivasi 16 Juni → 14/30 × 300.000 = 140.000
  (hari aktivasi tidak ditagih).
- **SUBTOTAL (PRORATA + BIAYA)** = prorata + biaya pemasangan + kabel + tiang +
  materai. Dasar hitung diskon & PPN. Yang masuk kolom `invoices.subtotal`.

Prorata adalah **komponen di dalam** subtotal, bukan angka sejajar. Tata letak
lama menampilkan subtotal sebelum komponennya diinput — inilah yang bikin
karyawan baru bingung:

```
[ SUBTOTAL (prorata+biaya) ] [ TAGIHAN PRORATE ]   ← subtotal muncul duluan
[ pemasangan ] [ kabel ] [ tiang ]                 ← padahal isinya dari sini
[ diskon ] [ PPN % ]
[ TOTAL ]
```

Sekarang urutannya mengikuti alur hitung, dan yang di bawah bukan input lagi
melainkan kwitansi:

```
[ tanggal aktivasi ]                               ← input admin
[ pemasangan ] [ materai ] [ kabel ] [ tiang ]     ← input admin
────────────── kwitansi (read-only) ──────────────
Langganan <bulan> (X dari Y hari)
Biaya pemasangan / materai / kabel / tiang
[diskon]  [PPN %]                                  ← baris hanya muncul kalau > 0
TAGIHAN PERTAMA
Mulai <bulan depan>: Rp X/bulan, jatuh tempo tanggal 10
```
