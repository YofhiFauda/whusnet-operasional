# Analisa: Duplikasi Tagihan & Pembayaran Hasil Migrasi Legacy

Ditulis 2026-07-22. Dipicu laporan: pelanggan **Ardiyanto Cahyo Nugroho** paket
Rp 165.000 tapi tagihan tertulis Rp 330.000 dengan dua pembayaran awal, dan
**Wiyono Wonoketro** punya dua invoice AWAL (Rp 120.032 + Rp 11.000).

Dokumen ini terpisah dari [`analisa-pencegahan-tagihan-dobel.md`](analisa-pencegahan-tagihan-dobel.md).
Dokumen itu membahas duplikasi yang lahir dari **cron penagihan bulanan**.
Dokumen ini membahas duplikasi yang **sudah terlanjur ikut masuk lewat migrasi
`jetis_db` / `sand_db`** — sumbernya beda, obatnya beda.

Semua angka di bawah hasil query DB `whusnet_operasional` per 2026-07-22 dan
pembacaan langsung `jetis_db_aplikasi_jetis.sql`.

---

## 1. Yang harus dipahami dulu soal skema legacy

Tiga fakta ini yang bikin migrasi meleset. Tanpa ini, semua bug di bawah kelihatan
seperti kejadian acak.

### 1.1 `IDBIAYA` konstan seumur hidup pelanggan

```sql
CREATE TABLE `biaya_tagihan` (
  `IDBIAYA` varchar(15) NOT NULL,      -- IN000035
  `IDPELANGGAN` varchar(11) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `BIAYAPASANG` int(50) NOT NULL,
  `BIAYABULANAN` int(50) NOT NULL,
  `BIAYALAINLAIN` int(50) NOT NULL,    -- materai, hampir selalu 11000
  `TOTALBIAYA` int(50) NOT NULL,       -- KORUP, lihat 1.3
  `TGLINSERT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);
```

`apikeuangan_buktitransaksitagihan.IDTRANSAKSI` menunjuk ke `IDBIAYA` itu. Karena
`IDBIAYA` tidak pernah berganti, **seluruh pembayaran bulanan pelanggan sepanjang
hidupnya memakai `IDTRANSAKSI` yang sama**. Yang membedakan periode cuma kolom
`BULANTAGIHAN`.

Artinya: `IDBIAYA` **bukan** nomor invoice. Dia nomor *kontrak biaya*. Memperlakukannya
sebagai nomor invoice adalah akar Bug 1 dan Bug 2.

### 1.2 `TGLINSERT` adalah `ON UPDATE`, bukan tanggal terbit

`ON UPDATE current_timestamp()` artinya kolom ini ikut berubah tiap kali baris
disentuh. Jadi dia timestamp **perubahan terakhir**, bukan tanggal tagihan dibuat.

Contoh: `IN000037` (Rum Astuti) punya `TGLINSERT = 2025-05-08` padahal pembayarannya
tercatat November 2022.

### 1.3 `TOTALBIAYA` korup

```
('IN000028', ..., 250000, 110000, 11000, 110,    '2024-03-19'),   -- harusnya 371000
('IN000905', ..., 0,      110645, 11000, 122,    '2023-10-01'),   -- harusnya 121645
('IN000037', ..., 0,      165000, 11000, 176,    '2025-05-08'),   -- harusnya 176000
```

Terpotong tiga digit. Migrasi sudah benar mengabaikan kolom ini — dicatat di sini
supaya tidak ada yang tergoda memakainya sebagai sumber kebenaran nanti.

### 1.4 Legacy menulis baris `biaya_tagihan` tiap klik "Berhasil Active"

Bukan tiap tagihan terbit. Kalau admin klik dua kali, lahir dua `IDBIAYA`. Kalau
pelanggan putus lalu aktif lagi, lahir `IDBIAYA` baru lagi.

Statistik `jetis_db`, 2.149 baris `biaya_tagihan`:

| Kategori | Jumlah |
|---|---|
| `BIAYAPASANG > 0` — tagihan registrasi PSB **asli** | **29** |
| `BIAYAPASANG = 0` **dan** `BIAYABULANAN = 0` — baris log aktivasi murni (cuma materai) | **233** |
| `IDPERMINTAAN` punya >1 baris biaya | 98 |
| ...yang signature-nya beda sehingga lolos dedup | 49 |

Hanya **29 dari 2.149** baris yang benar-benar tagihan pemasangan. Sisanya bukan.

---

## 2. Kasus Ardiyanto Cahyo Nugroho (RQ000004)

### Data mentah legacy

```
biaya_tagihan   IN000035 | PE000002 | RQ000004 | PASANG=0 | BULANAN=165000 | LAINLAIN=11000 | TGLINSERT 2022-11-02 04:11:20
bukti_tagihan   6361f31b0fa | IN000035 | BAYAR=165000 | BULANTAGIHAN 2022-11-02 | FLAG=2 | INSERTED 2022-11-02 04:33:31
                63aa65564fd | IN000035 | BAYAR=165000 | BULANTAGIHAN 2022-11-02 | FLAG=0 | INSERTED 2022-12-27 03:24:06
permintaan      RQ000004 → aktivasi 2022-02-07
```

### Hasil migrasi

```
INV-IN000035-AWAL | periode 2022-11 | subtotal 11.000 | total 330.000 | lunas
PAY-6361f31b0fa   | 2022-11-02 | 165.000
PAY-63aa65564fd   | 2022-12-27 | 165.000
```

### Vonis

Baris `63aa65564fd` adalah **duplikat sistem legacy**, bukan pembayaran bulan
berikutnya. Buktinya `BULANTAGIHAN` kedua baris identik (`2022-11-02`). Kalau
memang pembayaran Desember, `BULANTAGIHAN`-nya akan `2022-12-xx`. Yang benar hanya
`6361f31b0fa`.

Migrasi memperparah dengan **menjumlahkan keduanya** jadi total tagihan.

### Aturan pembeda yang dipakai seterusnya

> Dua baris `buktitransaksitagihan` dengan `IDTRANSAKSI` **dan** `BULANTAGIHAN`
> yang sama = satu pembayaran yang tercatat dobel. Kalau `BULANTAGIHAN` berbeda =
> dua pembayaran periode berbeda yang sah.

Hasil penerapan aturan ini ke seluruh data terimpor:

| | Grup | Baris |
|---|---|---|
| `old_transaction_id` + `billing_period` sama → **duplikat** | 21 | 42 |
| ...di antaranya nominal identik → duplikat murni, aman dihapus otomatis | 17 | 34 |
| ...nominal berbeda → butuh keputusan manual (lihat 2.1) | 4 | 8 |
| Periode berbeda → pembayaran sah yang salah tempat, butuh invoice BULANAN sendiri | 4 | 8 |

Semua 21 grup duplikat berasal dari satu batch yang sama: `INSERTED_AT` berpasangan
`2022-11-02` + `2022-12-27 03:2x`. Ini jejak satu event re-insert massal di sistem
lama, bukan kebetulan.

### 2.1 Empat grup yang nominalnya beda — jangan dihapus buta

```
IN000119  2022-06  165.000 vs 176.000     (selisih tepat 11.000 = materai)
IN000168  2022-08  165.000 vs 176.000     (selisih tepat 11.000 = materai)
IN000214  2022-11  130.000 vs 141.000     (selisih tepat 11.000 = materai)
IN000189  2022-11  820.000 vs 110.000     (data uji, "Ahmadfatonitesdata")
```

Tiga yang pertama: satu baris sudah termasuk materai, satu belum. Kemungkinan besar
koreksi manual admin lama, bukan dua pembayaran. Yang dipertahankan harus yang
bermaterai (176.000 / 141.000), bukan yang pertama masuk. Butuh konfirmasi bisnis.

### 2.2 Empat grup periode berbeda — ini pembayaran sah

```
IN000006  Yuni Astuti      2022-01 + 2022-12
IN000007  Siti Juariyah    2022-11 + 2022-12
IN000027  Luluk Afiah      2022-02 + 2022-12
IN000266  Netty Sulandari  2022-11 + 2022-12
```

Yang salah bukan pembayarannya, tapi tidak adanya invoice BULANAN untuk periode
kedua. Jangan dihapus — harus diterbitkan invoice tujuannya.

---

## 3. Kasus Wiyono Wonoketro (RQ001191)

Jejak legacy lengkap:

```
2024-06-10  PENGAJUAN
2024-06-25  Terpasang
2024-06-26  "Berhasil Active" diklik 9x dalam 2 menit (02:15:09 – 02:17:22)
              → biaya_tagihan IN001266..IN001274, sembilan baris
                semuanya PASANG=0, BULANAN=0, LAINLAIN=11000
              → buktitransaksitagihan 667b79addf3, BAYAR=0
2025-04-12  Putus Langganan ("menunggak januari")
2025-05-01  Kembali Proses
2025-05-05  Berhasil Active
              → biaya_tagihan IN001635, PASANG=0, BULANAN=109032, LAINLAIN=11000
              → bukti 681869ab9a6, BAYAR=120032
2025-12-09  Putus Langganan
```

Hasil migrasi:

```
INV-IN001266-AWAL | periode 2024-06 | total 11.000  | paid 0        | belum_dibayar   ← hantu
INV-IN001635-AWAL | periode 2025-05 | total 120.032 | paid 120.032  | lunas           ← sah
```

### Vonis

Dedup signature **bekerja benar** — sembilan baris 2024-06-26 dikenali identik dan
dikerucutkan jadi satu (`IN001266`). Masalahnya bukan dedup gagal, tapi:

**Baris yang bertahan seharusnya tidak jadi invoice sama sekali.** `BIAYAPASANG=0`
dan `BIAYABULANAN=0` — tidak ada yang ditagihkan. Cuma materai. Itu baris log
aktivasi, dan `buktitransaksitagihan`-nya pun `BAYAR=0` (sudah benar di-skip oleh
guard `BAYAR <= 0`).

Ini **guard yang asimetris**: sisi pembayaran menolak baris nol, sisi invoice tidak.
Hasilnya utang hantu Rp 11.000 yang tidak pernah ada di sistem lama.

`INV-IN001635-AWAL` sendiri sah nominalnya, tapi **salah tipe** — itu tagihan
reaktivasi, bukan tagihan awal pemasangan (`BIAYAPASANG=0`).

---

## 4. Daftar cacat migrasi

Enam, urut dari dampak terbesar.

### Bug 1 — total tagihan awal = jumlah SEMUA pembayaran bulanan

`app/Console/Commands/MigrateLegacyDataCommand.php:899-907`

```php
$costPaymentMap[$costId] = ($costPaymentMap[$costId] ?? 0) + (int) ($row['BAYAR'] ?? 0);
```

Di-key `costId` saja, padahal `costId` konstan seumur hidup (§1.1). Lalu baris 968-971:

```php
$paidFromTagihan = $costPaymentMap[$costId] ?? 0;
$actualPaid = $paidFromTagihan + $paidFromPasang;
$totalAmount = $actualPaid > 0 ? $actualPaid : $billedTotal;
```

N pembayaran → total = N × harga bulanan. Ardiyanto 2 × 165.000 = 330.000.

**Dampak: 25 invoice, gelembung Rp 4.768.774.**

Perbaikan: key jadi `costId|BULANTAGIHAN`.

### Bug 2 — semua pembayaran dirutekan ke satu invoice AWAL

`MigrateLegacyDataCommand.php:913, 1044`

```php
$invoiceTypeByCostId[$costId] = 'awal';                             // satu nilai per costId
$invoiceType = strtoupper($invoiceTypeByCostId[$costId] ?? 'bulanan');
// → old_invoice_id = "{$costId}-AWAL" untuk SEMUA bukti bayar
```

`BULANTAGIHAN` hanya dipakai mengisi `payments.billing_period`, tidak pernah dipakai
menerbitkan invoice per periode. Riwayat penagihan bulanan legacy hilang.

**Dampak: 1.707 invoice AWAL vs 45 BULANAN.** Rasio itu mustahil untuk ISP jalan
4 tahun. 52 pembayaran menumpuk di 25 invoice.

### Bug 3 — `TGLINSERT` dipakai sebagai tanggal terbit & periode

`MigrateLegacyDataCommand.php:926-935`

```php
$issueDate = Carbon::parse($row['TGLINSERT'])->format('Y-m-d');
$dueDate = Carbon::parse($issueDate)->addDays(10)->format('Y-m-d');
$billingPeriod = Carbon::parse($issueDate)->format('Y-m');
```

`TGLINSERT` adalah `ON UPDATE` (§1.2).

**Dampak: 327 dari 1.707 invoice AWAL periodenya ≠ bulan aktivasi.** Selisih
terjauh 25 bulan. Ardiyanto aktivasi Feb 2022, periode invoice jadi Nov 2022.

Sebaran selisih: 0 bln → 150, 1 bln → 41, 2 bln → 21, 25 bln → 10, sisanya menyebar.

### Bug 4 — materai dianggap penanda tagihan registrasi

`MigrateLegacyDataCommand.php:959`

```php
$isRegistrationRow = $installationFee > 0 || $otherFee > 0;
```

`BIAYALAINLAIN = 11000` (materai) ada di hampir semua baris, termasuk pelanggan
lama dan baris log aktivasi. Jadi hampir semua baris lolos sebagai "registrasi".

**Dampak: 1.707 dari 1.707 invoice AWAL punya `BIAYAPASANG = 0`.** Tidak satu pun
benar-benar tagihan PSB. Padahal legacy hanya punya 29 baris ber-`BIAYAPASANG > 0`.

Perbaikan: `$isRegistrationRow = $installationFee > 0;`

### Bug 5 — baris nol tetap jadi invoice (utang hantu)

Konsekuensi Bug 4 pada baris `PASANG=0 & BULANAN=0`. Sisi pembayaran punya guard:

```php
if ((int) ($row['BAYAR'] ?? 0) <= 0) { continue; }   // baris 1006
```

Sisi invoice tidak punya padanannya. **233 baris log aktivasi** di legacy berpotensi
jadi invoice; sebagian besar terserap karena `totalAmount` ditimpa nilai pembayaran
(Bug 1), menyisakan yang benar-benar tanpa pembayaran. Saat ini tersisa **1 invoice
hantu** (Wiyono, Rp 11.000) — kecil, tapi ini utang yang tidak pernah ada, dan
jumlahnya akan berubah begitu Bug 1 diperbaiki.

### Bug 6 — `subtotal` dobel hitung materai, tidak nyambung `total_amount`

`app/Http/Controllers/CustomerController.php:2448-2450`

```php
$subtotal = $invoiceType === InvoiceType::AWAL
    ? (float) ($row['installation_fee'] ?? 0) + (float) ($row['other_fee'] ?? 0) + (float) ($row['prorate_amount'] ?? 0)
    : ($row['monthly_fee'] ?? $service->monthly_price);
```

Dua cacat sekaligus:

1. `prorate_amount` di command baris 982 diisi `$totalAmount` yang **sudah termasuk**
   `other_fee`. Ditambah `other_fee` lagi di sini → materai dihitung dua kali.
   Contoh Singgih Hardiyanto: subtotal 121.645 vs total 110.645.
2. `installation_fee` tidak pernah tersimpan — kolom DB-nya `extra_installation_fee`
   dan isinya 0 untuk semua baris.

**Dampak: 1.687 dari 1.707 invoice AWAL punya `subtotal ≠ total_amount`.** 520 di
antaranya `subtotal = 11.000` (materai saja) sementara `total_amount` ratusan ribu.

---

## 5. Reaktivasi: 35 layanan punya dua invoice AWAL

Pola berulang — pelanggan putus lalu aktif lagi, legacy bikin `IDBIAYA` baru,
migrasi bikin invoice AWAL kedua.

```
svc=1087  RQ001191  Wiyono Wonoketro
   INV-IN001266-AWAL  per=2024-06  total=11.000    paid=0        belum_dibayar
   INV-IN001635-AWAL  per=2025-05  total=120.032   paid=120.032  lunas

svc=640   RQ000734  Endang Siti Wahyuni
   INV-IN000819-AWAL  per=2023-07  total=120.032   lunas
   INV-IN001828-AWAL  per=2025-08  total=137.806   lunas
```

Dari 35 kasus, hanya Wiyono yang salah satu invoicenya hantu. Sisanya nominalnya
sah dan lunas — yang salah **tipenya**: seharusnya prorata reaktivasi, bukan
tagihan awal pemasangan.

Empat kasus khusus, dua AWAL di periode berdekatan (prorata + bulan penuh):

```
svc=227  RQ000251  Bambang Tetuko   2022-11 (88.000)  + 2022-12 (165.000)
svc=258  RQ000289  Djarijanto       2022-12 (58.065)  + 2022-12 (150.000)
svc=269  RQ000306  Haryanto         2022-12 (56.774)  + 2022-12 (110.000)
svc=271  RQ000308  Sugianto         2023-01 (85.516)  + 2023-01 (265.516)
```

Tiga terakhir tabrakan periode. Lolos dedup karena nominalnya beda.

---

## 6. Yang sudah bersih (jangan diutak-atik)

Diverifikasi 2026-07-22, tidak perlu diperiksa ulang saat remediasi:

| Cek | Hasil |
|---|---|
| Payment yatim (`invoice_id` null) | 0 |
| `paid_amount` menyimpang dari Σ payment valid | 0 |
| Invoice `lunas` tanpa payment | 0 |
| Pelanggan punya >1 layanan | 0 |
| Payment nominal ≤ 0 lolos masuk | 0 (guard `PaymentObserver` bekerja) |
| Pelanggan `active` tanpa invoice | 16 / 1.488 |

Dua temuan yang **terlihat** seperti bug tapi bukan:

- **25 pasang nama+POP kembar** — semuanya pendaftaran ulang legacy
  (`old_customer_id` berbeda, satu `rejected`/`terminated` + satu `active`).
  Contoh: Mistiani `PE000001` rejected + `PE000899` active. Data sah.
- **1.411 / 1.956 `customer_services.monthly_price ≠ harga paket`** — harga nego
  per pelanggan. Sesuai `CLAUDE.md`, `customer_services` yang jadi sumber kebenaran
  harga, bukan `internet_packages`.

---

## 7. Ringkasan dampak

| Cacat | Terdampak | Nilai |
|---|---|---|
| Total tagihan awal menggelembung (Bug 1) | 25 invoice | Rp 4.768.774 |
| Pembayaran duplikat murni (nominal sama) | 17 grup / 34 baris | — |
| Pembayaran duplikat perlu keputusan manual | 4 grup / 8 baris | — |
| Pembayaran sah tanpa invoice tujuan | 4 grup / 8 baris | — |
| Periode invoice salah (Bug 3) | 327 invoice | — |
| Tipe invoice salah AWAL (Bug 4) | 1.707 invoice | — |
| Invoice hantu (Bug 5) | 1 invoice | Rp 11.000 |
| `subtotal ≠ total_amount` (Bug 6) | 1.687 invoice | — |
| Layanan dengan >1 invoice AWAL (§5) | 35 layanan | — |

---

## 8. Urutan perbaikan

Langkah 1–3 **sudah dikerjakan** (MIGRASI-T003). Langkah 4–5 belum — butuh persetujuan
karena menyentuh data uang.

1. ✅ **Perbaiki `MigrateLegacyDataCommand`** (belum menyentuh data produksi)
   - `$isRegistrationRow = $installationFee > 0` saja
   - Lewati baris `PASANG=0 && BULANAN=0` — jangan terbitkan invoice
   - `costPaymentMap` di-key `costId|BULANTAGIHAN`
   - Dedup bukti bayar per `(IDTRANSAKSI, BULANTAGIHAN)`; nominal beda → tandai
     butuh review, jangan diam-diam dibuang
   - Terbitkan satu invoice BULANAN per `BULANTAGIHAN` unik
   - `issue_date` / `billing_period` dari `activation_date` + `BULANTAGIHAN`,
     buang `TGLINSERT`
   - Baris biaya kedua untuk request yang sama & jaraknya jauh → tandai reaktivasi,
     bukan AWAL
2. ✅ **Satukan rumus `subtotal`** di `CustomerController` — `subtotal = total_amount - ppn + discount`
3. ✅ **Test regresi** dinamai sesuai gejala, memakai fixture Ardiyanto & Wiyono:
   `tests/Feature/MigrasiLegacyTagihanDobelPerPeriodeTest.php` (8 test) dengan
   `tests/fixtures/legacy/duplikasi-tagihan-migrasi.sql`
4. ✅ **Import ulang dari nol** — bukan command remediasi. Saat diperiksa, DB ternyata
   100% berisi data migrasi (0 pelanggan/tagihan/pembayaran lahir di sistem baru,
   0 tiket, 0 task), jadi menambal data lama tidak ada gunanya. Dijalankan 2026-07-22:

   ```bash
   php artisan migrate:fresh --seed
   php artisan app:import-legacy-sql jetis_db_aplikasi_jetis.sql --branch-code=C --branch-name=Jetis
   php artisan app:import-legacy-sql sand_db_sandya.sql  --branch-code=J --branch-name=Sandya
   ```

   Catatan operasional: butuh `php -d memory_limit=2G` (fase validasi melebihi 128M),
   dan `CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync` kalau Redis tidak
   jalan (`.env` menunjuk host `redis`, hostname docker).
5. ⬜ **Tiga grup nominal beda (§2.1)** diputuskan manual oleh tim billing, jangan
   diotomatiskan. Command sudah mencetaknya sebagai peringatan tiap import:

   ```
   3 bukti bayar dobel dengan NOMINAL BERBEDA — yang terbesar dipakai, tinjau manual:
     - IN000119 periode 2022-06: 176.000 vs 165.000
     - IN000168 periode 2022-08: 176.000 vs 165.000
     - IN000214 periode 2022-11: 141.000 vs 130.000
   ```

   IN000189 tidak lagi muncul: itu akun data uji, dan kedua pembayarannya ternyata
   tagihan + biaya pasang, bukan duplikat.

---

## 9. Hasil setelah import ulang (2026-07-22)

| Cek | Sebelum | Sesudah |
|---|---|---|
| Invoice AWAL / BULANAN | 1.707 / 45 | **27 / 1.710** |
| `subtotal ≠ total_amount` | 1.687 | **0** |
| Invoice AWAL tanpa biaya pasang | 1.707 | **0** |
| Invoice dengan >1 pembayaran | 25 | **0** |
| Invoice hantu (materai saja, belum dibayar) | 1 | **0** |
| Tabrakan (layanan, periode, jenis) | 4 | **4** — sama, lihat di bawah |
| AWAL periode ≠ bulan aktivasi | 327 / 1.707 | **6 / 27** |
| Payment yatim / `paid_amount` melenceng / lunas tanpa bayar | 0 | **0** |

Dua kasus yang dilaporkan:

```
Ardiyanto  INV-IN000035-202211  bulanan  2022-11  165.000  lunas   1 pembayaran
Wiyono     INV-IN001635-202505  bulanan  2025-05  120.032  lunas   utang hantu hilang
```

### Cacat ke-7 yang baru ketahuan saat verifikasi

Import pertama menyisakan 8 tabrakan periode (naik dari 4). Penyebabnya baris bukti
`BAYAR=0` ikut dibuang dari peta periode, padahal `BULANTAGIHAN`-nya satu-satunya
penanda periode untuk tagihan yang **belum dibayar**. Akibatnya invoice reaktivasi
jatuh ke periode aktivasi pertama.

```
Boyke Santiago RQ000324
  IN001334  BULANAN=13355   LAIN=12000  bukti BAYAR=25355  BULANTAGIHAN 2024-08-28
  IN001619  BULANAN=129097  LAIN=12000  bukti BAYAR=0      BULANTAGIHAN 2025-05-02

sebelum : keduanya jatuh di periode 2024-08 → tabrakan
sesudah : 2024-08 (25.355, lunas) + 2025-05 (141.097, belum dibayar)
```

Diperbaiki dengan memisah dua peta: `$periodsByCost` (semua periode, termasuk
`BAYAR=0`) dan `$paidByCostPeriod` (nominal, hanya `BAYAR>0`). Tabrakan 8 → 4.

### Empat tabrakan yang tersisa memang nyata di legacy

```
RQ000289  Djarijanto             2022-12  150.000 + 58.065   dua-duanya lunas
RQ000306  Haryanto               2022-12  110.000 + 56.774   dua-duanya lunas
RQ000308  Sugianto               2023-01  265.516 + 85.516   dua-duanya lunas
RQ000311  Wakhid Fandra Bahtiar  2022-12  110.000 + 60.323   dua-duanya lunas
```

Dua `IDBIAYA` berbeda, dua pembayaran nyata di bulan yang sama (prorata + bulan
penuh). Uangnya betul-betul masuk, jadi bukan duplikat migrasi — ini keputusan
bisnis. `billing:cleanup-legacy-duplicate-invoices` sudah menampung kasus ini.

### Yang terlihat menyimpang tapi benar

- **6 invoice AWAL periodenya ≠ bulan aktivasi** — pelanggan aktif akhir Januari
  (26–31), tagihan pertama tertagih Februari. Wajar. Dua di antaranya akun data uji.
- **1 invoice bernilai Rp 11.000** (Naufal Grandi Sundoro, 2024-01) — `paid_amount`
  juga 11.000 dan berstatus lunas. Uangnya nyata, bukan utang hantu.
- **2 invoice AWAL dengan dua pembayaran** — satu dari bukti tagihan, satu dari bukti
  pemasangan (`-PASANG`). Dua pembayaran berbeda untuk satu tagihan awal, memang begitu.
- **13 invoice periodenya lebih awal dari `activation_date`** dan **14 pembayaran
  bertanggal sebelum aktivasi** — pelanggan reaktivasi. `customer_services` menyimpan
  aktivasi TERAKHIR, invoice menyimpan riwayat langganan sebelumnya. Dua-duanya benar
  di konteksnya. Contoh: Ketut Wibisono, invoice 2022-02 (lunas) dengan
  `activation_date` 2025-11.

---

## 10. Sapuan pola serupa (2026-07-22)

Pertanyaan yang wajar setelah tujuh cacat: apakah **skema yang sama** ada di tempat
lain? Disapu satu per satu, bukan diasumsikan bersih.

### Cacat 8 — metadata pembayaran di-key `IDTRANSAKSI` saja

Ditemukan, diperbaiki. `apikeuangan_buktitransaksilunas` kena penyakit identik dengan
`buktitransaksitagihan`: di-key `IDTRANSAKSI` yang konstan seumur hidup pelanggan.

```php
// sebelum — satu baris tertua dicap ke SEMUA pembayaran cost id itu
$lunasByTransaction[$transactionId] = $row;
$lunas = $lunasByTransaction[$row['IDTRANSAKSI'] ?? ''] ?? null;
```

Di `jetis_db`: 52 baris lunas, **13 `IDTRANSAKSI` punya >1 baris**, 2 di antaranya
tersebar lebih dari satu bulan, 5 punya metode bayar berbeda. Akibatnya kuitansi bulan
kedua mencatut metode, penerima, dan catatan pembayaran bulan pertama.

Bedanya dengan tabel tagihan: **`BULANTAGIHAN` kosong di seluruh 52 baris**, jadi
periode hanya bisa ditebak dari bulan `TGLBAYAR`. Perbaikannya menyiapkan dua peta —
per periode kalau ketemu, baris tertua sebagai cadangan.

Dampaknya nominal nol (metadata saja), tapi menyentuh jejak audit pembayaran.

### Yang disapu dan ternyata bersih

| Dugaan | Hasil |
|---|---|
| Kolom `ON UPDATE` lain dipakai sebagai tanggal peristiwa | `buktitransaksipemasangan.TGLBAYAR` juga `ON UPDATE`, tapi 274 baris tanpa satu pun duplikat per kode, dan cuma menghasilkan 2 pembayaran `-PASANG`. Tidak ada sumber tanggal yang lebih baik. |
| Dedup `biaya_tagihan` memakai `date(TGLINSERT)` yang tidak stabil | 59 grup identik-tanpa-tanggal; hanya **1** grup yang tanggalnya berbeda, dan itu baris sampah (`||0|0|0`, tanpa pelanggan) yang memang sudah dibuang. |
| `buktitransaksipemasangan` punya >1 baris per kode | 0 dari 274. |
| Invoice bernilai ≤ 0, `remaining_amount` negatif, `paid > total` | 0 / 0 / 0. |
| Invoice berperiode di masa depan / `issue_date` > hari ini | 0 / 0. |
| `customer_services.monthly_price` kosong | 48 dari 1.956 — pelanggan yang di legacy memang tidak pernah ditagih. |

### Yang tidak bisa diperbaiki dari data

`apikeuangan_buktitransaksilunas` cuma menutup **52 dari 1.726** pembayaran (3%).
Sisanya jatuh ke default `cash` dengan penerima kosong — bukan karena migrasi salah,
tapi karena sistem lama memang tidak mencatatnya. Jangan dikarang.

---

## Referensi

- `app/Console/Commands/MigrateLegacyDataCommand.php` — §899-1107
- `app/Http/Controllers/CustomerController.php` — §2380-2576
- `docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md` — duplikasi sisi cron
- `docs/billing-pembayaran/perbandingan-tagihan-awal-vs-bulanan-legacy.md`
- `docs/ANALISA_KELENGKAPAN_MIGRASI_jetis_db.MD`
- `jetis_db_aplikasi_jetis.sql` — dump sumber
