# Scope Migrasi Data Pelanggan dan Billing

Dokumen ini menjadi catatan arah pengembangan baru berdasarkan database lama:

`sand_db_sandya.sql`

Tujuan utama website baru adalah memindahkan data lama WHUSNET/Sandya ke sistem baru dengan fokus hanya pada:

1. Data pelanggan.
2. Data layanan pelanggan.
3. Data paket internet.
4. Data tagihan/billing.
5. Data pembayaran.
6. Import data melalui Excel.

Website baru tidak perlu menyalin seluruh fitur website lama. Data lama tetap penting, tetapi yang dimasukkan ke sistem baru hanya data yang berhubungan langsung dengan pelanggan dan billing pembayaran.

---

## Kesimpulan Scope

Yang dibutuhkan:

- Semua data pelanggan dari database lama masuk ke website baru.
- Semua data layanan pelanggan yang melekat ke pelanggan ikut dimasukkan.
- Semua data paket lama ikut dimasukkan sebagai master paket.
- Semua data biaya, tagihan, dan pembayaran lama yang bisa dihubungkan ke pelanggan ikut dimasukkan.
- Import utama dilakukan lewat Excel.
- Data hasil import bisa dilihat, dicari, difilter, diedit, dan dipakai untuk billing.

Yang tidak dibutuhkan untuk tahap ini:

- Sistem teknisi lapangan lengkap.
- Workflow survey lengkap.
- Workflow pemasangan lengkap.
- Monitoring OLT.
- Monitoring router.
- Inventory barang.
- Ticketing gangguan.
- WhatsApp notification.
- Payment gateway.
- Auto suspend pelanggan.
- Auto generate invoice kompleks.
- Modul keuangan/jurnal lengkap.
- Multi-role permission yang terlalu detail.

Catatan penting:

Data teknis dari pemasangan lama tetap boleh disimpan di detail pelanggan, tetapi hanya sebagai informasi pelanggan, bukan sebagai modul operasional jaringan yang kompleks.

---

## Sumber Data Lama yang Relevan

Database lama memiliki banyak tabel. Untuk scope pelanggan dan billing, tabel yang relevan adalah sebagai berikut.

| Tabel Lama | Fungsi di Database Lama | Dipakai di Sistem Baru | Catatan |
| --- | --- | --- | --- |
| `pengguna` | Data orang/pelanggan/staff | Ya | Sumber utama data pelanggan. Filter utama pelanggan biasanya `IDPENGGUNA` dengan prefix `PE`. |
| `prosedure_permintaan_wifi` | Data request/langganan/status layanan | Ya | Menghubungkan pelanggan dengan paket, status pemasangan, status aktif/putus/gagal, dan ID biaya. |
| `paket` | Master paket internet | Ya | Menjadi master paket baru. |
| `biaya_tagihan` | Biaya pemasangan, bulanan, biaya lain | Ya | Menjadi sumber biaya awal atau invoice awal. |
| `penagihan` | Data tagihan bulanan | Ya, jika ada data | Menjadi invoice/tagihan jika datanya tersedia. |
| `apikeuangan_buktitransaksitagihan` | Bukti transaksi tagihan | Ya | Menjadi data invoice/payment historis jika bisa dihubungkan ke request pelanggan. |
| `apikeuangan_buktitransaksilunas` | Bukti transaksi lunas | Ya, jika ada data | Menjadi data pembayaran historis. |
| `apikeuangan_buktitransaksipemasangan` | Bukti pembayaran pemasangan | Ya | Menjadi pembayaran/biaya pemasangan pelanggan. |
| `setting_billing` | Konfigurasi billing lama | Ya, sederhana | Ambil aturan jatuh tempo dasar, misalnya tanggal jatuh tempo tanggal 10. |
| `tglpenagihan` | Referensi tanggal penagihan | Ya, jika digunakan | Bisa menjadi informasi billing cycle. |
| `laporan_pemasangan_wifi` | Data teknis hasil pemasangan | Ya, sebagai detail pelanggan | Simpan informasi teknis seperti IP, SN router/ONT, ODP, port, redaman, foto, dan catatan. |
| `survey_pemasangan_wifi` | Data survey lokasi | Opsional sebagai detail pelanggan | Simpan hanya jika ingin mempertahankan histori lokasi/survey lama. |
| `tb_alamat` | Referensi wilayah | Ya, jika perlu | Bisa membantu melengkapi desa/kecamatan/kota dari `IDWILAYAH`. |
| `cabang` | Data cabang | Ya, sederhana | Bisa menjadi field cabang/POP pelanggan. |
| `users` | Akun lama | Tidak wajib | Tidak perlu dimigrasikan sebagai user login baru, kecuali butuh referensi penerima/petugas lama. |
| `pengguna` prefix `PG` | Staff/petugas lama | Opsional referensi | Bisa disimpan sebagai nama petugas lama di catatan, bukan modul staff lengkap. |

---

## Data yang Harus Masuk ke Pelanggan Baru

Data pelanggan baru harus mampu menampung data dari `pengguna`, `prosedure_permintaan_wifi`, `paket`, `biaya_tagihan`, dan `laporan_pemasangan_wifi`.

### Identitas Pelanggan

Sumber utama: `pengguna`

| Field Baru | Sumber Lama |
| --- | --- |
| `old_customer_id` | `pengguna.IDPENGGUNA` |
| `customer_code` | Kode baru aplikasi, atau sementara sama dengan `IDPENGGUNA` |
| `full_name` | `NAMADEPAN` + `NAMABELAKANG` |
| `identity_number` | `KTP_SIM` |
| `gender` | `JENISKELAMIN` |
| `phone` | `HP` |
| `alternative_phone` | `TLP` |
| `email` | `EMAIL` |
| `customer_type` | `JENISPELANGGAN` |
| `company_name` | `NAMAPERUSAHAAN` |
| `npwp` | `NPWP` |
| `old_account_status` | `STATUSAKUN` |
| `registration_date` | `inserted_at` |
| `ktp_photo` | `FOTOKTP` |
| `profile_photo` | `FOTO` |

### Alamat Pelanggan

Sumber utama: `pengguna`

| Field Baru | Sumber Lama |
| --- | --- |
| `full_address` | `ALMT` |
| `old_region_id` | `IDWILAYAH` |
| `city` | `KOTA` |
| `district` | `KEC` |
| `village` | `DESA` |
| `branch_id` atau `old_branch_id` | `IDCABANG` |

Catatan:

- Jika `KOTA`, `KEC`, dan `DESA` kosong, sistem boleh menyimpan apa adanya dan menandai data perlu dilengkapi.
- Jika `IDWILAYAH` bisa dicocokkan dengan `tb_alamat`, data wilayah dapat dilengkapi otomatis.

### Layanan Pelanggan

Sumber utama: `prosedure_permintaan_wifi`

| Field Baru | Sumber Lama |
| --- | --- |
| `old_request_id` | `IDPERMINTAAN` |
| `old_customer_id` | `IDPENGGUNA` |
| `old_package_id` | `IDPAKET` |
| `old_cost_id` | `IDBIAYA` |
| `request_status` | `STATUS` |
| `installation_status` | `STATUSPASANG` |
| `service_status` | `STATUSLANGGANAN` atau hasil mapping dari `STATUS` |
| `termination_or_activation_date` | `TGL_AKTIFPUTUS` |
| `created_by_old` | `CREATED` |
| `survey_by_old` | `DISURVEY` |
| `approved_by_old` | `DIACC` |
| `processed_by_old` | `DIPROSES` |
| `reported_by_old` | `DILAPORKAN` |
| `survey_at` | `TGLSURVEY` |
| `approved_at` | `TGLDIACC` |
| `processed_at` | `TGLDIPROSES` |
| `finished_at` | `TGLSELESAI` |
| `verified_by_old` | `VERIFIED` |
| `verified_at` | `VERIFIED_AT` |
| `network_type` | `JENISJARINGAN` |
| `member_type` | `JENISMEMBER` |
| `device_action_status` | `STATUSTINDAKANALAT` |
| `device_status` | `STATUSALAT` |
| `reason` | `ALASAN` |

Catatan:

- Data ini tidak perlu dibuat menjadi workflow proses panjang di tahap awal.
- Data ini cukup tampil di detail pelanggan sebagai histori layanan lama.
- Status lama seperti `ACTIVE`, `PUTUS`, `GAGAL`, `DISURVEI`, dan `PENGAJUAN` perlu dimapping ke status baru yang sederhana.

### Mapping Status Sederhana

| Status Lama | Status Baru |
| --- | --- |
| `ACTIVE` | `aktif` |
| `PUTUS` | `berhenti` |
| `GAGAL` | `gagal` |
| `DISURVEI` | `survey` |
| `PENGAJUAN` | `calon_pelanggan` |
| Kosong/null | `belum_diketahui` |

---

## Data Paket Baru

Sumber utama: `paket`

| Field Baru | Sumber Lama |
| --- | --- |
| `old_package_id` | `KODEPAKET` |
| `name` | `NAMA_PAKET` |
| `package_type` | `JENIS_PAKET` |
| `category` | `KATEGORI_PAKET` |
| `monthly_price` | `HARGA` |
| `upload_speed` | `SPEEDUP` |
| `download_speed` | `SPEEDDOWN` |
| `upload_limit` | `LIMITUP` |
| `download_limit` | `LIMITDOWN` |
| `olt_profile` | `PROFILOLT` |
| `ppp_profile` | `PROFILPPP` |
| `bonus` | `BONUS` |
| `description` | `KETERANGAN` |

Catatan:

- Harga paket harus tetap disimpan sebagai snapshot di layanan pelanggan dan invoice.
- Jika harga master paket berubah, histori tagihan pelanggan lama tidak boleh ikut berubah.

---

## Data Billing dan Pembayaran

Data billing baru harus bisa menampung histori dari beberapa tabel lama.

### Biaya Awal / Biaya Tagihan

Sumber utama: `biaya_tagihan`

| Field Baru | Sumber Lama |
| --- | --- |
| `old_cost_id` | `IDBIAYA` |
| `old_customer_id` | `IDPELANGGAN` |
| `old_request_id` | `IDPERMINTAAN` |
| `installation_fee` | `BIAYAPASANG` |
| `monthly_fee` | `BIAYABULANAN` |
| `other_fee` | `BIAYALAINLAIN` |
| `total_amount` | `TOTALBIAYA` |
| `created_at` | `TGLINSERT` |

Catatan:

- Data ini bisa dibuat sebagai invoice awal atau catatan biaya awal pelanggan.
- Jika `TOTALBIAYA` kosong atau tidak sesuai, sistem tetap menyimpan nilai aslinya dan menandai perlu review.

### Tagihan Bulanan

Sumber utama: `penagihan`

| Field Baru | Sumber Lama |
| --- | --- |
| `old_invoice_id` | `IDTAGIHAN` |
| `old_customer_id` | `IDPELANGGAN` |
| `old_request_id` | `IDPERMINTAAN` |
| `billing_date` | `TGLPENAGIHAN` |
| `total_amount` | `TOTALBULANAN` |
| `status` | `STATUS` |

Catatan:

- Jika tabel `penagihan` kosong, invoice historis bisa dibentuk dari `biaya_tagihan` dan `apikeuangan_buktitransaksitagihan`.

### Bukti Transaksi Tagihan

Sumber utama: `apikeuangan_buktitransaksitagihan`

| Field Baru | Sumber Lama |
| --- | --- |
| `old_payment_id` | `IDUNIQ` |
| `old_transaction_id` | `IDTRANSAKSI` |
| `old_request_id` | `IDPERMINTAAN` |
| `billing_index` | `NOINDEXTAGIHAN` |
| `amount` | `BAYAR` |
| `billing_period` | `BULANTAGIHAN` |
| `flag` | `FLAG` |
| `created_at` | `INSERTED_AT` |
| `notification_flag` | `notivwa` |

Catatan:

- `IDTRANSAKSI` sering cocok dengan `biaya_tagihan.IDBIAYA`, sehingga bisa dipakai untuk menghubungkan pembayaran dengan biaya awal/invoice.
- Baris yang `IDPERMINTAAN` kosong tetap disimpan di import log, tetapi tidak langsung menjadi pembayaran pelanggan sampai bisa dicocokkan.

### Bukti Transaksi Lunas

Sumber utama: `apikeuangan_buktitransaksilunas`

| Field Baru | Sumber Lama |
| --- | --- |
| `old_paid_id` | `IDUNIQ` |
| `old_transaction_id` | `IDTRANSAKSI` |
| `old_request_id` | `IDPERMINTAAN` |
| `payment_date` | `TGLBAYAR` |
| `billing_period` | `BULANTAGIHAN` |
| `payment_method` | `JENISPEMBAYARAN` |
| `amount` | `BAYAR` |
| `received_by_old` | `IDPENERIMA` |
| `deposited_by_old` | `IDPENYETOR` |
| `note` | `KET` |
| `billing_index` | `NOINDEXTAGIHAN` |

---

## Data Teknis yang Tetap Masuk ke Detail Pelanggan

Sumber utama: `laporan_pemasangan_wifi`

Data ini tetap penting karena melekat ke pelanggan, tetapi tidak perlu dijadikan modul teknisi/OLT/ODP yang kompleks.

| Field Baru | Sumber Lama |
| --- | --- |
| `old_report_id` | `IDREPORT` |
| `old_customer_id` | `IDPENGGUNA` |
| `old_request_id` | `IDPERMINTAAN` |
| `connection_type` | `JENIS` |
| `test_upload` | `TESTUP` |
| `test_download` | `TESTDOWN` |
| `ssid` | `SSID` |
| `ip_address` | `IPADDR` |
| `antenna_mac` | `MACADDR_ANTENA` |
| `router_mac` | `MACADDR_ROOTER` |
| `router_or_ont_serial` | `SNROOTER_FIBER` |
| `odp_number` | `NOMOR_ODP` |
| `odp_port` | `NOMOR_PORT_ODP` |
| `olt_port` | `NOMOR_PORT_OLT` |
| `wireless_signal` | `SIGNAL_WIRELESS` |
| `fiber_signal` | `SIGNAL_KABEL` |
| `location_source` | `LOKASIPEMANCAR` |
| `note` | `KETERANGAN` |
| `speedtest_photo` | `FOTOSPEED` |
| `form_photo` | `FOTOFORMULIR` |
| `signed_form_photo` | `FOTOTTDFORMULIR` |
| `router_photo` | `FOTOROOTER` |
| `cable_photo` | `FOTOKABEL` |

Catatan:

- Field foto lama cukup disimpan sebagai nama/path file lama.
- Jika file fisik foto lama tidak ikut dimigrasikan, tetap simpan nama file untuk referensi.

---

## Rekomendasi Struktur Database Baru

Untuk kebutuhan ini, struktur minimal yang cukup adalah:

```txt
customers
customer_addresses
customer_services
customer_technical_details
internet_packages
invoices
payments
import_batches
import_errors
```

Opsional jika ingin lebih rapi:

```txt
branches
legacy_staff_references
billing_settings
```

Tidak perlu membuat tabel khusus untuk OLT, ODP, router, ticketing, inventory, atau workflow teknisi pada tahap ini.

---

## Alur Import Excel

Karena input utama yang diinginkan adalah Excel, alur import sebaiknya dibuat seperti ini:

```txt
Database lama sand_db_sandya.sql
-> Ekstrak data pelanggan, layanan, paket, billing, pembayaran
-> Susun menjadi file Excel template baru
-> Upload Excel ke website baru
-> Validasi data
-> Preview data
-> Konfirmasi import
-> Simpan ke tabel baru
-> Tampilkan hasil import dan error
```

Import Excel tidak perlu terlalu kompleks di awal. Yang penting:

- Bisa upload file.
- Bisa membaca header.
- Bisa validasi field wajib.
- Bisa menampilkan preview.
- Bisa mencatat baris gagal.
- Bisa menghindari duplikasi berdasarkan `old_customer_id`, `old_request_id`, dan `old_transaction_id`.

---

## Template Excel yang Disarankan

Untuk migrasi awal, lebih baik memakai beberapa sheet Excel:

1. `customers`
2. `packages`
3. `services`
4. `technical_details`
5. `invoices`
6. `payments`

### Sheet `customers`

```csv
old_customer_id,customer_code,full_name,identity_number,gender,phone,alternative_phone,email,customer_type,company_name,npwp,full_address,old_region_id,city,district,village,old_branch_id,old_account_status,registration_date,ktp_photo,profile_photo
```

### Sheet `packages`

```csv
old_package_id,name,package_type,category,monthly_price,upload_speed,download_speed,upload_limit,download_limit,olt_profile,ppp_profile,bonus,description
```

### Sheet `services`

```csv
old_request_id,old_customer_id,old_package_id,old_cost_id,request_status,installation_status,service_status,activation_date,survey_at,approved_at,processed_at,finished_at,verified_at,network_type,member_type,reason
```

### Sheet `technical_details`

```csv
old_report_id,old_customer_id,old_request_id,connection_type,test_upload,test_download,ssid,ip_address,antenna_mac,router_mac,router_or_ont_serial,odp_number,odp_port,olt_port,wireless_signal,fiber_signal,location_source,note,speedtest_photo,form_photo,signed_form_photo,router_photo,cable_photo
```

### Sheet `invoices`

```csv
old_invoice_id,old_cost_id,old_customer_id,old_request_id,billing_period,issue_date,due_date,installation_fee,monthly_fee,other_fee,total_amount,status
```

### Sheet `payments`

```csv
old_payment_id,old_transaction_id,old_invoice_id,old_customer_id,old_request_id,payment_date,billing_period,payment_method,amount,received_by_old,deposited_by_old,note,status
```

---

## Aturan Validasi Import

### Wajib untuk pelanggan

- `old_customer_id`
- `full_name`
- `phone` atau `identity_number` atau `full_address`
- `registration_date` jika tersedia

Catatan:

Karena data lama tidak selalu rapi, pelanggan jangan langsung ditolak hanya karena nomor HP/email/alamat kosong. Lebih baik tetap masuk, lalu diberi status `perlu_dilengkapi`.

### Wajib untuk layanan

- `old_request_id`
- `old_customer_id`
- `old_package_id`
- `request_status`

### Wajib untuk paket

- `old_package_id`
- `name`
- `monthly_price`

### Wajib untuk invoice

- `old_customer_id` atau `old_request_id`
- `total_amount`
- `billing_period` atau `issue_date`

### Wajib untuk pembayaran

- `old_payment_id`
- `amount`
- `payment_date`
- `old_transaction_id` atau `old_request_id`

---

## Aturan Duplikasi

Data tidak boleh dobel ketika import diulang.

Kunci unik yang disarankan:

| Data | Kunci Unik |
| --- | --- |
| Pelanggan | `old_customer_id` |
| Paket | `old_package_id` |
| Layanan | `old_request_id` |
| Biaya awal/invoice lama | `old_cost_id` atau `old_invoice_id` |
| Pembayaran | `old_payment_id` |
| Detail teknis | `old_report_id` |

Jika file Excel diimport ulang:

- Data dengan kunci lama yang sama dapat di-skip.
- Atau diupdate jika user memilih mode update.
- Semua konflik harus masuk import report.

---

## Tampilan Website yang Dibutuhkan

Menu minimal:

1. Dashboard sederhana.
2. Data Pelanggan.
3. Import Excel.
4. Paket Internet.
5. Tagihan.
6. Pembayaran.
7. Laporan sederhana.

### Data Pelanggan

Fitur:

- Daftar pelanggan.
- Search nama, kode lama, kode baru, HP, alamat.
- Filter status pelanggan.
- Filter paket.
- Detail pelanggan.
- Edit data pelanggan.
- Lihat layanan lama.
- Lihat billing/tagihan pelanggan.
- Lihat pembayaran pelanggan.
- Lihat data teknis pelanggan.

### Billing

Fitur:

- Daftar tagihan.
- Buat tagihan manual.
- Lihat tagihan per pelanggan.
- Status tagihan: `belum_dibayar`, `sebagian`, `lunas`, `batal`.
- Input pembayaran manual.
- Update nominal terbayar dan sisa tagihan.

### Import

Fitur:

- Upload Excel.
- Preview hasil import.
- Konfirmasi import.
- Daftar import batch.
- Detail baris gagal.
- Export error jika dibutuhkan.

---

## Batasan Pengembangan

Developer/AI tidak boleh menambah fitur di luar kebutuhan pelanggan dan billing pembayaran.

Jika menemukan data lama yang menarik tetapi tidak langsung berhubungan dengan pelanggan atau billing, jangan dibuat modul baru. Simpan sebagai catatan post-MVP.

Contoh post-MVP:

- Ticketing gangguan.
- WhatsApp notification.
- Integrasi MikroTik.
- Integrasi OLT.
- Inventory barang.
- Modul teknisi.
- Modul jurnal keuangan.
- Auto suspend.
- Payment gateway.

---

## Keputusan Penting

1. Data lama tetap dianggap penting.
2. Data lama tidak perlu ditiru struktur aplikasinya.
3. Website baru hanya perlu menyimpan dan menampilkan data pelanggan beserta billing pembayaran.
4. Import Excel menjadi jalur utama migrasi.
5. Data teknis lama boleh masuk ke detail pelanggan, tetapi tidak dibuat menjadi sistem teknis kompleks.
6. Billing dibuat sederhana dan manual terlebih dahulu.
7. Semua histori pembayaran yang bisa dicocokkan ke pelanggan harus dipertahankan.
8. Data yang belum bisa dicocokkan jangan dibuang; simpan di import error/review.

