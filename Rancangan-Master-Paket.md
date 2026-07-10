# Rancangan Master Paket Layanan WHUSNET

Dokumen ini merancang **Master Paket Layanan** berdasarkan data paket internet pada `paket-layanan-whusnet.md`. Master Paket menjadi acuan utama saat pelanggan memilih layanan, saat import Excel pelanggan, dan saat sistem menghitung biaya layanan awal.

## 1. Tujuan Master Paket

Master Paket digunakan untuk:

- Menyimpan daftar paket internet resmi WHUSNET.
- Menjadi sumber pilihan paket pada form pendaftaran pelanggan.
- Menjadi acuan harga, bandwidth, kontrak, fitur, dan biaya pemasangan.
- Memisahkan paket rumah, bisnis broadband, bisnis UKM, dan dedicated.
- Menjaga histori harga pelanggan dengan cara menyimpan snapshot paket ke data langganan pelanggan.
- Memudahkan management melihat paket aktif, harga, kategori, dan performa penjualan paket.

## 2. Prinsip Rancangan

- **Paket memiliki kode unik**: contoh `Net138`, `NetSo100`, `Dedicated1G`.
- **Paket boleh dinonaktifkan, bukan dihapus**: paket lama tetap dibutuhkan untuk histori pelanggan.
- **Harga pelanggan disimpan sebagai snapshot**: jika harga master berubah, tagihan pelanggan lama tidak otomatis berubah tanpa proses migrasi harga.
- **Kategori dan group dipisah**: kategori adalah kelompok besar, group adalah sub-kelompok paket.
- **Bandwidth asli tetap disimpan sebagai label**: contoh `70 Mbps 1:8`, `Up To 35 Mbps`, `1 Gbps`.
- **Angka bandwidth disimpan terstruktur**: download/upload Mbps disimpan sebagai angka agar bisa dihitung dan difilter.
- **Fitur paket dibuat fleksibel**: IPTV, CCTV, router, AP, login portal, bandwidth management, dan fitur lain disimpan sebagai daftar fitur.
- **Syarat dan ketentuan disimpan jelas**: kontrak, larangan pemakaian, paket khusus pelanggan lama, dan catatan pemasangan tidak boleh hilang.

## 3. Kategori Paket

| Kategori | Keterangan | Target Pengguna |
| --- | --- | --- |
| Paket Home Broadband | Paket rumahan | Rumah tinggal pribadi |
| Paket Bisnis Broadband | Paket bisnis dengan rasio 1:4 atau 1:8 | Kantor, usaha, bisnis menengah |
| Paket Bisnis UKM | Paket cafe, warung kopi, dan UKM | Usaha kecil dengan user terbatas |
| Paket Bisnis Dedicated | Paket dedicated | Bisnis dengan kebutuhan bandwidth dedicated |

## 4. Group Paket

| Kategori | Group Paket |
| --- | --- |
| Paket Home Broadband | Reguler Broadband Home Internet Only |
| Paket Home Broadband | Broadband Internet + TV |
| Paket Home Broadband | Penawaran Khusus |
| Paket Bisnis Broadband | Bisnis Broadband 1:4 & 1:8 |
| Paket Bisnis UKM | Cafe & Warung Kopi / UKM |
| Paket Bisnis Dedicated | Internet Bisnis Dedicated |

## 5. Field Master Paket

| Field | Wajib | Tipe Data | Keterangan |
| --- | --- | --- | --- |
| `package_code` | Ya | string unik | Kode paket, contoh `Net138` |
| `name` | Ya | string | Nama paket yang ditampilkan |
| `category` | Ya | string/enum | Kategori besar paket |
| `package_group` | Ya | string/enum | Sub-kelompok paket |
| `bandwidth_label` | Ya | string | Label asli bandwidth dari brosur |
| `download_speed_mbps` | Tidak | decimal | Angka download dalam Mbps |
| `upload_speed_mbps` | Tidak | decimal | Angka upload dalam Mbps jika tersedia |
| `contention_ratio` | Tidak | integer | Rasio sharing, contoh 4 atau 8 |
| `monthly_price` | Ya | decimal | Harga bulanan |
| `modem` | Tidak | string | Singleband, Dualband, Dualband Wifi6 |
| `features` | Tidak | JSON/array | IPTV, CCTV, AP, router, login portal, dll |
| `max_users` | Tidak | integer | Batas pengguna untuk paket UKM |
| `ip_address_type` | Tidak | string | IP Private, 1 Public Static, 1 IP Public, dll |
| `contract_period_months` | Tidak | integer | Masa kontrak dalam bulan |
| `installation_fee` | Tidak | decimal | Biaya pasang/registrasi dalam angka |
| `installation_fee_label` | Tidak | string | Label biaya pasang sesuai brosur |
| `profile` | Tidak | string | Profile PPPoE/MikroTik/billing jika sudah ada |
| `terms` | Tidak | text | Syarat dan ketentuan paket |
| `is_active` | Ya | boolean | Status paket aktif/tidak aktif |

## 6. Data Paket Awal

### 6.1 Paket Home Broadband

Peringatan khusus: paket Home Broadband hanya untuk rumah tinggal pribadi. Paket ini tidak boleh dijual untuk bisnis, rumah kost, warung, cafe, atau pemakaian usaha.

#### Reguler Broadband Home Internet Only

| Kode | Bandwidth | Harga Bulanan | Modem | Fitur | Biaya Pasang | Kontrak |
| --- | --- | ---: | --- | --- | --- | --- |
| Net138 | 50 Mbps | Rp 138.000 | Singleband | - | Gratis | 12 bulan |
| Net150 | 100 Mbps | Rp 150.000 | Dualband | - | Gratis | 8 bulan |
| Net165 | 150 Mbps | Rp 165.000 | Dualband | - | Gratis | 6 bulan |
| Net198 | 200 Mbps | Rp 198.000 | Dualband Wifi6 | CCTV 1CH | Gratis, tambah Rp 200.000 jika ambil CCTV | 6 bulan |

#### Broadband Internet + TV

| Kode | Bandwidth | Harga Bulanan | Modem | Fitur | Biaya Pasang | Kontrak |
| --- | --- | ---: | --- | --- | --- | --- |
| NetTC138 | 40 Mbps | Rp 138.000 | Singleband | IPTV | Rp 50.000 | 12 bulan |
| NetTC150 | 70 Mbps | Rp 150.000 | Dualband | IPTV | Rp 50.000 | 8 bulan |
| NetTC165 | 100 Mbps | Rp 165.000 | Dualband | IPTV | Rp 50.000 | 6 bulan |
| NetTC198 | 150 Mbps | Rp 198.000 | Dualband Wifi6 | IPTV, CCTV 1CH | Rp 200.000 CCTV + Rp 50.000 IPTV | 6 bulan |

#### Penawaran Khusus

| Kode | Bandwidth | Harga Bulanan | Syarat |
| --- | --- | ---: | --- |
| NetP110 | 25 Mbps | Rp 110.000 | Khusus pelanggan lama, minimal langganan 6 bulan |
| NetP125 | 30 Mbps | Rp 125.000 | Khusus pelanggan lama, minimal langganan 6 bulan |

### 6.2 Paket Bisnis Broadband

| Kode | Bandwidth | Rasio | Harga Bulanan | Fitur | IP Address | Biaya Instalasi | Kontrak |
| --- | --- | ---: | ---: | --- | --- | ---: | --- |
| NetSoLite75 | 70 Mbps | 1:8 | Rp 450.000 | Unlimited, 1 AP + 1 Router | IP Private | Rp 500.000 | 12 bulan |
| NetSoLite100 | 100 Mbps | 1:8 | Rp 550.000 | Unlimited, 1 AP + 1 Router | IP Private | Rp 500.000 | 12 bulan |
| NetSo100 | 100 Mbps | 1:4 | Rp 700.000 | Unlimited, 1 AP & 1 Router | 1 Public Static | Rp 2.500.000 | 12 bulan |
| NetSo200 | 200 Mbps | 1:4 | Rp 1.350.000 | Unlimited, 1 AP & 1 Router | 1 Public Static | Rp 2.500.000 | 12 bulan |
| NetSo300 | 300 Mbps | 1:4 | Rp 2.150.000 | Unlimited, 1 AP & 1 Router | 1 Public Static | Rp 2.500.000 | 12 bulan |
| NetSo500 | 500 Mbps | 1:4 | Rp 3.200.000 | Unlimited, 2 AP & 1 Router | 2 Public Static | Rp 2.500.000 | 12 bulan |
| NetSo1G | 1 Gbps | 1:4 | Rp 5.900.000 | Unlimited, 3 AP & 1 Router | 2 Public Static | Rp 2.500.000 | 12 bulan |

### 6.3 Paket Bisnis UKM

| Kode | Bandwidth | Harga Bulanan | Fitur | Maks. Pengguna | Biaya Registrasi |
| --- | --- | ---: | --- | ---: | ---: |
| NetBLite25 | Up To 35 Mbps | Rp 200.000 | Login Portal, Bandwidth Management, 1 AP Wifi6 | 5 | Rp 150.000 |
| NetBLite55 | Up To 55 Mbps | Rp 250.000 | Login Portal, Bandwidth Management, 1 AP Wifi6 | 7 | Rp 150.000 |
| NetBLite110 | Up To 110 Mbps | Rp 390.000 | Login Portal, Bandwidth Management, 1 AP Wifi6 + 1 AP Wifi5 | 15 | Rp 250.000 |
| NetBLite165 | Up To 165 Mbps | Rp 490.000 | Login Portal, Bandwidth Management, 1 AP Wifi6 + 1 AP Wifi6 | 30 | Rp 250.000 |
| NetBLite330 | Up To 330 Mbps | Rp 690.000 | Login Portal, Bandwidth Management, 1 AP Wifi6 + 1 AP Wifi6 | 50 | Rp 250.000 |
| NetBLite550 | Up To 550 Mbps | Rp 980.000 | Login Portal, Bandwidth Management, 1 AP Wifi6 + 1 AP Wifi6 | 60 | Rp 250.000 |

### 6.4 Paket Bisnis Dedicated

| Kode | Bandwidth | Harga Bulanan | Fitur | IP Address | Biaya Instalasi | Kontrak |
| --- | --- | ---: | --- | --- | ---: | --- |
| Dedicated100 | 100 Mbps | Rp 3.500.000 | 2 AP Wifi6 & 1 Router | 1 IP Public | Rp 2.500.000 | 12 bulan |
| Dedicated250 | 250 Mbps | Rp 6.500.000 | 2 AP Wifi6 & 1 Router | 1 IP Public | Rp 2.500.000 | 12 bulan |
| Dedicated500 | 500 Mbps | Rp 12.000.000 | 2 AP Wifi6 & 1 Router | 2 IP Public | Rp 2.500.000 | 12 bulan |
| Dedicated1G | 1 Gbps | Rp 23.000.000 | 2 AP Wifi6 & 1 Router | 2 IP Public | Rp 2.500.000 | 12 bulan |

## 7. Aturan Bisnis Master Paket

### 7.1 Aturan Umum

- `package_code` tidak boleh duplikat.
- Paket yang sudah pernah dipakai pelanggan tidak boleh dihapus.
- Paket lama cukup dibuat `is_active = false`.
- Paket aktif harus memiliki kategori, group, nama, label bandwidth, dan harga bulanan.
- Harga bulanan disimpan tanpa format rupiah, contoh `138000`.
- Biaya pemasangan disimpan tanpa format rupiah jika dapat dihitung.
- Jika biaya pemasangan berupa kondisi khusus, simpan angka utama di `installation_fee` dan teks lengkap di `installation_fee_label`.
- Jika upload speed belum ditentukan, `upload_speed_mbps` boleh kosong.
- Jika paket 1 Gbps, simpan `download_speed_mbps = 1000`.
- Jika bandwidth memiliki label `Up To`, label tetap disimpan di `bandwidth_label`.

### 7.2 Aturan Paket Home Broadband

- Hanya boleh dipakai untuk rumah tinggal pribadi.
- Tidak boleh dipakai untuk bisnis, rumah kost, warung, cafe, dan penggunaan usaha.
- Sistem perlu menampilkan peringatan saat admin/sales memilih kategori Home Broadband.
- Paket `NetP110` dan `NetP125` hanya boleh dipilih untuk pelanggan lama dengan minimal masa langganan 6 bulan.

### 7.3 Aturan Paket Bisnis

- Paket bisnis boleh dipakai untuk pelanggan usaha.
- Rasio 1:4 atau 1:8 harus tercatat pada `contention_ratio`.
- Jenis IP harus tercatat jika paket menyediakan IP private/public.
- Masa kontrak default 12 bulan kecuali ada perubahan kebijakan.

### 7.4 Aturan Paket UKM

- Paket UKM harus menyimpan batas maksimal pengguna.
- Fitur login portal dan bandwidth management harus tercatat.
- Biaya registrasi diperlakukan sebagai biaya pemasangan/registrasi awal.

### 7.5 Aturan Paket Dedicated

- Paket dedicated harus memiliki informasi IP public.
- Masa kontrak default 12 bulan.
- Paket dedicated harus mudah difilter terpisah dari paket broadband biasa.

## 8. Snapshot ke Data Langganan Pelanggan

Saat pelanggan memilih paket, data penting dari Master Paket harus disalin ke `customer_subscriptions`.

Data yang perlu disnapshot:

- `service_package_id`.
- `package_code`.
- `package_name`.
- `package_category`.
- `package_group`.
- `bandwidth_label`.
- `download_speed_mbps`.
- `upload_speed_mbps`.
- `monthly_price`.
- `contract_period_months`.
- `installation_fee`.
- `terms`.

Alasan snapshot:

- Harga pelanggan lama tetap akurat walaupun harga master berubah.
- Kontrak pelanggan lama tetap bisa dibaca sesuai paket saat daftar.
- Laporan histori tidak rusak ketika paket lama dinonaktifkan.

## 9. Tampilan Master Paket

### 9.1 Daftar Paket

Kolom daftar yang disarankan:

- Kode paket.
- Nama paket.
- Kategori.
- Group.
- Bandwidth.
- Rasio.
- Harga bulanan.
- Modem/perangkat.
- Fitur utama.
- Kontrak.
- Biaya pasang.
- Status aktif.
- Aksi detail/edit.

Filter:

- Kategori.
- Group.
- Status aktif.
- Rentang harga.
- Bandwidth.
- Rasio.
- Jenis IP.
- Kontrak.

Pencarian:

- Kode paket.
- Nama paket.
- Bandwidth.
- Fitur.
- Terms.

### 9.2 Detail Paket

Detail paket sebaiknya menampilkan:

- Identitas paket.
- Kategori dan group.
- Harga dan biaya awal.
- Bandwidth dan rasio.
- Modem/perangkat.
- Fitur.
- IP address.
- Maksimal pengguna.
- Masa kontrak.
- Syarat dan ketentuan.
- Status aktif.
- Riwayat perubahan harga jika fitur audit sudah tersedia.

## 10. Hak Akses

| Role | Hak Akses |
| --- | --- |
| Admin | Tambah, edit, aktif/nonaktif paket |
| Management | Lihat paket, harga, kategori, dan ringkasan |
| Sales | Lihat dan memilih paket aktif saat input pelanggan |
| Agent | Lihat dan memilih paket aktif saat input pelanggan |
| Finance | Lihat harga, biaya pasang, dan terms biaya |
| Teknisi/FOP | Lihat info teknis paket jika diperlukan |

Management sebaiknya melihat Master Paket dalam bentuk read-only dan dashboard ringkasan. Perubahan harga atau paket baru sebaiknya dilakukan admin dengan persetujuan management.

## 11. Import dan Export Master Paket

Master Paket dapat diinput manual atau diimport dari Excel.

Kolom import yang disarankan:

- Kode paket.
- Nama paket.
- Kategori.
- Group.
- Bandwidth label.
- Download Mbps.
- Upload Mbps.
- Rasio.
- Harga bulanan.
- Modem.
- Fitur.
- Maksimal pengguna.
- Jenis IP.
- Masa kontrak bulan.
- Biaya pasang.
- Label biaya pasang.
- Profile.
- Terms.
- Status aktif.

Aturan import:

- Kode paket wajib unik.
- Harga wajib angka.
- Download/upload Mbps wajib angka jika diisi.
- Rasio wajib angka jika diisi.
- Fitur boleh dipisahkan dengan koma, lalu disimpan sebagai array.
- Status aktif menerima nilai `aktif`, `tidak aktif`, `1`, `0`, `true`, atau `false`.
- Import harus menampilkan preview dan error per baris sebelum disimpan.

Export yang disarankan:

- Export semua paket.
- Export paket aktif.
- Export per kategori.
- Export format template import.

## 12. Laporan Management

Ringkasan yang berguna untuk management:

- Total paket aktif dan tidak aktif.
- Jumlah paket per kategori.
- Paket termurah dan termahal per kategori.
- Distribusi harga paket.
- Jumlah pelanggan per paket.
- Paket paling banyak dipakai.
- Paket tanpa pelanggan.
- Paket yang sudah tidak aktif tetapi masih punya pelanggan aktif.
- Paket dengan biaya instalasi gratis.
- Paket dengan IP public.
- Paket khusus pelanggan lama.

## 13. Rekomendasi Implementasi Database

Tabel utama: `service_packages`.

Field minimal:

```text
id
package_code
name
category
package_group
bandwidth_label
download_speed_mbps
upload_speed_mbps
contention_ratio
monthly_price
modem
features
max_users
ip_address_type
contract_period_months
installation_fee
installation_fee_label
profile
terms
is_active
created_at
updated_at
```

Index yang disarankan:

- Unique index pada `package_code`.
- Index pada `category` dan `package_group`.
- Index pada `is_active`.
- Index pada `monthly_price` jika filter harga sering dipakai.
- Index pada `download_speed_mbps` jika filter bandwidth sering dipakai.

## 14. Definisi Selesai MVP Master Paket

Master Paket dianggap siap untuk MVP jika:

- Semua paket dari `paket-layanan-whusnet.md` sudah masuk.
- Admin bisa melihat daftar paket.
- Admin bisa tambah dan edit paket.
- Admin bisa aktif/nonaktif paket.
- Sales dan agent hanya bisa memilih paket aktif.
- Paket Home Broadband menampilkan peringatan pemakaian hanya untuk rumah tinggal pribadi.
- Paket penawaran khusus bisa ditandai sebagai paket khusus pelanggan lama.
- Harga paket tersalin sebagai snapshot saat pelanggan daftar.
- Import Excel pelanggan dapat memvalidasi nama/kode paket terhadap Master Paket.
- Management bisa melihat daftar paket dan ringkasan paket tanpa mengubah data langsung.
