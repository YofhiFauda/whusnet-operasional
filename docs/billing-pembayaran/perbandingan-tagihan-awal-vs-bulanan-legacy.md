# Perbandingan: Cara Membedakan Tagihan Awal vs Bulanan — Legacy vs Whusnet Operasional

Dokumen pembanding antara sistem lama (CodeIgniter, hasil analisa di
`ALUR-PEMBAYARAN.md` di root repo) dan sistem sekarang. Tujuannya satu:
menjelaskan **kenapa** aturan legacy tidak diwarisi apa adanya, supaya waktu
migrasi data atau debugging tagihan tidak ada yang mencoba "mengembalikan"
perilaku lama.

Ringkas: **konsepnya sama, mekanisme pembedanya beda mendasar.**

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
| Legacy | `31 - 21 = 10` hari — hari aktivasi **tidak** ditagih | 35.484 |
| Sekarang | `31 - 21 + 1 = 11` hari — hari aktivasi **ditagih** | **39.032** |

Legacy juga punya cabang khusus "kalau besok sudah tanggal 1 → tagih 1 hari"
yang **tidak** direplikasi.

Konvensi sekarang dipilih karena pelanggan sudah menerima layanan pada hari
aktivasi: 21–31 Juli = 11 hari terpakai. Legacy menggratiskan hari pemasangan.

**Di kode ini hanya ada satu implementasi, tanpa percabangan konvensi** —
`App\Services\InitialInvoiceService::calculate()`. Rumus legacy hidup di
dokumentasi saja (`ALUR-PEMBAYARAN.md`, hasil bedah aplikasi lama); tidak ada
baris PHP di repo ini yang memakainya. Rumus JS di
`verifications/admin.blade.php` cuma cermin untuk preview — kalau meleset,
nilai server yang tersimpan.

Dikunci oleh `tests/Unit/InitialInvoiceProrateFormulaTest.php`. Kalau angka
kanonik 39.032 berubah jadi 35.484, berarti konvensi harinya bergeser balik ke
legacy tanpa keputusan bisnis.

**Implikasi migrasi:** tagihan awal legacy tidak bisa diverifikasi ulang dengan
rumus baru — selisih 1 hari harga adalah selisih yang diharapkan. Karena itu
`MigrateLegacyDataCommand` tidak menghitung ulang prorata, tapi mengambil
nominal yang **benar-benar tertagih/terbayar** (`total_amount` dari bukti
tagihan + bukti pemasangan).

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
3. Unique index + `InvoiceObserver` sebagai backstop.

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
prorateDays = jumlah_hari_bulan(issue_date) - tanggal(issue_date) + 1
prorata     = round(prorateDays / jumlah_hari_bulan * customer_service.monthly_price)
subtotal    = prorata + biaya_pemasangan + kabel_tambahan + tiang_tambahan + materai
afterDisc   = max(0, subtotal - customer_service.discount)
ppn_amount  = round(afterDisc * customer_service.ppn / 100, 2)
total       = afterDisc + ppn_amount
```

Rumus JS di `verifications/admin.blade.php` wajib dijaga identik supaya angka
yang dilihat admin sama dengan yang tersimpan — tapi kalau menyimpang, yang
menang tetap server.

### 6.1 Aturan bisnis terkonfirmasi (2026-07-21)

1. **PPN sudah termasuk di harga paket**, berlaku untuk semua paket. Master
   `internet_packages.ppn = 0`, jadi rumus di atas menghasilkan tambahan nol.
   Field PPN tetap dipertahankan di form dan di kolom `invoices.ppn` sebagai
   cadangan kalau suatu saat ada paket yang dipisah pajaknya. **Jangan**
   menaikkan basis prorata ke `total_monthly_bill` untuk "mengembalikan" PPN —
   itu memungut pajak dua kali.
2. **Jatuh tempo bulanan selalu tanggal 10**, semua paket. Tagihan bulanan
   terbit tanggal 1, tempo tanggal 10.
3. **Tagihan awal dibayar saat aktivasi**, bukan menunggu jatuh tempo. Jatuh
   temponya = tanggal aktivasi itu sendiri.
4. **Materai** ikut ditagih di tagihan awal, disimpan di `invoices.other_fee`.
   Tidak pernah muncul di tagihan bulanan.
5. **Prorata & materai hanya ada di tagihan awal.** Mulai bulan berikutnya
   pelanggan bayar penuh sesuai paket.

### 6.2 Contoh kanonik

Pelanggan pasang **21 Juli 2026**, paket **Rp 110.000/bulan**:

```
Juli (tagihan awal, dibayar saat aktivasi 21 Juli)
  prorateDays = 31 - 21 + 1 = 11 hari
  prorata     = round(11/31 × 110.000) = 39.032
  + biaya pemasangan / kabel / tiang / materai bila ada

Agustus dan seterusnya (tagihan bulanan)
  terbit 1 Agustus, jatuh tempo 10 Agustus
  nominal = 110.000 penuh (tanpa prorata, tanpa materai)
```

Angka 39.032 adalah acuan verifikasi rumus. Rumus legacy (`31 - 21` = 10 hari,
hari aktivasi tidak ditagih) menghasilkan 35.484 — kalau perhitungan sistem
mendarat di angka itu, berarti konvensi harinya bergeser balik ke legacy.

---

## Referensi

- `ALUR-PEMBAYARAN.md` — analisa sistem lama
- `app/Services/InitialInvoiceService.php`
- `app/Http/Controllers/CustomerVerificationController.php` (`finalVerify`)
- `app/Console/Commands/GenerateMonthlyInvoicesCommand.php`
- `app/Console/Commands/MigrateLegacyDataCommand.php`
- `docs/billing-pembayaran/archive/analisa-bug-migrasi-tagihan-awal-bulanan.md`
