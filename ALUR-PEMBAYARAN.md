# Alur Pembayaran Awal vs Pembayaran Bulanan

Dokumentasi hasil analisa kode. Kunci pembeda kedua jenis pembayaran adalah
keberadaan baris di tabel **`apikeuangan_buktitransaksipemasangan`**:

- Baris **belum ada** → biaya pemasangan awal belum lunas, tagihan aktif = tagihan awal.
- Baris **sudah ada** → biaya pemasangan awal lunas, tagihan berikutnya = tagihan bulanan.

---

## 1. Verifikasi administrasi → tagihan awal dibuat

Pemicu: teknisi menjalankan `laporPemasangan()` sehingga `STATUSPASANG = 'Terpasang'`.
Admin membuka form verifikasi lalu menyimpan.

Alur teknis:

1. `views/pelanggan/proses/@_dataAntrianProses.php:973` — `material.saveVerifikasiAdministrasi()`
2. POST ke `pelanggan/Controll_antrianProses/updateBiaya`
3. `controllers/pelanggan/Controll_antrianProses.php:641` — `updateBiaya()`
4. `models/pelanggan/M__antrianProses.php:173` — `insertBiayaTagihan()`

Yang terjadi di database (satu rangkaian di `insertBiayaTagihan`):

| # | Tabel | Aksi |
|---|-------|------|
| 1 | `prosedure_permintaan_wifi` | UPDATE `STATUSPASANG='Berhasil'`, `STATUS='ACTIVE'`, isi `IDBIAYA`, `VERIFIED`, `VERIFIED_AT` |
| 2 | `riwayat_pelanggan` | INSERT `STATUSTINDAKAN='Berhasil Active'` |
| 3 | `biaya_tagihan` | INSERT master biaya: `BIAYAPASANG`, `BIAYABULANAN`, `BIAYALAINLAIN`, `TOTALBIAYA` |
| 4 | `apikeuangan_buktitransaksitagihan` | INSERT tagihan awal: `BAYAR = TOTALBIAYA`, `BULANTAGIHAN = tanggal hari ini` |

`IDBIAYA` dihasilkan dari `generateKodeForm('IN', 'Tambah')` dan dipakai sebagai
nomor transaksi induk / nomor pelanggan.

## 2. Nominal tagihan awal memakai prorata

Field `BIAYABULANAN` pada form verifikasi bersifat read-only
(`@_dataAntrianProses.php:3180`), diisi lewat AJAX `material.GetTagihanAwal()`
(view baris 380) → `Controll_antrianProses::GetTagihanAwal()` (baris 760) →
helper `GetTagihanAwal($idpaket)` di `helpers/my_helper.php:584`.

Rumus:

```
perhari   = HARGA_PAKET / jumlah_hari_bulan_ini
sisa_hari = jumlah_hari_bulan_ini - tanggal_hari_ini
biaya     = (besok sudah tanggal 1) ? perhari * 1 : sisa_hari * perhari
hasil     = round(biaya)
```

Jadi bulan pertama dihitung prorata sisa hari, bukan harga paket penuh.

`TOTALBIAYA` dihitung di sisi klien pada `material.TOTAL()` (view baris 374):

```
TOTALBIAYA = BIAYABULANAN(prorata) + BIAYAPASANG + BIAYALAINLAIN
```

`BIAYAPASANG` diinput manual oleh admin (view baris 3195).

## 3. Pelanggan membayar tagihan awal

Menu Pembayaran → `controllers/transaksi/Controll_Pembayaran.php:42` `save()`.

Percabangan ada di `models/transaksi/M__Pembayaran.php:112` `checkIdBayar()`,
yang mengecek tabel `apikeuangan_buktitransaksipemasangan`:

**Return `"totalbiaya"` (belum ada baris) = pembayaran awal:**

1. INSERT `apikeuangan_buktitransaksilunas`
2. UPDATE `apikeuangan_buktitransaksitagihan.FLAG = 2` (lunas)
3. INSERT `apikeuangan_buktitransaksipemasangan` (`IDPERMINTAAN = IDBIAYA`, `TGLBAYAR`)

Langkah 3 hanya terjadi sekali seumur pelanggan dan menjadi penanda permanen
bahwa biaya pemasangan sudah lunas.

**Return `"biayabulanan"` (baris sudah ada) = pembayaran bulanan:**
langkah 1 dan 2 saja, tanpa langkah 3.

Setelah itu sistem mengirim notifikasi WhatsApp berisi bulan tagihan dan total bayar.

## 4. Pembuatan tagihan bulan berikutnya

`controllers/cronfunctions/CronTambahTagihanBulanan.php`

- `getDatacron()` (baris 116) mengambil semua `biaya_tagihan` milik permintaan
  dengan `STATUS='ACTIVE' AND STATUSPASANG='Berhasil'`.
- `cekDataBiayaAwal()` (baris 159) mengecek `apikeuangan_buktitransaksipemasangan`:
  - belum ada → tagihan baru memakai `TOTALBIAYA` (baris 67), artinya biaya
    pemasangan awal masih ditagihkan.
  - sudah ada → tagihan baru memakai `BIAYABULANAN` (baris 78).

`controllers/cronfunctions/CronTagihanBulanan.php` memakai pengecekan yang sama
lewat `bayarpemasangan()` (baris 199). Nominal bulanan diambil `bayarbulanan()`
(baris 211) langsung dari `paket.HARGA`, yaitu harga penuh, lalu disinkronkan
kembali ke `biaya_tagihan.BIAYABULANAN`.

## Ringkasan perbedaan

| | Pembayaran Awal | Pembayaran Bulanan |
|---|---|---|
| Pemicu record | `updateBiaya()` saat verifikasi admin | cron `CronTambahTagihanBulanan` / `CronTagihanBulanan` |
| Nominal | `TOTALBIAYA` = biaya pasang + prorata + lain-lain | `BIAYABULANAN` = harga paket penuh |
| Dasar hitung bulanan | prorata `GetTagihanAwal()` | `paket.HARGA` |
| `NOINDEXTAGIHAN` | `0` | bertambah setiap bulan menunggak |
| Efek samping saat dibayar | INSERT `apikeuangan_buktitransaksipemasangan` | tidak ada |
| Deteksi di kode | `checkIdBayar()` return `"totalbiaya"` | return `"biayabulanan"` |

## Status FLAG pada `apikeuangan_buktitransaksitagihan`

- `0` = belum dibayar
- `1` = ada di `apikeuangan_buktitransaksiterkumpul` (pembayaran terkumpul/cicil)
- `2` = lunas, diset oleh `Controll_Pembayaran::save()`

## Temuan

`CronTagihanBulanan::getDatacron()` baris 186 masih memiliki filter hardcode
`pw.IDPERMINTAAN = 'RQ000247'`, sehingga cron tersebut hanya memproses satu
pelanggan. Perlu dicek apakah cron ini masih dipakai di produksi; jika ya,
filter tersebut harus dihapus.
