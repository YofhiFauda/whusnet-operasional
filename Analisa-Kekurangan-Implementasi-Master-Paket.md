# Analisa Kekurangan Implementasi Master Paket

Dokumen ini mencatat kekurangan implementasi Master Paket pada project saat ini berdasarkan perbandingan antara:

- `paket-layanan-whusnet.md`
- `Rancangan-Master-Paket.md`
- implementasi migration, model, seeder, route, dan test yang sudah ada

Status saat ini: implementasi Master Paket sudah memiliki fondasi database, model, seeder, dan test. Namun belum siap disebut sebagai modul Master Paket operasional penuh.

## 1. Ringkasan Status Implementasi

Yang sudah tersedia:

- Tabel `service_packages`.
- Model `ServicePackage`.
- Seeder `WhusnetServicePackageSeeder`.
- Pemanggilan seeder dari `DatabaseSeeder`.
- Test `WhusnetServicePackageSeederTest`.
- Data awal 27 paket WHUSNET.

Yang belum tersedia:

- Route Master Paket.
- Controller Master Paket.
- Halaman daftar Master Paket.
- Halaman detail Master Paket.
- Form tambah/edit paket.
- Fitur aktif/nonaktif paket melalui UI.
- Hak akses management/admin/sales.
- Validasi aturan bisnis paket.
- Import/export Master Paket.
- Audit perubahan paket.
- Snapshot paket ke data langganan pelanggan.

## 2. Kekurangan Data Seeder

### 2.1 Biaya Instalasi NetSo100 Tidak Sesuai Sumber

Pada `paket-layanan-whusnet.md`, paket `NetSo100` memiliki biaya instalasi:

```text
Rp 2.500.000
```

Namun pada seeder `WhusnetServicePackageSeeder`, nilai yang tersimpan adalah:

```text
250000
```

Seharusnya kemungkinan besar:

```text
2500000
```

Dampak:

- Biaya awal pelanggan bisnis bisa salah.
- Laporan biaya instalasi menjadi tidak akurat.
- Invoice awal berpotensi kurang tagih.

Rekomendasi:

- Perbaiki nilai `installation_fee` paket `NetSo100` menjadi `2500000`.
- Tambahkan test khusus untuk memvalidasi biaya instalasi paket bisnis utama.

### 2.2 Biaya CCTV Net198 Berpotensi Salah Makna

Paket `Net198` memiliki biaya pasang:

```text
Gratis + 200rb jika ambil CCTV
```

Implementasi saat ini menyimpan:

```text
installation_fee = 200000
installation_fee_label = Gratis + 200rb jika ambil CCTV
```

Masalah:

- Sistem bisa membaca `200000` sebagai biaya wajib.
- Padahal biaya tersebut bersifat opsional jika pelanggan mengambil CCTV.

Rekomendasi:

- Simpan biaya pasang utama sebagai `0` atau `null`.
- Simpan biaya CCTV sebagai add-on/fitur biaya tambahan.
- Tetap simpan teks lengkap pada `installation_fee_label`.

### 2.3 Biaya NetTC198 Merupakan Gabungan Add-on

Paket `NetTC198` memiliki biaya:

```text
Rp 200.000 CCTV + Rp 50.000 IPTV
```

Implementasi saat ini menyimpan:

```text
installation_fee = 250000
```

Masalah:

- Nilai total benar, tetapi komponen biaya tidak terpisah.
- Sistem tidak bisa membedakan biaya IPTV dan CCTV.
- Sulit dikembangkan jika pelanggan hanya mengambil sebagian add-on.

Rekomendasi:

- Tetap boleh menyimpan total untuk MVP.
- Untuk pengembangan lanjut, buat master add-on atau item biaya paket:
  - IPTV setup fee
  - CCTV setup fee
  - perangkat tambahan

## 3. Kekurangan Struktur Data

### 3.1 Aturan Bisnis Masih Tersimpan Sebagai Teks

Saat ini aturan seperti:

- paket rumahan hanya untuk rumah tinggal pribadi
- paket khusus pelanggan lama
- minimal langganan 6 bulan
- masa kontrak

sebagian besar masih disimpan pada field `terms`.

Masalah:

- Sistem tidak bisa melakukan validasi otomatis.
- Admin/sales masih bergantung pada membaca catatan manual.
- Import Excel sulit memvalidasi kelayakan paket.

Rekomendasi field tambahan:

```text
is_home_only
is_existing_customer_only
minimum_subscription_months
requires_management_approval
```

### 3.2 Belum Ada Penanda Paket Khusus Pelanggan Lama

Paket `NetP110` dan `NetP125` hanya boleh dipakai untuk pelanggan lama minimal 6 bulan.

Saat ini aturan tersebut hanya ada di `terms`.

Masalah:

- Sales/admin masih bisa memilih paket tersebut untuk pelanggan baru.
- Sistem tidak bisa memblokir pilihan paket yang tidak sesuai.

Rekomendasi:

- Tambahkan `is_existing_customer_only = true`.
- Tambahkan `minimum_subscription_months = 6`.
- Validasi saat paket dipilih di form pelanggan.

### 3.3 Belum Ada Penanda Paket Rumahan

Kategori `Paket Home Broadband` memang sudah ada, tetapi belum ada flag khusus untuk membatasi pemakaian.

Masalah:

- Paket Home Broadband masih bisa dipakai untuk bisnis, kost, warung, cafe, atau UKM jika hanya mengandalkan pilihan manual.

Rekomendasi:

- Tambahkan `is_home_only = true` untuk semua paket Home Broadband.
- Saat memilih paket Home Broadband, tampilkan peringatan:

```text
Paket ini hanya untuk rumah tinggal pribadi dan tidak boleh digunakan untuk bisnis, kost, warung, cafe, atau usaha lain.
```

### 3.4 Belum Ada Struktur Add-on Paket

Beberapa paket memiliki fitur atau biaya tambahan seperti:

- CCTV 1CH
- IPTV
- AP tambahan
- router
- biaya registrasi

Saat ini fitur disimpan sebagai JSON array dan biaya tambahan sebagian masuk ke `installation_fee`.

Masalah:

- Tidak bisa menghitung biaya add-on secara detail.
- Tidak bisa memilih add-on opsional per pelanggan.
- Sulit membuat invoice awal yang rinci.

Rekomendasi:

- Untuk MVP, `features` JSON masih cukup.
- Untuk tahap lanjut, buat tabel:

```text
service_package_addons
```

Field yang disarankan:

```text
id
service_package_id
addon_name
addon_type
price
is_optional
notes
```

## 4. Kekurangan Modul Operasional

### 4.1 Belum Ada Route Master Paket

Saat ini route master yang tersedia baru:

```text
/master/wilayah
```

Belum ada route:

```text
/master/paket
```

Rekomendasi route:

```text
GET /master/paket
GET /master/paket/{servicePackage}
GET /master/paket/create
POST /master/paket
GET /master/paket/{servicePackage}/edit
PUT /master/paket/{servicePackage}
PATCH /master/paket/{servicePackage}/toggle-active
```

### 4.2 Belum Ada Controller Master Paket

Belum ada controller:

```text
App\Http\Controllers\Master\ServicePackageController
```

Rekomendasi method awal:

```text
index
show
create
store
edit
update
toggleActive
```

### 4.3 Belum Ada Halaman Daftar Master Paket

Halaman daftar paket diperlukan untuk admin dan management.

Kolom yang disarankan:

- Kode paket.
- Nama paket.
- Kategori.
- Group.
- Bandwidth.
- Rasio.
- Harga bulanan.
- Fitur utama.
- Kontrak.
- Biaya pasang.
- Status aktif.

Filter yang disarankan:

- Kategori.
- Group.
- Status aktif.
- Rentang harga.
- Bandwidth.
- Rasio.
- Jenis IP.
- Kontrak.

### 4.4 Belum Ada Halaman Detail Master Paket

Halaman detail dibutuhkan agar management bisa melihat paket tanpa mengedit.

Informasi detail:

- Identitas paket.
- Harga dan biaya awal.
- Bandwidth dan rasio.
- Modem/perangkat.
- Fitur.
- IP address.
- Maksimal pengguna.
- Masa kontrak.
- Syarat dan ketentuan.
- Status aktif.

### 4.5 Belum Ada Form Tambah/Edit Paket

Admin belum dapat mengelola paket melalui aplikasi.

Rekomendasi:

- Buat form create/edit.
- Validasi `package_code` unik.
- Validasi harga wajib angka.
- Validasi status aktif boolean.
- Validasi kategori dan group wajib.
- Validasi bandwidth label wajib.

## 5. Kekurangan Hak Akses

Belum ada pembagian akses Master Paket.

Rekomendasi hak akses:

| Role | Hak Akses |
| --- | --- |
| Admin | Tambah, edit, aktif/nonaktif paket |
| Management | Lihat paket dan ringkasan, read-only |
| Sales | Lihat dan memilih paket aktif |
| Agent | Lihat dan memilih paket aktif |
| Finance | Lihat harga, biaya pasang, dan terms biaya |
| Teknisi/FOP | Lihat info teknis paket jika diperlukan |

Catatan:

- Management sebaiknya tidak langsung mengubah data master.
- Perubahan harga atau paket baru sebaiknya melalui admin dan approval management.

## 6. Kekurangan Snapshot ke Langganan Pelanggan

Rancangan menyarankan data paket disalin ke data langganan pelanggan saat pelanggan memilih paket.

Data yang perlu disnapshot:

```text
service_package_id
package_code
package_name
package_category
package_group
bandwidth_label
download_speed_mbps
upload_speed_mbps
monthly_price
contract_period_months
installation_fee
terms
```

Status saat ini:

- Belum ada implementasi pelanggan/langganan yang memakai Master Paket.
- Belum ada proses snapshot.

Dampak jika tidak disnapshot:

- Jika harga master berubah, histori pelanggan lama bisa rancu.
- Kontrak lama bisa berubah secara tampilan jika hanya membaca data master terbaru.

Rekomendasi:

- Saat membuat `customer_subscriptions`, salin data penting paket ke kolom snapshot.
- Jangan hanya bergantung pada relasi `service_package_id`.

## 7. Kekurangan Import dan Export

### 7.1 Belum Ada Import Master Paket

Belum ada fitur import Excel untuk Master Paket.

Rekomendasi:

- Import Master Paket bisa menyusul setelah CRUD manual.
- Wajib ada preview data sebelum simpan.
- Validasi error per baris.

### 7.2 Belum Ada Export Master Paket

Belum ada export paket.

Export yang disarankan:

- Semua paket.
- Paket aktif.
- Per kategori.
- Template import.

## 8. Kekurangan Audit dan Riwayat

Belum ada audit perubahan paket.

Perubahan yang sebaiknya diaudit:

- Perubahan harga bulanan.
- Perubahan biaya pasang.
- Perubahan status aktif.
- Perubahan terms.
- Perubahan fitur.
- Penambahan paket baru.

Data audit minimal:

```text
user_id
service_package_id
action
old_values
new_values
changed_at
notes
```

## 9. Kekurangan Test

Test saat ini sudah memvalidasi:

- total 27 paket
- jumlah paket per kategori
- beberapa detail paket
- seeder idempotent

Test yang masih perlu ditambahkan:

- Biaya instalasi `NetSo100` harus `2500000`.
- Semua `package_code` unik.
- Semua paket aktif memiliki kategori, group, bandwidth label, dan harga.
- Paket Home Broadband memiliki flag `is_home_only`.
- Paket `NetP110` dan `NetP125` memiliki flag pelanggan lama.
- Paket 1 Gbps tersimpan sebagai `1000 Mbps`.
- `features` tersimpan sebagai array.
- Paket nonaktif tidak muncul di pilihan paket pelanggan.

## 10. Prioritas Perbaikan

### Prioritas 1: Perbaikan Data

- Perbaiki biaya instalasi `NetSo100`.
- Tinjau ulang biaya opsional `Net198`.
- Tinjau ulang struktur biaya gabungan `NetTC198`.

### Prioritas 2: Struktur Aturan Paket

- Tambahkan flag paket rumahan.
- Tambahkan flag paket pelanggan lama.
- Tambahkan minimal bulan langganan.
- Tambahkan flag approval jika dibutuhkan.

### Prioritas 3: Modul UI Master Paket

- Tambahkan route `/master/paket`.
- Buat `ServicePackageController`.
- Buat halaman daftar paket.
- Buat halaman detail paket.
- Buat form tambah/edit paket.
- Buat aksi aktif/nonaktif paket.

### Prioritas 4: Integrasi ke Pelanggan

- Paket aktif muncul di form pelanggan.
- Paket yang tidak aktif tidak bisa dipilih.
- Snapshot paket tersimpan di langganan pelanggan.
- Validasi paket khusus diterapkan saat pelanggan mendaftar.

### Prioritas 5: Management dan Audit

- Buat tampilan read-only untuk management.
- Buat ringkasan paket.
- Tambahkan audit perubahan paket.
- Tambahkan export paket.

## 11. Definisi Implementasi Master Paket Siap Operasional

Master Paket dianggap siap operasional jika:

- Semua data paket sesuai dengan `paket-layanan-whusnet.md`.
- Admin dapat mengelola paket melalui aplikasi.
- Paket dapat diaktifkan dan dinonaktifkan tanpa dihapus.
- Management dapat melihat daftar dan ringkasan paket secara read-only.
- Sales dan agent hanya dapat memilih paket aktif.
- Aturan paket rumahan dan paket pelanggan lama divalidasi sistem.
- Data paket tersnapshot ke langganan pelanggan.
- Import pelanggan dapat memvalidasi paket terhadap Master Paket.
- Perubahan harga dan status paket tercatat dalam audit.
- Test mencakup data penting dan aturan bisnis utama.
