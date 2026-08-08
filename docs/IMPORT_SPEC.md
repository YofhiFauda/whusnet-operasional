# IMPORT_SPEC.md

# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan Dokumen

Dokumen ini menjelaskan spesifikasi import data pelanggan lama melalui Excel/CSV.

AI/developer wajib membaca dokumen ini sebelum membuat fitur:

* download template import,
* upload Excel/CSV,
* mapping kolom,
* validasi import,
* preview import,
* konfirmasi import,
* import batch,
* import error,
* penyimpanan data import ke master pelanggan.

---

# 1. Prinsip Import

Import digunakan untuk memindahkan data pelanggan lama ke sistem billing baru.

Prinsip utama:

1. Data import tidak boleh langsung masuk master pelanggan.
2. Data import harus dibaca terlebih dahulu.
3. Data import harus divalidasi.
4. Sistem harus menampilkan preview.
5. Admin harus mengonfirmasi import.
6. Data valid masuk master pelanggan.
7. Data invalid masuk daftar error.
8. Semua proses import harus memiliki log.
9. Data hasil import harus bisa diedit manual seperti pelanggan input manual.
10. Data import dan input manual harus masuk struktur database yang sama.

---

# 2. Alur Import

Alur import wajib:

```txt
Admin Login
→ Buka Menu Import Pelanggan
→ Download Template
→ Isi Data dari Database Lama
→ Upload Excel/CSV
→ Sistem Membaca File
→ Sistem Mapping Kolom
→ Sistem Validasi Data
→ Sistem Menampilkan Preview
→ Admin Konfirmasi Import
→ Data Valid Masuk Master Pelanggan
→ Data Invalid Masuk Daftar Error
→ Sistem Membuat Log Import
```

---

# 3. Format File

Format yang didukung MVP:

```txt
.xlsx
.csv
```

Aturan:

1. File harus memiliki header kolom.
2. Header kolom harus sesuai template atau dapat dimapping.
3. File tidak boleh kosong.
4. File harus memiliki minimal satu baris data.
5. File yang tidak sesuai format harus ditolak.
6. Ukuran file maksimal dapat ditentukan di konfigurasi aplikasi.

---

# 4. Kolom Wajib Import

Kolom wajib minimum:

| Kolom           | Wajib | Keterangan                    |
| --------------- | ----- | ----------------------------- |
| old_customer_id | Ya    | ID pelanggan dari sistem lama |
| full_name       | Ya    | Nama lengkap pelanggan        |
| primary_phone   | Ya    | Nomor HP utama                |
| full_address    | Ya    | Alamat pemasangan             |
| village         | Ya    | Desa/Kelurahan                |
| district        | Ya    | Kecamatan                     |
| city            | Ya    | Kota/Kabupaten                |
| pop_code        | Ya    | Kode POP/Cabang               |
| package_name    | Ya    | Nama paket internet           |
| package_price   | Ya    | Harga paket                   |
| activation_date | Ya    | Tanggal aktivasi              |
| due_date        | Ya    | Tanggal jatuh tempo           |
| service_status  | Ya    | Status layanan                |

---

# 5. Kolom Opsional Import

Kolom opsional:

| Kolom               | Wajib | Keterangan          |
| ------------------- | ----- | ------------------- |
| identity_number     | Tidak | NIK/nomor identitas |
| gender              | Tidak | Jenis kelamin       |
| alternative_phone   | Tidak | Nomor HP alternatif |
| email               | Tidak | Email               |
| province            | Tidak | Provinsi            |
| latitude            | Tidak | Latitude            |
| longitude           | Tidak | Longitude           |
| pppoe_username      | Tidak | Username PPPoE      |
| pppoe_password      | Tidak | Password PPPoE      |
| odp                 | Tidak | ODP                 |
| odp_port            | Tidak | Port ODP            |
| modem_serial_number | Tidak | Serial Number modem |
| modem_mac_address   | Tidak | MAC address modem   |
| wifi_ssid           | Tidak | Nama WiFi           |
| wifi_password       | Tidak | Password WiFi       |
| vlan_id             | Tidak | VLAN ID             |
| technical_note      | Tidak | Catatan teknis      |
| billing_note        | Tidak | Catatan billing     |

---

# 6. Header Template Import

Template MVP disarankan menggunakan header berikut:

```csv
old_customer_id,full_name,identity_number,gender,primary_phone,alternative_phone,email,full_address,village,district,city,province,latitude,longitude,pop_code,package_name,package_price,activation_date,due_date,service_status,pppoe_username,pppoe_password,odp,odp_port,modem_serial_number,modem_mac_address,wifi_ssid,wifi_password,vlan_id,technical_note,billing_note
```

---

# 7. Contoh Data Import

Contoh:

```csv
old_customer_id,full_name,identity_number,gender,primary_phone,alternative_phone,email,full_address,village,district,city,province,latitude,longitude,pop_code,package_name,package_price,activation_date,due_date,service_status,pppoe_username,pppoe_password,odp,odp_port,modem_serial_number,modem_mac_address,wifi_ssid,wifi_password,vlan_id,technical_note,billing_note
OLD001,Budi Santoso,3502000000000001,L,081234567890,,budi@example.com,Jl. Raya Siman No 1,Siman,Siman,Ponorogo,Jawa Timur,,,SMN,Net150,150000,2026-01-01,2026-01-10,aktif,budi001,pass001,ODP-SMN-01,1,ZTE123456,AA:BB:CC:DD:EE:FF,WiFi-Budi,wifi123,100,Data teknis lama,Tagihan mulai Januari
```

---

# 8. Mapping Kolom

## 8.1 Prinsip Mapping

Mapping digunakan jika header file dari sistem lama berbeda dengan template baru.

Contoh:

| Header File Lama | Mapping ke Sistem Baru |
| ---------------- | ---------------------- |
| ID Pelanggan     | old_customer_id        |
| Nama             | full_name              |
| No HP            | primary_phone          |
| Alamat           | full_address           |
| Cabang           | pop_code               |
| Paket            | package_name           |
| Harga            | package_price          |

## 8.2 Aturan Mapping

1. Sistem harus membaca header file.
2. Sistem menampilkan pilihan mapping.
3. Field wajib harus memiliki mapping.
4. Jika field wajib tidak dimapping, import tidak bisa dilanjutkan.
5. Mapping dapat disimpan jika dibutuhkan di masa depan.
6. Untuk MVP, mapping manual sederhana sudah cukup.

---

# 9. Validasi Import

## 9.1 Validasi Field Wajib

Sistem harus mengecek:

1. `old_customer_id` tidak kosong.
2. `full_name` tidak kosong.
3. `primary_phone` tidak kosong.
4. `full_address` tidak kosong.
5. `village` tidak kosong.
6. `district` tidak kosong.
7. `city` tidak kosong.
8. `pop_code` tidak kosong.
9. `package_name` tidak kosong.
10. `package_price` tidak kosong.
11. `activation_date` tidak kosong.
12. `due_date` tidak kosong.
13. `service_status` tidak kosong.

## 9.2 Validasi Duplikasi

Sistem harus mengecek:

1. `old_customer_id` tidak duplikat di file yang sama.
2. `old_customer_id` tidak duplikat dengan database jika sudah pernah diimport.
3. `primary_phone` boleh sama jika memang bisnis mengizinkan, tetapi harus diberi warning.
4. `registration_number` sistem baru tidak boleh duplikat.
5. CID tidak dibuat saat import kecuali pelanggan langsung diaktifkan sesuai aturan sistem.

## 9.3 Validasi POP

Sistem harus mengecek:

1. `pop_code` harus tersedia di tabel `pops`.
2. POP harus aktif.
3. Jika POP tidak ditemukan, baris menjadi invalid.
4. Jika POP nonaktif, baris menjadi invalid atau warning sesuai kebijakan.

## 9.4 Validasi Paket

Sistem harus mengecek:

1. `package_name` harus tersedia di `internet_packages`.
2. Paket harus aktif.
3. Jika paket tidak ditemukan, baris menjadi invalid.
4. Harga paket dari file dapat dibandingkan dengan master paket.
5. Jika harga berbeda, sistem memberi warning atau menggunakan harga file sebagai snapshot sesuai kebijakan.

## 9.5 Validasi Harga

Sistem harus mengecek:

1. `package_price` harus angka.
2. `package_price` tidak boleh negatif.
3. `package_price` tidak boleh kosong.
4. Jika ada simbol Rp atau titik/koma, sistem harus membersihkan format jika memungkinkan.

Contoh valid:

```txt
150000
Rp150000
150.000
```

Nilai akhir disimpan sebagai:

```txt
150000
```

## 9.6 Validasi Tanggal

Sistem harus mengecek:

1. `activation_date` harus tanggal valid.
2. `due_date` harus tanggal valid.
3. Format yang diterima:

   * YYYY-MM-DD
   * DD/MM/YYYY
   * DD-MM-YYYY
4. Sistem menyimpan tanggal dalam format database standar.
5. Jika tanggal tidak valid, baris invalid.

## 9.7 Validasi Status Layanan

Status layanan yang diterima:

```txt
calon_pelanggan
survey
menunggu_pemasangan
aktif
isolir
nonaktif
berhenti
```

Mapping status dari file lama dapat dilakukan.

Contoh:

| Status Lama | Status Baru |
| ----------- | ----------- |
| Active      | aktif       |
| AKTIF       | aktif       |
| Isolir      | isolir      |
| Putus       | berhenti    |
| Non Aktif   | nonaktif    |

---

# 10. Preview Import

Preview import harus menampilkan:

1. Total baris.
2. Total valid.
3. Total invalid.
4. Total warning.
5. Daftar data valid.
6. Daftar data invalid.
7. Alasan data invalid.
8. Tombol konfirmasi import.
9. Tombol batal.
10. Tombol download error jika dibutuhkan.

## Kolom Preview

Minimal preview menampilkan:

```txt
Row Number
Old Customer ID
Nama
No HP
POP
Paket
Harga
Status Layanan
Status Validasi
Error Message
```

---

# 11. Import Batch

Setiap upload file membuat satu import batch.

## Field Import Batch

```txt
batch_number
file_name
uploaded_by
total_rows
valid_rows
invalid_rows
imported_rows
status
```

## Status Import Batch

```txt
uploaded
previewed
validated
imported
failed
cancelled
```

## Aturan

1. Batch dibuat saat file diupload.
2. Status berubah menjadi `previewed` setelah preview berhasil.
3. Status berubah menjadi `validated` setelah validasi selesai.
4. Status berubah menjadi `imported` setelah data valid masuk master pelanggan.
5. Status `failed` jika proses gagal.
6. Status `cancelled` jika admin membatalkan.

---

# 12. Import Error

Setiap data invalid harus disimpan ke `import_errors`.

## Field Import Error

```txt
import_batch_id
row_number
field_name
error_message
raw_data
```

## Contoh Error

| row_number | field_name      | error_message              |
| ---------: | --------------- | -------------------------- |
|          5 | pop_code        | POP tidak ditemukan        |
|          7 | package_name    | Paket tidak tersedia       |
|          9 | package_price   | Harga tidak valid          |
|         11 | activation_date | Format tanggal tidak valid |

---

# 13. Penyimpanan Data Valid

Saat admin konfirmasi import, data valid disimpan ke tabel:

1. `customers`
2. `customer_addresses`
3. `customer_services`
4. `customer_devices` jika data teknis tersedia
5. `customer_documents` jika nanti ada import dokumen
6. `audit_logs`

## Aturan Penyimpanan Customer

Sistem harus menyimpan:

```txt
registration_number
old_customer_id
full_name
identity_number
gender
primary_phone
alternative_phone
email
registration_date
data_completeness_status
customer_status
pop_id
```

## Aturan Penyimpanan Address

Sistem harus menyimpan:

```txt
customer_id
full_address
village
district
city
province
latitude
longitude
```

## Aturan Penyimpanan Service

Sistem harus menyimpan:

```txt
customer_id
internet_package_id
package_name_snapshot
download_speed_snapshot
upload_speed_snapshot
monthly_price
discount
ppn
total_monthly_bill
activation_date
due_date
billing_cycle
service_status
billing_status
```

## Aturan Penyimpanan Device

Jika tersedia, sistem menyimpan:

```txt
customer_id
pppoe_username
pppoe_password
odp
odp_port
serial_number
mac_address
wifi_ssid
wifi_password
vlan_id
technical_note
```

---

# 14. Aturan Generate ID Saat Import

Saat import pelanggan:

1. Sistem tetap membuat `registration_number` baru sesuai aturan POP.
2. Sistem menyimpan `old_customer_id` dari sistem lama.
3. CID tidak dibuat jika pelanggan belum aktif/siap billing.
4. Jika pelanggan dari file berstatus aktif dan data wajib lengkap, sistem boleh membuat CID saat proses aktivasi/import sesuai kebijakan.
5. Jika kebijakan MVP lebih aman, CID dibuat manual saat admin klik aktivasi layanan.

Rekomendasi MVP:

```txt
Import hanya membuat registration_number.
CID dibuat saat aktivasi layanan.
```

---

# 15. Data Valid vs Siap Billing

Data valid import tidak selalu berarti siap billing.

## Data Valid Import

Data valid import berarti:

1. Format benar.
2. Field wajib import tersedia.
3. POP valid.
4. Paket valid.
5. Tanggal valid.
6. Harga valid.

## Data Siap Billing

Data siap billing berarti:

1. Data pelanggan lengkap.
2. Layanan aktif.
3. Paket aktif.
4. Harga ada.
5. Tanggal aktivasi ada.
6. Tanggal jatuh tempo ada.
7. Status layanan memungkinkan billing.

---

# 16. Audit Log Import

Saat import berhasil, audit log harus mencatat:

1. User yang melakukan import.
2. Waktu import.
3. Nama file.
4. Batch number.
5. Total rows.
6. Total valid.
7. Total invalid.
8. Total imported.
9. Module: `imports`
10. Action: `import`

---

# 17. Larangan Import

AI/developer tidak boleh:

1. Menyimpan data invalid ke master pelanggan.
2. Mengabaikan POP yang tidak ditemukan.
3. Mengabaikan paket yang tidak ditemukan.
4. Membuat invoice otomatis saat import MVP.
5. Membuat payment otomatis saat import.
6. Menghapus pelanggan lama saat import.
7. Menimpa data pelanggan existing tanpa konfirmasi.
8. Membuat CID tanpa aturan aktivasi.
9. Mengabaikan import batch.
10. Mengabaikan import error.

---

# 18. Acceptance Criteria Import

Modul import dianggap selesai jika:

* [ ] Admin dapat download template.
* [ ] Admin dapat upload Excel/CSV.
* [ ] Sistem membaca file.
* [ ] Sistem menampilkan mapping kolom.
* [ ] Sistem memvalidasi field wajib.
* [ ] Sistem mengecek duplikasi.
* [ ] Sistem mengecek POP.
* [ ] Sistem mengecek paket.
* [ ] Sistem mengecek harga.
* [ ] Sistem mengecek tanggal.
* [ ] Sistem mengecek status layanan.
* [ ] Sistem menampilkan preview.
* [ ] Sistem menampilkan data valid dan invalid.
* [ ] Sistem menjelaskan alasan data gagal.
* [ ] Admin dapat konfirmasi import.
* [ ] Data valid masuk master pelanggan.
* [ ] Data invalid tidak masuk master pelanggan.
* [ ] Import batch tersimpan.
* [ ] Import error tersimpan.
* [ ] Data hasil import bisa diedit manual.

---

## Catatan: Scope Lintas Cabang pada Resolusi Invoice (Fix 2026-07-21)

Detail + test regresi: `docs/ANALISA_BUG_LIST_PELANGGAN_DAN_MIGRASI_REQ_ID.md` (Bug #1). Lihat juga
`docs/LEGACY_MIGRATION_ACCURACY_ANALYSIS.md`.

**Masalah:** nomor legacy (`old_request_id`/`RQ`, `old_invoice_id`/`old_cost_id`/`IDBIAYA`) menomori
ulang dari 1 di tiap cabang → **tidak unik lintas cabang**. `RQ000005` ada di `jetis_db` (Hanif,
PE000003) DAN `sand_db` (Eva, PE000005). `CustomerController::resolveLegacyInvoiceId()` dulu
mencocokkan pembayaran → invoice lewat 3 fallback DB (`old_invoice_id`, `old_cost_id`,
`old_request_id`+`billing_period`) **tanpa scope pelanggan**. Kalau jetis di-import lebih dulu,
pembayaran Eva nyangkut ke invoice Hanif → user lapor "penggunannya Hanif Saifulloh".

**Fix:** loop pembayaran meresolusi `paymentCustomerId` (di-scope per cabang) lebih dulu, lalu
`resolveLegacyInvoiceId(..., $customerId)` membatasi semua fallback DB dengan
`->where('customer_id', $customerId)`. `old_request_id`/`old_customer_id` tetap disimpan **mentah**
(jejak legacy) — keunikan dijaga lewat scope query, konsisten dengan `customer_code` yang unique
per-POP. Pola yang harus dipegang: **setiap `Model::where('kolom_legacy_id', ...)` pada jalur migrasi
WAJIB di-scope per cabang/pelanggan** — jangan pernah anggap nomor legacy unik global.

Checklist tambahan yang harus lolos:

* [ ] Pembayaran menempel ke invoice **pelanggan yang sama** (bukan cabang lain yang kebetulan
      nomor legacy-nya sama).
* [ ] Dua cabang dengan `RQ`/`IDBIAYA` identik tetap menghasilkan pelanggan, invoice, & pembayaran
      terpisah.
