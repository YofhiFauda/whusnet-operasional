# Perbandingan: Cara Membedakan Tagihan Awal vs Bulanan — Legacy vs Whusnet Operasional

Dokumen pembanding antara sistem lama (CodeIgniter, hasil analisa di
`ALUR-PEMBAYARAN.md` di root repo) dan sistem sekarang. Tujuannya satu:
menjelaskan **kenapa** aturan legacy tidak diwarisi apa adanya, supaya waktu
migrasi data atau debugging tagihan tidak ada yang mencoba "mengembalikan"
perilaku lama.

Ringkas: **konsepnya sama, mekanisme pembedanya beda mendasar.**

Penghitungan = jika pelanggan mendaftar pada tanggal 31 dan bulan juli juga sampai 31 makaaa tagihan akan 1
  bulan penuh dengan pembayaran bulan Agustus jadi Juli Gratis dan pelanggan langsung bayar
  bulanan
---

## 1. Kunci pembeda

| | Legacy | Whusnet Operasional |
|---|---|---|
| Penanda jenis tagihan | **Implisit** — ada/tidaknya baris di `apikeuangan_buktitransaksipemasangan` | **Eksplisit** — kolom `invoices.invoice_type` (`App\Enums\InvoiceType`: `awal`, `bulanan`, `reaktivasi`) |
| Kapan jenis ditentukan | Saat **pembayaran** (`M__Pembayaran::checkIdBayar()`) dan saat **cron** (`cekDataBiayaAwal()`, `bayarpemasangan()`) | Saat **invoice dibuat**, sekali, lalu tidak berubah |
| Efek samping pembayaran | INSERT baris `…_pemasangan` = penanda permanen "biaya pemasangan lunas" | Tidak ada. `Payment` hanya menempel ke invoice yang jenisnya sudah pasti |
| Jumlah tempat yang menentukan jenis | 3 (`checkIdBayar`, `cekDataBiayaAwal`, `bayarpemasangan`) | 1 kolom |

Legacy **menyimpulkan** jenis tagihan dari jejak pembayaran — jenis tagihan
jadi turunan dari state kas. Konsekuensinya: selama pembayaran awal belum
tercatat, cron terus menerbitkan tagihan `TOTALBIAYA` (biaya pemasangan ikut
ditagih ulang tiap bulan).

Sistem sekarang membalik arahnya: jenis ditulis di sumber, pembayaran jadi
konsumen pasif.

- `CustomerVerificationController::finalVerify()` → `InvoiceType::AWAL`
- `GenerateMonthlyInvoicesCommand` → `InvoiceType::BULANAN`

---

## 2. Yang secara substansi sama

1. Tagihan awal terbit saat **verifikasi administrasi**, bukan saat pendaftaran.
   Legacy: `Controll_antrianProses::updateBiaya()`. Sekarang: `finalVerify()`.
2. Tagihan awal = biaya pemasangan + prorata bulan pertama + biaya lain-lain,
   dalam **satu** record tagihan.
3. Tagihan bulanan = harga paket, digenerate proses terjadwal.
   Legacy: `CronTambahTagihanBulanan`. Sekarang: `billing:generate-monthly-invoices`.

---

## 3. Yang berbeda di detail

### 3.1 Rumus prorata — beda 1 hari

**Bukan dua rumus.** Rumusnya satu:

```
prorata = jumlah_hari_ditagih × (harga_paket / jumlah_hari_bulan)
```

Yang berbeda hanya cara menghitung `jumlah_hari_ditagih`:

| | Hitungan | Contoh: 21 Juli, paket 110.000 |
|---|---|---|
| Berlaku (= legacy) | `31 - 21 = 10` hari — hari aktivasi **tidak** ditagih | **35.484** |
| Ditolak | `31 - 21 + 1 = 11` hari — hari aktivasi ditagih | 39.032 |

Keputusan bisnis 2026-07-21: konvensi hari legacy dipertahankan — hari
pemasangan digratiskan. Pembulatannya juga mengikuti legacy, `round` bukan
`floor`: `10 × (110.000/31) = 35.483,87 → 35.484`. Selisih satu rupiah itu
disengaja supaya nominal tagihan awal hasil migrasi bisa dicocokkan angka per
angka.

Satu hal yang **tidak** direplikasi: cabang legacy "kalau besok sudah tanggal 1
→ tagih 1 hari". Aktivasi di hari terakhir bulan menyisakan 0 hari, dan
keputusannya ditagih **sebulan penuh**. Akibatnya ada tebing di ujung bulan —
aktif 30 Juli bayar 1 hari (3.548), aktif 31 Juli bayar 110.000. Itu disengaja;
jangan diratakan tanpa keputusan bisnis baru.

**Di kode ini hanya ada satu implementasi, tanpa percabangan konvensi** —
`App\Services\InitialInvoiceService::calculate()`. Rumus JS di
`verifications/admin.blade.php` cuma cermin untuk preview dan wajib dijaga
identik — kalau meleset, nilai server yang tersimpan.

Dikunci oleh `tests/Unit/InitialInvoiceProrateFormulaTest.php`. Kalau angka
kanonik 35.484 berubah jadi 39.032, berarti ada yang menambahkan hari aktivasi
ke hitungan tanpa keputusan bisnis.

**Implikasi migrasi:** karena konvensi hari dan pembulatannya sama dengan
legacy, nominal prorata lama seharusnya cocok. Meski begitu
`MigrateLegacyDataCommand` tetap **tidak** menghitung ulang prorata, tapi
mengambil nominal yang benar-benar tertagih/terbayar (`total_amount` dari bukti
tagihan + bukti pemasangan) — data lama bisa mengandung koreksi manual, dan
cabang "besok tanggal 1 → tagih 1 hari" tidak direplikasi di sini.

### 3.2 Nominal bulanan

| Legacy | Sekarang |
|---|---|
| `paket.HARGA` mentah, lalu **ditulis balik** ke `biaya_tagihan.BIAYABULANAN` | `customer_service.monthly_price` − diskon + PPN%, tidak menyentuh master |

Tulis-balik legacy berarti perubahan harga paket bisa merembet ke record biaya
lama. Sekarang harga di-snapshot di `customer_services` saat berlangganan.

### 3.3 Perlindungan tagihan dobel

Legacy tidak punya. Sekarang tiga lapis di `GenerateMonthlyInvoicesCommand`:

1. Lewati bulan aktivasi — periode itu sudah ditagih invoice `AWAL`.
2. Lewati kalau sudah ada invoice `BULANAN` untuk periode yang sama.
3. `InvoiceObserver::creating()` sebagai backstop.

**Lapis 1 tidak punya cadangan.** Lapis 2 dan 3 sama-sama di-scope
`invoice_type`, jadi keduanya menganggap `AWAL` dan `BULANAN` pada periode yang
sama bukan duplikat. Tabel `invoices` juga **tidak** punya unique index —
migration `add_duplicate_guard_indexes_to_invoices_and_payments` sengaja hanya
memasangnya di `payments` karena data invoice hasil migrasi masih menyimpan
pelanggaran historis (alasannya ada di docblock migration itu).

Akibatnya `customer_services.activation_date` harus benar; kalau isinya meleset
satu bulan, pelanggan menerima dua tagihan untuk periode yang sama dan tidak ada
lapis lain yang menahannya. Dulu kolom itu diisi `registration_date` saat
pendaftaran dan tidak pernah ditimpa saat aktivasi — dijaga sekarang oleh
`tests/Feature/AktivasiTertagihDobelKarenaActivationDateStaleTest.php`. Backfill
data lama belum dikerjakan (`BILLING-B0b` di `docs/TASKS.md`, Blocked).

Rencana menghilangkan ketergantungan pada satu kolom ini — penjaga lintas-jenis,
command audit, unique index — ada di
[analisa-pencegahan-tagihan-dobel.md](analisa-pencegahan-tagihan-dobel.md).

Ditambah `issue_date`/`due_date` memakai jendela kalender tetap (terbit
tanggal 1, jatuh tempo tanggal 10), bukan "tanggal cron jalan + 10 hari" —
supaya tidak melenceng kalau command telat jalan atau dipicu manual.

### 3.4 Status pembayaran

| Legacy `FLAG` | Sekarang |
|---|---|
| `0` belum dibayar | `InvoiceStatus::BELUM_DIBAYAR` |
| `1` masuk `…_terkumpul` | — (tidak ada padanan langsung) |
| `2` lunas | `InvoiceStatus::LUNAS` |

Legacy tidak punya konsep bayar sebagian yang rapi. Sekarang
`paid_amount`/`remaining_amount` bikin `InvoiceStatus::SEBAGIAN` jadi
first-class.

### 3.5 Arti kolom `ppn`

`invoices.ppn` menyimpan **persen**, bukan nominal — `invoices/show.blade.php`
menghitung ulang nominalnya dari persen itu. Menyimpan nominal di kolom
tersebut membuat detail tagihan menampilkan "PPN 16500%".

---

## 4. Terjemahan saat migrasi

`MigrateLegacyDataCommand` memecah satu baris `biaya_tagihan` legacy jadi dua
invoice:

| Kondisi baris legacy | Hasil |
|---|---|
| `BIAYAPASANG > 0` atau `BIAYALAINLAIN > 0` | invoice `-AWAL`, `invoice_type = awal` |
| selain itu, `BIAYABULANAN > 0` | invoice `-BULANAN`, `invoice_type = bulanan` |

Pembayaran diarahkan mengikuti jenis invoice yang benar-benar dibuat untuk
`IDBIAYA` tersebut. Pembayaran dari `apikeuangan_buktitransaksipemasangan`
**selalu** ke invoice `AWAL` — tabel itu memang hanya mencatat pelunasan biaya
pemasangan.

Baris `BAYAR = 0` dilewati: itu placeholder log aktivasi, bukan pembayaran.
`PaymentObserver::creating()` juga menolaknya dari semua jalur masuk.

---

## 5. Celah legacy yang sengaja tidak diwarisi

### 5.1 Nominal dihitung klien

Legacy menghitung prorata di server (`GetTagihanAwal()`), tapi `TOTALBIAYA`
dijumlah di JavaScript (`material.TOTAL()`) lalu dikirim POST. Form verifikasi
sistem ini awalnya mengulang pola yang sama: `subtotal`, `discount`, `ppn`,
`prorate_amount`, dan `total_amount` semuanya input `readonly` hasil hitungan
JS. `readonly` cuma penghalang UI — siapa pun yang bisa POST bisa mengirim
nominal apa saja.

**Sudah ditutup.** `finalVerify()` sekarang hanya memvalidasi field yang benar-
benar diinput admin (periode, tanggal, tiga biaya tambahan) dan menghitung
ulang seluruh nominal lewat `App\Services\InitialInvoiceService`. Nilai kiriman
klien diabaikan; JS di `verifications/admin.blade.php` statusnya **preview**.

Dijaga oleh `tests/Feature/InitialInvoiceProrateIgnoresClientAmountTest.php`.

### 5.2 Filter hardcode di cron

`CronTagihanBulanan::getDatacron()` legacy masih menyimpan
`pw.IDPERMINTAAN = 'RQ000247'`, sehingga cron itu hanya memproses satu
pelanggan. Tidak relevan lagi di sistem ini, tapi penting waktu membaca data
legacy: tagihan bulanan legacy bisa tidak lengkap karena cron-nya lumpuh.

### 5.3 `InvoiceType::REAKTIVASI`

Tidak ada padanan di legacy, jadi tidak ada acuan migrasi. Belum punya consumer
otomatis — lihat `docs/post-mvp/RENCANA_OTOMATISASI_TERLAMBAT_SUSPEND_HARDWARE.md`.

---

## 6. Rumus tagihan awal yang berlaku sekarang

Sumber tunggal: `App\Services\InitialInvoiceService::calculate()`.

```
prorateDays = jumlah_hari_bulan(issue_date) - tanggal(issue_date)
              (kalau hasilnya 0 → jumlah_hari_bulan, aktivasi hari terakhir ditagih penuh)
prorata     = round(prorateDays / jumlah_hari_bulan * customer_service.monthly_price)
subtotal    = prorata + biaya_pemasangan + kabel_tambahan + tiang_tambahan + materai
afterDisc   = max(0, subtotal - customer_service.discount)
ppn_amount  = round(afterDisc * customer_service.ppn / 100, 2)
total       = afterDisc + ppn_amount
```

Materai masuk lewat `other_fee` dan hanya menambah subtotal — bukan basis
prorata. `next_month_amount` dihitung terpisah (`monthly_price` − diskon, lalu
PPN persen) untuk baris "mulai bulan depan" di kwitansi; angkanya wajib sama
dengan yang nanti diterbitkan `GenerateMonthlyInvoicesCommand`.

Rumus JS di `verifications/admin.blade.php` wajib dijaga identik supaya angka
yang dilihat admin sama dengan yang tersimpan — tapi kalau menyimpang, yang
menang tetap server.

### 6.1 Aturan bisnis terkonfirmasi (2026-07-21)

Aturan di bawah sudah disepakati bisnis. **Disepakati ≠ sudah jalan** — kolom
status menyebut mana yang benar-benar hidup di kode hari ini. Jangan membaca
bagian ini sebagai deskripsi perilaku sistem.

| # | Aturan | Status | Di mana |
|---|---|---|---|
| 1 | PPN sudah termasuk harga paket (rate 0) | **Berlaku** | `InitialInvoiceService::calculate()` baca `customer_services.ppn` |
| 2 | Jatuh tempo bulanan selalu tanggal 10 | **Berlaku** | `GenerateMonthlyInvoicesCommand` |
| 3 | Tagihan awal jatuh tempo = tanggal aktivasi | **Berlaku** | `finalVerify()` menurunkannya dari `issue_date`; tidak lagi diinput admin |
| 4 | Materai ditagih di tagihan awal, simpan di `invoices.other_fee` | **Berlaku** | field `other_fee` di form → `calculate()` → kolom `invoices.other_fee` |
| 5 | Prorata & materai hanya di tagihan awal, bulan berikutnya penuh | **Berlaku** | `GenerateMonthlyInvoicesCommand` menghitung murni dari `monthly_price` |

Rincian:

1. **PPN sudah termasuk di harga paket**, berlaku untuk semua paket. Nilai yang
   dibaca saat menghitung adalah `customer_services.ppn` (snapshot per
   pelanggan), bukan tabel paket — `internet_packages.ppn` hanya nilai awal
   yang disalin saat berlangganan, defaultnya `0.00`. Field PPN tetap
   dipertahankan di form dan di kolom `invoices.ppn` sebagai cadangan kalau
   suatu saat ada paket yang dipisah pajaknya. **Jangan** menaikkan basis
   prorata ke `total_monthly_bill` untuk "mengembalikan" PPN — itu memungut
   pajak dua kali.
2. **Jatuh tempo bulanan selalu tanggal 10**, semua paket. Tagihan bulanan
   terbit tanggal 1, tempo tanggal 10.
3. **Tagihan awal dibayar saat aktivasi**, bukan menunggu jatuh tempo. Jatuh
   temponya = tanggal aktivasi itu sendiri. `billing_period` dan `due_date`
   tidak lagi diterima dari form — keduanya diturunkan dari `issue_date` di
   `finalVerify()`.
4. **Materai** ikut ditagih di tagihan awal, disimpan di `invoices.other_fee`.
   Tidak pernah muncul di tagihan bulanan. Perhatikan `customer_services`
   punya kolom bernama sama dengan arti berbeda (biaya di luar standar yang
   melekat ke layanan) — jangan disalin silang.
5. **Prorata & materai hanya ada di tagihan awal.** Mulai bulan berikutnya
   pelanggan bayar penuh sesuai paket.

Penanda bulan aktivasi adalah `customer_services.activation_date`, ditimpa
dengan `issue_date` saat `finalVerify()`. Kolom itu satu-satunya yang bisa
membedakan invoice AWAL dan BULANAN pada periode yang sama — dua penjaga dobel
lainnya di-scope per `invoice_type`, jadi keduanya menganggap AWAL dan BULANAN
bukan duplikat. Lihat 3.3.

### 6.2 Contoh kanonik

Pelanggan pasang **21 Juli 2026**, paket **Rp 110.000/bulan**:

```
Juli (tagihan awal, dibayar saat aktivasi 21 Juli)
  prorateDays = 31 - 21 = 10 hari (hari aktivasi digratiskan)
  prorata     = round(10/31 × 110.000) = 35.484
  + biaya pemasangan / kabel / tiang / materai bila ada

Agustus dan seterusnya (tagihan bulanan)
  terbit 1 Agustus, jatuh tempo 10 Agustus
  nominal = 110.000 penuh (tanpa prorata, tanpa materai)
```

Angka 35.484 adalah acuan verifikasi rumus. Kalau perhitungan sistem mendarat di
39.032, berarti hari aktivasi ikut ditagih — konvensinya bergeser tanpa
keputusan bisnis. Kalau mendarat di 35.483, pembulatannya berubah dari `round`
jadi `floor`.

---

## Referensi

- `ALUR-PEMBAYARAN.md` — analisa sistem lama
- `app/Services/InitialInvoiceService.php`
- `app/Http/Controllers/CustomerVerificationController.php` (`finalVerify`)
- `app/Console/Commands/GenerateMonthlyInvoicesCommand.php`
- `app/Console/Commands/MigrateLegacyDataCommand.php`
- `docs/billing-pembayaran/archive/analisa-bug-migrasi-tagihan-awal-bulanan.md`
